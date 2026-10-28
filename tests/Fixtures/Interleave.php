<?php

namespace Venusian\Serve\Tests\Fixtures;

use Closure;
use Fiber;

/**
 * Runs request bodies as fibers, interleaved at every Fiber::suspend(): A runs to its first
 * suspend, then B, then A resumes, and so on, as concurrent requests share one loop when served.
 */
final class Interleave
{
    /**
     * @param array<string, Closure(): mixed> $bodies
     * @return array<string, mixed> each body's return value, by key
     */
    public static function run(array $bodies): array
    {
        $fibers = array_map(fn (Closure $body) => new Fiber($body), $bodies);
        $results = [];

        foreach ($fibers as $fiber) {
            $fiber->start();
        }

        while ($fibers) {
            foreach ($fibers as $key => $fiber) {
                if ($fiber->isTerminated()) {
                    $results[$key] = $fiber->getReturn();
                    unset($fibers[$key]);
                    continue;
                }

                $fiber->resume();
            }
        }

        return $results;
    }
}
