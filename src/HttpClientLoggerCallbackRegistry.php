<?php

declare(strict_types=1);

namespace Andriichuk\HttpClientLogger;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Registry of callbacks invoked after a request is logged (success or exception).
 * Callbacks receive the request, response (null on exception), and execution time in ms.
 *
 * @phpstan-type LogCallback callable(RequestInterface, ResponseInterface|null, float): void
 */
final class HttpClientLoggerCallbackRegistry
{
    /**
     * @var list<LogCallback>
     */
    private array $callbacks = [];

    /**
     * Register a callback to run when a request is logged (after enabled/status checks).
     *
     * @param  LogCallback  $callback  (RequestInterface $request, ?ResponseInterface $response, float $executionTimeMs): void
     */
    public function addCallback(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    /**
     * Remove all registered callbacks. Useful in tests.
     */
    public function clearCallbacks(): void
    {
        $this->callbacks = [];
    }

    /**
     * Invoke all registered callbacks with the given request, response (null on exception), and timing.
     */
    public function invoke(RequestInterface $request, ?ResponseInterface $response, float $timeMs): void
    {
        foreach ($this->callbacks as $callback) {
            $callback($request, $response, $timeMs);
        }
    }
}
