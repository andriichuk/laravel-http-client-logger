<?php

namespace Andriichuk\HttpClientLogger\Tests;

use Andriichuk\HttpClientLogger\HttpClientLoggerServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Monolog\Formatter\LineFormatter;
use Orchestra\Testbench\TestCase as Orchestra;

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
        $config['enabled'] = true;
        // In tests, log 2xx by default so tests that expect success logs work
        $config['report'] = array_merge($config['report'] ?? [], ['success' => true]);
        $app['config']->set('http-client-logger', $config);

        $app['config']->set('logging.channels.http_client', [
            'driver' => 'single',
            'path' => storage_path('logs/http_client_test.log'),
            'level' => 'debug',
            'formatter' => LineFormatter::class,
            'formatter_with' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context%\n",
                'dateFormat' => 'Y-m-d H:i:s',
            ],
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
