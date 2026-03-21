<?php

namespace Andriichuk\HttpClientLogger\Tests;

use Andriichuk\HttpClientLogger\HttpClientLoggerServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
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

        // In-memory handler: avoids file permission/locking issues on Windows CI (vendor storage, temp, workspace).
        $app['config']->set('logging.channels.http_client', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
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
        /** @var Logger $channel */
        $channel = Log::channel('http_client');
        /** @var MonologLogger $monolog */
        $monolog = $channel->getLogger();
        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $out = '';
                foreach ($handler->getRecords() as $record) {
                    $out .= $record->formatted ?? '';
                }

                return $out;
            }
        }

        return '';
    }

    protected function clearLog(): void
    {
        /** @var Logger $channel */
        $channel = Log::channel('http_client');
        /** @var MonologLogger $monolog */
        $monolog = $channel->getLogger();
        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $handler->clear();

                return;
            }
        }
    }
}
