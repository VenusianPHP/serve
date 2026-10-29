<?php

use Mariner\View\FiberOutputBuffers;
use Venusian\Serve\Tests\Fixtures\LoopPause;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->loop = $this->app['event-loop'];
});
afterEach(fn () => WebApp::tearDown($this));

/** A callback that hands every byte it gets to $sink, keeping nothing in the buffer. */
function sinkInto(array &$sink): Closure
{
    return function (string $bytes) use (&$sink) {
        if ($bytes !== '') {
            $sink[] = $bytes;
        }

        return '';
    };
}

it('keeps each fiber\'s callback buffer streaming across its suspends, in order', function () {
    $sinks = ['a' => [], 'b' => []];

    $tasks = array_map(function (string $name) use (&$sinks) {
        return $this->loop->async(function () use ($name, &$sinks) {
        FiberOutputBuffers::start(sinkInto($sinks[$name]), 1);

        foreach ([1, 2, 3] as $i) {
            echo "{$name}{$i}";
            LoopPause::yield();
        }

        FiberOutputBuffers::end();
        });
    }, ['a', 'b']);

    foreach ($tasks as $task) {
        $this->loop->await($task);
    }

    expect($sinks)->toBe(['a' => ['a1', 'a2', 'a3'], 'b' => ['b1', 'b2', 'b3']]);
});

it('flushes into the callback, not away, what was buffered when the fiber suspends', function () {
    $sink = [];

    $this->loop->await($this->loop->async(function () use (&$sink) {
        FiberOutputBuffers::start(sinkInto($sink));   // no chunk size: holds until flushed
        echo 'held ';
        LoopPause::yield();
        echo 'after';
        FiberOutputBuffers::end();
    }));

    expect(implode('', $sink))->toBe('held after');
});

it('brings a mixed stack back as it was: plain under a callback, a callback under plain', function () {
    $sink = [];

    [$outer, $inner] = $this->loop->await($this->loop->async(function () use (&$sink) {
        ob_start();
        echo 'p1 ';
        FiberOutputBuffers::start(sinkInto($sink), 1);
        echo 'h1 ';
        ob_start();
        echo 'q1 ';
        LoopPause::yield();
        echo 'q2';
        $inner = ob_get_clean();
        echo 'h2';
        FiberOutputBuffers::end();
        echo 'p2';

        return [ob_get_clean(), $inner];
    }));

    expect($outer)->toBe('p1 p2')
        ->and($inner)->toBe('q1 q2')
        ->and(implode('', $sink))->toBe('h1 h2');
});

it('forgets a callback buffer its owner ended before suspending', function () {
    $sink = [];

    [$plain] = $this->loop->await($this->loop->async(function () use (&$sink) {
        FiberOutputBuffers::start(sinkInto($sink), 1);
        echo 'streamed';
        ob_end_flush();          // ended without end(): the record goes stale
        ob_start();              // a plain buffer now at that level
        echo 'plain ';
        LoopPause::yield();
        echo 'kept';

        return [ob_get_clean()];
    }));

    expect($plain)->toBe('plain kept')
        ->and($sink)->toBe(['streamed']);
});

it('leaves the stack alone when a fiber suspended inside start() is torn down without resuming', function () {
    // PHP tears suspended fibers down at exit, after it has ended every output buffer: the fiber's
    // finally then runs with its buffer parked, and end() must not pop what isn't its own.
    $script = tempnam(sys_get_temp_dir(), 'fob').'.php';
    file_put_contents($script, '<?php
        require '.var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true).';
        use Mariner\View\FiberOutputBuffers;
        $registry = new Voyager\IOPools\ResourceRegistry();
        $loop = new Voyager\IOPools\EventLoop($registry, new Voyager\IOPools\LoopWaiter($registry, new Voyager\IOPools\Waiter\StreamSelectWaiterBackend(), 1_000_000), new Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine());
        FiberOutputBuffers::register($loop);
        $loop->async(function () use ($loop) {
            FiberOutputBuffers::start(fn (string $bytes) => "", 1);
            try { $loop->until(fn () => false); } finally { FiberOutputBuffers::end(); echo "unwound"; }
        });
    ');

    exec(escapeshellarg(PHP_BINARY).' -d display_errors=stderr '.escapeshellarg($script).' 2>&1', $output, $status);
    @unlink($script);

    expect($status)->toBe(0)
        ->and(implode("\n", $output))->toBe('unwound');
});

it('is a plain ob_start with a callback outside a loop fiber', function () {
    $sink = [];

    FiberOutputBuffers::start(sinkInto($sink), 1);
    echo 'main';
    FiberOutputBuffers::end();

    expect($sink)->toBe(['main']);
});
