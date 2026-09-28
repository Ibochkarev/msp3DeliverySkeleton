<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Ibochkarev\Msp3DeliverySkeleton\Exception\DeliveryException;
use Ibochkarev\Msp3DeliverySkeleton\Service\SafeLogger;
use Ibochkarev\Msp3DeliverySkeleton\Service\Settings;
use MODX\Revolution\modX;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

/**
 * PROVIDER: API endpoint. Replace paths and request shape with the carrier API.
 */
final class ApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly Signature $signature,
        private readonly SafeLogger $logger,
        private readonly int $timeout = 10,
    ) {
    }

    public static function fromModx(modX $modx, Settings $settings, ?Signature $signature = null, ?SafeLogger $logger = null): self
    {
        $signature ??= new Signature($settings);
        $logger ??= new SafeLogger($modx, $settings->debug());
        $timeout = $settings->timeout();
        if (class_exists(Client::class)) {
            $http = new Client(['timeout' => $timeout]);
        } else {
            $http = $modx->services->get(ClientInterface::class);
            if (!$http instanceof ClientInterface) {
                throw new DeliveryException('HTTP client is not available');
            }
        }
        $factory = new HttpFactory();

        return new self($settings->apiUrl(), $http, $factory, $factory, $signature, $logger, $timeout);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     */
    public function get(string $path, array $query = [], array $headers = []): ApiResponse
    {
        // PROVIDER: retry — only for safe GET errors, limited attempts, never for shipment create
        return $this->request('GET', $path, $query, null, $headers);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function post(string $path, array $body = [], array $headers = []): ApiResponse
    {
        return $this->request('POST', $path, [], $body, $headers);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function put(string $path, array $body = [], array $headers = []): ApiResponse
    {
        return $this->request('PUT', $path, [], $body, $headers);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     */
    public function delete(string $path, array $query = [], array $headers = []): ApiResponse
    {
        return $this->request('DELETE', $path, $query, null, $headers);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     */
    private function request(string $method, string $path, array $query, ?array $body, array $headers): ApiResponse
    {
        $url = $this->buildUrl($path, $query);
        $encoded = '';
        if ($body !== null) {
            try {
                $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new DeliveryException('Provider request body is not valid JSON', previous: $exception);
            }
        }
        $headers = $this->signature->apply($headers, $method, $path, $encoded);
        if ($encoded !== '' && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }
        $headers['Accept'] ??= 'application/json';

        $request = $this->requests->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($encoded !== '') {
            $request = $request->withBody($this->streams->createStream($encoded));
        }

        try {
            $psr = $this->http->sendRequest($request);
        } catch (Throwable $exception) {
            $this->logger->error('HTTP transport failed', [
                'method' => $method,
                'path' => $path,
            ]);
            throw new DeliveryException('Provider request failed', previous: $exception);
        }

        $status = $psr->getStatusCode();
        $raw = (string) $psr->getBody();
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        $json = is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
        $requestId = $psr->getHeaderLine('X-Request-Id');
        if ($requestId === '') {
            $fromJson = $json['request_id'] ?? $json['requestId'] ?? null;
            $requestId = is_scalar($fromJson) ? (string) $fromJson : '';
        }
        $response = new ApiResponse($status, $json, $requestId !== '' ? $requestId : null);

        $this->logger->debug('Provider HTTP', [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'request_id' => $response->requestId(),
            'timeout' => $this->timeout,
        ]);

        if ($status < 200 || $status >= 300) {
            $code = $json['code'] ?? $json['error_code'] ?? $json['error'] ?? null;
            throw new DeliveryException(
                'Provider HTTP ' . $status,
                $status,
                is_scalar($code) ? (string) $code : null,
                $response->requestId(),
            );
        }

        return $response;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $base = rtrim($this->baseUrl, '/');
        $url = $base . '/' . ltrim($path, '/');
        if ($query === []) {
            return $url;
        }
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            $pairs[$key] = (string) $value;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($pairs);
    }
}
