<?php

namespace App\Enums;

use InvalidArgumentException;

enum DomainEdgeMode: string
{
    case Direct = 'direct';
    case Cloudflare = 'cloudflare';

    public static function fromConfig(mixed $value): self
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return self::Direct;
        }

        return self::tryFrom($value) ?? throw new InvalidArgumentException(
            "Unknown OPENLINK_DOMAIN_EDGE value \"{$value}\". Use \"direct\" or \"cloudflare\".",
        );
    }
}
