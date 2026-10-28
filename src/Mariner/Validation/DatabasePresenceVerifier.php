<?php

namespace Mariner\Validation;

use Closure;
use Voyager\Database\ConnectionResolverInterface;

class DatabasePresenceVerifier implements DatabasePresenceVerifierInterface
{
    /**
     * The database connection instance.
     *
     * @var \Voyager\Database\ConnectionResolverInterface
     */
    protected $db;

    /**
     * The database connection to use.
     *
     * @var string
     */
    protected $connection;

    /**
     * Whether counts run on a worker pool, and which one.
     */
    protected bool $offloaded = false;

    protected ?string $pool = null;

    /**
     * Create a new database presence verifier.
     *
     * @param  \Voyager\Database\ConnectionResolverInterface  $db
     */
    public function __construct(ConnectionResolverInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Count the number of objects in a collection having the given value.
     *
     * @param  string  $collection
     * @param  string  $column
     * @param  string  $value
     * @param  int|string|null  $excludeId
     * @param  string|null  $idColumn
     * @param  array  $extra
     * @return int
     */
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = [])
    {
        $query = $this->table($collection)->where($column, '=', $value);

        if (! is_null($excludeId) && $excludeId !== 'NULL') {
            $query->where($idColumn ?: 'id', '<>', $excludeId);
        }

        $query = $this->addConditions($query, $extra);

        return $this->offloaded ? $query->via($this->pool)->count()->wait() : $query->count();
    }

    /**
     * Count the number of objects in a collection with the given values.
     *
     * @param  string  $collection
     * @param  string  $column
     * @param  array  $values
     * @param  array  $extra
     * @return int
     */
    public function getMultiCount($collection, $column, array $values, array $extra = [])
    {
        $query = $this->table($collection)->whereIn($column, $values);

        $query = $this->addConditions($query, $extra)->distinct();

        return $this->offloaded ? $query->via($this->pool)->count($column)->wait() : $query->count($column);
    }

    /**
     * A copy whose counts run on a worker pool, waited on in place: inside a loop task, the wait leaves the loop free.
     *
     * @param  string|null  $pool  'thread' or 'process'; null takes the framework's choice
     * @return static
     */
    public function via(?string $pool = null)
    {
        $verifier = clone $this;
        $verifier->offloaded = true;
        $verifier->pool = $pool;

        return $verifier;
    }

    /**
     * Add the given conditions to the query.
     *
     * @param  \Voyager\Database\Query\Builder  $query
     * @param  array  $conditions
     * @return \Voyager\Database\Query\Builder
     */
    protected function addConditions($query, $conditions)
    {
        foreach ($conditions as $key => $value) {
            if ($value instanceof Closure) {
                $query->where(function ($query) use ($value) {
                    $value($query);
                });
            } else {
                $this->addWhere($query, $key, $value);
            }
        }

        return $query;
    }

    /**
     * Add a "where" clause to the given query.
     *
     * @param  \Voyager\Database\Query\Builder  $query
     * @param  string  $key
     * @param  string  $extraValue
     * @return void
     */
    protected function addWhere($query, $key, $extraValue)
    {
        if ($extraValue === 'NULL') {
            $query->whereNull($key);
        } elseif ($extraValue === 'NOT_NULL') {
            $query->whereNotNull($key);
        } elseif (str_starts_with($extraValue, '!')) {
            $query->where($key, '!=', mb_substr($extraValue, 1));
        } else {
            $query->where($key, $extraValue);
        }
    }

    /**
     * Get a query builder for the given table.
     *
     * @param  string  $table
     * @return \Voyager\Database\Query\Builder
     */
    protected function table($table)
    {
        return $this->db->connection($this->connection)->table($table)->useWritePdo();
    }

    /**
     * Set the connection to be used.
     *
     * @param  string  $connection
     * @return void
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }
}
