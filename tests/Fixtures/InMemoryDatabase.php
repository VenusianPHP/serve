<?php

namespace Venusian\Serve\Tests\Fixtures;

use PDO;
use Voyager\Database\ConnectionResolver;
use Voyager\Database\ConnectionResolverInterface;
use Voyager\Database\Instrument\Model;
use Voyager\Database\SQLiteConnection;

/**
 * Points Instrument models at a fresh in-memory SQLite connection, and puts the previous resolver back.
 */
final class InMemoryDatabase
{
    private static ?ConnectionResolverInterface $original = null;

    public static function use(): SQLiteConnection
    {
        self::$original = Model::getConnectionResolver();

        $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
        $resolver = new ConnectionResolver(['default' => $connection]);
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);

        return $connection;
    }

    public static function restore(): void
    {
        self::$original ? Model::setConnectionResolver(self::$original) : Model::unsetConnectionResolver();
        self::$original = null;
    }
}
