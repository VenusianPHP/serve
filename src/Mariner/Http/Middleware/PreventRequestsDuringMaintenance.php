<?php

namespace Mariner\Http\Middleware;

use Closure;
use ErrorException;
use Mariner\Contracts\Http\MaintenanceMode;
use Mariner\Http\Maintenance\MaintenanceModeBypassCookie;
use Mariner\Http\Middleware\Concerns\ExcludesPaths;
use Symfony\Component\HttpKernel\Exception\HttpException;
use TypeError;
use Voyager\Contracts\Vessel\TheServiceContainer;
use Voyager\NutsAndBolts\DataObjects\Arr;

class PreventRequestsDuringMaintenance
{
    use ExcludesPaths;

    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array<int, string>
     */
    protected $except = [];

    /**
     * The URIs that should be accessible during maintenance, added with except().
     *
     * @var array<int, string>
     */
    protected static $neverPrevent = [];

    public function __construct(protected TheServiceContainer $app) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Mariner\Http\Request  $request
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \ErrorException
     */
    public function handle($request, Closure $next)
    {
        if ($this->inExceptArray($request)) {
            return $next($request);
        }

        $maintenance = $this->app->make(MaintenanceMode::class);

        if ($maintenance->active()) {
            try {
                $data = $maintenance->data();
            } catch (ErrorException|TypeError $exception) {
                // `up` ran between the check and the read.
                if (! $maintenance->active()) {
                    return $next($request);
                }

                throw $exception;
            }

            if (is_string($data['secret'] ?? null) && hash_equals($data['secret'], $request->path())) {
                return $this->bypassResponse($data['secret']);
            }

            if ($this->hasValidBypassCookie($request, $data)) {
                return $next($request);
            }

            if (isset($data['redirect']) && ! $request->expectsJson()) {
                $path = $data['redirect'] === '/'
                    ? $data['redirect']
                    : trim($data['redirect'], '/');

                if ($request->path() !== $path) {
                    return redirect($path);
                }
            }

            if (isset($data['template']) && ! $request->expectsJson()) {
                return response(
                    $data['template'],
                    $data['status'] ?? 503,
                    $this->getHeaders($data)
                );
            }

            throw new HttpException(
                $data['status'] ?? 503,
                'Service Unavailable',
                null,
                $this->getHeaders($data)
            );
        }

        return $next($request);
    }

    /**
     * Determine if the incoming request has a maintenance mode bypass cookie.
     *
     * @param  \Mariner\Http\Request  $request
     */
    protected function hasValidBypassCookie($request, array $data): bool
    {
        return isset($data['secret']) &&
            $request->cookie(MaintenanceModeBypassCookie::NAME) &&
            MaintenanceModeBypassCookie::isValid(
                $request->cookie(MaintenanceModeBypassCookie::NAME),
                $data['secret']
            );
    }

    /**
     * Redirect the user to their intended destination with a maintenance mode bypass cookie.
     *
     * @return \Mariner\Http\RedirectResponse
     */
    protected function bypassResponse(string $secret)
    {
        return redirect()->intended('/')->withCookie(
            MaintenanceModeBypassCookie::create($secret)
        );
    }

    /**
     * Get the headers that should be sent with the response.
     */
    protected function getHeaders(array $data): array
    {
        $headers = isset($data['retry']) ? ['Retry-After' => $data['retry']] : [];

        if (isset($data['refresh'])) {
            $headers['Refresh'] = $data['refresh'];
        }

        return $headers;
    }

    /**
     * Get the URIs that should be accessible even when maintenance mode is enabled.
     *
     * @return array<int, string>
     */
    public function getExcludedPaths()
    {
        return array_merge($this->except, static::$neverPrevent);
    }

    /**
     * Indicate that the given URIs should always be accessible.
     *
     * @param  array|string  $uris
     */
    public static function except($uris): void
    {
        static::$neverPrevent = array_values(array_unique(
            array_merge(static::$neverPrevent, Arr::wrap($uris))
        ));
    }

    /**
     * Flush the state of the middleware.
     */
    public static function flushState(): void
    {
        static::$neverPrevent = [];
    }
}
