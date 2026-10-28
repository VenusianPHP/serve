<?php

namespace Mariner\Auth;

use Closure;
use Mariner\Contracts\Auth\Factory as FactoryContract;
use Mariner\NutsAndBolts\RebindsCallbacksToSelf;
use InvalidArgumentException;
use ReflectionException;
use RuntimeException;

use function Voyager\NutsAndBolts\enum_value;

/**
 * @mixin \Mariner\Contracts\Auth\Guard
 * @mixin \Mariner\Contracts\Auth\StatefulGuard
 */
class AuthManager implements FactoryContract
{
    use CreatesUserProviders, RebindsCallbacksToSelf;

    /**
     * The application instance.
     *
     * @var \Voyager\Contracts\Core\FrameworkCore
     */
    protected $app;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];


    /**
     * The user resolver shared by various services.
     *
     * Determines the default user for Gate, Request, and the Authenticatable contract.
     *
     * @var \Closure
     */
    protected $userResolver;

    /**
     * Create a new Auth manager instance.
     *
     * @param  \Voyager\Contracts\Core\FrameworkCore  $app
     */
    public function __construct($app)
    {
        $this->app = $app;

        $this->userResolver = fn ($guard = null) => $this->guard($guard)->user();
    }

    /**
     * Attempt to get the guard from the local cache.
     *
     * @param  \UnitEnum|string|null  $name
     * @return \Mariner\Contracts\Auth\Guard|\Mariner\Contracts\Auth\StatefulGuard
     */
    public function guard($name = null)
    {
        $name = enum_value($name) ?: $this->getDefaultDriver();

        $guard = $this->guardState()->guards[$name] ??= $this->resolve($name);

        // Laravel hands guards each new request and each new events dispatcher through container rebinding
        // (refresh('request'), rebinding('events')). Vessel has none, so a guard built earlier in this scope
        // (a feature test's earlier call) would keep the old ones; give it the current ones.
        if (method_exists($guard, 'setRequest') && $this->app->isBound('request')) {
            $guard->setRequest($this->app['request']);
        }

        if (method_exists($guard, 'setDispatcher') && $this->app->isBound('signals')) {
            $guard->setDispatcher($this->app['signals']);
        }

        return $guard;
    }

    /**
     * Resolve the given guard.
     *
     * @param  string  $name
     * @return \Mariner\Contracts\Auth\Guard|\Mariner\Contracts\Auth\StatefulGuard
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($name, $config);
        }

        $driverMethod = 'create'.ucfirst($config['driver']).'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($name, $config);
        }

        throw new InvalidArgumentException(
            "Auth driver [{$config['driver']}] for guard [{$name}] is not defined."
        );
    }

    /**
     * Call a custom driver creator.
     *
     * @param  string  $name
     * @param  array  $config
     * @return mixed
     */
    protected function callCustomCreator($name, array $config)
    {
        return $this->customCreators[$config['driver']]($this->app, $name, $config);
    }

    /**
     * Create a session based authentication guard.
     *
     * @param  string  $name
     * @param  array  $config
     * @return \Mariner\Auth\SessionGuard
     */
    public function createSessionDriver($name, $config)
    {
        $guard = new SessionGuard(
            $name,
            $this->createUserProvider($config['provider'] ?? null),
            $this->app['session.store'],
            rehashOnLogin: $this->app['config']->get('hashing.rehash_on_login', true),
            timeboxDuration: $this->app['config']->get('auth.timebox_duration', 200000),
            hashKey: $this->app['config']->get('app.key'),
        );

        // When using the remember me functionality of the authentication services we
        // will need to be set the encryption instance of the guard, which allows
        // secure, encrypted cookie values to get generated for those cookies.
        $guard->setCookieJar($this->app['cookie']);

        $guard->setDispatcher($this->app['signals']);

        $guard->setRequest($this->app['request']);

        if (isset($config['remember'])) {
            $guard->setRememberDuration($config['remember']);
        }

        return $guard;
    }

    /**
     * Create a token based authentication guard.
     *
     * @param  string  $name
     * @param  array  $config
     * @return \Mariner\Auth\TokenGuard
     */
    public function createTokenDriver($name, $config)
    {
        // The token guard implements a basic API token based guard implementation
        // that takes an API token field from the request and matches it to the
        // user in the database or another persistence layer where users are.
        $guard = new TokenGuard(
            $this->createUserProvider($config['provider'] ?? null),
            $this->app['request'],
            $config['input_key'] ?? 'api_token',
            $config['storage_key'] ?? 'api_token',
            $config['hash'] ?? false
        );

        return $guard;
    }

    /**
     * Get the guard configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->app['config']["auth.guards.{$name}"];
    }

    /**
     * Get the default authentication driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['auth.defaults.guard'];
    }

    /**
     * Set the default guard driver the factory should serve.
     *
     * @param  \UnitEnum|string|null  $name
     * @return void
     */
    public function shouldUse($name)
    {
        $name = enum_value($name) ?: $this->getDefaultDriver();

        $this->setDefaultDriver($name);

        $this->userResolver = fn ($name = null) => $this->guard($name)->user();
    }

    /**
     * Set the default authentication driver name.
     *
     * @param  \UnitEnum|string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['auth.defaults.guard'] = enum_value($name);
    }

    /**
     * Register a new callback based request guard.
     *
     * @param  string  $driver
     * @param  callable  $callback
     * @return $this
     */
    public function viaRequest($driver, callable $callback)
    {
        return $this->extend($driver, function () use ($callback) {
            return new RequestGuard($callback, $this->app['request'], $this->createUserProvider());
        });
    }

    /**
     * Get the user resolver callback.
     *
     * @return \Closure
     */
    public function userResolver()
    {
        return $this->userResolver;
    }

    /**
     * Set the callback to be used to resolve users.
     *
     * @param  \Closure  $userResolver
     * @return $this
     */
    public function resolveUsersUsing(Closure $userResolver)
    {
        $this->userResolver = $userResolver;

        return $this;
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     * @param  \Closure  $callback
     *
     * @param-closure-this  $this  $callback
     *
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        try {
            $callback = $this->bindCallbackToSelf($callback) ?? throw new RuntimeException('Unable to bind custom driver callback');
        } catch (ReflectionException $e) {
            throw new RuntimeException('Unable to bind custom driver callback', previous: $e);
        }

        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Register a custom provider creator Closure.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return $this
     */
    public function provider($name, Closure $callback)
    {
        $this->customProviderCreators[$name] = $callback;

        return $this;
    }

    /**
     * Determines if any guards have already been resolved.
     *
     * @return bool
     */
    public function hasResolvedGuards()
    {
        return count($this->guardState()->guards) > 0;
    }

    /**
     * Forget all of the resolved guard instances.
     *
     * @return $this
     */
    public function forgetGuards()
    {
        $this->guardState()->guards = [];

        return $this;
    }

    /**
     * Set the application instance used by the manager.
     *
     * @param  \Voyager\Contracts\Core\FrameworkCore  $app
     * @return $this
     */
    public function setApplication($app)
    {
        $this->app = $app;

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->guard()->{$method}(...$parameters);
    }

    /**
     * The guards resolved for the current request.
     */
    protected function guardState(): GuardState
    {
        return $this->app->make(GuardState::class);
    }
}
