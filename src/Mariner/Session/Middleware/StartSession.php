<?php

namespace Mariner\Session\Middleware;

use Closure;
use Mariner\Contracts\Session\Session;
use Mariner\Http\Request;
use Mariner\Routing\Route;
use Mariner\Session\SessionManager;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\NutsAndBolts\Facades\Date;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Mariner\Session\Store;
use Mariner\Session\ProvidesAsyncHandler;

class StartSession
{
    /**
     * The session manager.
     *
     * @var \Mariner\Session\SessionManager
     */
    protected $manager;

    /**
     * The callback that can resolve an instance of the cache factory.
     *
     * @var callable|null
     */
    protected $cacheFactoryResolver;

    /**
     * Create a new session middleware.
     *
     * @param  \Mariner\Session\SessionManager  $manager
     * @param  callable|null  $cacheFactoryResolver
     */
    public function __construct(SessionManager $manager, ?callable $cacheFactoryResolver = null)
    {
        $this->manager = $manager;
        $this->cacheFactoryResolver = $cacheFactoryResolver;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $this->sessionConfigured()) {
            return $next($request);
        }

        $session = $this->getSession($request);

        if ($this->manager->shouldBlock() ||
            ($request->route() instanceof Route && $request->route()->locksFor())) {
            return $this->handleRequestWhileBlocking($request, $session, $next);
        }

        return $this->handleStatefulRequest($request, $session, $next);
    }

    /**
     * Handle the given request within session state.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Mariner\Contracts\Session\Session  $session
     * @param  \Closure  $next
     * @return mixed
     */
    protected function handleRequestWhileBlocking(Request $request, $session, Closure $next)
    {
        if (! $request->route() instanceof Route) {
            return;
        }

        $lockFor = $request->route()->locksFor()
            ? $request->route()->locksFor()
            : $this->manager->defaultRouteBlockLockSeconds();

        $lock = $this->cache($this->manager->blockDriver())
            ->lock('session:'.$session->getId(), $lockFor)
            ->betweenBlockedAttemptsSleepFor(50);

        try {
            $lock->block(
                ! is_null($request->route()->waitsFor())
                    ? $request->route()->waitsFor()
                    : $this->manager->defaultRouteBlockWaitSeconds()
            );

            return $this->handleStatefulRequest($request, $session, $next);
        } finally {
            $lock?->release();
        }
    }

    /**
     * Handle the given request within session state.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Mariner\Contracts\Session\Session  $session
     * @param  \Closure  $next
     * @return mixed
     */
    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        // If a session driver has been configured, we will need to start the session here
        // so that the data is ready for an application. Note that the Laravel sessions
        // do not make use of PHP "native" sessions in any way since they are crappy.
        $request->setMarinerSession(
            $this->startSession($request, $session)
        );

        $this->collectGarbage($session);

        $response = $next($request);

        $this->storeCurrentUrl($request, $session);

        $this->addCookieToResponse($response, $session);

        // Again, if the session has been configured we will need to close out the session
        // so that the attributes may be persisted to some storage medium. We will also
        // add the session identifier cookie to the application response headers now.
        $this->saveSession($request);

        return $response;
    }

    /**
     * Start the session for the given request.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Mariner\Contracts\Session\Session  $session
     * @return \Mariner\Contracts\Session\Session
     */
    protected function startSession(Request $request, $session)
    {
        return tap($session, function ($session) use ($request) {
            $session->setRequestOnHandler($request);

            $this->usesAsyncSessions($session) ? $session->async($this->asyncPool())->start()->wait() : $session->start();
        });
    }

    /**
     * Get the session implementation from the manager.
     *
     * @param  \Mariner\Http\Request  $request
     * @return \Mariner\Contracts\Session\Session
     */
    public function getSession(Request $request)
    {
        return tap($this->manager->driver(), function ($session) use ($request) {
            $session->setId($request->cookies->get($session->getName()));
        });
    }

    /**
     * Remove the garbage from the session if necessary.
     *
     * @param  \Mariner\Contracts\Session\Session  $session
     * @return void
     */
    protected function collectGarbage(Session $session)
    {
        $config = $this->manager->getSessionConfig();

        // Here we will see if this request hits the garbage collection lottery by hitting
        // the odds needed to perform garbage collection on any given request. If we do
        // hit it, we'll call this handler to let it delete all the expired sessions.
        if ($this->configHitsLottery($config)) {
            $handler = $session->getHandler();

            $this->usesAsyncSessions($session) && $handler instanceof ProvidesAsyncHandler
                ? $handler->asyncHandler($this->asyncPool())->gc($this->getSessionLifetimeInSeconds())->wait()
                : $handler->gc($this->getSessionLifetimeInSeconds());
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     *
     * @param  array  $config
     * @return bool
     */
    protected function configHitsLottery(array $config)
    {
        return random_int(1, $config['lottery'][1]) <= $config['lottery'][0];
    }

    /**
     * Store the current URL for the request if necessary.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Mariner\Contracts\Session\Session  $session
     * @return void
     */
    protected function storeCurrentUrl(Request $request, $session)
    {
        if ($request->isMethod('GET') &&
            $request->route() instanceof Route &&
            ! $request->ajax() &&
            ! $request->prefetch() &&
            ! $request->isPrecognitive()) {
            $session->setPreviousUrl($request->fullUrl());

            if (method_exists($session, 'setPreviousRoute')) {
                $session->setPreviousRoute($request->route()->getName());
            }
        }
    }

    /**
     * Add the session cookie to the application response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Mariner\Contracts\Session\Session  $session
     * @return void
     */
    protected function addCookieToResponse(Response $response, Session $session)
    {
        if ($this->sessionIsPersistent($config = $this->manager->getSessionConfig())) {
            $response->headers->setCookie(new Cookie(
                $session->getName(),
                $session->getId(),
                $this->getCookieExpirationDate(),
                $config['path'],
                $config['domain'],
                $config['secure'],
                $config['http_only'] ?? true,
                false,
                $config['same_site'] ?? null,
                $config['partitioned'] ?? false
            ));
        }
    }

    /**
     * Save the session data to storage.
     *
     * @param  \Mariner\Http\Request  $request
     * @return void
     */
    protected function saveSession($request)
    {
        if (! $request->isPrecognitive()) {
            $session = $this->manager->driver();

            $this->usesAsyncSessions($session) ? $session->async($this->asyncPool())->save()->wait() : $session->save();
        }
    }

    /**
     * Get the session lifetime in seconds.
     *
     * @return int
     */
    protected function getSessionLifetimeInSeconds()
    {
        return ($this->manager->getSessionConfig()['lifetime'] ?? null) * 60;
    }

    /**
     * Get the cookie lifetime in seconds.
     *
     * @return \DateTimeInterface|int
     */
    protected function getCookieExpirationDate()
    {
        return $this->manager->getSessionConfig()['expire_on_close']
            ? 0
            : Date::instance(
                Carbon::now()->addSeconds($this->getSessionLifetimeInSeconds())
            );
    }

    /**
     * Determine if a session driver has been configured.
     *
     * @return bool
     */
    protected function sessionConfigured()
    {
        return ! is_null($this->manager->getSessionConfig()['driver'] ?? null);
    }

    /**
     * Determine if the configured session driver is persistent.
     *
     * @param  array|null  $config
     * @return bool
     */
    protected function sessionIsPersistent(?array $config = null)
    {
        $config = $config ?: $this->manager->getSessionConfig();

        return ! is_null($config['driver'] ?? null);
    }

    /**
     * Resolve the given cache driver.
     *
     * @param  string  $driver
     * @return \Voyager\Contracts\Cache\Repository
     */
    protected function cache($driver)
    {
        return call_user_func($this->cacheFactoryResolver)->driver($driver);
    }

    /**
     * Whether the app chose session I/O through the store's async variant (config session.async).
     * Off by default: the store then reads and writes as it always has.
     */
    protected function usesAsyncSessions($session): bool
    {
        return $session instanceof Store && (bool) ($this->manager->getSessionConfig()['async'] ?? false);
    }

    /**
     * The worker pool async session I/O offloads to: 'thread', 'process', or null for the framework's rule.
     */
    protected function asyncPool(): ?string
    {
        return $this->manager->getSessionConfig()['async_pool'] ?? null;
    }
}
