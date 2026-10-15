<?php

declare(strict_types=1);

namespace MultiplayerGratis\Http;

final class Response
{
    private readonly array $headers;

    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        array $headers = [],
    ) {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
