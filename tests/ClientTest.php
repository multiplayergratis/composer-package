<?php

declare(strict_types=1);

namespace MultiplayerGratis\Tests;

use MultiplayerGratis\Client;
use MultiplayerGratis\Exception\ApiException;
use MultiplayerGratis\Exception\AuthenticationException;
use MultiplayerGratis\Exception\ConflictException;
use MultiplayerGratis\Exception\LockedException;
use MultiplayerGratis\Exception\NotFoundException;
use MultiplayerGratis\Exception\RateLimitException;
use MultiplayerGratis\Http\HttpClientInterface;
use MultiplayerGratis\Http\Response;
use MultiplayerGratis\PowerSignal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testGetServersSendsAuthorizedRequest(): void
    {
        $http = new FakeHttpClient(new Response(200, '[{"id":123,"name":"Survival"}]'));
        $client = new Client('mg_test', http: $http);

        $servers = $client->getServers();

        self::assertSame([['id' => 123, 'name' => 'Survival']], $servers);
        self::assertSame('GET', $http->method);
        self::assertSame('https://multiplayer.gratis/api/v1/servers', $http->url);
        self::assertSame('Bearer mg_test', $http->headers['Authorization']);
        self::assertNull($http->body);
    }

    public function testEndpointsUseExpectedPaths(): void
    {
        $http = new FakeHttpClient(new Response(200, '{}'));
        $client = new Client('mg_test', 'https://example.test/api/v1/', $http);

        $client->getAccount();
        self::assertSame('https://example.test/api/v1/account', $http->url);

        $client->getServer(123);
        self::assertSame('https://example.test/api/v1/servers/123', $http->url);

        $client->getServerResources('123');
        self::assertSame('https://example.test/api/v1/servers/123/resources', $http->url);
    }

    public function testRestartSendsPowerSignal(): void
    {
        $http = new FakeHttpClient(new Response(204, ''));
        $client = new Client('mg_test', http: $http);

        self::assertSame([], $client->restartServer(123));
        self::assertSame('POST', $http->method);
        self::assertSame('https://multiplayer.gratis/api/v1/servers/123/power', $http->url);
        self::assertSame('application/json', $http->headers['Content-Type']);
        self::assertSame('{"signal":"restart"}', $http->body);
    }

    public function testPowerSignalAcceptsString(): void
    {
        $http = new FakeHttpClient(new Response(200, '{}'));
        (new Client('mg_test', http: $http))->sendPowerSignal(1, 'kill');

        self::assertSame('{"signal":"kill"}', $http->body);
    }

    public function testInvalidPowerSignalIsRejected(): void
    {
        $this->expectException(\ValueError::class);
        (new Client('mg_test', http: new FakeHttpClient(new Response(200, '{}'))))->sendPowerSignal(1, 'explode');
    }

    #[DataProvider('errorProvider')]
    public function testErrorsAreMappedToExceptions(int $status, string $exception): void
    {
        $client = new Client('mg_test', http: new FakeHttpClient(new Response($status, '{"error":"Etwas ging schief"}')));

        try {
            $client->getServer(123);
            self::fail('Keine Exception geworfen.');
        } catch (ApiException $e) {
            self::assertInstanceOf($exception, $e);
            self::assertSame('Etwas ging schief', $e->getMessage());
            self::assertSame($status, $e->getStatusCode());
        }
    }

    public static function errorProvider(): iterable
    {
        yield '401' => [401, AuthenticationException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ConflictException::class];
        yield '423' => [423, LockedException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ApiException::class];
    }

    public function testRateLimitExposesRetryAfter(): void
    {
        $client = new Client('mg_test', http: new FakeHttpClient(
            new Response(429, '{"error":"Zu viele Anfragen"}', ['Retry-After' => '30']),
        ));

        try {
            $client->getServers();
            self::fail('Keine Exception geworfen.');
        } catch (RateLimitException $e) {
            self::assertSame(30, $e->getRetryAfter());
        }
    }

    public function testEmptyApiKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client('  ');
    }
}

final class FakeHttpClient implements HttpClientInterface
{
    public ?string $method = null;
    public ?string $url = null;

    public array $headers = [];
    public ?string $body = null;

    public function __construct(private readonly Response $response)
    {
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;

        return $this->response;
    }
}
