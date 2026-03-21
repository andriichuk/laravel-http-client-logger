<?php

namespace Andriichuk\HttpClientLogger;

use Illuminate\Http\Client\PendingRequest;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HttpClientLoggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-http-client-logger')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(HttpClientLoggerCallbackRegistry::class, function () {
            return new HttpClientLoggerCallbackRegistry;
        });
    }

    public function bootingPackage(): void
    {
        PendingRequest::macro('name', function (string $name): PendingRequest {
            /** @var PendingRequest $this */
            return $this->withOptions(['laravel_http_client_logger_name' => $name]);
        });

        PendingRequest::macro('log', function (array $context = []): PendingRequest {
            /** @var PendingRequest $this */
            $name = $this->options['laravel_http_client_logger_name'] ?? null;

            if ($name !== null) {
                $context = array_merge(['name' => $name], $context);
            }

            return $this->withMiddleware(app(HttpClientLoggerMiddleware::class)->__invoke($context));
        });
    }
}
