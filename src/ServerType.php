<?php

declare(strict_types=1);

namespace MultiplayerGratis;

enum ServerType: string
{
    case Gameserver = 'gameserver';
    case Runtime = 'runtime';
    case Software = 'software';
    case Webspace = 'webspace';

    public static function fromName(self|string $type): self
    {
        return $type instanceof self
            ? $type
            : self::tryFromName($type) ?? throw new \ValueError(sprintf('Unbekannte Server-Art "%s".', $type));
    }

    public static function tryFromName(string $type): ?self
    {
        $type = strtolower(trim($type));

        return self::tryFrom($type) ?? self::tryFrom(rtrim($type, 's'));
    }

    public function pathName(): string
    {
        return $this->value . 's';
    }
}
