# Laravel HTTP Client Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andriichuk/laravel-http-client-logger.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-http-client-logger)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-http-client-logger/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/andriichuk/laravel-http-client-logger/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-http-client-logger/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/andriichuk/laravel-http-client-logger/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/andriichuk/laravel-http-client-logger.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-http-client-logger)

A **super simple**, configurable logger for Laravel’s HTTP client (outgoing requests). Ideal for APIs and third-party integrations.

- **Sanitization** — Mask sensitive fields and headers (e.g. password, authorization).
- **Filters** — Limit by response status (2xx, 4xx, 5xx, etc.).
- **Headers** — Choose which request/response headers to include in logs. Use `['*']` for all; when the arrays are empty, no header keys are added to the log at all.
- **Optional response body** — Include decoded, sanitized response body (JSON); non-JSON can be truncated or skipped.
- **Request files (multipart)** — When sending files via `attach()`, log metadata (name, original_name, size, mime_type, extension) in context as `uploaded_files`; no file contents are logged. Aligns with [laravel-http-logger](https://github.com/andriichuk/laravel-http-logger).

## Installation

**Requirements:** PHP 8.2+ and Laravel 10.x, 11.x or 12.x.

Install the package via Composer:

```bash
composer require andriichuk/laravel-http-client-logger
```

Publish the config file:

```bash
php artisan vendor:publish --tag="http-client-logger-config"
```

(Optional) Add a dedicated log channel for HTTP client logs in `config/logging.php` (e.g. a separate file or stack). If you skip this, the package uses your default log channel.

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

> **Important — when is logging on?** If `LOG_HTTP_CLIENT_REQUESTS` is not set in `.env`, the logger follows **`APP_DEBUG`**: it is enabled when `APP_DEBUG=true` (e.g. local) and disabled when `APP_DEBUG=false` (e.g. production). To force it on or off, set **`LOG_HTTP_CLIENT_REQUESTS=true`** or **`LOG_HTTP_CLIENT_REQUESTS=false`** in your `.env`, or set `'enabled'` in `config/http-client-logger.php`. The log channel can be overridden with **`HTTP_CLIENT_LOG_CHANNEL`** (e.g. `HTTP_CLIENT_LOG_CHANNEL=http_client` to use the channel above).

## Configuration

After publishing, configure `config/http-client-logger.php` as needed.

| Key | Description | Default |
| --- | --- | --- |
| `enabled` | Master switch for HTTP client logging. When `LOG_HTTP_CLIENT_REQUESTS` is unset, falls back to `APP_DEBUG`. When disabled, the `log` macro still exists but no entries are written. | `env('LOG_HTTP_CLIENT_REQUESTS', APP_DEBUG)` |
| `channel` | Log channel name (must exist in `config/logging.php`). | `HTTP_CLIENT_LOG_CHANNEL` or `LOG_CHANNEL` or `'stack'` |
| `report` | Which response status categories to log: `info` (1xx), `success` (2xx), `redirect` (3xx), `client_error` (4xx), `server_error` (5xx). Each key is a boolean. | `info`/`success` → `false`; `redirect`/`client_error`/`server_error` → `true` |
| `log_level_by_status` | Map each status category to a PSR log level (`debug`, `info`, `notice`, `warning`, `error`, etc.). 5xx → `error` and 4xx → `warning` by default for easier filtering. | `client_error` → `warning`; `server_error` → `error`; others → `info` |
| `include_response` | Include response body in log context. | `true` |
| `include_non_json_response` | When `include_response` is true, include non-JSON bodies (HTML, text, etc.) in the log (truncated). Set to `true` to include them; default logs as `'[skipped]'`. | `false` |
| `include_request_headers` | Request header names (lowercase) to include. Use `['*']` for all. When empty, no request headers are added to the log. | `['*']` |
| `include_response_headers` | Response header names (lowercase) to include. Use `['*']` for all. When empty, no response headers are added to the log. | `['*']` |
| `include_uploaded_files_metadata` | When true, multipart requests that send files (e.g. via `attach()`) include file metadata in log context as `uploaded_files`: name, original_name, size, mime_type, extension, error. No file contents are logged. | `true` |
| `sensitive_fields` | Request/response body keys to replace with `***`. | `['token', 'refresh_token', 'password', …]` |
| `sensitive_headers` | Header names (lowercase) to replace with `***`. | `['authorization', 'cookie']` |
| `max_string_value_length` | Max length for string values in bodies (and non-JSON response body) before truncation. Use `null` to disable truncation. | `100` |
| `message_prefix` | Prefix for the log message. | `'[HttpClientLogger] '` |

**Response body logging:** JSON responses are decoded and sanitized; `max_string_value_length` applies to each string value. Non-JSON responses are logged as a truncated string or `'[skipped]'`. The log **level** follows response status by default (5xx → `error`, 4xx → `warning`, 1xx/2xx/3xx → `info`); configure via `log_level_by_status`.

## Usage

Use the `log` macro on the Laravel HTTP client to log that request and its response (or failure).

**Basic:**

```php
use Illuminate\Support\Facades\Http;

Http::log()->get('https://api.example.com/users');
Http::log()->post('https://api.example.com/orders', ['item' => 'widget']);
```

**With context (e.g. name in the log message):**

```php
Http::log(['name' => 'Stripe'])->post('https://api.stripe.com/v1/charges', $payload);
```

**Using the `name` macro:**

Use the `name` macro to add a `name` key to the logging context and include it in the log message. Handy for chaining and identifying which integration a request belongs to.

```php
Http::name('Stripe')->log()->get('https://api.stripe.com/v1/balance');
```

The name appears in the log message right after the prefix (e.g. `[HttpClientLogger] Stripe GET https://api.stripe.com/v1/balance`).

**Sending files (multipart):**

When you send files with `attach()`, the logger can include file metadata in the log context as `uploaded_files` (name, original_name, size, mime_type, extension). No file contents are logged. Enable or disable via `include_uploaded_files_metadata` in config.

```php
Http::log()->attach('document', $fileContents, 'report.pdf')->post('https://api.example.com/upload');
// or with Laravel's UploadedFile:
Http::log()->attach('avatar', $request->file('avatar'))->post('https://api.example.com/profile/photo');
```

When `enabled` is `false` in config, the macro is still available but the middleware does not write any log entries.

## Callbacks

You can register one or more callbacks that run when a request is logged (after the same checks that allow logging: `enabled`, and for success responses, the status filter). Use this to push metrics, notify, or run custom logic whenever an outgoing request is logged.

**Callback signature:** `(RequestInterface $request, ?ResponseInterface $response, float $executionTimeMs): void`

- On **success** (in `logSuccess`), `$response` is the PSR-7 response.
- On **exception** (in `logException`), `$response` is `null`.

Register callbacks in your `AppServiceProvider::boot()` method using the `HttpClientLogger` facade:

```php
use Andriichuk\HttpClientLogger\HttpClientLogger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

public function boot(): void
{
    HttpClientLogger::addCallback(function (RequestInterface $request, ?ResponseInterface $response, float $executionTimeMs): void {
        // $response is null when the request failed (exception path)
        // Runs only when logging is enabled and (for success) status is reported
    });
}
```

Callbacks are invoked before the package writes to the log context. If a callback throws, the exception is not caught by the package and bubbles up to the caller.

## Example log output

**Successful API call (200):** `INFO` level, with name and sanitized response.

```
[2026-03-14 10:15:22] local.INFO: [HttpClientLogger] Stripe GET https://api.stripe.com/v1/balance {"response_status_code":200,"request_headers":{"content-type":["application/json"],"authorization":"***"},"response_headers":{"content-type":["application/json"]},"request_body":[],"response_body":{"available":[{"amount":1000,"currency":"usd"}],"token":"***"},"execution_time_ms":142}
```

**Client error (422):** `WARNING` level, authorization masked, JSON response with validation errors.

```
[2026-03-14 10:16:01] local.WARNING: [HttpClientLogger] POST https://api.example.com/orders {"response_status_code":422,"request_headers":{"content-type":["application/json"],"authorization":"***"},"response_headers":[],"request_body":{"item":"widget","quantity":-1},"response_body":{"message":"The quantity must be at least 1.","errors":{"quantity":["The quantity must be at least 1."]}},"execution_time_ms":89}
```

**Message (no name):** `[HttpClientLogger] GET https://api.example.com/users`

**Multipart with file:** `uploaded_files` in context (metadata only; no file contents).

```
[2026-03-14 10:20:00] local.INFO: [HttpClientLogger] POST https://api.example.com/upload {"response_status_code":200,"request_headers":{...},"response_headers":{},"request_body":"...","response_body":"...","execution_time_ms":45,"uploaded_files":[{"name":"document","original_name":"report.pdf","size":null,"mime_type":null,"extension":"pdf","error":0}]}
```

## Testing

Run the test suite with Pest:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Serhii Andriichuk](https://github.com/andriichuk)
- [All Contributors](https://github.com/andriichuk/laravel-http-client-logger/contributors)
