<?php

declare(strict_types=1);

namespace Andriichuk\HttpClientLogger;

use Illuminate\Support\Facades\Facade;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @method static void addCallback(callable(RequestInterface, ResponseInterface|null, float): void $callback)
 *
 * @see HttpClientLoggerCallbackRegistry
 */
final class HttpClientLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HttpClientLoggerCallbackRegistry::class;
    }
}
