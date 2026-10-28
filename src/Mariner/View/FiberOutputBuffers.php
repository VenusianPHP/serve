<?php

namespace Mariner\View;

use Fiber;
use Voyager\IOPools\EventLoop;
use WeakMap;

/**
 * Gives every fiber the event loop runs its own output buffers.
 *
 * PHP keeps one output-buffer stack per process, and views render by buffering, so a request that
 * suspends mid-render would otherwise write into whichever request's buffer is open. When a loop
 * fiber suspends, the buffers it opened are taken off the stack and kept with it; when it resumes,
 * they are opened again, in the same order, with what they held.
 *
 * PHP does not expose a buffer's output callback, so a buffer opened with plain ob_start($callback)
 * comes back without it. A buffer opened through start() keeps its callback: it is flushed into the
 * callback on the way off the stack, and reopened with it.
 */
final class FiberOutputBuffers
{
    /**
     * The buffers each fiber opened through start(), by level.
     *
     * @var WeakMap<Fiber, array<int, array{0: callable, 1: int}>>|null
     */
    private static ?WeakMap $handlers = null;

    public static function register(EventLoop $loop): void
    {
        $base = new WeakMap;
        $parked = new WeakMap;

        $loop->onFiberStart(function (Fiber $fiber) use ($base) {
            $base[$fiber] = ob_get_level();
        });

        $loop->onFiberSuspend(
            function (Fiber $fiber) use ($base, $parked) {
                $handlers = self::live($fiber);
                $held = [];

                while (($level = ob_get_level()) > ($base[$fiber] ?? $level)) {
                    if (isset($handlers[$level])) {
                        // Into the callback, not dropped: it sees PHP_OUTPUT_HANDLER_FINAL here.
                        ob_end_flush();
                        array_unshift($held, ['handler' => $handlers[$level]]);
                    } else {
                        array_unshift($held, ['contents' => ob_get_clean()]);
                    }
                }

                $parked[$fiber] = $held;
            },
            function (Fiber $fiber) use ($parked) {
                // Bottom-up, each level as it was: its own callback, or plain with what it held.
                foreach ($parked[$fiber] ?? [] as $buffer) {
                    if (isset($buffer['handler'])) {
                        ob_start($buffer['handler'][0], $buffer['handler'][1]);
                    } else {
                        ob_start();
                        echo $buffer['contents'];
                    }
                }

                unset($parked[$fiber]);
            },
        );
    }

    /**
     * Open an output buffer with $handler that stays with the loop fiber opening it, callback and
     * all, across its suspends. Outside a loop fiber it is a plain ob_start($handler, $chunk_size).
     *
     * The callback sees PHP_OUTPUT_HANDLER_FINAL each time the fiber suspends, as the buffer leaves
     * the stack, and again when the owner ends it: FINAL there means "this buffer is off the stack
     * for now", never "the output has ended". The owner decides when its output ends, and calls
     * end() when it closes the buffer.
     *
     * @param  callable(string, int): (string|false)  $handler
     */
    public static function start(callable $handler, int $chunk_size = 0): void
    {
        ob_start($handler, $chunk_size);

        if (! is_null($fiber = Fiber::getCurrent())) {
            self::$handlers ??= new WeakMap;
            $handlers = self::$handlers[$fiber] ?? [];
            $handlers[ob_get_level()] = [$handler, $chunk_size];
            self::$handlers[$fiber] = $handlers;
        }
    }

    /** End the buffer start() opened last, flushing it into its callback. */
    public static function end(): void
    {
        $level = ob_get_level();
        ob_end_flush();

        if (! is_null($fiber = Fiber::getCurrent()) && isset(self::$handlers[$fiber])) {
            $handlers = self::$handlers[$fiber];
            unset($handlers[$level]);
            self::$handlers[$fiber] = $handlers;
        }
    }

    /**
     * The fiber's start() buffers still open: a level the owner ended (or that is now some other,
     * plain buffer) is forgotten before anything is flushed into the wrong callback.
     *
     * @return array<int, array{0: callable, 1: int}>
     */
    private static function live(Fiber $fiber): array
    {
        if (is_null(self::$handlers) || ! isset(self::$handlers[$fiber])) {
            return [];
        }

        $status = ob_get_status(true);
        $handlers = array_filter(
            self::$handlers[$fiber],
            fn (int $level) => $level <= ob_get_level()
                && ($status[$level - 1]['name'] ?? 'default output handler') !== 'default output handler',
            ARRAY_FILTER_USE_KEY,
        );

        self::$handlers[$fiber] = $handlers;

        return $handlers;
    }
}
