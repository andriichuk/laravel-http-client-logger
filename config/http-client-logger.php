<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Log HTTP Client Requests
    |--------------------------------------------------------------------------
    |
    | This option controls whether HTTP client requests should be logged.
    | Use LOG_HTTP_CLIENT_REQUESTS in .env to enable or disable explicitly.
    | When LOG_HTTP_CLIENT_REQUESTS is not set, logging follows APP_DEBUG
    | (enabled in debug mode, disabled in production).
    |
    */
    'enabled' => env('LOG_HTTP_CLIENT_REQUESTS', (bool) env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel to use. Ensure this channel exists in config/logging.php.
    | Defaults to HTTP_CLIENT_LOG_CHANNEL, or LOG_CHANNEL, or "stack" if unset.
    |
    */
    'channel' => env('HTTP_CLIENT_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),

    /*
    |--------------------------------------------------------------------------
    | Report by Status
    |--------------------------------------------------------------------------
    |
    | Which response status codes to log: 1xx info, 2xx success, 3xx redirect,
    | 4xx client_error, 5xx server_error. Set to true to log that category.
    |
    */
    'report' => [
        'info' => false,
        'success' => false,
        'redirect' => true,
        'client_error' => true,
        'server_error' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Level by Response Status
    |--------------------------------------------------------------------------
    |
    | Map each response status category to a PSR log level. Used so 4xx/5xx
    | can be logged as warning/error for easier filtering. Keys match 'report':
    | info, success, redirect, client_error, server_error. Levels: debug, info,
    | notice, warning, error, critical, alert, emergency.
    |
    */
    'log_level_by_status' => [
        'info' => 'info',
        'success' => 'info',
        'redirect' => 'info',
        'client_error' => 'warning',
        'server_error' => 'error',
    ],

    /*
    |--------------------------------------------------------------------------
    | Include Response Body
    |--------------------------------------------------------------------------
    |
    | When true, the response body is decoded and sanitized (for JSON) and
    | included in the log context.
    |
    */
    'include_response' => true,

    /*
    |--------------------------------------------------------------------------
    | Include Non-JSON Response Body (HTML, text, etc.)
    |--------------------------------------------------------------------------
    |
    | When include_response is true, JSON responses are always decoded and
    | sanitized. When this option is true, non-JSON responses (e.g. HTML or
    | plain text) are also included in the log (truncated by max_string_value_length).
    | Default false logs non-JSON response body as '[skipped]'.
    |
    */
    'include_non_json_response' => false,

    /*
    |--------------------------------------------------------------------------
    | Request Headers to Include
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to include in the log context for the request.
    | Use ['*'] to include all request headers. When empty, no request headers
    | are added to the log at all.
    |
    */
    'include_request_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Response Headers to Include
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to include in the log context for the response.
    | Use ['*'] to include all response headers. When empty, no response
    | headers are added to the log at all.
    |
    */
    'include_response_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Include Request Files Metadata (multipart)
    |--------------------------------------------------------------------------
    |
    | When true, multipart requests that send files (e.g. via attach()) will
    | include file metadata in the log context as "uploaded_files": name,
    | original_name, size, mime_type, extension, error. No file contents are
    | logged. Aligns with laravel-http-logger's include_uploaded_files_metadata.
    |
    */
    'include_uploaded_files_metadata' => true,

    /*
    |--------------------------------------------------------------------------
    | Sensitive Body Fields
    |--------------------------------------------------------------------------
    |
    | Request/response body keys to replace with *** in logs (e.g. token, password).
    |
    */
    'sensitive_fields' => [
        'token',
        '_token',
        'refresh_token',
        'password',
        'confirm_password',
        'access_token',
        'api_key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Headers
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to replace with *** in logs (e.g. authorization, cookie).
    |
    */
    'sensitive_headers' => [
        'authorization',
        'cookie',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max String Value Length
    |--------------------------------------------------------------------------
    |
    | Maximum length for string values in request/response bodies before
    | truncation (ellipsis added). Also used to truncate non-JSON response
    | bodies (e.g. HTML or plain text) when logged. Set to null to disable
    | truncation (log full length).
    |
    */
    'max_string_value_length' => 100,

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
