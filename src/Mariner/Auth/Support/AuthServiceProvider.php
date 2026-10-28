<?php

namespace Mariner\Auth\Support;

use Mariner\Contracts\Auth\Access\Gate;
use Voyager\NutsAndBolts\ServiceProvider;

/**
 * The base for an app's own auth provider: list model => policy in $policies and they are
 * registered with the Gate before the app's providers boot.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy for each model.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [];

    /**
     * Register the policies once the app starts booting.
     *
     * @return void
     */
    public function register()
    {
        $this->booting(function () {
            $this->registerPolicies();
        });
    }

    /**
     * Register each policy with the Gate.
     *
     * @return void
     */
    public function registerPolicies()
    {
        $gate = $this->app->make(Gate::class);

        foreach ($this->policies() as $model => $policy) {
            $gate->policy($model, $policy);
        }
    }

    /**
     * The policies by model.
     *
     * @return array<class-string, class-string>
     */
    public function policies()
    {
        return $this->policies;
    }
}
