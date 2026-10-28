<?php

namespace Venusian\Serve\Tests\Fixtures;

use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Voyager\Contracts\Debug\ExceptionHandler;

/**
 * An exception handler that records what is reported to it, for the assertion Laravel's ExceptionHandlerFake gave.
 */
class ExceptionRecorder implements ExceptionHandler
{
    /** @var list<Throwable> */
    private array $reported = [];

    public function report(Throwable $e): void
    {
        $this->reported[] = $e;
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function renderForConsole(OutputInterface $output, Throwable $e): void
    {
        //
    }

    public function assertReported(string $exception): void
    {
        expect(array_filter($this->reported, fn ($e) => $e instanceof $exception))->not->toBeEmpty();
    }
}
