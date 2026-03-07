<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable HTTP Client Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, the package registers the "log" macro on the Laravel HTTP
    | client. Use Http::log()->get(...) to log that request/response.
    |
    */
    'enabled' => env('HTTP_CLIENT_LOGGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel to use for HTTP client request/response logs. Must exist
    | in config/logging.php (e.g. 'http', 'stack', 'single').
    |
    */
    'channel' => env('HTTP_CLIENT_LOGGER_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Which Response Statuses to Log
    |--------------------------------------------------------------------------
    |
    | Control which HTTP status categories are logged for successful responses.
    | Exceptions (network errors, timeouts) are always logged when they occur.
    |
    */
    'report' => [
        'info' => true,         // 1xx
        'success' => true,      // 2xx
        'redirect' => true,     // 3xx
        'client_error' => true, // 4xx
        'server_error' => true, // 5xx
    ],

    /*
    |--------------------------------------------------------------------------
    | Include Response Body
    |--------------------------------------------------------------------------
    |
    | Whether to include the response body in the log context.
    |
    */
    'include_response_body' => true,

    /*
    |--------------------------------------------------------------------------
    | Request Headers to Include
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to include in logs. Empty array = include all.
    |
    */
    'include_request_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Response Headers to Include
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to include in logs. Empty array = include all.
    |
    */
    'include_response_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Body Fields
    |--------------------------------------------------------------------------
    |
    | Request/response body keys to replace with "***" in logs.
    |
    */
    'sensitive_fields' => [
        'token',
        'password',
        'refresh_token',
        'secret',
        'api_key',
        'authorization',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Headers
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to replace with "***" in logs.
    |
    */
    'sensitive_headers' => [
        'authorization',
        'cookie',
        'x-api-key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Body Length
    |--------------------------------------------------------------------------
    |
    | Maximum string length for body values before truncation (with "…").
    |
    */
    'max_body_length' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Log Message Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for the log message (e.g. for filtering in log aggregators).
    |
    */
    'message_prefix' => '[HttpClientLogger] ',

];
