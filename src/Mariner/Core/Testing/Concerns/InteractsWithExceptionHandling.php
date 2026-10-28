<?php

namespace Mariner\Core\Testing\Concerns;

use Closure;
use Mariner\Contracts\Http\ExceptionRenderer as ExceptionRendererContract;
use Mariner\Core\Http\ExceptionRenderer;
use Mariner\Http\Request;
use Mariner\Testing\Assert;
use Mariner\Testing\Concerns\WithoutExceptionHandlingHandler;
use Mariner\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Voyager\NutsAndBolts\Concerns\ReflectsClosures;

trait InteractsWithExceptionHandling
{
    use ReflectsClosures;

    /**
     * The renderer the kernel used before exception handling was turned off.
     *
     * @var \Mariner\Contracts\Http\ExceptionRenderer|null
     */
    protected $originalExceptionRenderer;

    /**
     * Restore exception handling.
     *
     * @return $this
     */
    protected function withExceptionHandling()
    {
        if ($this->originalExceptionRenderer) {
            $this->app->registerInstance(ExceptionRenderer::class, $this->originalExceptionRenderer);
        }

        return $this;
    }

    /**
     * Only handle the given exceptions via the exception renderer.
     *
     * @param  list<class-string<\Throwable>>  $exceptions
     * @return $this
     */
    protected function handleExceptions(array $exceptions)
    {
        return $this->withoutExceptionHandling($exceptions);
    }

    /**
     * Only handle validation exceptions via the exception renderer.
     *
     * @return $this
     */
    protected function handleValidationExceptions()
    {
        return $this->handleExceptions([ValidationException::class]);
    }

    /**
     * Disable exception handling for the test: the kernel's renderer rethrows, unreported,
     * every exception but those in $except, which the original renderer still handles.
     *
     * @param  list<class-string<\Throwable>>  $except
     * @return $this
     */
    protected function withoutExceptionHandling(array $except = [])
    {
        $this->originalExceptionRenderer ??= $this->app->make(ExceptionRendererContract::class);

        // The kernel resolves the renderer per request, through the contract's alias to this key.
        $this->app->registerInstance(ExceptionRenderer::class, new class($this->originalExceptionRenderer, $except) implements ExceptionRendererContract, WithoutExceptionHandlingHandler
        {
            /**
             * @param  list<class-string<\Throwable>>  $except
             */
            public function __construct(
                protected ExceptionRendererContract $original,
                protected array $except = [],
            ) {}

            public function handle(Request $request, Throwable $e): Response
            {
                foreach ($this->except as $class) {
                    if ($e instanceof $class) {
                        return $this->original->handle($request, $e);
                    }
                }

                if ($e instanceof NotFoundHttpException) {
                    throw new NotFoundHttpException(
                        "{$request->method()} {$request->url()}", $e, is_int($e->getCode()) ? $e->getCode() : 0
                    );
                }

                throw $e;
            }
        });

        return $this;
    }

    /**
     * Assert that the given callback throws an exception with the given message when invoked.
     *
     * @param  \Closure  $test
     * @param  (\Closure(\Throwable): bool)|class-string<\Throwable>  $expectedClass
     * @param  string|null  $expectedMessage
     * @return $this
     */
    protected function assertThrows(Closure $test, string|Closure $expectedClass = Throwable::class, ?string $expectedMessage = null)
    {
        [$expectedClass, $expectedClassCallback] = $expectedClass instanceof Closure
            ? [$this->firstClosureParameterType($expectedClass), $expectedClass]
            : [$expectedClass, null];

        try {
            $test();

            $thrown = false;
        } catch (Throwable $exception) {
            $thrown = $exception instanceof $expectedClass && ($expectedClassCallback === null || $expectedClassCallback($exception));

            $actualMessage = $exception->getMessage();
        }

        Assert::assertTrue(
            $thrown,
            sprintf('Failed asserting that exception of type "%s" was thrown.', $expectedClass)
        );

        if (isset($expectedMessage)) {
            Assert::assertStringContainsString($expectedMessage, $actualMessage);
        }

        return $this;
    }

    /**
     * Assert that the given callback does not throw an exception.
     *
     * @param  \Closure  $test
     * @return $this
     */
    protected function assertDoesntThrow(Closure $test)
    {
        try {
            $test();

            $thrown = false;
        } catch (Throwable $exception) {
            $thrown = true;

            $exceptionClass = get_class($exception);
            $exceptionMessage = $exception->getMessage();
        }

        Assert::assertFalse(
            $thrown,
            sprintf('Unexpected exception of type %s with message %s was thrown.', $exceptionClass ?? null, $exceptionMessage ?? null)
        );

        return $this;
    }
}
