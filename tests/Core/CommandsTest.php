<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\Console\Kernel;

/**
 * Every #[AsCommand] class under src/Mariner, found by reading the source, so a command added
 * later is checked without editing this test.
 *
 * @return array<string, array{string}>
 */
function marinerCommands(): array
{
    $root = dirname(__DIR__, 2).'/src/Mariner';
    $commands = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php' || ! str_contains($source = file_get_contents($file), '#[AsCommand(')) {
            continue;
        }

        preg_match('/^namespace\s+([^;]+);/m', $source, $namespace);
        $class = $namespace[1].'\\'.$file->getBasename('.php');
        $names = (new ReflectionClass($class))->getAttributes(AsCommand::class)[0]->newInstance()->name;

        // "make:session-table|session:table" declares a name and its aliases; each must work.
        // A leading "|" is Symfony's mark for a hidden command, not an empty alias.
        foreach (explode('|', ltrim($names, '|')) as $name) {
            $commands[$name] = [$name];
        }
    }

    ksort($commands);

    return $commands;
}

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

it('finds Mariner commands to check', function () {
    expect(array_keys(marinerCommands()))->toContain('route:cache', 'make:controller', 'make:session-table');
});

it('registers every Mariner command with the console kernel', function (string $name) {
    $output = new BufferedOutput;

    $code = $this->app->make(Kernel::class)->handle(new ArrayInput(['command' => 'help', 'command_name' => $name]), $output);

    expect($code)->toBe(0, "Computer can't run [{$name}]:\n".$output->fetch());
})->with(marinerCommands());

it('lists its commands, with the internal serve:worker hidden', function () {
    $output = new BufferedOutput;
    $code = $this->app->make(Kernel::class)->handle(new ArrayInput(['command' => 'list']), $output);
    $listed = $output->fetch();

    expect($code)->toBe(0, $listed)
        ->and($listed)->toMatch('/^\s+serve\s/m')
        ->and($listed)->not->toContain('serve:worker');
});
