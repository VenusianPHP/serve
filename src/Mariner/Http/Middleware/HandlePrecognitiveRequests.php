<?php

namespace Mariner\Http\Middleware;

use Closure;
use LogicException;
use Mariner\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Mariner\Routing\Contracts\ControllerDispatcher as ControllerDispatcherContract;
use Voyager\Contracts\Vessel\TheServiceContainer;

/**
 * Marks a request that asks for Precognition, so its route resolves (and validates) its parameters
 * and answers 204 without running the action.
 *
 * Laravel swaps the dispatcher bindings on the container for the request and puts them back after;
 * under a server running several requests at once, that swap would reach the others. Here everything
 * about a precognitive request lives on the request: the mark, and any dispatchers an app chooses for
 * it through dispatchUsing() from prepareForPrecognition(). Binding the dispatcher contracts on the
 * container from that hook, as Laravel apps do, is refused with a LogicException.
 */
class HandlePrecognitiveRequests
{
    public function __construct(protected TheServiceContainer $container) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, $next)
    {
        if (! $request->isAttemptingPrecognition()) {
            return $this->appendVaryHeader($request, $next($request));
        }

        $this->preparing($request);

        return tap($next($request), function ($response) use ($request) {
            $response->headers->set('Precognition', 'true');

            $this->appendVaryHeader($request, $response);
        });
    }

    /**
     * Prepare the request for Precognition. An app that wants the real action to run on precognitive
     * requests overrides this and calls dispatchUsing().
     *
     * @param  \Mariner\Http\Request  $request
     * @return void
     */
    protected function prepareForPrecognition($request)
    {
        $request->attributes->set('precognitive', true);
    }

    /**
     * Choose, for this request only, the dispatchers its route runs with instead of the Precognition ones.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  (\Closure(\Voyager\Contracts\Vessel\TheServiceContainer): \Mariner\Routing\Contracts\CallableDispatcher)|null  $callable
     * @param  (\Closure(\Voyager\Contracts\Vessel\TheServiceContainer): \Mariner\Routing\Contracts\ControllerDispatcher)|null  $controller
     * @return void
     */
    protected function dispatchUsing($request, ?Closure $callable = null, ?Closure $controller = null)
    {
        if ($callable) {
            $request->attributes->set('precognition.callable', $callable);
        }

        if ($controller) {
            $request->attributes->set('precognition.controller', $controller);
        }
    }

    /**
     * Run prepareForPrecognition(), refusing a hook that rebinds the dispatcher contracts on the
     * container: that binding is global, and would reach every other request the server is running.
     *
     * @param  \Mariner\Http\Request  $request
     *
     * @throws \LogicException
     */
    protected function preparing($request): void
    {
        $before = array_map(fn ($contract) => $this->resolution($contract), [CallableDispatcherContract::class, ControllerDispatcherContract::class]);

        $this->prepareForPrecognition($request);

        foreach ([CallableDispatcherContract::class, ControllerDispatcherContract::class] as $i => $contract) {
            if ($this->resolution($contract) !== $before[$i]) {
                // Put back what the contract resolved to, so the refused binding never serves another request.
                is_object($before[$i])
                    ? $this->container->registerInstance($contract, $before[$i])
                    : $this->container->bind($contract, $before[$i]);

                throw new LogicException(
                    static::class."::prepareForPrecognition() rebound [{$contract}] on the container. That binding is global and would reach "
                    .'every request the server is running; call $this->dispatchUsing($request, ...) instead, which applies to this request only. '
                    .'See the Precognition note in the serve OKF (Http).'
                );
            }
        }
    }

    /**
     * What a dispatcher contract resolves to: the instance when the binding is shared, else its class.
     */
    private function resolution(string $contract): object|string|null
    {
        if (! $this->container->isBound($contract)) {
            return null;
        }

        $first = $this->container->make($contract);

        return $first === $this->container->make($contract) ? $first : $first::class;
    }

    /**
     * Append the appropriate "Vary" header to the given response.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function appendVaryHeader($request, $response)
    {
        return tap($response, fn () => $response->headers->set('Vary', implode(', ', array_filter([
            $response->headers->get('Vary'),
            'Precognition',
        ]))));
    }
}
