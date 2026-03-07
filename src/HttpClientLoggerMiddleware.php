<?php

declare(strict_types=1);

namespace Andriichuk\HttpClientLogger;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Guzzle middleware that logs outgoing HTTP request/response (or failure) to a Laravel log channel.
 * All behaviour is driven by config('http-client-logger'): channel, report, headers, sanitization, etc.
 * Use via Laravel HTTP client: Http::log()->get(...) or Http::log(['client' => 'Name'])->post(...).
 */
final readonly class HttpClientLoggerMiddleware
{
    /**
     * Returns a Guzzle middleware callable that logs the request and response (or exception)
     * according to the "http-client-logger" config.
     *
     * @param  array<string, mixed>  $context  Optional context (e.g. ['client' => 'ApiName']) included in log message
     * @return callable Guzzle middleware function
     */
    public function __invoke(array $context = []): callable
    {
        return function (callable $handler) use ($context): callable {
            return function (RequestInterface $request, array $options) use ($context, $handler): PromiseInterface {
                $start = hrtime(true);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($context, $request, $start): ResponseInterface {
                        $timeMs = $this->captureTiming($start);
                        $this->logSuccess($request, $response, $context, $timeMs);

                        return $response;
                    }
                )->otherwise(function (Throwable $throwable) use ($request, $context, $start): never {
                    $timeMs = $this->captureTiming($start);
                    $this->logException($request, $throwable, $context, $timeMs);

                    throw $throwable;
                });
            };
        };
    }

    private function captureTiming(float $start): float
    {
        return round((hrtime(true) - $start) / 1_000_000);
    }

    private function logSuccess(
        RequestInterface $request,
        ResponseInterface $response,
        array $context,
        float $timeMs
    ): void {
        $config = config('http-client-logger', []);
        if (! ($config['enabled'] ?? true)) {
            return;
        }

        $status = $response->getStatusCode();
        if (! $this->shouldLogStatus($status, $config['report'] ?? [])) {
            return;
        }

        $channel = $config['channel'] ?? 'stack';
        $messagePrefix = $config['message_prefix'] ?? '[HttpClientLogger] ';
        $clientName = ! empty($context['client']) ? ' ('.$context['client'].')' : '';

        $requestBody = $this->readStream($request->getBody());
        $requestBodyParsed = $this->parseAndSanitizeBody($requestBody, $config);
        $includeRequestHeaders = $config['include_request_headers'] ?? [];
        $requestHeaders = $this->filterHeaders($request->getHeaders(), $includeRequestHeaders, $config['sensitive_headers'] ?? []);

        $bodyStream = $response->getBody();
        $responseBodyRaw = $bodyStream->getContents();
        $bodyStream->rewind();

        $responseBody = 'skipped';
        if ($config['include_response_body'] ?? true) {
            $responseBody = $this->parseAndSanitizeBody($responseBodyRaw, $config);
        }
        $includeResponseHeaders = $config['include_response_headers'] ?? [];
        $responseHeaders = $this->filterHeaders($this->headersToArray($response->getHeaders()), $includeResponseHeaders, $config['sensitive_headers'] ?? []);

        $logContext = [
            'request_headers' => $requestHeaders,
            'request_body' => $requestBodyParsed,
            'response_status' => $status,
            'response_headers' => $responseHeaders,
            'response_body' => $responseBody,
            'execution_time_ms' => $timeMs,
        ];

        Log::channel($channel)->info(
            $messagePrefix.$request->getMethod().' '.(string) $request->getUri().$clientName,
            $logContext
        );
    }

    private function logException(
        RequestInterface $request,
        Throwable $throwable,
        array $context,
        float $timeMs
    ): void {
        $config = config('http-client-logger', []);
        if (! ($config['enabled'] ?? true)) {
            return;
        }

        $channel = $config['channel'] ?? 'stack';
        $messagePrefix = $config['message_prefix'] ?? '[HttpClientLogger] ';
        $clientName = ! empty($context['client']) ? ' ('.$context['client'].')' : '';

        $handlerContext = method_exists($throwable, 'getHandlerContext')
            ? /** @var array{errno?: int, error?: string} */ ($throwable->getHandlerContext() ?? [])
            : [];

        $requestBody = $this->readStream($request->getBody());
        $requestBodyParsed = $this->parseAndSanitizeBody($requestBody, $config);
        $includeRequestHeaders = $config['include_request_headers'] ?? [];
        $requestHeaders = $this->filterHeaders($request->getHeaders(), $includeRequestHeaders, $config['sensitive_headers'] ?? []);

        Log::channel($channel)->error(
            $messagePrefix.'Request failed: '.$request->getMethod().' '.(string) $request->getUri().$clientName.' — '.$throwable->getMessage(),
            [
                'request_headers' => $requestHeaders,
                'request_body' => $requestBodyParsed,
                'error_code' => $throwable->getCode(),
                'errno' => $handlerContext['errno'] ?? null,
                'error' => $handlerContext['error'] ?? null,
                'execution_time_ms' => $timeMs,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function shouldLogStatus(int $status, array $report): bool
    {
        if (empty($report)) {
            return true;
        }
        $category = match (true) {
            $status >= 100 && $status < 200 => 'info',
            $status >= 200 && $status < 300 => 'success',
            $status >= 300 && $status < 400 => 'redirect',
            $status >= 400 && $status < 500 => 'client_error',
            $status >= 500 => 'server_error',
            default => 'info',
        };

        return (bool) ($report[$category] ?? true);
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @param  list<string>  $include
     * @param  list<string>  $sensitive
     * @return array<string, array<int, string>>
     */
    private function filterHeaders(array $headers, array $include, array $sensitive): array
    {
        $sensitiveLower = array_map('strtolower', $sensitive);
        $result = [];
        foreach ($headers as $name => $values) {
            $nameLower = strtolower($name);
            if ($include !== [] && ! in_array($nameLower, $include, true)) {
                continue;
            }
            $result[$name] = in_array($nameLower, $sensitiveLower, true) ? ['***'] : $values;
        }

        return $result;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function headersToArray(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $values) {
            $out[$name] = is_array($values) ? $values : [$values];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|string
     */
    private function parseAndSanitizeBody(string $raw, array $config): array|string
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $this->sanitize($decoded, $config);
        }

        $maxLength = (int) ($config['max_body_length'] ?? 1000);
        if (mb_strlen($raw) > $maxLength) {
            return mb_substr($raw, 0, $maxLength).'…';
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function sanitize(array $data, array $config): array
    {
        $sensitive = $config['sensitive_fields'] ?? ['token', 'password', 'refresh_token'];
        $maxLength = (int) ($config['max_body_length'] ?? 1000);

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $data[$key] = '***';

                continue;
            }
            if (is_string($value)) {
                $data[$key] = mb_strlen($value) > $maxLength
                    ? mb_substr($value, 0, $maxLength).'…'
                    : $value;
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value, $config);
            }
        }

        return $data;
    }

    private function readStream(StreamInterface $stream): string
    {
        $pos = $stream->tell();
        $stream->rewind();
        $contents = $stream->getContents();
        $stream->seek($pos);

        return $contents;
    }
}
