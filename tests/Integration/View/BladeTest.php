<?php

namespace Venusian\Serve\Tests\Integration\View;

use Mariner\NutsAndBolts\Facades\Blade;
use Mariner\NutsAndBolts\Facades\View;
use Mariner\View\Component;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use function Voyager\Filesystem\join_paths;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('view.paths', [__DIR__.'/Fixtures/templates']);
});

test('rendering blade string', function () {
    expect(Blade::render('Hello {{ $name }}', ['name' => 'Taylor']))->toBe('Hello Taylor');
});

test('rendering blade long maxpathlen string', function () {
    $longString = str_repeat('a', PHP_MAXPATHLEN);

    $result = Blade::render($longString.'{{ $name }}', ['name' => 'a']);

    expect($result)->toBe($longString.'a');
});

test('rendering blade long maxpathlen string with exact length', function () {
    // The PHP_MAXPATHLEN restriction is only active, if
    // open_basedir is set and active. Otherwise, the check
    // for the PHP_MAXPATHLEN is not active.
    if (ini_get('open_basedir') === '' && version_compare(\PHPUnit\Runner\Version::id(), '12.1.0', '<')) {
        $openBaseDir = explode(DIRECTORY_SEPARATOR, __DIR__)[0].DIRECTORY_SEPARATOR.PATH_SEPARATOR.sys_get_temp_dir();
        $iniSet = ini_set(
            'open_basedir',
            $openBaseDir
        );

        expect($iniSet)->not->toBeFalse('Could not set config for open_basedir.');
    }

    for ($i = PHP_MAXPATHLEN - 200; $i <= PHP_MAXPATHLEN + 1; $i++) {
        $longString = str_repeat('x', $i);

        $result = Blade::render($longString);

        expect($result)->toBe($longString);
    }
});

test('rendering blade component instance', function () {
    $component = new HelloComponent('Taylor');

    expect(Blade::renderComponent($component))->toBe('Hello Taylor');
});

test('basic blade rendering', function () {
    $view = View::make('hello', ['name' => 'Taylor'])->render();

    expect(trim($view))->toBe('Hello Taylor');
});

test('rendering a component', function () {
    $view = View::make('uses-panel', ['name' => 'Taylor'])->render();

    expect(trim($view))->toBe('<div class="ml-2">
    Hello Taylor
</div>');
});

test('rendering a dynamic component', function () {
    $view = View::make('uses-panel-dynamically', ['name' => 'Taylor'])->render();

    expect(trim($view))->toBe('<div class="ml-2" wire:model="foo" wire:model.lazy="bar">
    Hello Taylor
</div>');
});

test('rendering the same dynamic component with different attributes', function () {
    $view = View::make('varied-dynamic-calls')->render();

    expect(trim($view))->toBe('<span class="text-medium">
    Hello Taylor
</span>
<span >
    Hello Samuel
</span>');
});

test('inline link type attributes dont add extra spacing at end', function () {
    $view = View::make('uses-link')->render();

    expect(trim($view))->toBe('This is a sentence with a <a href="https://laravel.com">link</a>.');
});

test('appendable attributes', function () {
    $view = View::make('uses-appendable-panel', ['name' => 'Taylor', 'withInjectedValue' => true])->render();

    expect(trim($view))->toBe('<div class="mt-4 bg-gray-100" data-controller="inside-controller outside-controller" foo="bar">
    Hello Taylor
</div>');

    $view = View::make('uses-appendable-panel', ['name' => 'Taylor', 'withInjectedValue' => false])->render();

    expect(trim($view))->toBe('<div class="mt-4 bg-gray-100" data-controller="inside-controller" foo="bar">
    Hello Taylor
</div>');
});

test('ed nested anonymous attribute proxying works correctly', function () {
    $view = View::make('uses-child-input')->render();

    expect(trim($view))->toBe('<input class="disabled-class" foo="bar" type="text" disabled />');
});

test('consume defaults', function () {
    $view = View::make('consume')->render();

    expect(trim($view))->toBe('<h1>Menu</h1>
<div>Slot: A, Color: orange, Default: foo</div>
<div>Slot: B, Color: red, Default: foo</div>
<div>Slot: C, Color: blue, Default: foo</div>
<div>Slot: D, Color: red, Default: foo</div>
<div>Slot: E, Color: red, Default: foo</div>
<div>Slot: F, Color: yellow, Default: foo</div>');
});

test('consume with props', function () {
    $view = View::make('consume', ['color' => 'rebeccapurple'])->render();

    expect(trim($view))->toBe('<h1>Menu</h1>
<div>Slot: A, Color: orange, Default: foo</div>
<div>Slot: B, Color: rebeccapurple, Default: foo</div>
<div>Slot: C, Color: blue, Default: foo</div>
<div>Slot: D, Color: rebeccapurple, Default: foo</div>
<div>Slot: E, Color: rebeccapurple, Default: foo</div>
<div>Slot: F, Color: yellow, Default: foo</div>');
});

test('name attribute can be used if using short slot names', function () {
    $content = Blade::render('<x-input-with-slot>
    <x-slot:input name="my_form_field" class="text-input-lg" data-test="data">Test</x-slot:input>
</x-input-with-slot>');

    expect(trim($content))->toBe('<div>
    <input type="text" class="input text-input-lg" data-test="data" name="my_form_field" />
</div>');
});

test('name attribute cant be used if not using short slot names', function () {
    $content = Blade::render('<x-input-with-slot>
    <x-slot name="input" class="text-input-lg" data-test="data">Test</x-slot>
</x-input-with-slot>');

    expect(trim($content))->toBe('<div>
    <input type="text" class="input text-input-lg" data-test="data" />
</div>');
});

test('bound name attribute can be used if using short slot names', function () {
    $content = Blade::render('<x-input-with-slot>
    <x-slot:input :name="\'my_form_field\'" class="text-input-lg" data-test="data">Test</x-slot:input>
</x-input-with-slot>');

    expect(trim($content))->toBe('<div>
    <input type="text" class="input text-input-lg" data-test="data" name="my_form_field" />
</div>');
});

test('bound name attribute can be used if using short slot names and not first attribute', function () {
    $content = Blade::render('<x-input-with-slot>
    <x-slot:input class="text-input-lg" :name="\'my_form_field\'" data-test="data">Test</x-slot:input>
</x-input-with-slot>');

    expect(trim($content))->toBe('<div>
    <input type="text" class="input text-input-lg" name="my_form_field" data-test="data" />
</div>');
});

test('dynamic component slot attributes are not compiled as blade', function () {
    $payload = '{{ 7191 * 2 }}';

    $static = Blade::render('<x-input-with-slot>
    <x-slot:input :data-x="$payload">Test</x-slot:input>
</x-input-with-slot>', ['payload' => $payload]);

    $dynamic = Blade::render('<x-dynamic-component component="input-with-slot">
    <x-slot:input :data-x="$payload">Test</x-slot:input>
</x-dynamic-component>', ['payload' => $payload]);

    expect($static)->toContain('data-x="{{ 7191 * 2 }}"');
    expect($dynamic)->not->toContain('14382');
    expect(trim($dynamic))->toBe(trim($static));
});

test('no name passed to slot uses default name', function () {
    $content = Blade::render('<x-link href="#"><x-slot>default slot</x-slot></x-link>');

    expect(trim($content))->toBe('<a href="#">default slot</a>');
});

test('include scoped does not inherit parent scope', function () {
    // Regular @include passes parent scope variables
    $regularInclude = View::make('uses-include-regular', [
        'parentVar' => 'parent-value',
        'explicitVar' => 'explicit-value',
    ])->render();

    expect(trim($regularInclude))->toBe('Parent: parent-value, Explicit: explicit-value');

    // @includeIsolated does NOT pass parent scope variables
    $scopedInclude = View::make('uses-include-scoped', [
        'parentVar' => 'parent-value',
        'explicitVar' => 'explicit-value',
    ])->render();

    expect(trim($scopedInclude))->toBe('Parent: undefined, Explicit: explicit-value');
});

test('view cache command handles configured blade extensions', function () {
    View::addExtension('sh', 'blade');
    $this->artisan('view:cache');

    $compiledFiles = Finder::create()->in(app('config')->get('view.compiled'))->files();
    $found = collect($compiledFiles)
        ->contains(fn (SplFileInfo $file) => str_contains($file->getContents(), 'echo "<?php echo e($scriptMessage); ?>" > output.log'));
    expect($found)->toBeTrue();
});

test('view cache command deduplicates paths before compiling', function () {
    View::addNamespace('templates', join_paths(__DIR__, 'Fixtures', 'templates'));
    View::addNamespace('components', join_paths(__DIR__, 'Fixtures', 'templates', 'components'));

    Blade::partialMock()->expects('compile')->with(realpath(__DIR__.'/Fixtures/templates/components/panel.blade.php'));

    $this->artisan('view:cache');
});

class HelloComponent extends Component
{
    public $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function render()
    {
        return 'Hello {{ $name }}';
    }
}
