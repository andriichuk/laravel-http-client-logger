# Changelog

All notable changes to `laravel-http-client-logger` will be documented in this file.

## 1.0.0 - 2025-03-07

- Initial release: configurable HTTP client request/response logger for Laravel
- `Http::log()` and `Http::log(['name' => 'Name'])` macro for logging outgoing requests
- Config: channel, report (status filters), include_response_body, request/response headers, sensitive_fields, sensitive_headers, max_body_length, message_prefix
- Exception path: failed requests (e.g. connection errors) are logged when enabled
