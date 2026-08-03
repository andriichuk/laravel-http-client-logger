# Changelog

All notable changes to `laravel-http-client-logger` will be documented in this file.

## 0.2.3 - 2026-08-03

- Fixed: `include_request_headers` and `include_response_headers` now match header names
  case-insensitively. Previously a configured name that was not already lowercased (e.g.
  `X-Request-Id`) silently matched nothing, so the header was omitted from the log while
  the `request_headers`/`response_headers` key was still emitted as an empty array.

## 1.0.0 - 2025-03-07

- Initial release: configurable HTTP client request/response logger for Laravel
- `Http::log()` and `Http::log(['name' => 'Name'])` macro for logging outgoing requests
- Config: channel, report (status filters), include_response_body, request/response headers, sensitive_fields, sensitive_headers, max_body_length, message_prefix
- Exception path: failed requests (e.g. connection errors) are logged when enabled
