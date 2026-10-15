<?php

declare(strict_types=1);

namespace MultiplayerGratis\Http;

interface HttpClientInterface
{
    public function send(string $method, string $url, array $headers = [], ?string $body = null): Response;
}
