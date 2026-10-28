<?php

namespace Venusian\Serve\Tests\Integration\Validation;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Schema\Blueprint;
use Voyager\Database\Testing\RefreshDatabase;
use Voyager\NutsAndBolts\Str;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\DatabasePresenceVerifier;
use Mariner\Validation\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('uuid');
        $table->string('first_name');
    });

    User::create(['uuid' => (string) Str::uuid(), 'first_name' => 'John']);
    User::create(['uuid' => (string) Str::uuid(), 'first_name' => 'Jim']);
});

test('exists', function () {
    $validator = getValidator(['first_name' => ['John', 'Taylor']], ['first_name' => 'exists:users']);
    expect($validator->passes())->toBeFalse();
});

test('unique', function () {
    $validator = getValidator(['first_name' => 'John'], ['first_name' => 'unique:'.User::class]);
    expect($validator->passes())->toBeFalse();

    $validator = getValidator(['first_name' => 'John'], ['first_name' => 'unique:'.User::class.',first_name,1']);
    expect($validator->passes())->toBeTrue();

    $validator = getValidator(['first_name' => 'Taylor'], ['first_name' => 'unique:'.User::class]);
    expect($validator->passes())->toBeTrue();
});

test('unique with custom model key', function () {
    $_SERVER['CUSTOM_MODEL_KEY_NAME'] = 'uuid';

    $validator = getValidator(['first_name' => 'John'], ['first_name' => 'unique:'.UserWithUuid::class]);
    expect($validator->passes())->toBeFalse();

    $user = UserWithUuid::where('first_name', 'John')->first();

    $validator = getValidator(['first_name' => 'John'], ['first_name' => 'unique:'.UserWithUuid::class.',first_name,'.$user->uuid]);
    expect($validator->passes())->toBeTrue();

    $validator = getValidator(['first_name' => 'John'], ['first_name' => 'unique:users,first_name,'.$user->uuid.',uuid']);
    expect($validator->passes())->toBeTrue();

    $validator = getValidator(['first_name' => 'John'], ['first_name' => 'unique:users,first_name,'.$user->uuid.',id']);
    expect($validator->passes())->toBeFalse();

    $validator = getValidator(['first_name' => 'Taylor'], ['first_name' => 'unique:'.UserWithUuid::class]);
    expect($validator->passes())->toBeTrue();

    unset($_SERVER['CUSTOM_MODEL_KEY_NAME']);
});

test('implicit attribute formatting', function () {
    $translator = new Translator(new ArrayLoader, 'en');
    $translator->addLines(['validation.string' => ':attribute must be a string!'], 'en');
    $validator = new Validator($translator, [['name' => 1]], ['*.name' => 'string']);

    $validator->setImplicitAttributesFormatter(function ($attribute) {
        [$line, $attribute] = explode('.', $attribute);

        return sprintf('%s at line %d', $attribute, $line + 1);
    });

    $validator->passes();

    expect($validator->getMessageBag()->all()[0])->toBe('name at line 1 must be a string!');
});

function getValidator(array $data, array $rules)
{
    $translator = new Translator(new ArrayLoader, 'en');
    $validator = new Validator($translator, $data, $rules);
    $validator->setPresenceVerifier(new DatabasePresenceVerifier(app('db')));

    return $validator;
}

class User extends Model
{
    public $timestamps = false;
    protected $guarded = [];
}

class UserWithUuid extends Model
{
    protected $table = 'users';
    public $timestamps = false;
    protected $guarded = [];
    protected $keyType = 'string';
    public $incrementing = false;

    public function getKeyName()
    {
        return 'uuid';
    }
}
