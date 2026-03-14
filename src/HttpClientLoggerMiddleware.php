<?php

declare(strict_types=1);

namespace Andriichuk\HttpClientLogger;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Guzzle middleware that logs outgoing HTTP request/response (or failure) to a Laravel log channel.
 * All behaviour is driven by the "http-client-logger" config (injected via constructor).
 * Use via Laravel HTTP client: Http::log()->get(...) or Http::log(['name' => 'Name'])->post(...).
 */
final readonly class HttpClientLoggerMiddleware
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        #[Config('http-client-logger', [])]
        private array $config
    ) {}

    /**
     * Returns a Guzzle middleware callable that logs the request and response (or exception)
     * according to the "http-client-logger" config.
     *
     * @param  array<string, mixed>  $context  Optional context (e.g. ['name' => 'ApiName']) included in log message
     * @return callable Guzzle middleware function
     */
    public function __invoke(array $context = []): callable
    {
        return function (callable $handler) use ($context): callable {
            return function (RequestInterface $request, array $options) use ($context, $handler): PromiseInterface {
                $start = hrtime(true);
                $requestFilesMeta = $this->extractMultipartFilesMetadataFromRequest($request);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($context, $request, $start, $requestFilesMeta): ResponseInterface {
                        $timeMs = $this->captureTiming($start);
                        $this->logSuccess($request, $response, $context, $requestFilesMeta, $timeMs);

                        return $response;
                    }
                )->otherwise(function (Throwable $throwable) use ($request, $context, $start, $requestFilesMeta): never {
                    $timeMs = $this->captureTiming($start);
                    $this->logException($request, $throwable, $context, $requestFilesMeta, $timeMs);

                    throw $throwable;
                });
            };
        };
    }

    private function captureTiming(float $start): float
    {
        return round((hrtime(true) - $start) / 1_000_000);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{channel: string, message_prefix: string, name_in_message: string, request_headers: array, request_body: array|string}
     */
    private function prepareRequestLogData(RequestInterface $request, array $context): array
    {
        $requestBody = $this->readStream($request->getBody());
        $requestBodyParsed = $this->parseAndSanitizeBody($requestBody, $this->config, true);
        $includeRequestHeaders = $this->config['include_request_headers'] ?? [];
        $requestHeaders = $this->filterHeaders($request->getHeaders(), $includeRequestHeaders, $this->config['sensitive_headers'] ?? []);

        return [
            'channel' => $this->config['channel'] ?? 'stack',
            'message_prefix' => $this->config['message_prefix'] ?? '[HttpClientLogger] ',
            'name_in_message' => ! empty($context['name']) ? $context['name'].' ' : '',
            'request_headers' => $requestHeaders,
            'request_body' => $requestBodyParsed,
        ];
    }

    private function logSuccess(
        RequestInterface $request,
        ResponseInterface $response,
        array $context,
        array $requestFilesMeta,
        float $timeMs
    ): void {
        if (! ($this->config['enabled'] ?? false)) {
            return;
        }

        $status = $response->getStatusCode();

        if (! $this->shouldLogStatus($status, $this->config['report'] ?? [])) {
            return;
        }

        $prepared = $this->prepareRequestLogData($request, $context);

        $bodyStream = $response->getBody();
        $responseBodyRaw = $bodyStream->getContents();
        $bodyStream->rewind();

        $responseBody = '[skipped]';

        if ($this->config['include_response'] ?? $this->config['include_response_body'] ?? true) {
            $includeNonJson = (bool) ($this->config['include_non_json_response'] ?? false);
            $responseBody = $this->parseAndSanitizeBody($responseBodyRaw, $this->config, $includeNonJson);
        }

        $includeRequestHeaders = $this->config['include_request_headers'] ?? [];
        $includeResponseHeaders = $this->config['include_response_headers'] ?? [];
        $responseHeaders = $this->filterHeaders($this->headersToArray($response->getHeaders()), $includeResponseHeaders, $this->config['sensitive_headers'] ?? []);

        $logContext = [
            'request_body' => $prepared['request_body'],
            'response_status_code' => $status,
            'response_body' => $responseBody,
            'execution_time_ms' => $timeMs,
        ];

        if ($includeRequestHeaders !== []) {
            $logContext['request_headers'] = $prepared['request_headers'];
        }
        if ($includeResponseHeaders !== []) {
            $logContext['response_headers'] = $responseHeaders;
        }

        if (($this->config['include_uploaded_files_metadata'] ?? true) && $requestFilesMeta !== []) {
            $logContext['uploaded_files'] = $requestFilesMeta;
        }

        $logLevel = $this->logLevelForStatus($status, $this->config['log_level_by_status'] ?? []);

        Log::channel($prepared['channel'])->log(
            $logLevel,
            $prepared['message_prefix'].$prepared['name_in_message'].($prepared['name_in_message'] !== '' ? '' : ' ').$request->getMethod().' '.(string) $request->getUri(),
            $logContext
        );
    }

    private function logException(
        RequestInterface $request,
        Throwable $throwable,
        array $context,
        array $requestFilesMeta,
        float $timeMs
    ): void {
        if (! ($this->config['enabled'] ?? false)) {
            return;
        }

        $prepared = $this->prepareRequestLogData($request, $context);
        $includeRequestHeaders = $this->config['include_request_headers'] ?? [];

        $handlerContext = method_exists($throwable, 'getHandlerContext')
            ? /** @var array{errno?: int, error?: string} */ ($throwable->getHandlerContext() ?? [])
            : [];

        $errorContext = [
            'request_body' => $prepared['request_body'],
            'error_code' => $throwable->getCode(),
            'errno' => $handlerContext['errno'] ?? null,
            'error' => $handlerContext['error'] ?? null,
            'execution_time_ms' => $timeMs,
        ];

        if ($includeRequestHeaders !== []) {
            $errorContext['request_headers'] = $prepared['request_headers'];
        }
        if (($this->config['include_uploaded_files_metadata'] ?? true) && $requestFilesMeta !== []) {
            $errorContext['uploaded_files'] = $requestFilesMeta;
        }

        Log::channel($prepared['channel'])->error(
            $prepared['message_prefix'].$prepared['name_in_message'].($prepared['name_in_message'] !== '' ? '' : ' ').'Request failed: '.$request->getMethod().' '.(string) $request->getUri().' — '.$throwable->getMessage(),
            $errorContext
        );
    }

    private function statusCategory(int $status): string
    {
        return match (true) {
            $status >= 100 && $status < 200 => 'info',
            $status >= 200 && $status < 300 => 'success',
            $status >= 300 && $status < 400 => 'redirect',
            $status >= 400 && $status < 500 => 'client_error',
            $status >= 500 => 'server_error',
            default => 'info',
        };
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function shouldLogStatus(int $status, array $report): bool
    {
        if (empty($report)) {
            return true;
        }

        return (bool) ($report[$this->statusCategory($status)] ?? true);
    }

    /**
     * @param  array<string, string>  $logLevelByStatus
     */
    private function logLevelForStatus(int $status, array $logLevelByStatus): string
    {
        return $logLevelByStatus[$this->statusCategory($status)] ?? 'info';
    }

    /**
     * Extract file metadata from a multipart/form-data request body by parsing part headers.
     * No file contents are read; stream is rewound after parsing. Matches laravel-http-logger structure.
     *
     * @return array<int, array{name: string, original_name: string, size: int|null, mime_type: string|null, extension: string|null, error: int}>
     */
    private function extractMultipartFilesMetadataFromRequest(RequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (! str_contains(strtolower($contentType), 'multipart/form-data')) {
            return [];
        }

        if (! preg_match('/boundary=(?:"([^"]+)"|([^\s;]+))/i', $contentType, $m)) {
            return [];
        }
        $boundary = trim($m[1] ?? $m[2], '"');

        $stream = $request->getBody();
        if (! $stream->isSeekable()) {
            return [];
        }

        $pos = $stream->tell();
        $stream->rewind();
        $raw = $stream->getContents();
        $stream->seek($pos);

        $meta = [];
        $delim = "\r\n--".$boundary;
        $parts = $delim !== "\r\n--" ? explode($delim, $raw) : [$raw];
        foreach ($parts as $i => $part) {
            if ($i === 0) {
                $part = preg_replace('/^.*\r\n/', '', $part);
            }
            if ($part === '' || $part === "--\r\n" || $part === "-") {
                continue;
            }
            $headerEnd = strpos($part, "\r\n\r\n");
            if ($headerEnd === false) {
                continue;
            }
            $headers = substr($part, 0, $headerEnd);
            $contentDisposition = null;
            $contentTypePart = null;
            foreach (explode("\r\n", $headers) as $line) {
                if (stripos($line, 'Content-Disposition:') === 0) {
                    $contentDisposition = $line;
                }
                if (stripos($line, 'Content-Type:') === 0) {
                    $contentTypePart = trim(substr($line, 12));
                }
            }
            if ($contentDisposition === null || ! preg_match('/name\s*=\s*"([^"]+)"/', $contentDisposition, $nameM)) {
                continue;
            }
            $name = $nameM[1];
            $filename = null;
            if (preg_match('/filename\s*=\s*"([^"]*)"/', $contentDisposition, $fnM)) {
                $filename = $fnM[1];
            } elseif (preg_match("/filename\s*=\s*'([^']*)'/", $contentDisposition, $fnM)) {
                $filename = $fnM[1];
            }
            if ($filename === null || $filename === '') {
                continue;
            }
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $meta[] = [
                'name' => $name,
                'original_name' => $filename,
                'size' => null,
                'mime_type' => $contentTypePart ?: null,
                'extension' => $extension !== '' ? $extension : null,
                'error' => 0,
            ];
        }

        return $meta;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @param  list<string>  $include
     * @param  list<string>  $sensitive
     * @return array<string, array<int, string>>
     */
    private function filterHeaders(array $headers, array $include, array $sensitive): array
    {
        if ($include === []) {
            return [];
        }

        $sensitiveLower = array_map('strtolower', $sensitive);
        $result = [];

        foreach ($headers as $name => $values) {
            $nameLower = strtolower($name);
            if ($include !== [] && ! in_array('*', $include, true) && ! in_array($nameLower, $include, true)) {
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
    private function parseAndSanitizeBody(string $raw, array $config, bool $includeNonJson = true): array|string
    {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $sanitizer = new Sanitizer;
            $sensitive = $config['sensitive_fields'] ?? null;
            $maxLength = $this->maxStringLength($config);

            return $sanitizer->sanitize($decoded, $sensitive, $maxLength);
        }

        if (! $includeNonJson) {
            return '[skipped]';
        }

        $maxLength = $this->maxStringLength($config);

        if ($maxLength !== null && mb_strlen($raw) > $maxLength) {
            return mb_substr($raw, 0, $maxLength).'…';
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function maxStringLength(array $config): ?int
    {
        $v = $config['max_string_value_length'] ?? $config['max_body_length'] ?? 1000;

        return $v === null ? null : (int) $v;
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
