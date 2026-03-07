<?php

namespace Andriichuk\HttpClientLogger\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Andriichuk\HttpClientLogger\HttpClientLoggerServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Andriichuk\\HttpClientLogger\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            HttpClientLoggerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');

        $config = require __DIR__.'/../config/http-client-logger.php';
        $app['config']->set('http-client-logger', $config);

        $app['config']->set('logging.channels.http_client', [
            'driver' => 'single',
            'path' => storage_path('logs/http_client_test.log'),
            'level' => 'debug',
        ]);
    }

    protected function getLogContent(): string
    {
        $path = storage_path('logs/http_client_test.log');

        return file_exists($path) ? file_get_contents($path) : '';
    }

    protected function clearLog(): void
    {
        $path = storage_path('logs/http_client_test.log');
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
