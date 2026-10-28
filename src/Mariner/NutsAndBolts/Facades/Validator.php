<?php

namespace Mariner\NutsAndBolts\Facades;

/**
 * @method static \Mariner\Validation\Validator make(array $data, array $rules, array $messages = [], array $attributes = [])
 * @method static array validate(array $data, array $rules, array $messages = [], array $attributes = [])
 * @method static void extend(string $rule, \Closure|string $extension, string|null $message = null)
 * @method static void extendImplicit(string $rule, \Closure|string $extension, string|null $message = null)
 * @method static void extendDependent(string $rule, \Closure|string $extension, string|null $message = null)
 * @method static void replacer(string $rule, \Closure|string $replacer)
 * @method static void includeUnvalidatedArrayKeys()
 * @method static void excludeUnvalidatedArrayKeys()
 * @method static void fakeDnsLookups(bool $value = true)
 * @method static void resolver(\Closure $resolver)
 * @method static \Mariner\Contracts\Translation\Translator getTranslator()
 * @method static \Mariner\Validation\PresenceVerifierInterface getPresenceVerifier()
 * @method static void setPresenceVerifier(\Mariner\Validation\PresenceVerifierInterface $presenceVerifier)
 * @method static \Voyager\Contracts\Vessel\TheServiceContainer|null getContainer()
 * @method static \Mariner\Validation\Factory setContainer(\Voyager\Contracts\Vessel\TheServiceContainer $container)
 *
 * @see \Mariner\Validation\Factory
 */
class Validator extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'validator';
    }
}
