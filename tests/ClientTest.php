<?php

declare(strict_types=1);

namespace MultiplayerGratis\Tests;

use MultiplayerGratis\Client;
use MultiplayerGratis\Exception\ApiException;
use MultiplayerGratis\Exception\AuthenticationException;
use MultiplayerGratis\Exception\ConflictException;
use MultiplayerGratis\Exception\ForbiddenException;
use MultiplayerGratis\Exception\InsufficientCoinsException;
use MultiplayerGratis\Exception\LockedException;
use MultiplayerGratis\Exception\NotFoundException;
use MultiplayerGratis\Exception\RateLimitException;
use MultiplayerGratis\Exception\ServiceUnavailableException;
use MultiplayerGratis\Exception\ValidationException;
use MultiplayerGratis\Http\HttpClientInterface;
use MultiplayerGratis\Http\Response;
use MultiplayerGratis\PowerSignal;
use MultiplayerGratis\ServerType;
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

    #[DataProvider('endpointProvider')]
    public function testEndpointRequests(\Closure $call, string $method, string $path, ?string $body): void
    {
        $http = new FakeHttpClient(new Response(200, '{}'));
        $call(new Client('mg_test', http: $http));

        self::assertSame($method, $http->method);
        self::assertSame('https://multiplayer.gratis/api/v1' . $path, $http->url);
        self::assertSame($body, $http->body);
    }

    public static function endpointProvider(): iterable
    {
        yield 'transactions' => [fn (Client $c) => $c->getTransactions(), 'GET', '/account/transactions', null];
        yield 'types' => [fn (Client $c) => $c->getTypes(), 'GET', '/types', null];
        yield 'packages' => [fn (Client $c) => $c->getPackages(), 'GET', '/packages', null];
        yield 'package' => [fn (Client $c) => $c->getPackage('plus'), 'GET', '/packages/plus', null];
        yield 'eggs' => [fn (Client $c) => $c->getEggs(), 'GET', '/eggs', null];
        yield 'eggs gameservers' => [fn (Client $c) => $c->getEggs('gameserver'), 'GET', '/eggs/gameservers', null];
        yield 'eggs runtimes plural' => [fn (Client $c) => $c->getEggs('runtimes'), 'GET', '/eggs/runtimes', null];
        yield 'eggs softwares enum' => [fn (Client $c) => $c->getEggs(ServerType::Software), 'GET', '/eggs/softwares', null];
        yield 'eggs webspaces' => [fn (Client $c) => $c->getEggs('webspace'), 'GET', '/eggs/webspaces', null];
        yield 'quote' => [fn (Client $c) => $c->quote('plus', 'HERBST20'), 'POST', '/orders/quote', '{"package":"plus","code":"HERBST20"}'];
        yield 'quote ohne code' => [fn (Client $c) => $c->quote('plus'), 'POST', '/orders/quote', '{"package":"plus"}'];
        yield 'order' => [
            fn (Client $c) => $c->orderServer('Survival', 'gameserver', 'plus', '3', 'HERBST20', 'discord:123456789'),
            'POST',
            '/servers',
            '{"name":"Survival","type":"gameserver","package":"plus","egg":3,"code":"HERBST20","reference":"discord:123456789"}',
        ];
        yield 'order minimal' => [
            fn (Client $c) => $c->orderServer('Survival', 'gameserver', 'plus', 3),
            'POST',
            '/servers',
            '{"name":"Survival","type":"gameserver","package":"plus","egg":3}',
        ];
        yield 'renew' => [fn (Client $c) => $c->renewServer(123), 'POST', '/servers/123/renew', null];
        yield 'upgrades' => [fn (Client $c) => $c->getUpgrades(123), 'GET', '/servers/123/upgrade', null];
        yield 'upgrade' => [fn (Client $c) => $c->upgradeServer(123, 'pro'), 'POST', '/servers/123/upgrade', '{"package":"pro"}'];
        yield 'servers' => [fn (Client $c) => $c->getServers(), 'GET', '/servers', null];
        yield 'servers by type' => [fn (Client $c) => $c->getServersByType('runtime'), 'GET', '/servers/type/runtimes', null];
        yield 'servers by type plural' => [fn (Client $c) => $c->getServersByType('gameservers'), 'GET', '/servers/type/gameservers', null];
        yield 'servers by status' => [fn (Client $c) => $c->getServersByStatus('online'), 'GET', '/servers/status/online', null];
        yield 'servers by reference' => [fn (Client $c) => $c->getServersByReference('discord:1'), 'GET', '/servers/reference/discord%3A1', null];
        yield 'order software plural' => [
            fn (Client $c) => $c->orderServer('Mein Bot', 'softwares', 'starter', 12),
            'POST',
            '/servers',
            '{"name":"Mein Bot","type":"software","package":"starter","egg":12}',
        ];
        yield 'update' => [fn (Client $c) => $c->updateServer(123, 'Survival 2', 'kunde-77'), 'PATCH', '/servers/123', '{"name":"Survival 2","reference":"kunde-77"}'];
        yield 'update nur referenz' => [fn (Client $c) => $c->updateServer(123, reference: 'kunde-77'), 'PATCH', '/servers/123', '{"reference":"kunde-77"}'];
        yield 'delete' => [fn (Client $c) => $c->deleteServer(123, 'Survival'), 'DELETE', '/servers/123', '{"confirm":"Survival"}'];
        yield 'backups' => [fn (Client $c) => $c->getBackups(123), 'GET', '/servers/123/backups', null];
        yield 'backup erstellen' => [fn (Client $c) => $c->createBackup(123), 'POST', '/servers/123/backups', '{}'];
        yield 'backup mit name' => [fn (Client $c) => $c->createBackup(123, 'Vor Update'), 'POST', '/servers/123/backups', '{"name":"Vor Update"}'];
        yield 'backup restore' => [fn (Client $c) => $c->restoreBackup(123, 'ab-12'), 'POST', '/servers/123/backups/ab-12/restore', null];
        yield 'backup download' => [fn (Client $c) => $c->getBackupDownload(123, 'ab-12'), 'GET', '/servers/123/backups/ab-12/download', null];
        yield 'backup delete' => [fn (Client $c) => $c->deleteBackup(123, 'ab-12'), 'DELETE', '/servers/123/backups/ab-12', null];
    }

    public function testUpdateServerNeedsNameOrReference(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Client('mg_test', http: new FakeHttpClient(new Response(200, '{}'))))->updateServer(123);
    }

    public function testUnknownServerTypeIsRejected(): void
    {
        $this->expectException(\ValueError::class);
        (new Client('mg_test', http: new FakeHttpClient(new Response(200, '{}'))))->getEggs('minecraft');
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
        yield '402' => [402, InsufficientCoinsException::class];
        yield '403' => [403, ForbiddenException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ConflictException::class];
        yield '422' => [422, ValidationException::class];
        yield '423' => [423, LockedException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ApiException::class];
        yield '502' => [502, ServiceUnavailableException::class];
        yield '503' => [503, ServiceUnavailableException::class];
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
