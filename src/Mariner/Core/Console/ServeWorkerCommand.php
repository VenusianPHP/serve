<?php

namespace Mariner\Core\Console;

use Mariner\Core\Server\ServedRequest;
use Mariner\Core\Server\ServerOptions;
use Symfony\Component\Console\Attribute\AsCommand;

/** One of the workers `serve --workers` runs: a serve sharing its port with the others. */
#[AsCommand(name: 'serve:worker')]
class ServeWorkerCommand extends ServeCommand
{
    protected ?string $signature = 'serve:worker
                    {--index=0 : This worker\'s number, prefixed to its lines}
                    {--host= : The address to listen on}
                    {--port= : The port to listen on}
                    {--max-in-flight= : Requests handled at once}
                    {--https : Serve as behind a proxy that terminates TLS}';

    protected string $description = 'Serve as one of several workers sharing a port';

    // Hidden through the command, not #[AsCommand(hidden: true)]: that names it "|serve:worker", and
    // the console splits names on "|", registering one with no name that breaks `computer list`.
    protected bool $hidden = true;

    public function handle(): int
    {
        return parent::handle();
    }

    /** A worker is one process: never a supervisor of its own. */
    public function option($key = null)
    {
        return match ($key) {
            'workers' => 1,
            'driver' => 'process',
            default => parent::option($key),
        };
    }

    protected function serverOptions(): ServerOptions
    {
        $options = parent::serverOptions();

        // Every option as given, by name, with the port shared between workers.
        return new ServerOptions(...[...get_object_vars($options), 'reuse_port' => true]);
    }

    protected function requestLine(ServedRequest $served): void
    {
        $this->output->write(sprintf('<fg=gray>[%d]</>', (int) parent::option('index')));
        parent::requestLine($served);
    }
}
