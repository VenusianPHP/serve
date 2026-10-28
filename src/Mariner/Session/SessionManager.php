<?php

namespace Mariner\Session;

use InvalidArgumentException;
use Voyager\NutsAndBolts\Manager;

use function Voyager\NutsAndBolts\enum_value;

/**
 * @mixin \Mariner\Session\Store
 */
class SessionManager extends Manager
{
    /**
     * Call a custom driver creator.
     *
     * @param  string  $driver
     * @return \Mariner\Session\Store
     */
    protected function callCustomCreator(string $driver): mixed
    {
        return $this->buildSession(parent::callCustomCreator($driver));
    }

    /**
     * Create an instance of the "null" session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createNullDriver()
    {
        return $this->buildSession(new NullSessionHandler);
    }

    /**
     * Create an instance of the "array" session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createArrayDriver()
    {
        return $this->buildSession(new ArraySessionHandler(
            $this->config->get('session.lifetime')
        ));
    }

    /**
     * Create an instance of the "cookie" session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createCookieDriver()
    {
        return $this->buildSession(new CookieSessionHandler(
            $this->vessel->make('cookie'),
            $this->config->get('session.lifetime'),
            $this->config->get('session.expire_on_close')
        ));
    }

    /**
     * Create an instance of the file session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createFileDriver()
    {
        return $this->createNativeDriver();
    }

    /**
     * Create an instance of the file session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createNativeDriver()
    {
        $lifetime = $this->config->get('session.lifetime');

        return $this->buildSession(new FileSessionHandler(
            $this->vessel->make('files'), $this->config->get('session.files'), $lifetime
        ));
    }

    /**
     * Create an instance of the database session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createDatabaseDriver()
    {
        $table = $this->config->get('session.table');

        $lifetime = $this->config->get('session.lifetime');

        return $this->buildSession(new DatabaseSessionHandler(
            $this->getDatabaseConnection(), $table, $lifetime, $this->vessel
        ));
    }

    /**
     * Get the database connection for the database driver.
     *
     * @return \Voyager\Database\Connection
     */
    protected function getDatabaseConnection()
    {
        $connection = $this->config->get('session.connection');

        return $this->vessel->make('db')->connection($connection);
    }

    /**
     * Create an instance of the APC session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createApcDriver()
    {
        return $this->createCacheBased('apc');
    }

    /**
     * Create an instance of the Memcached session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createMemcachedDriver()
    {
        return $this->createCacheBased('memcached');
    }

    /**
     * Create an instance of the Redis session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createRedisDriver()
    {
        $handler = $this->createCacheHandler('redis');

        $store = $handler->getCache()->getStore();

        $store->setConnection($this->config->get('session.connection'));

        if ($prefix = $this->config->get('session.prefix')) {
            $store->setPrefix($prefix);
        }

        return $this->buildSession($handler);
    }

    /**
     * Create an instance of the DynamoDB session driver.
     *
     * @return \Mariner\Session\Store
     */
    protected function createDynamodbDriver()
    {
        return $this->createCacheBased('dynamodb');
    }

    /**
     * Create an instance of a cache driven driver.
     *
     * @param  string  $driver
     * @return \Mariner\Session\Store
     */
    protected function createCacheBased($driver)
    {
        return $this->buildSession($this->createCacheHandler($driver));
    }

    /**
     * Create the cache based session handler instance.
     *
     * @param  string  $driver
     * @return \Mariner\Session\CacheBasedSessionHandler
     */
    protected function createCacheHandler($driver)
    {
        $store = $this->config->get('session.store') ?: $driver;

        return new CacheBasedSessionHandler(
            clone $this->vessel->make('cache')->store($store),
            $this->config->get('session.lifetime')
        );
    }

    /**
     * Build the session instance.
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \Mariner\Session\Store
     */
    protected function buildSession($handler)
    {
        return $this->config->get('session.encrypt')
            ? $this->buildEncryptedSession($handler)
            : new Store(
                $this->config->get('session.cookie'),
                $handler,
                $id = null,
                $this->config->get('session.serialization', 'php')
            );
    }

    /**
     * Build the encrypted session instance.
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \Mariner\Session\EncryptedStore
     */
    protected function buildEncryptedSession($handler)
    {
        return new EncryptedStore(
            $this->config->get('session.cookie'),
            $handler,
            $this->vessel['encrypter'],
            $id = null,
            $this->config->get('session.serialization', 'php'),
        );
    }

    /**
     * Determine if requests for the same session should wait for each to finish before executing.
     *
     * @return bool
     */
    public function shouldBlock()
    {
        return $this->config->get('session.block', false);
    }

    /**
     * Get the name of the cache store / driver that should be used to acquire session locks.
     *
     * @return string|null
     */
    public function blockDriver()
    {
        return $this->config->get('session.block_store');
    }

    /**
     * Get the maximum number of seconds the session lock should be held for.
     *
     * @return int
     */
    public function defaultRouteBlockLockSeconds()
    {
        return $this->config->get('session.block_lock_seconds', 10);
    }

    /**
     * Get the maximum number of seconds to wait while attempting to acquire a route block session lock.
     *
     * @return int
     */
    public function defaultRouteBlockWaitSeconds()
    {
        return $this->config->get('session.block_wait_seconds', 10);
    }

    /**
     * Get the session configuration.
     *
     * @return array
     */
    public function getSessionConfig()
    {
        return $this->config->get('session');
    }

    /**
     * Get the default session driver name.
     *
     * @return string|null
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('session.driver');
    }

    /**
     * Set the default session driver name.
     *
     * @param  \UnitEnum|string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->config->set('session.driver', enum_value($name));
    }

    /**
     * Get a session store, opened once per request.
     *
     * Stores hold the request's session data, so they're cached in the request's SessionState
     * rather than on this shared manager.
     */
    public function driver(?string $driver = null): mixed
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new InvalidArgumentException(sprintf('Unable to resolve NULL driver for [%s].', static::class));
        }

        return $this->sessionState()->drivers[$driver] ??= $this->createDriver($driver);
    }

    /**
     * Get the session stores opened during the current request.
     */
    public function getDrivers(): array
    {
        return $this->sessionState()->drivers;
    }

    /**
     * Forget the session stores opened during the current request.
     */
    public function forgetDrivers(): static
    {
        $this->sessionState()->drivers = [];

        return $this;
    }

    protected function sessionState(): SessionState
    {
        return $this->vessel->make(SessionState::class);
    }
}
