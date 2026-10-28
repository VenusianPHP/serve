<?php

namespace Mariner\Session;

use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use RuntimeException;
use SessionHandlerInterface;
use Symfony\Component\Finder\Finder;
use Mariner\NutsAndBolts\Concurrency\Offload;

class FileSessionHandler implements SessionHandlerInterface, ProvidesAsyncHandler
{
    /**
     * The filesystem instance.
     *
     * @var \Voyager\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The path where sessions should be stored.
     *
     * @var string
     */
    protected $path;

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected $minutes;

    /**
     * Create a new file driven handler instance.
     *
     * @param  \Voyager\Filesystem\Filesystem  $files
     * @param  string  $path
     * @param  int  $minutes
     */
    public function __construct(Filesystem $files, $path, $minutes)
    {
        $this->path = $path;
        $this->files = $files;
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
        return $this->files->isFile($this->path.'/'.$id);
    }

    /**
     * {@inheritdoc}
     *
     * @return string|false
     */
    public function read($sessionId): string|false
    {
        if ($this->files->isFile($path = $this->path.'/'.$sessionId) &&
            $this->files->lastModified($path) >= Carbon::now()->subMinutes($this->minutes)->getTimestamp()) {
            return $this->files->sharedGet($path);
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function write($sessionId, $data): bool
    {
        $this->files->put($this->path.'/'.$sessionId, $data, true);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function destroy($sessionId): bool
    {
        $this->files->delete($this->path.'/'.$sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function gc($lifetime): int
    {
        $files = Finder::create()
            ->in($this->path)
            ->files()
            ->ignoreDotFiles(true)
            ->date('<= now - '.$lifetime.' seconds');

        $deletedSessions = 0;

        foreach ($files as $file) {
            $this->files->delete($file->getRealPath());
            $deletedSessions++;
        }

        return $deletedSessions;
    }

    /**
     * The async variant: reads and writes as offloaded file calls on a worker pool, garbage
     * collection as one worker gig. 'thread', 'process' or null for the framework's pool rule.
     */
    public function via(?string $pool = null): AsyncSessionHandler
    {
        $files = $this->files->via($pool);
        $loop = app('event-loop');

        return new AsyncSessionCalls(
            read: fn (string $id) => $loop->async(function () use ($files, $id) {
                $path = $this->path.'/'.$id;

                if ($files->isFile($path)->wait() &&
                    $files->lastModified($path)->wait() >= Carbon::now()->subMinutes($this->minutes)->getTimestamp()) {
                    return $files->sharedGet($path)->wait();
                }

                return '';
            }),
            write: fn (string $id, string $data) => $files->put($this->path.'/'.$id, $data, true)->then(fn () => true),
            destroy: fn (string $id) => $files->delete($this->path.'/'.$id)->then(fn () => true),
            gc: fn (int $lifetime) => Offload::submit(app(), new FileSessionCollection($this->path, $lifetime), $pool),
        );
    }

    /**
     * Delete the session files older than the lifetime, in this process. Garbage collection's work,
     * also what FileSessionCollection runs inside a worker.
     */
    public function collect(int $lifetime): int
    {
        return $this->gc($lifetime);
    }

    public function asyncHandler(?string $pool = null): AsyncSessionHandler
    {
        return $this->via($pool);
    }
}
