<?php

namespace Mariner\Core\Http;

use Closure;
use Mariner\Auth\Access\AuthorizationException;
use Mariner\Auth\AuthenticationException;
use Mariner\Contracts\Http\ExceptionRenderer as ExceptionRendererContract;
use Mariner\Contracts\NutsAndBolts\Responsable;
use Mariner\Http\Exceptions\HttpResponseException;
use Mariner\Http\Exceptions\OriginMismatchException;
use Mariner\Http\RedirectResponse;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Mariner\NutsAndBolts\ViewErrorBag;
use Mariner\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Mariner\Routing\Router;
use Mariner\Session\TokenMismatchException;
use Mariner\Validation\ValidationException;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Voyager\Contracts\IOPools\CancelledException;
use Voyager\NutsAndBolts\Collection;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Contracts\Vessel\TheServiceContainer;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\Database\RecordNotFoundException;
use Voyager\Database\RecordsNotFoundException;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\Concerns\ReflectsClosures;
use WeakMap;

/**
 * Turns an exception raised while handling a web request into its response, the way Laravel
 * v13.37.0's Foundation exception handler renders. Reporting stays with Voyager's handler.
 */
class ExceptionRenderer implements ExceptionRendererContract
{
    use ReflectsClosures;

    /**
     * Callbacks that may render an exception, first non-null response wins.
     *
     * @var list<\Closure>
     */
    protected array $renderCallbacks = [];

    /**
     * Decides whether an exception renders as JSON, instead of $request->expectsJson().
     */
    protected ?Closure $shouldRenderJsonWhenCallback = null;

    /**
     * Inputs never flashed back to the session after a validation failure.
     *
     * @var list<string>
     */
    protected array $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Exceptions this renderer has already reported.
     *
     * @var \WeakMap<\Throwable, true>
     */
    protected WeakMap $reported;

    public function __construct(
        protected readonly TheServiceContainer $container,
    ) {
        $this->reported = new WeakMap;
    }

    /**
     * Register a callback that may render exceptions of its first parameter's type.
     *
     * @param  \Closure(\Throwable, \Mariner\Http\Request): mixed  $callback
     * @return $this
     */
    public function renderable(Closure $callback): static
    {
        $this->renderCallbacks[] = $callback;

        return $this;
    }

    /**
     * Decide with $callback whether an exception renders as JSON.
     *
     * @param  \Closure(\Mariner\Http\Request, \Throwable): bool  $callback
     * @return $this
     */
    public function shouldRenderJsonWhen(Closure $callback): static
    {
        $this->shouldRenderJsonWhenCallback = $callback;

        return $this;
    }

    /**
     * Never flash these inputs back after a validation failure.
     *
     * @param  list<string>  $inputs
     * @return $this
     */
    public function dontFlash(array $inputs): static
    {
        $this->dontFlash = array_values(array_unique(array_merge($this->dontFlash, $inputs)));

        return $this;
    }

    public function handle(Request $request, Throwable $e): SymfonyResponse
    {
        // A cancelled request isn't a failure to render: its client is gone, so it unwinds to the server.
        if ($e instanceof CancelledException) {
            throw $e;
        }

        $this->report($e);

        try {
            return $this->render($request, $e);
        } catch (Throwable $failure) {
            $this->report($failure);

            return new Response('Server Error', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
    }

    /**
     * Render the exception into a response.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render(Request $request, Throwable $e): SymfonyResponse
    {
        if (method_exists($e, 'render') && $response = $e->render($request)) {
            return Router::toResponse($request, $response);
        }

        if ($e instanceof Responsable) {
            return $e->toResponse($request);
        }

        $e = $this->prepareException($e);

        if ($response = $this->renderViaCallbacks($request, $e)) {
            return Router::toResponse($request, $response);
        }

        return match (true) {
            $e instanceof HttpResponseException => $e->getResponse(),
            $e instanceof AuthenticationException => $this->unauthenticated($request, $e),
            $e instanceof ValidationException => $this->convertValidationExceptionToResponse($e, $request),
            default => $this->renderExceptionResponse($request, $e),
        };
    }

    /**
     * Report the exception to Voyager's handler unless this renderer already has.
     *
     * A reporter that throws is recorded with error_log(), so the request still gets its response.
     *
     * @param  \Throwable  $e
     * @return void
     */
    protected function report(Throwable $e): void
    {
        if (isset($this->reported[$e])) {
            return;
        }

        $this->reported[$e] = true;

        try {
            $this->container->make(ExceptionHandler::class)->report($e);
        } catch (Throwable $failure) {
            error_log(sprintf(
                'Mariner could not report %s (%s): %s: %s',
                $e::class, $e->getMessage(), $failure::class, $failure->getMessage()
            ));
        }
    }

    /**
     * Map the exception to the HTTP exception it renders as.
     *
     * @param  \Throwable  $e
     * @return \Throwable
     */
    protected function prepareException(Throwable $e): Throwable
    {
        return match (true) {
            $e instanceof BackedEnumCaseNotFoundException => new NotFoundHttpException($e->getMessage(), $e),
            $e instanceof ModelNotFoundException => new NotFoundHttpException($e->getMessage(), $e),
            $e instanceof AuthorizationException && $e->hasStatus() => new HttpException(
                $e->status(), $e->response()?->message() ?: (SymfonyResponse::$statusTexts[$e->status()] ?? 'Whoops, looks like something went wrong.'), $e
            ),
            $e instanceof AuthorizationException && ! $e->hasStatus() => new AccessDeniedHttpException($e->getMessage(), $e),
            $e instanceof OriginMismatchException => new HttpException(403, $e->getMessage(), $e),
            $e instanceof TokenMismatchException => new HttpException(419, $e->getMessage(), $e),
            $e instanceof RequestExceptionInterface => new BadRequestHttpException('Bad request.', $e),
            $e instanceof RecordNotFoundException => new NotFoundHttpException('Not found.', $e),
            $e instanceof RecordsNotFoundException => new NotFoundHttpException('Not found.', $e),
            default => $e,
        };
    }

    /**
     * The first non-null response from a callback registered for the exception's type.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Throwable  $e
     * @return mixed
     */
    protected function renderViaCallbacks(Request $request, Throwable $e): mixed
    {
        foreach ($this->renderCallbacks as $callback) {
            foreach ($this->firstClosureParameterTypes($callback) as $type) {
                if (is_a($e, $type) && ! is_null($response = $callback($e, $request))) {
                    return $response;
                }
            }
        }

        return null;
    }

    /**
     * 401 for an unauthenticated request: JSON, a redirect to where it may log in, or no content.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Mariner\Auth\AuthenticationException  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated(Request $request, AuthenticationException $e): SymfonyResponse
    {
        if ($this->shouldReturnJson($request, $e)) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $redirect_to = $e->redirectTo($request);

        if (! $redirect_to) {
            return response()->noContent(401);
        }

        return redirect()->guest($redirect_to);
    }

    /**
     * The validation exception's own response, else 422 JSON or a redirect back with the errors.
     *
     * @param  \Mariner\Validation\ValidationException  $e
     * @param  \Mariner\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertValidationExceptionToResponse(ValidationException $e, Request $request): SymfonyResponse
    {
        if ($e->response) {
            return $e->response;
        }

        if ($this->shouldReturnJson($request, $e)) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        }

        return redirect($e->redirectTo ?? url()->previous())
            ->withInput(Arr::except($request->input(), $this->dontFlash))
            ->withErrors($e->errors(), $request->input('_error_bag', $e->errorBag));
    }

    /**
     * Render as JSON or HTML.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderExceptionResponse(Request $request, Throwable $e): SymfonyResponse
    {
        return $this->shouldReturnJson($request, $e)
            ? $this->prepareJsonResponse($e)
            : $this->prepareResponse($request, $e);
    }

    /**
     * Determine if the exception renders as JSON.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Throwable  $e
     * @return bool
     */
    protected function shouldReturnJson(Request $request, Throwable $e): bool
    {
        return $this->shouldRenderJsonWhenCallback
            ? (bool) call_user_func($this->shouldRenderJsonWhenCallback, $request, $e)
            : $request->expectsJson();
    }

    /**
     * The HTML response: the debug page for a non-HTTP exception in debug mode, else the status page.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function prepareResponse(Request $request, Throwable $e): SymfonyResponse
    {
        if (! $e instanceof HttpExceptionInterface && $this->debug()) {
            return $this->toMarinerResponse($this->convertExceptionToResponse($e), $e)->prepare($request);
        }

        if (! $e instanceof HttpExceptionInterface) {
            $e = new HttpException(500, $e->getMessage(), $e);
        }

        return $this->toMarinerResponse($this->renderHttpException($e), $e)->prepare($request);
    }

    /**
     * The app's errors/{status} (or errors/{n}xx) view if it has one, else Symfony's error page.
     *
     * A view that fails to render is thrown in debug mode and reported otherwise.
     *
     * @param  \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderHttpException(HttpExceptionInterface $e): SymfonyResponse
    {
        if ($view = $this->httpExceptionView($e)) {
            try {
                return response()->view($view, [
                    'errors' => new ViewErrorBag,
                    'exception' => $e,
                ], $e->getStatusCode(), $e->getHeaders());
            } catch (Throwable $t) {
                $this->debug() && throw $t;

                $this->report($t);
            }
        }

        return $this->convertExceptionToResponse($e);
    }

    /**
     * The view for the exception's status, if the app defines one.
     *
     * @param  \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface  $e
     * @return string|null
     */
    protected function httpExceptionView(HttpExceptionInterface $e): ?string
    {
        if (! $this->container->isBound('view')) {
            return null;
        }

        $views = $this->container->make('view');

        foreach (['errors.'.$e->getStatusCode(), 'errors.'.intdiv($e->getStatusCode(), 100).'xx'] as $view) {
            if ($views->exists($view)) {
                return $view;
            }
        }

        return null;
    }

    /**
     * Symfony's HTML error page for the exception, with the trace in debug mode.
     *
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertExceptionToResponse(Throwable $e): SymfonyResponse
    {
        $http = $e instanceof HttpExceptionInterface;

        return new SymfonyResponse(
            (new HtmlErrorRenderer($this->debug()))->render($e)->getAsString(),
            $http ? $e->getStatusCode() : 500,
            $http ? $e->getHeaders() : []
        );
    }

    /**
     * JSON for the exception: its details in debug mode, else only a safe message.
     *
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function prepareJsonResponse(Throwable $e): SymfonyResponse
    {
        $http = $e instanceof HttpExceptionInterface;

        return response()->json(
            $this->debug() ? [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => (new Collection($e->getTrace()))->map(fn ($trace) => Arr::except($trace, ['args']))->all(),
            ] : [
                'message' => $http ? $e->getMessage() : 'Server Error',
            ],
            $http ? $e->getStatusCode() : 500,
            $http ? $e->getHeaders() : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * The Symfony response as a Mariner response carrying the exception.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Throwable  $e
     * @return \Mariner\Http\Response|\Mariner\Http\RedirectResponse
     */
    protected function toMarinerResponse(SymfonyResponse $response, Throwable $e): Response|RedirectResponse
    {
        $response = $response instanceof SymfonyRedirectResponse
            ? new RedirectResponse($response->getTargetUrl(), $response->getStatusCode(), $response->headers->all())
            : new Response($response->getContent(), $response->getStatusCode(), $response->headers->all());

        return $response->withException($e);
    }

    protected function debug(): bool
    {
        return (bool) $this->container->make('config')->get('app.debug');
    }
}
