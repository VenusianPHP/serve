<?php

namespace Venusian\Serve\Tests\Translation;

use Mariner\Contracts\Translation\Loader;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\Collection;
use Venusian\Serve\Tests\Translation\Fixtures\Enums\Bar;
use Venusian\Serve\Tests\Translation\Fixtures\Enums\Baz;
use Venusian\Serve\Tests\Translation\Fixtures\Enums\Foo;
use Mariner\Translation\MessageSelector;
use Mariner\Translation\Translator;
use InvalidArgumentException;
use Mockery;

test('set locale rejects path traversal', function () {
    $t = new Translator(getLoader(), 'en');

    foreach (['../secret', '..\\secret', '..', "en\0"] as $locale) {
        try {
            $t->setLocale($locale);

            $this->fail("Locale [{$locale}] should have been rejected.");
        } catch (InvalidArgumentException $e) {
            expect($e->getMessage())->toBe('Invalid characters present in locale.');
        }
    }

    expect($t->getLocale())->toBe('en');
});

test('has method returns false when returned translation is null', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get'])->setConstructorArgs([getLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with('foo', [], 'bar')->willReturn('foo');
    expect($t->has('foo', 'bar'))->toBeFalse();

    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get'])->setConstructorArgs([getLoader(), 'en', 'sp'])->getMock();
    $t->expects($this->once())->method('get')->with('foo', [], 'bar')->willReturn('bar');
    expect($t->has('foo', 'bar'))->toBeTrue();

    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get'])->setConstructorArgs([getLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with('foo', [], 'bar', false)->willReturn('bar');
    expect($t->hasForLocale('foo', 'bar'))->toBeTrue();

    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get'])->setConstructorArgs([getLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with('foo', [], 'bar', false)->willReturn('foo');
    expect($t->hasForLocale('foo', 'bar'))->toBeFalse();

    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'foo', '*')->andReturn(['foo' => 'bar']);
    expect($t->hasForLocale('foo'))->toBeTrue();

    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'foo', '*')->andReturn([]);
    expect($t->hasForLocale('foo'))->toBeFalse();
});

test('get method properly loads and retrieves item', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo', 'qux' => ['tree :foo', 'breeze :foo']]);
    expect($t->get('foo::bar.qux', ['foo' => 'bar'], 'en'))->toEqual(['tree bar', 'breeze bar']);
    expect($t->get('foo::bar.baz', ['foo' => 'bar'], 'en'))->toBe('breeze bar');
    expect($t->get('foo::bar.foo'))->toBe('foo');
});

test('get method properly loads and retrieves array item', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo', 'qux' => ['tree :foo', 'breeze :foo', 'beep' => ['rock' => 'tree :foo']]]);
    expect($t->get('foo::bar', ['foo' => 'bar'], 'en'))->toEqual(['foo' => 'foo', 'baz' => 'breeze bar', 'qux' => ['tree bar', 'breeze bar', 'beep' => ['rock' => 'tree bar']]]);
    expect($t->get('foo::bar.baz', ['foo' => 'bar'], 'en'))->toBe('breeze bar');
    expect($t->get('foo::bar.foo'))->toBe('foo');
});

test('string method properly loads and retrieves string item', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn(['baz' => 'breeze :foo']);
    expect($t->string('foo::bar.baz', ['foo' => 'bar'], 'en'))->toBe('breeze bar');
});

test('string method throws exception for array item', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn(['baz' => ['breeze']]);

    $t->string('foo::bar.baz', [], 'en');
})->throws(InvalidArgumentException::class, 'Translation value for key [foo::bar.baz] must be a string, array given.');

test('array method properly loads and retrieves array item', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn(['baz' => ['breeze :foo']]);
    expect($t->array('foo::bar.baz', ['foo' => 'bar'], 'en'))->toBe(['breeze bar']);
});

test('array method throws exception for string item', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn(['baz' => 'breeze']);

    $t->array('foo::bar.baz', [], 'en');
})->throws(InvalidArgumentException::class, 'Translation value for key [foo::bar.baz] must be an array, string given.');

test('get method for non existing returns same key', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo', 'qux' => ['tree :foo', 'breeze :foo']]);
    $t->getLoader()->expects('load')->with('en', 'unknown', 'foo')->andReturn([]);
    expect($t->get('foo::unknown', ['foo' => 'bar'], 'en'))->toBe('foo::unknown');
    expect($t->get('foo::bar.unknown', ['foo' => 'bar'], 'en'))->toBe('foo::bar.unknown');
    expect($t->get('foo::unknown.bar'))->toBe('foo::unknown.bar');
});

test('trans method properly loads and retrieves item with htmlin the message', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'foo', '*')->andReturn(['bar' => 'breeze <p>test</p>']);
    expect($t->get('foo.bar', [], 'en'))->toBe('breeze <p>test</p>');
});

test('get method properly loads and retrieves item with capitalization', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods([])->setConstructorArgs([getLoader(), 'en'])->getMock();
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :0 :Foo :BAR']);
    expect($t->get('foo::bar.baz', ['john', 'foo' => 'bar', 'bar' => 'foo'], 'en'))->toBe('breeze john Bar FOO');
    expect($t->get('foo::bar.foo'))->toBe('foo');
});

test('get method properly loads and retrieves item with longest replacements first', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo :foobar']);
    expect($t->get('foo::bar.baz', ['foo' => 'bar', 'foobar' => 'taylor'], 'en'))->toBe('breeze bar taylor');
    expect($t->get('foo::bar.baz', ['foo' => 'foo bar baz', 'foobar' => 'taylor'], 'en'))->toBe('breeze foo bar baz taylor');
    expect($t->get('foo::bar.foo'))->toBe('foo');
});

test('get method properly loads and retrieves item for fallback', function () {
    $t = new Translator(getLoader(), 'en');
    $t->setFallback('lv');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'bar', 'foo')->andReturn([]);
    $t->getLoader()->expects('load')->with('lv', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo']);
    expect($t->get('foo::bar.baz', ['foo' => 'bar'], 'en'))->toBe('breeze bar');
    expect($t->get('foo::bar.foo'))->toBe('foo');
});

test('get does not call get line twice for missing key when locale matches fallback', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['getLine'])->setConstructorArgs([getLoader(), 'en'])->getMock();
    $t->setFallback('en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);

    $t->expects($this->once())->method('getLine')->with('*', 'messages', 'en', 'test', [])->willReturn(null);

    $t->get('messages.test', [], 'en');
});

test('get method properly loads and retrieves item for global namespace', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'foo', '*')->andReturn(['bar' => 'breeze :foo']);
    expect($t->get('foo.bar', ['foo' => 'bar']))->toBe('breeze bar');
});

test('choice method properly loads and retrieves item for an int', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'localeForChoice'])->setConstructorArgs([getLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with('foo', [], 'en')->willReturn('line');
    $t->expects($this->once())->method('localeForChoice')->with('foo', null)->willReturn('en');
    $selector = Mockery::mock(MessageSelector::class);
    $selector->expects('choose')->with('line', 10, 'en')->andReturn('choiced');
    $t->setSelector($selector);

    $t->choice('foo', 10, ['replace']);
});

test('choice method properly loads and retrieves item for afloat', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'localeForChoice'])->setConstructorArgs([getLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with('foo', [], 'en')->willReturn('line');
    $t->expects($this->once())->method('localeForChoice')->with('foo', null)->willReturn('en');
    $selector = Mockery::mock(MessageSelector::class);
    $selector->expects('choose')->with('line', 1.2, 'en')->andReturn('choiced');
    $t->setSelector($selector);

    $t->choice('foo', 1.2, ['replace']);
});

test('choice method properly counts collections and loads and retrieves item', function () {
    $selector = Mockery::mock(MessageSelector::class);
    $selector->expects('choose')->times(2)->with('line', 3, 'en')->andReturn('choiced');
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'localeForChoice'])->setConstructorArgs([getLoader(), 'en'])->getMock();
    $t->expects($this->exactly(2))->method('get')->with('foo', [], 'en')->willReturn('line');
    $t->expects($this->exactly(2))->method('localeForChoice')->with('foo', null)->willReturn('en');
    $t->setSelector($selector);

    $values = ['foo', 'bar', 'baz'];
    $t->choice('foo', $values, ['replace']);

    $values = new Collection(['foo', 'bar', 'baz']);
    $t->choice('foo', $values, ['replace']);
});

test('choice method properly selects locale for choose', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'hasForLocale'])->setConstructorArgs([getLoader(), 'cs'])->getMock();
    $t->setFallback('en');
    $t->expects($this->once())->method('get')->with('foo', [], 'en')->willReturn('line');
    $t->expects($this->once())->method('hasForLocale')->with('foo', 'cs')->willReturn(false);
    $selector = Mockery::mock(MessageSelector::class);
    $selector->expects('choose')->with('line', 10, 'en')->andReturn('choiced');
    $t->setSelector($selector);

    $t->choice('foo', 10, ['replace']);
});

test('choice method properly uses custom count replacement', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'localeForChoice'])->setConstructorArgs([getLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with(':count foos', [], 'en')->willReturn('{1} :count foos|[2,*] :count foos');
    $t->expects($this->once())->method('localeForChoice')->with(':count foos', null)->willReturn('en');
    $selector = Mockery::mock(MessageSelector::class);
    $selector->expects('choose')->with('{1} :count foos|[2,*] :count foos', 1234, 'en')->andReturn(':count foos');
    $t->setSelector($selector);

    expect($t->choice(':count foos', 1234, ['count' => '1,234']))->toBe('1,234 foos');
});

test('get json', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn(['foo' => 'one']);
    expect($t->get('foo'))->toBe('one');
});

test('get json replaces', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn(['foo :i:c :u' => 'bar :i:c :u']);
    expect($t->get('foo :i:c :u', ['i' => 'one', 'c' => 'two', 'u' => 'three']))->toBe('bar onetwo three');
});

test('get json has atomic replacements', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn(['Hello :foo!' => 'Hello :foo!']);
    expect($t->get('Hello :foo!', ['foo' => 'baz:bar', 'bar' => 'abcdef']))->toBe('Hello baz:bar!');
});

test('get json replaces for associative input', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn(['foo :i :c' => 'bar :i :c']);
    expect($t->get('foo :i :c', ['i' => 'eye', 'c' => 'see']))->toBe('bar eye see');
});

test('get json preserves order', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn(['to :name I give :greeting' => ':greeting :name']);
    expect($t->get('to :name I give :greeting', ['name' => 'David', 'greeting' => 'Greetings']))->toBe('Greetings David');
});

test('get json for non existing json key looks for regular keys', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'foo', '*')->andReturn(['bar' => 'one']);
    expect($t->get('foo.bar'))->toBe('one');
});

test('get json for non existing json key looks for regular keys and replace', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'foo', '*')->andReturn(['bar' => 'one :message']);
    expect($t->get('foo.bar', ['message' => 'two']))->toBe('one two');
});

test('get json for non existing returns same key', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'Foo that bar', '*')->andReturn([]);
    expect($t->get('Foo that bar'))->toBe('Foo that bar');
});

test('get json for non existing returns same key and replaces', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'foo :message', '*')->andReturn([]);
    expect($t->get('foo :message', ['message' => 'baz']))->toBe('foo baz');
});

test('empty fallbacks', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'foo :message', '*')->andReturn([]);
    expect($t->get('foo :message', ['message' => null]))->toBe('foo ');
});

test('get json replaces with stringable', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()
        ->expects('load')
        ->with('en', '*', '*')
        ->andReturn(['test' => 'the date is :date']);

    $date = Carbon::createFromTimestamp(0);

    expect($t->get('test', ['date' => $date]))->toBe('the date is 1970-01-01 00:00:00');

    $t->stringable(function (\Voyager\NutsAndBolts\DataObjects\Carbon $carbon) {
        return $carbon->format('jS M Y');
    });
    expect($t->get('test', ['date' => $date]))->toBe('the date is 1st Jan 1970');
});

test('get json replaces with enums', function () {
    $t = new Translator(getLoader(), 'en');
    $t->getLoader()
        ->expects('load')
        ->with('en', '*', '*')
        ->andReturn([
            'string_backed_enum' => 'Laravel 12 was released in :month 2025',
            'int_backed_enum' => 'Stay tuned for Laravel v:version',
            'unit_enum' => ':person gets excited about every new Laravel release',
        ]);

    expect($t->get('string_backed_enum', ['month' => Baz::February]))->toBe('Laravel 12 was released in February 2025');

    expect($t->get('int_backed_enum', ['version' => Bar::Thirteen]))->toBe('Stay tuned for Laravel v13');

    expect($t->get('unit_enum', ['person' => Foo::Hosni]))->toBe('Hosni gets excited about every new Laravel release');
});

test('tag replacements', function () {
    $t = new Translator(getLoader(), 'en');

    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'We have some nice <docs-link>documentation</docs-link>', '*')->andReturn([]);

    expect($t->get(
        'We have some nice <docs-link>documentation</docs-link>',
        [
            'docs-link' => fn ($children) => "<a href=\"https://laravel.com/docs\">$children</a>",
        ]
    ))->toBe('We have some nice <a href="https://laravel.com/docs">documentation</a>');
});

test('tag replacements handle multiple of same tag', function () {
    $t = new Translator(getLoader(), 'en');

    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', '<bold-this>bold</bold-this> something else <bold-this>also bold</bold-this>', '*')->andReturn([]);

    expect($t->get(
        '<bold-this>bold</bold-this> something else <bold-this>also bold</bold-this>',
        [
            'bold-this' => fn ($children) => "<b>$children</b>",
        ]
    ))->toBe('<b>bold</b> something else <b>also bold</b>');
});

test('determine locales using method', function () {
    $t = new Translator(getLoader(), 'en');
    $t->determineLocalesUsing(function ($locales) {
        expect($locales)->toBe(['en']);

        return ['en', 'lz'];
    });
    $t->getLoader()->expects('load')->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('en', 'foo', '*')->andReturn([]);
    $t->getLoader()->expects('load')->with('lz', 'foo', '*')->andReturn([]);
    expect($t->get('foo'))->toBe('foo');
});

function getLoader()
{
    return Mockery::mock(Loader::class);
}
