<?php

declare(strict_types=1);

namespace MultiplayerGratis;

use MultiplayerGratis\Exception\ApiException;
use MultiplayerGratis\Exception\AuthenticationException;
use MultiplayerGratis\Exception\ConflictException;
use MultiplayerGratis\Exception\LockedException;
use MultiplayerGratis\Exception\NotFoundException;
use MultiplayerGratis\Exception\RateLimitException;
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

    public function getServers(): array
    {
        return $this->request('GET', '/servers');
    }

    public function getServer(int|string $id): array
    {
        return $this->request('GET', '/servers/' . $this->encodeId($id));
    }

    public function getServerResources(int|string $id): array
    {
        return $this->request('GET', '/servers/' . $this->encodeId($id) . '/resources');
    }

    public function sendPowerSignal(int|string $id, PowerSignal|string $signal): array
    {
        $signal = $signal instanceof PowerSignal ? $signal : PowerSignal::from($signal);

        return $this->request('POST', '/servers/' . $this->encodeId($id) . '/power', [
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
            $payload = json_encode($body, JSON_THROW_ON_ERROR);
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
            404 => new NotFoundException($message, 404),
            409 => new ConflictException($message, 409),
            423 => new LockedException($message, 423),
            429 => new RateLimitException($message, 429, $this->retryAfter($response)),
            default => new ApiException($message, $response->statusCode),
        };
    }

    private function retryAfter(Response $response): ?int
    {
        $value = $response->header('Retry-After');

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    private function encodeId(int|string $id): string
    {
        $id = (string) $id;
        if ($id === '') {
            throw new \InvalidArgumentException('Die Server-ID darf nicht leer sein.');
        }

        return rawurlencode($id);
    }
}
