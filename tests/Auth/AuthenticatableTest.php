<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\User;

test('it returns same remember token for string', function () {
    $user = new User;
    $user->setRememberToken('sample_token');
    expect($user->getRememberToken())->toBe('sample_token');
});

test('it returns string as remember token when it was set to true', function () {
    $user = new User;
    $user->setRememberToken(true);
    expect($user->getRememberToken())->toBe('1');
});

test('it returns null when remember token name was set to empty', function () {
    $user = new class extends User
    {
        public function getRememberTokenName()
        {
            return '';
        }
    };
    $user->setRememberToken(true);
    expect($user->getRememberToken())->toBeNull();
});
