<?php

declare(strict_types=1);

namespace MultiplayerGratis\Exception;

class ApiException extends \RuntimeException
{
    public function getStatusCode(): int
    {
        return $this->getCode();
    }
}
