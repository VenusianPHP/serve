<?php

namespace Mariner\NutsAndBolts\Facades;

use Mariner\Contracts\Auth\Access\Gate as GateContract;

/**
 * @method static bool has(\UnitEnum|array|string $ability)
 * @method static \Mariner\Auth\Access\Response allowIf(\Mariner\Auth\Access\Response|\Closure|bool $condition, string|null $message = null, string|null $code = null)
 * @method static \Mariner\Auth\Access\Response denyIf(\Mariner\Auth\Access\Response|\Closure|bool $condition, string|null $message = null, string|null $code = null)
 * @method static \Mariner\Auth\Access\Gate define(\UnitEnum|string $ability, callable|array|string $callback)
 * @method static \Mariner\Auth\Access\Gate resource(string $name, string $class, array|null $abilities = null)
 * @method static \Mariner\Auth\Access\Gate policy(string $class, string $policy)
 * @method static \Mariner\Auth\Access\Gate before(callable $callback)
 * @method static \Mariner\Auth\Access\Gate after(callable $callback)
 * @method static bool allows(iterable|\UnitEnum|string $ability, mixed $arguments = [])
 * @method static bool denies(iterable|\UnitEnum|string $ability, mixed $arguments = [])
 * @method static bool check(iterable|\UnitEnum|string $abilities, mixed $arguments = [])
 * @method static bool any(iterable|\UnitEnum|string $abilities, mixed $arguments = [])
 * @method static bool none(iterable|\UnitEnum|string $abilities, mixed $arguments = [])
 * @method static \Mariner\Auth\Access\Response authorize(\UnitEnum|string $ability, mixed $arguments = [])
 * @method static \Mariner\Auth\Access\Response inspect(\UnitEnum|string $ability, mixed $arguments = [])
 * @method static mixed raw(string $ability, mixed $arguments = [])
 * @method static mixed getPolicyFor(object|string $class)
 * @method static \Mariner\Auth\Access\Gate guessPolicyNamesUsing(callable $callback)
 * @method static mixed resolvePolicy(object|string $class)
 * @method static \Mariner\Auth\Access\Gate forUser(\Mariner\Contracts\Auth\Authenticatable|mixed $user)
 * @method static array abilities()
 * @method static array policies()
 * @method static \Mariner\Auth\Access\Gate defaultDenialResponse(\Mariner\Auth\Access\Response $response)
 * @method static \Mariner\Auth\Access\Gate setContainer(\Voyager\Contracts\Vessel\TheServiceContainer $container)
 * @method static \Mariner\Auth\Access\Response denyWithStatus(int $status, string|null $message = null, int|null $code = null)
 * @method static \Mariner\Auth\Access\Response denyAsNotFound(string|null $message = null, int|null $code = null)
 *
 * @see \Mariner\Auth\Access\Gate
 */
class Gate extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return GateContract::class;
    }
}
