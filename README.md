# Laravel HTTP Client Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andriichuk/laravel-http-client-logger.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-http-client-logger)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-http-client-logger/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/andriichuk/laravel-http-client-logger/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-http-client-logger/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/andriichuk/laravel-http-client-logger/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/andriichuk/laravel-http-client-logger.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-http-client-logger)

A configurable logger for Laravel’s HTTP client (outgoing requests). Log request and response details to a dedicated channel with optional sanitization of sensitive data, status filters, and configurable headers—ideal for APIs and third-party integrations.

## Installation

Install the package via Composer:

```bash
composer require andriichuk/laravel-http-client-logger
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-http-client-logger-config"
```

Add a log channel for HTTP client logs in `config/logging.php` (e.g. a dedicated file or stack):

```php
'channels' => [
    // ...
    'http_client' => [
        'driver' => 'daily',
        'path' => storage_path('logs/http_client.log'),
        'level' => 'debug',
    ],
],
```

Set the package to use that channel in `config/http-client-logger.php`:

```php
'channel' => env('HTTP_CLIENT_LOGGER_CHANNEL', 'http_client'),
```

## Configuration

After publishing, configure `config/http-client-logger.php` as needed.

| Key | Description | Example |
|-----|-------------|---------|
| `enabled` | Master switch for HTTP client logging. When disabled, the `log` macro still exists but no entries are written. | `true` or `env('HTTP_CLIENT_LOGGER_ENABLED', true)` |
| `channel` | Log channel name (must exist in `config/logging.php`). | `'http_client'` |
| `report` | Which response status categories to log: `info` (1xx), `success` (2xx), `redirect` (3xx), `client_error` (4xx), `server_error` (5xx). | `'client_error' => true` |
| `include_response_body` | Whether to include the response body in the log context. | `true` |
| `include_request_headers` | Request header names (lowercase) to include. Empty array = include all. | `['content-type', 'x-request-id']` |
| `include_response_headers` | Response header names (lowercase) to include. Empty array = include all. | `['content-type', 'x-request-id']` |
| `sensitive_fields` | Request/response body keys to replace with `***`. | `['token', 'password', 'refresh_token']` |
| `sensitive_headers` | Header names (lowercase) to replace with `***`. | `['authorization', 'cookie', 'x-api-key']` |
| `max_body_length` | Max string length for body values before truncation (with `…`). | `1000` |
| `message_prefix` | Prefix for the log message (e.g. for filtering in log aggregators). | `'[HttpClientLogger] '` |

## Usage

Use the `log` macro on the Laravel HTTP client to log that request and its response (or failure).

**Basic:**

```php
use Illuminate\Support\Facades\Http;

Http::log()->get('https://api.example.com/users');
Http::log()->post('https://api.example.com/orders', ['item' => 'widget']);
```

**With context (e.g. client name in the log message):**

```php
Http::log(['client' => 'Stripe'])->post('https://api.stripe.com/v1/charges', $payload);
```

When `enabled` is `false` in config, the macro is still available but the middleware does not write any log entries.

## Example log output

**Message:** `[HttpClientLogger] GET https://api.example.com/users`

**Context (example):**

```php
[
    'request_headers' => ['Content-Type' => ['application/json']],
    'request_body' => ['page' => 1],
    'response_status' => 200,
    'response_headers' => ['Content-Type' => ['application/json']],
    'response_body' => ['data' => [...], 'token' => '***'],
    'execution_time_ms' => 142,
]
```

## Example: log only 4xx/5xx with masked secrets

```php
// config/http-client-logger.php
return [
    'enabled' => true,
    'channel' => 'http_client',
    'report' => [
        'info' => false,
        'success' => false,
        'redirect' => false,
        'client_error' => true,
        'server_error' => true,
    ],
    'include_response_body' => true,
    'include_request_headers' => ['content-type', 'x-request-id'],
    'include_response_headers' => ['content-type', 'x-request-id'],
    'sensitive_fields' => ['token', 'password', 'refresh_token', 'secret', 'api_key'],
    'sensitive_headers' => ['authorization', 'cookie', 'x-api-key'],
    'max_body_length' => 1000,
    'message_prefix' => '[HttpClientLogger] ',
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Serhii Andriichuk](https://github.com/andriichuk)
- [All Contributors](https://github.com/andriichuk/laravel-http-client-logger/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
