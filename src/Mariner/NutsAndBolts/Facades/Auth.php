<?php

namespace Mariner\NutsAndBolts\Facades;

/**
 * @method static \Mariner\Contracts\Auth\Guard|\Mariner\Contracts\Auth\StatefulGuard guard(\UnitEnum|string|null $name = null)
 * @method static \Mariner\Auth\SessionGuard createSessionDriver(string $name, array $config)
 * @method static \Mariner\Auth\TokenGuard createTokenDriver(string $name, array $config)
 * @method static string getDefaultDriver()
 * @method static void shouldUse(\UnitEnum|string|null $name)
 * @method static void setDefaultDriver(\UnitEnum|string $name)
 * @method static \Mariner\Auth\AuthManager viaRequest(string $driver, callable $callback)
 * @method static \Closure userResolver()
 * @method static \Mariner\Auth\AuthManager resolveUsersUsing(\Closure $userResolver)
 * @method static \Mariner\Auth\AuthManager extend(string $driver, \Closure $callback)
 * @method static \Mariner\Auth\AuthManager provider(string $name, \Closure $callback)
 * @method static bool hasResolvedGuards()
 * @method static \Mariner\Auth\AuthManager forgetGuards()
 * @method static \Mariner\Auth\AuthManager setApplication(\Voyager\Contracts\Core\FrameworkCore $app)
 * @method static \Mariner\Contracts\Auth\UserProvider|null createUserProvider(string|null $provider = null)
 * @method static string getDefaultUserProvider()
 * @method static bool check()
 * @method static bool guest()
 * @method static \Mariner\Contracts\Auth\Authenticatable|null user()
 * @method static int|string|null id()
 * @method static bool validate(array $credentials = [])
 * @method static bool hasUser()
 * @method static \Mariner\Contracts\Auth\Guard setUser(\Mariner\Contracts\Auth\Authenticatable $user)
 * @method static bool attempt(array $credentials = [], bool $remember = false)
 * @method static bool once(array $credentials = [])
 * @method static void login(\Mariner\Contracts\Auth\Authenticatable $user, bool $remember = false)
 * @method static \Mariner\Contracts\Auth\Authenticatable|false loginUsingId(mixed $id, bool $remember = false)
 * @method static \Mariner\Contracts\Auth\Authenticatable|false onceUsingId(mixed $id)
 * @method static bool viaRemember()
 * @method static void logout()
 * @method static \Symfony\Component\HttpFoundation\Response|null basic(string $field = 'email', array $extraConditions = [])
 * @method static \Symfony\Component\HttpFoundation\Response|null onceBasic(string $field = 'email', array $extraConditions = [])
 * @method static bool attemptWhen(array $credentials = [], array|callable|null $callbacks = null, bool $remember = false)
 * @method static string hashPasswordForCookie(string|null $passwordHash)
 * @method static void logoutCurrentDevice()
 * @method static void logoutOtherDevices(string $password)
 * @method static void attempting(mixed $callback)
 * @method static \Mariner\Contracts\Auth\Authenticatable getLastAttempted()
 * @method static string getName()
 * @method static string getRecallerName()
 * @method static \Mariner\Auth\SessionGuard setRememberDuration(int $minutes)
 * @method static \Mariner\Contracts\Cookie\QueueingFactory getCookieJar()
 * @method static void setCookieJar(\Mariner\Contracts\Cookie\QueueingFactory $cookie)
 * @method static \Voyager\Contracts\Signals\SignalDispatcher getDispatcher()
 * @method static void setDispatcher(\Voyager\Contracts\Signals\SignalDispatcher $events)
 * @method static \Mariner\Contracts\Session\Session getSession()
 * @method static \Mariner\Contracts\Auth\Authenticatable|null getUser()
 * @method static \Symfony\Component\HttpFoundation\Request getRequest()
 * @method static \Mariner\Auth\SessionGuard setRequest(\Symfony\Component\HttpFoundation\Request $request)
 * @method static \Mariner\NutsAndBolts\Timebox getTimebox()
 * @method static \Mariner\Contracts\Auth\Authenticatable authenticate()
 * @method static \Mariner\Auth\SessionGuard forgetUser()
 * @method static \Mariner\Contracts\Auth\UserProvider getProvider()
 * @method static void setProvider(\Mariner\Contracts\Auth\UserProvider $provider)
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 *
 * @see \Mariner\Auth\AuthManager
 * @see \Mariner\Auth\SessionGuard
 */
class Auth extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'auth';
    }
}
