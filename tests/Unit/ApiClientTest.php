<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Unit;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ibochkarev\Msp3DeliverySkeleton\Api\ApiClient;
use Ibochkarev\Msp3DeliverySkeleton\Api\Signature;
use Ibochkarev\Msp3DeliverySkeleton\Exception\DeliveryException;
use Ibochkarev\Msp3DeliverySkeleton\Service\SafeLogger;
use Ibochkarev\Msp3DeliverySkeleton\Service\Settings;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ApiClientTest extends TestCase
{
    public function testGetPostPutDeleteAndJson(): void
    {
        $http = new FakeHttp(200, '{"cost":120,"request_id":"req-1"}', ['X-Request-Id' => 'hdr-1']);
        $client = $this->client($http);
        $response = $client->post('/calculate', ['city' => 'Moscow']);
        self::assertSame(200, $response->status());
        self::assertSame(120, $response->json()['cost']);
        self::assertSame('hdr-1', $response->requestId());
        self::assertSame('POST', $http->last()->getMethod());
        self::assertSame('https://api.example.test/calculate', (string) $http->last()->getUri());
        self::assertStringStartsWith('Bearer ', $http->last()->getHeaderLine('Authorization'));

        $http->status = 200;
        $client->get('/tariffs', ['q' => 'x']);
        self::assertStringContainsString('q=x', (string) $http->last()->getUri());
        $client->put('/shipments/1', ['status' => 'ok']);
        self::assertSame('PUT', $http->last()->getMethod());
        $client->delete('/shipments/1');
        self::assertSame('DELETE', $http->last()->getMethod());
    }

    public function testErrorKeepsSafeFieldsAndRedactsSecrets(): void
    {
        $http = new FakeHttp(422, '{"code":"E_RATE","error":"nope"}');
        $logger = new SafeLogger(null, true);
        $client = $this->client($http, $logger);
        try {
            $client->post('/calculate', ['api_key' => 'sk_live_abcdefgh1234']);
            self::fail('Expected DeliveryException');
        } catch (DeliveryException $exception) {
            self::assertSame(422, $exception->httpStatus);
            self::assertSame('E_RATE', $exception->providerCode);
            self::assertStringNotContainsString('sk_live', $exception->getMessage());
        }
        $joined = implode("\n", $logger->records);
        self::assertStringNotContainsString('sk_live_abcdefgh1234', $joined);
    }

    public function testInvalidJsonBodyThrows(): void
    {
        $http = new FakeHttp(200, '{"ok":true}');
        $client = $this->client($http);
        $this->expectException(DeliveryException::class);
        $client->post('/calculate', ['city' => "bad\xB1utf8"]);
    }

    private function client(FakeHttp $http, ?SafeLogger $logger = null): ApiClient
    {
        $factory = new HttpFactory();
        $settings = new Settings(null, null, ['api_key' => 'token-1']);

        return new ApiClient(
            'https://api.example.test',
            $http,
            $factory,
            $factory,
            new Signature($settings),
            $logger ?? new SafeLogger(),
            5
        );
    }
}

final class FakeHttp implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];
    public int $status;
    public string $body;
    /** @var array<string, string> */
    public array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $status, string $body, array $headers = [])
    {
        $this->status = $status;
        $this->body = $body;
        $this->headers = $headers;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return new Response($this->status, $this->headers, $this->body);
    }

    public function last(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)];
    }
}
