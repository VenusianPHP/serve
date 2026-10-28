<?php

namespace Mariner\Auth;

use InvalidArgumentException;

trait CreatesUserProviders
{
    /**
     * The registered custom provider creators.
     *
     * @var array
     */
    protected $customProviderCreators = [];

    /**
     * Create the user provider implementation for the driver.
     *
     * @param  string|null  $provider
     * @return \Mariner\Contracts\Auth\UserProvider|null
     *
     * @throws \InvalidArgumentException
     */
    public function createUserProvider($provider = null)
    {
        if (is_null($config = $this->getProviderConfiguration($provider))) {
            return null;
        }

        if (isset($this->customProviderCreators[$driver = ($config['driver'] ?? null)])) {
            return call_user_func(
                $this->customProviderCreators[$driver], $this->app, $config
            );
        }

        return match ($driver) {
            'database' => $this->createDatabaseProvider($config),
            'instrument' => $this->createInstrumentProvider($config),
            default => throw new InvalidArgumentException(
                "Authentication user provider [{$driver}] is not defined."
            ),
        };
    }

    /**
     * Get the user provider configuration.
     *
     * @param  string|null  $provider
     * @return array|null
     */
    protected function getProviderConfiguration($provider)
    {
        if ($provider = $provider ?: $this->getDefaultUserProvider()) {
            return $this->app['config']['auth.providers.'.$provider];
        }

        return null;
    }

    /**
     * Create an instance of the database user provider.
     *
     * @param  array  $config
     * @return \Mariner\Auth\DatabaseUserProvider
     */
    protected function createDatabaseProvider($config)
    {
        return new DatabaseUserProvider(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
        );
    }

    /**
     * Create an instance of the Instrument user provider.
     *
     * @param  array  $config
     * @return \Mariner\Auth\InstrumentUserProvider
     */
    protected function createInstrumentProvider($config)
    {
        return new InstrumentUserProvider($this->app['hash'], $config['model']);
    }

    /**
     * Get the default user provider name.
     *
     * @return string
     */
    public function getDefaultUserProvider()
    {
        return $this->app['config']['auth.defaults.provider'];
    }
}
