<?php

namespace Venusian\Serve\Tests\Session;

use Mariner\Cookie\CookieJar;
use Mariner\Session\CookieSessionHandler;
use Mariner\Session\Store;
use Mariner\NutsAndBolts\MessageBag;
use Voyager\NutsAndBolts\Str;
use Mariner\NutsAndBolts\ViewErrorBag;
use Mockery;
use ReflectionClass;
use SessionHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

test('session is loaded from handler', function () {
    $session = getSession();
    $session->getHandler()->expects('read')->with(getSessionId())->andReturn(serialize(['foo' => 'bar', 'bagged' => ['name' => 'taylor'], '123' => 'bax']));
    $session->start();

    expect($session->get('foo'))->toBe('bar');
    expect($session->get('123'))->toBe('bax');
    expect($session->get('bar', 'baz'))->toBe('baz');
    expect($session->has('foo'))->toBeTrue();
    expect($session->has('123'))->toBeTrue();
    expect($session->has('bar'))->toBeFalse();
    expect($session->isStarted())->toBeTrue();

    $session->put('baz', 'boom');
    expect($session->has('baz'))->toBeTrue();
});

test('session migration', function () {
    $session = getSession();
    $oldId = $session->getId();
    $session->getHandler()->shouldReceive('destroy')->never();
    expect($session->migrate())->toBeTrue();
    expect($session->getId())->not->toEqual($oldId);

    $session = getSession();
    $oldId = $session->getId();
    $session->getHandler()->expects('destroy')->with($oldId);
    expect($session->migrate(true))->toBeTrue();
    expect($session->getId())->not->toEqual($oldId);
});

test('session regeneration', function () {
    $session = getSession();
    $oldId = $session->getId();
    $session->getHandler()->shouldReceive('destroy')->never();
    expect($session->regenerate())->toBeTrue();
    expect($session->getId())->not->toEqual($oldId);
});

test('cant set invalid id', function () {
    $session = getSession();
    expect($session->isValidId($session->getId()))->toBeTrue();

    $session->setId(null);
    expect($session->getId())->not->toBeNull();
    expect($session->isValidId($session->getId()))->toBeTrue();

    $session->setId(['a']);
    expect($session->getId())->not->toBe(['a']);

    $session->setId('wrong');
    expect($session->getId())->not->toBe('wrong');
});

test('session invalidate', function () {
    $session = getSession();
    $oldId = $session->getId();

    $session->put('foo', 'bar');
    expect(count($session->all()))->toBeGreaterThan(0);

    $session->flash('name', 'Taylor');
    expect($session->has('name'))->toBeTrue();

    $session->getHandler()->expects('destroy')->with($oldId);
    expect($session->invalidate())->toBeTrue();

    expect($session->has('name'))->toBeFalse();
    expect($session->getId())->not->toEqual($oldId);
    expect($session->all())->toHaveCount(0);
});

test('brand new session is properly saved', function () {
    $session = getSession();
    $session->getHandler()->expects('read')->andReturn(serialize([]));
    $session->start();
    $session->put('foo', 'bar');
    $session->flash('baz', 'boom');
    $session->now('qux', 'norf');
    $session->getHandler()->expects('write')->with(
        getSessionId(),
        serialize([
            '_token' => $session->token(),
            'foo' => 'bar',
            'baz' => 'boom',
            '_flash' => [
                'new' => [],
                'old' => ['baz'],
            ],
        ])
    );
    $session->save();

    expect($session->isStarted())->toBeFalse();
});

test('session is properly updated', function () {
    $session = getSession();
    $session->getHandler()->expects('read')->andReturn(serialize([
        '_token' => Str::random(40),
        'foo' => 'bar',
        'baz' => 'boom',
        '_flash' => [
            'new' => [],
            'old' => ['baz'],
        ],
    ]));
    $session->start();

    $session->getHandler()->expects('write')->with(
        getSessionId(),
        serialize([
            '_token' => $session->token(),
            'foo' => 'bar',
            '_flash' => [
                'new' => [],
                'old' => [],
            ],
        ])
    );

    $session->save();

    expect($session->isStarted())->toBeFalse();
});

test('session is re saved when nothing has changed', function () {
    $session = getSession();
    $session->getHandler()->expects('read')->andReturn(serialize([
        '_token' => Str::random(40),
        'foo' => 'bar',
        'baz' => 'boom',
        '_flash' => [
            'new' => [],
            'old' => [],
        ],
    ]));
    $session->start();

    $session->getHandler()->expects('write')->with(
        getSessionId(),
        serialize([
            '_token' => $session->token(),
            'foo' => 'bar',
            'baz' => 'boom',
            '_flash' => [
                'new' => [],
                'old' => [],
            ],
        ])
    );

    $session->save();

    expect($session->isStarted())->toBeFalse();
});

test('session is re saved when nothing has changed except session id', function () {
    $session = getSession();
    $oldId = $session->getId();
    $token = Str::random(40);
    $session->getHandler()->expects('read')->with($oldId)->andReturn(serialize([
        '_token' => $token,
        'foo' => 'bar',
        'baz' => 'boom',
        '_flash' => [
            'new' => [],
            'old' => [],
        ],
    ]));
    $session->start();

    $oldId = $session->getId();
    $session->migrate();
    $newId = $session->getId();

    expect($oldId)->not->toEqual($newId);

    $session->getHandler()->expects('write')->with(
        $newId,
        serialize([
            '_token' => $token,
            'foo' => 'bar',
            'baz' => 'boom',
            '_flash' => [
                'new' => [],
                'old' => [],
            ],
        ])
    );

    $session->save();

    expect($session->isStarted())->toBeFalse();
});

test('old input flashing', function () {
    $session = getSession();
    $session->put('boom', 'baz');
    $session->flashInput(['foo' => 'bar', 'bar' => 0, 'name' => null]);

    expect($session->hasOldInput('foo'))->toBeTrue();
    expect($session->getOldInput('foo'))->toBe('bar');
    expect($session->getOldInput('bar'))->toEqual(0);
    expect($session->hasOldInput('boom'))->toBeFalse();

    $session->ageFlashData();

    expect($session->hasOldInput('foo'))->toBeTrue();
    expect($session->getOldInput('foo'))->toBe('bar');
    expect($session->getOldInput('bar'))->toEqual(0);
    expect($session->hasOldInput('boom'))->toBeFalse();

    expect($session->getOldInput('input', 'default'))->toBe('default');
    expect($session->getOldInput('name', 'default'))->toBeNull();
});

test('data flashing', function () {
    $session = getSession();
    $session->flash('foo', 'bar');
    $session->flash('bar', 0);
    $session->flash('baz');

    expect($session->has('foo'))->toBeTrue();
    expect($session->get('foo'))->toBe('bar');
    expect($session->get('bar'))->toEqual(0);
    expect($session->get('baz'))->toBeTrue();

    $session->ageFlashData();

    expect($session->has('foo'))->toBeTrue();
    expect($session->get('foo'))->toBe('bar');
    expect($session->get('bar'))->toEqual(0);

    $session->ageFlashData();

    expect($session->has('foo'))->toBeFalse();
    expect($session->get('foo'))->toBeNull();
});

test('data flashing now', function () {
    $session = getSession();
    $session->now('foo', 'bar');
    $session->now('bar', 0);

    expect($session->has('foo'))->toBeTrue();
    expect($session->get('foo'))->toBe('bar');
    expect($session->get('bar'))->toEqual(0);

    $session->ageFlashData();

    expect($session->has('foo'))->toBeFalse();
    expect($session->get('foo'))->toBeNull();
});

test('data merge new flashes', function () {
    $session = getSession();
    $session->flash('foo', 'bar');
    $session->put('fu', 'baz');
    $session->put('_flash.old', ['qu']);
    expect($session->get('_flash.new'))->toContain('foo');
    expect($session->get('_flash.new'))->not->toContain('fu');
    $session->keep(['fu', 'qu']);
    expect($session->get('_flash.new'))->toContain('foo');
    expect($session->get('_flash.new'))->toContain('fu');
    expect($session->get('_flash.new'))->toContain('qu');
    expect($session->get('_flash.old'))->not->toContain('qu');
});

test('reflash', function () {
    $session = getSession();
    $session->flash('foo', 'bar');
    $session->put('_flash.old', ['foo']);
    $session->reflash();
    expect($session->get('_flash.new'))->toContain('foo');
    expect($session->get('_flash.old'))->not->toContain('foo');
});

test('reflash with now', function () {
    $session = getSession();
    $session->now('foo', 'bar');
    $session->reflash();
    expect($session->get('_flash.new'))->toContain('foo');
    expect($session->get('_flash.old'))->not->toContain('foo');
});

test('only', function () {
    $session = getSession();
    $session->put('foo', 'bar');
    $session->put('qu', 'ux');
    expect($session->all())->toEqual(['foo' => 'bar', 'qu' => 'ux']);
    expect($session->only(['qu']))->toEqual(['qu' => 'ux']);
});

test('except', function () {
    $session = getSession();
    $session->put('foo', 'bar');
    $session->put('bar', 'baz');
    $session->put('qu', 'ux');

    expect($session->all())->toEqual(['foo' => 'bar', 'qu' => 'ux', 'bar' => 'baz']);
    expect($session->except(['foo']))->toEqual(['bar' => 'baz', 'qu' => 'ux']);
});

test('replace', function () {
    $session = getSession();
    $session->put('foo', 'bar');
    $session->put('qu', 'ux');
    $session->replace(['foo' => 'baz']);
    expect($session->get('foo'))->toBe('baz');
    expect($session->get('qu'))->toBe('ux');
});

test('remove', function () {
    $session = getSession();
    $session->put('foo', 'bar');
    $pulled = $session->remove('foo');
    expect($session->has('foo'))->toBeFalse();
    expect($pulled)->toBe('bar');
});

test('clear', function () {
    $session = getSession();
    $session->put('foo', 'bar');

    $session->flush();
    expect($session->has('foo'))->toBeFalse();

    $session->put('foo', 'bar');

    $session->flush();
    expect($session->has('foo'))->toBeFalse();
});

test('increment', function () {
    $session = getSession();

    $session->put('foo', 5);
    $foo = $session->increment('foo');
    expect($foo)->toEqual(6);
    expect($session->get('foo'))->toEqual(6);

    $foo = $session->increment('foo', 4);
    expect($foo)->toEqual(10);
    expect($session->get('foo'))->toEqual(10);

    $session->increment('bar');
    expect($session->get('bar'))->toEqual(1);
});

test('decrement', function () {
    $session = getSession();

    $session->put('foo', 5);
    $foo = $session->decrement('foo');
    expect($foo)->toEqual(4);
    expect($session->get('foo'))->toEqual(4);

    $foo = $session->decrement('foo', 4);
    expect($foo)->toEqual(0);
    expect($session->get('foo'))->toEqual(0);

    $session->decrement('bar');
    expect($session->get('bar'))->toEqual(-1);
});

test('has old input without key', function () {
    $session = getSession();
    $session->flash('boom', 'baz');
    expect($session->hasOldInput())->toBeFalse();

    $session->flashInput(['foo' => 'bar']);
    expect($session->hasOldInput())->toBeTrue();
});

test('handler needs request', function () {
    $session = getSession();
    expect($session->handlerNeedsRequest())->toBeFalse();
    $session->getHandler()->shouldReceive('setRequest')->never();

    $session = new Store('test', Mockery::mock(new CookieSessionHandler(new CookieJar, 60, false)));
    expect($session->handlerNeedsRequest())->toBeTrue();
    $session->getHandler()->expects('setRequest');
    $request = new Request;
    $session->setRequestOnHandler($request);
});

test('token', function () {
    $session = getSession();
    expect($session->token())->toEqual($session->token());
});

test('regenerate token', function () {
    $session = getSession();
    $token = $session->token();
    $session->regenerateToken();
    expect($session->token())->not->toEqual($token);
});

test('name', function () {
    $session = getSession();
    expect(getSessionName())->toEqual($session->getName());
    $session->setName('foo');
    expect($session->getName())->toBe('foo');
});

test('forget', function () {
    $session = getSession();
    $session->put('foo', 'bar');
    expect($session->has('foo'))->toBeTrue();
    $session->forget('foo');
    expect($session->has('foo'))->toBeFalse();

    $session->put('foo', 'bar');
    $session->put('bar', 'baz');
    $session->forget(['foo', 'bar']);
    expect($session->has('foo'))->toBeFalse();
    expect($session->has('bar'))->toBeFalse();
});

test('set previous url', function () {
    $session = getSession();
    $session->setPreviousUrl('https://example.com/foo/bar');

    expect($session->has('_previous.url'))->toBeTrue();
    expect($session->get('_previous.url'))->toBe('https://example.com/foo/bar');

    $url = $session->previousUrl();
    expect($url)->toBe('https://example.com/foo/bar');
});

test('password confirmed', function () {
    $session = getSession();
    expect($session->has('auth.password_confirmed_at'))->toBeFalse();
    $session->passwordConfirmed();
    expect($session->has('auth.password_confirmed_at'))->toBeTrue();
});

test('key push', function () {
    $session = getSession();
    $session->put('language', ['PHP' => ['Laravel']]);
    $session->push('language.PHP', 'Symfony');

    expect($session->get('language'))->toEqual(['PHP' => ['Laravel', 'Symfony']]);
});

test('key pull', function () {
    $session = getSession();
    $session->put('name', 'Taylor');

    expect($session->pull('name'))->toBe('Taylor');
    expect($session->pull('name', 'Taylor Otwell'))->toBe('Taylor Otwell');
    expect($session->pull('name'))->toBeNull();
});

test('key has', function () {
    $session = getSession();
    $session->put('first_name', 'Mehdi');
    $session->put('last_name', 'Rajabi');

    expect($session->has('first_name'))->toBeTrue();
    expect($session->has('last_name'))->toBeTrue();
    expect($session->has('first_name', 'last_name'))->toBeTrue();
    expect($session->has(['first_name', 'last_name']))->toBeTrue();

    expect($session->has('first_name', 'foo'))->toBeFalse();
    expect($session->has('foo', 'bar'))->toBeFalse();
});

test('key has any', function () {
    $session = getSession();
    $session->put('first_name', 'Mahmoud');
    $session->put('last_name', 'Ramadan');

    expect($session->hasAny('first_name'))->toBeTrue();
    expect($session->hasAny('first_name', 'last_name'))->toBeTrue();
    expect($session->hasAny(['first_name', 'last_name']))->toBeTrue();
    expect($session->hasAny(['first_name', 'middle_name']))->toBeTrue();

    expect($session->hasAny('middle_name'))->toBeFalse();
    expect($session->hasAny('foo', 'bar'))->toBeFalse();
    expect($session->hasAny(['foo', 'bar']))->toBeFalse();
});

test('key exists', function () {
    $session = getSession();
    $session->put('foo', 'bar');
    expect($session->exists('foo'))->toBeTrue();
    $session->put('baz', null);
    $session->put('hulk', ['one' => true]);
    expect($session->has('baz'))->toBeFalse();
    expect($session->exists('baz'))->toBeTrue();
    expect($session->exists('bogus'))->toBeFalse();
    expect($session->exists(['foo', 'baz']))->toBeTrue();
    expect($session->exists(['foo', 'baz', 'bogus']))->toBeFalse();
    expect($session->exists(['hulk.one']))->toBeTrue();
    expect($session->exists(['hulk.two']))->toBeFalse();
});

test('key missing', function () {
    $session = getSession();
    $session->put('foo', 'bar');
    expect($session->missing('foo'))->toBeFalse();
    $session->put('baz', null);
    $session->put('hulk', ['one' => true]);
    expect($session->has('baz'))->toBeFalse();
    expect($session->missing('baz'))->toBeFalse();
    expect($session->missing('bogus'))->toBeTrue();
    expect($session->missing(['foo', 'baz']))->toBeFalse();
    expect($session->missing(['foo', 'baz', 'bogus']))->toBeTrue();
    expect($session->missing(['hulk.one']))->toBeFalse();
    expect($session->missing(['hulk.two']))->toBeTrue();
});

test('backed enum key put', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 'Taylor');

    expect($session->get('user'))->toBe('Taylor');
    expect($session->get(SessionTestKey::User))->toBe('Taylor');
});

test('backed enum key get', function () {
    $session = getSession();
    $session->put('user', 'Taylor');

    expect($session->get(SessionTestKey::User))->toBe('Taylor');
    expect($session->get(SessionTestKey::Settings, 'default'))->toBe('default');
});

test('backed enum key has', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 'Taylor');
    $session->put(SessionTestKey::Settings, 'dark-mode');

    expect($session->has(SessionTestKey::User))->toBeTrue();
    expect($session->has(SessionTestKey::User, SessionTestKey::Settings))->toBeTrue();
    expect($session->has([SessionTestKey::User, SessionTestKey::Settings]))->toBeTrue();
    expect($session->has(SessionTestKey::Preference))->toBeFalse();
});

test('backed enum key has any', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 'Taylor');
    $session->put(SessionTestKey::Settings, 'dark-mode');

    expect($session->hasAny(SessionTestKey::User))->toBeTrue();
    expect($session->hasAny('user'))->toBeTrue();
    expect($session->hasAny(SessionTestKey::User, SessionTestKey::Preference, 'foo'))->toBeTrue();
    expect($session->hasAny([SessionTestKey::User, SessionTestKey::Preference, 'foo']))->toBeTrue();

    expect($session->hasAny(SessionTestKey::Preference))->toBeFalse();
    expect($session->hasAny('preference'))->toBeFalse();
    expect($session->hasAny(SessionTestKey::Preference, 'foo'))->toBeFalse();
    expect($session->hasAny([SessionTestKey::Preference, 'foo']))->toBeFalse();
});

test('backed enum key exists', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 'Taylor');
    $session->put(SessionTestKey::Settings, null);

    expect($session->exists(SessionTestKey::User))->toBeTrue();
    expect($session->exists(SessionTestKey::Settings))->toBeTrue();
    expect($session->exists(SessionTestKey::Preference))->toBeFalse();

    expect($session->exists('user'))->toBeTrue();
    expect($session->exists('preference'))->toBeFalse();
});

test('backed enum key missing', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 'Taylor');
    $session->put(SessionTestKey::Settings, null);

    expect($session->missing(SessionTestKey::User))->toBeFalse();
    expect($session->missing(SessionTestKey::Settings))->toBeFalse();
    expect($session->missing(SessionTestKey::Preference))->toBeTrue();

    expect($session->missing('user'))->toBeFalse();
    expect($session->missing('preference'))->toBeTrue();
});

test('backed enum key forget', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 'Taylor');
    expect($session->has('user'))->toBeTrue();

    $session->forget(SessionTestKey::User);
    expect($session->has('user'))->toBeFalse();

    $session->put(SessionTestKey::User, 'Taylor');
    $session->put(SessionTestKey::Settings, 'dark-mode');
    $session->forget([SessionTestKey::User, SessionTestKey::Settings]);
    expect($session->has('user'))->toBeFalse();
    expect($session->has('settings'))->toBeFalse();
});

test('backed enum key pull', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 'Taylor');

    expect($session->pull(SessionTestKey::User))->toBe('Taylor');
    expect($session->pull(SessionTestKey::User))->toBeNull();
    expect($session->pull(SessionTestKey::User, 'default'))->toBe('default');
});

test('backed enum key remember', function () {
    $session = getSession();

    $result = $session->remember(SessionTestKey::User, fn () => 'Taylor');

    expect($result)->toBe('Taylor');
    expect($session->get('user'))->toBe('Taylor');
    expect($session->remember(SessionTestKey::User, fn () => 'Otwell'))->toBe('Taylor');
});

test('backed enum key push', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, ['Taylor']);
    $session->push(SessionTestKey::User, 'Otwell');

    expect($session->get('user'))->toBe(['Taylor', 'Otwell']);
});

test('backed enum key increment', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 5);

    expect($session->increment(SessionTestKey::User))->toBe(6);
    expect($session->get('user'))->toBe(6);

    expect($session->increment(SessionTestKey::User, 4))->toBe(10);
    expect($session->get('user'))->toBe(10);
});

test('backed enum key decrement', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 5);

    expect($session->decrement(SessionTestKey::User))->toBe(4);
    expect($session->get('user'))->toBe(4);
});

test('backed enum key remove', function () {
    $session = getSession();
    $session->put(SessionTestKey::User, 'Taylor');

    expect($session->remove(SessionTestKey::User))->toBe('Taylor');
    expect($session->has('user'))->toBeFalse();
});

test('backed enum key flash', function () {
    $session = getSession();
    $session->flash(SessionTestKey::User, 'Taylor');
    expect($session->has(SessionTestKey::User))->toBeTrue();
});

test('backed enum key now', function () {
    $session = getSession();
    $session->now(SessionTestKey::User, 'Taylor');
    expect($session->has(SessionTestKey::User))->toBeTrue();
});

test('remember method calls put and returns default', function () {
    $session = getSession();
    $result = $session->remember('foo', function () {
        return 'bar';
    });
    expect($session->get('foo'))->toBe('bar');
    expect($result)->toBe('bar');
});

test('remember method returns previous value if it already sets', function () {
    $session = getSession();
    $session->put('key', 'foo');
    $result = $session->remember('key', function () {
        return 'bar';
    });
    expect($session->get('key'))->toBe('foo');
    expect($result)->toBe('foo');
});

test('validation errors can be serialized as json', function () {
    $session = getSession('json');
    $session->getHandler()->expects('read')->andReturn(serialize([]));
    $session->start();
    $session->put('errors', $errorBag = new ViewErrorBag);
    $messageBag = new MessageBag([
        'first_name' => [
            'Your first name is required',
            'Your first name must be at least 1 character',
        ],
    ]);
    $messageBag->setFormat('<p>:message</p>');
    $errorBag->put('default', $messageBag);

    $session->getHandler()->expects('write')->with(
        getSessionId(),
        json_encode([
            '_token' => $session->token(),
            'errors' => [
                'default' => [
                    'format' => '<p>:message</p>',
                    'messages' => [
                        'first_name' => [
                            'Your first name is required',
                            'Your first name must be at least 1 character',
                        ],
                    ],
                ],
            ],
            '_flash' => [
                'old' => [],
                'new' => [],
            ],
        ])
    );
    $session->save();

    expect($session->isStarted())->toBeFalse();
});

test('validation errors can be read as json', function () {
    $session = getSession('json');
    $session->getHandler()->expects('read')->with(getSessionId())->andReturn(json_encode([
        'errors' => [
            'default' => [
                'format' => '<p>:message</p>',
                'messages' => [
                    'first_name' => [
                        'Your first name is required',
                        'Your first name must be at least 1 character',
                    ],
                ],
            ],
        ],
    ]));
    $session->start();

    $errors = $session->get('errors');

    expect($errors)->toBeInstanceOf(ViewErrorBag::class);
    expect($errors->getBags()['default'])->toBeInstanceOf(MessageBag::class);
    expect($errors->getBags()['default']->getFormat())->toBe('<p>:message</p>');
    expect($errors->getBags()['default']->getMessages())->toEqual(['first_name' => [
        'Your first name is required',
        'Your first name must be at least 1 character',
    ]]);
});

test('it is macroable', function () {
    getSession()->macro('foo', function () {
        return 'macroable';
    });

    expect(getSession()->foo())->toBe('macroable');
});

function getSession($serialization = 'php')
{
    $reflection = new ReflectionClass(Store::class);

    return $reflection->newInstanceArgs(getMocks($serialization));
}

function getMocks($serialization = 'json')
{
    return [
        getSessionName(),
        Mockery::mock(SessionHandlerInterface::class),
        getSessionId(),
        $serialization,
    ];
}

function getSessionId()
{
    return 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
}

function getSessionName()
{
    return 'name';
}

enum SessionTestKey: string
{
    case User = 'user';
    case Settings = 'settings';
    case Preference = 'preference';
}
