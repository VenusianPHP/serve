<?php

namespace Mariner\Session;

use Voyager\Contracts\Cache\Repository as CacheContract;
use RuntimeException;
use SessionHandlerInterface;

class CacheBasedSessionHandler implements SessionHandlerInterface, ProvidesAsyncHandler
{
    /**
     * The cache repository instance.
     *
     * @var \Voyager\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * The number of minutes to store the data in the cache.
     *
     * @var int
     */
    protected $minutes;

    /**
     * Create a new cache driven handler instance.
     *
     * @param  \Voyager\Contracts\Cache\Repository  $cache
     * @param  int  $minutes
     */
    public function __construct(CacheContract $cache, $minutes)
    {
        $this->cache = $cache;
        $this->minutes = $minutes;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function create_sid(): string
    {
        return session_create_id() ?: throw new RuntimeException('Unable to create a session ID.');
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function validateId($id): bool
    {
        return $this->cache->has($id);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function read($sessionId): string
    {
        return $this->cache->get($sessionId, '');
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function write($sessionId, $data): bool
    {
        return $this->cache->put($sessionId, $data, $this->minutes * 60);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function destroy($sessionId): bool
    {
        return $this->cache->forget($sessionId);
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function gc($lifetime): int
    {
        return 0;
    }

    /**
     * Get the underlying cache repository.
     *
     * @return \Voyager\Contracts\Cache\Repository
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * The async variant, over the cache store's async repository: a blocking call on the same
     * session key made afterwards waits for it. Cache stores expire sessions themselves, so
     * garbage collection removes nothing.
     */
    public function async(): AsyncSessionHandler
    {
        $cache = $this->cache->async();
        $loop = app('event-loop');

        return new AsyncSessionCalls(
            read: fn (string $id) => $cache->get($id, ''),
            write: fn (string $id, string $data) => $cache->put($id, $data, $this->minutes * 60),
            destroy: fn (string $id) => $cache->forget($id),
            gc: function (int $lifetime) use ($loop) {
                $promise = $loop->promise();
                $promise->resolve(0);

                return $promise;
            },
        );
    }

    public function asyncHandler(?string $pool = null): AsyncSessionHandler
    {
        return $this->async();
    }
}
