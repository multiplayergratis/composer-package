<?php

declare(strict_types=1);

namespace MultiplayerGratis\Exception;

final class RateLimitException extends ApiException
{
    public function __construct(
        string $message = '',
        int $code = 429,
        private readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
