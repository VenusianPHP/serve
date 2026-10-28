<?php

namespace Mariner\Session\Console;

use Voyager\Console\MigrationGeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

use function Voyager\Filesystem\join_paths;

#[AsCommand(name: 'make:session-table', aliases: ['session:table'])]
class SessionTableCommand extends MigrationGeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected ?string $signature = 'make:session-table';

    /**
     * The console command name aliases.
     *
     * @var string[]
     */
    protected ?array $aliases = ['session:table'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a migration for the session database table';

    /**
     * Get the migration table name.
     *
     * @return string
     */
    protected function migrationTableName(): string
    {
        return 'sessions';
    }

    /**
     * Get the path to the migration stub file.
     *
     * @return string
     */
    protected function migrationStubFile(): string
    {
        return __DIR__.'/stubs/database.stub';
    }

    /**
     * Determine whether a migration for the table already exists.
     *
     * @param  string  $table
     * @return bool
     */
    protected function migrationExists(string $table): bool
    {
        return array_any([
            join_paths($this->venusian->databasePath('migrations'), '*_*_*_*_create_'.$table.'_table.php'),
            join_paths($this->venusian->databasePath('migrations'), '0001_01_01_000000_create_users_table.php'),
        ], fn ($path) => count($this->files->glob($path)) !== 0);
    }
}
