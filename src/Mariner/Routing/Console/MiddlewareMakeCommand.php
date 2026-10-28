<?php

namespace Mariner\Routing\Console;

use Voyager\Console\Concerns\CreatesMatchingTest;
use Voyager\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:middleware')]
class MiddlewareMakeCommand extends GeneratorCommand
{
    use CreatesMatchingTest;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected ?string $signature = 'make:middleware {name : The name of the middleware}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new HTTP middleware class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected ?string $type = 'Middleware';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/middleware.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->venusian->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace.'\Http\Middleware';
    }
}
