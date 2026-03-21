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
            'path' => $this->httpClientTestLogPath(),
            'level' => 'debug',
            'formatter' => LineFormatter::class,
            'formatter_with' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context%\n",
                'dateFormat' => 'Y-m-d H:i:s',
            ],
        ]);
    }

    protected function httpClientTestLogPath(): string
    {
        // Testbench's storage_path() points under vendor/ (not writable on Windows CI). sys_get_temp_dir()
        // can also fail there (short paths, locking). Use the package workspace, which is always writable.
        $dir = dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir.DIRECTORY_SEPARATOR.'http_client_test.log';
    }

    protected function getLogContent(): string
    {
        $path = $this->httpClientTestLogPath();

        return file_exists($path) ? file_get_contents($path) : '';
    }

    protected function clearLog(): void
    {
        $path = $this->httpClientTestLogPath();
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
