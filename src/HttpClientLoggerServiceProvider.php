<?php

namespace Andriichuk\HttpClientLogger;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Andriichuk\HttpClientLogger\Commands\HttpClientLoggerCommand;
use Illuminate\Http\Client\PendingRequest;

class HttpClientLoggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-http-client-logger')
            ->hasConfigFile();
    }

    public function boot(): void
    {
        PendingRequest::macro('log', function (array $context = []): PendingRequest {
            /** @var PendingRequest $this */
            return $this->withMiddleware(app(HttpClientLoggerMiddleware::class)->__invoke($context));
        });
    }
}
