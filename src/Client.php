<?php

declare(strict_types=1);

namespace MultiplayerGratis;

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
use MultiplayerGratis\Http\CurlHttpClient;
use MultiplayerGratis\Http\HttpClientInterface;
use MultiplayerGratis\Http\Response;

final class Client
{
    public const DEFAULT_BASE_URL = 'https://multiplayer.gratis/api/v1';

    private readonly string $baseUrl;

    private readonly HttpClientInterface $http;

    public function __construct(
        private readonly string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?HttpClientInterface $http = null,
    ) {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('Der API-Schlüssel darf nicht leer sein.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = $http ?? new CurlHttpClient();
    }

    public function getAccount(): array
    {
        return $this->request('GET', '/account');
    }

    public function getTransactions(): array
    {
        return $this->request('GET', '/account/transactions');
    }

    public function getTypes(): array
    {
        return $this->request('GET', '/types');
    }

    public function getPackages(): array
    {
        return $this->request('GET', '/packages');
    }

    public function getPackage(int|string $id): array
    {
        return $this->request('GET', '/packages/' . $this->encodeId($id));
    }

    public function getEggs(ServerType|string|null $type = null): array
    {
        return $this->request('GET', $type === null ? '/eggs' : '/eggs/' . ServerType::fromName($type)->pathName());
    }

    public function quote(string $package, ?string $code = null): array
    {
        return $this->request('POST', '/orders/quote', $this->filter([
            'package' => $package,
            'code' => $code,
        ]));
    }

    public function orderServer(
        string $name,
        ServerType|string $type,
        string $package,
        int|string $egg,
        ?string $code = null,
        ?string $reference = null,
    ): array {
        return $this->request('POST', '/servers', $this->filter([
            'name' => $name,
            'type' => ServerType::fromName($type)->value,
            'package' => $package,
            'egg' => is_numeric($egg) ? (int) $egg : $egg,
            'code' => $code,
            'reference' => $reference,
        ]));
    }

    public function renewServer(int|string $id): array
    {
        return $this->request('POST', $this->serverPath($id, '/renew'));
    }

    public function getUpgrades(int|string $id): array
    {
        return $this->request('GET', $this->serverPath($id, '/upgrade'));
    }

    public function upgradeServer(int|string $id, string $package): array
    {
        return $this->request('POST', $this->serverPath($id, '/upgrade'), ['package' => $package]);
    }

    public function getServers(): array
    {
        return $this->request('GET', '/servers');
    }

    public function getServersByType(ServerType|string $type): array
    {
        return $this->request('GET', '/servers/type/' . ServerType::fromName($type)->pathName());
    }

    public function getServersByStatus(string $status): array
    {
        return $this->request('GET', '/servers/status/' . $this->encodeId($status));
    }

    public function getServersByReference(string $reference): array
    {
        return $this->request('GET', '/servers/reference/' . $this->encodeId($reference));
    }

    public function getServer(int|string $id): array
    {
        return $this->request('GET', $this->serverPath($id));
    }

    public function updateServer(int|string $id, ?string $name = null, ?string $reference = null): array
    {
        $body = $this->filter(['name' => $name, 'reference' => $reference]);
        if ($body === []) {
            throw new \InvalidArgumentException('Es muss mindestens Name oder Referenz angegeben werden.');
        }

        return $this->request('PATCH', $this->serverPath($id), $body);
    }

    public function deleteServer(int|string $id, string $confirm): array
    {
        return $this->request('DELETE', $this->serverPath($id), ['confirm' => $confirm]);
    }

    public function getServerResources(int|string $id): array
    {
        return $this->request('GET', $this->serverPath($id, '/resources'));
    }

    public function sendPowerSignal(int|string $id, PowerSignal|string $signal): array
    {
        $signal = $signal instanceof PowerSignal ? $signal : PowerSignal::from($signal);

        return $this->request('POST', $this->serverPath($id, '/power'), [
            'signal' => $signal->value,
        ]);
    }

    public function startServer(int|string $id): array
    {
        return $this->sendPowerSignal($id, PowerSignal::Start);
    }

    public function stopServer(int|string $id): array
    {
        return $this->sendPowerSignal($id, PowerSignal::Stop);
    }

    public function restartServer(int|string $id): array
    {
        return $this->sendPowerSignal($id, PowerSignal::Restart);
    }

    public function killServer(int|string $id): array
    {
        return $this->sendPowerSignal($id, PowerSignal::Kill);
    }

    public function getBackups(int|string $id): array
    {
        return $this->request('GET', $this->serverPath($id, '/backups'));
    }

    public function createBackup(int|string $id, ?string $name = null): array
    {
        return $this->request('POST', $this->serverPath($id, '/backups'), $this->filter(['name' => $name]));
    }

    public function restoreBackup(int|string $id, string $uuid): array
    {
        return $this->request('POST', $this->backupPath($id, $uuid, '/restore'));
    }

    public function getBackupDownload(int|string $id, string $uuid): array
    {
        return $this->request('GET', $this->backupPath($id, $uuid, '/download'));
    }

    public function deleteBackup(int|string $id, string $uuid): array
    {
        return $this->request('DELETE', $this->backupPath($id, $uuid));
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'multiplayer-gratis-php-client',
        ];

        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body === [] ? new \stdClass() : $body, JSON_THROW_ON_ERROR);
        }

        $response = $this->http->send($method, $this->baseUrl . $path, $headers, $payload);
        $data = $this->decode($response);

        if ($response->statusCode >= 400) {
            throw $this->createException($response, $data);
        }

        return $data;
    }

    private function decode(Response $response): array
    {
        if (trim($response->body) === '') {
            return [];
        }

        try {
            $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ApiException(
                sprintf('Ungültige JSON-Antwort der API (HTTP %d).', $response->statusCode),
                $response->statusCode,
                $e,
            );
        }

        return is_array($data) ? $data : ['data' => $data];
    }

    private function createException(Response $response, array $data): ApiException
    {
        $message = isset($data['error']) && is_string($data['error'])
            ? $data['error']
            : sprintf('API-Anfrage fehlgeschlagen (HTTP %d).', $response->statusCode);

        return match ($response->statusCode) {
            401 => new AuthenticationException($message, 401),
            402 => new InsufficientCoinsException($message, 402),
            403 => new ForbiddenException($message, 403),
            404 => new NotFoundException($message, 404),
            409 => new ConflictException($message, 409),
            422 => new ValidationException($message, 422),
            423 => new LockedException($message, 423),
            429 => new RateLimitException($message, 429, $this->retryAfter($response)),
            502, 503 => new ServiceUnavailableException($message, $response->statusCode),
            default => new ApiException($message, $response->statusCode),
        };
    }

    private function retryAfter(Response $response): ?int
    {
        $value = $response->header('Retry-After');

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    private function serverPath(int|string $id, string $suffix = ''): string
    {
        return '/servers/' . $this->encodeId($id) . $suffix;
    }

    private function backupPath(int|string $id, string $uuid, string $suffix = ''): string
    {
        return $this->serverPath($id, '/backups/' . $this->encodeId($uuid) . $suffix);
    }

    private function filter(array $values): array
    {
        return array_filter($values, static fn ($value) => $value !== null);
    }

    private function encodeId(int|string $id): string
    {
        $id = trim((string) $id);
        if ($id === '') {
            throw new \InvalidArgumentException('Die ID darf nicht leer sein.');
        }

        return rawurlencode($id);
    }
}
