<?php

namespace App\Enums;

enum DestinationHealthStatus: string
{
    case Unchecked = 'unchecked';
    case Reachable = 'reachable';
    case HttpFailure = 'http_failure';
    case DnsFailure = 'dns_failure';
    case TlsFailure = 'tls_failure';
    case ConnectionFailure = 'connection_failure';
    case Timeout = 'timeout';
    case Inconclusive = 'inconclusive';
    case Blocked = 'blocked';

    public static function failures(): array
    {
        return [self::HttpFailure, self::DnsFailure, self::TlsFailure, self::ConnectionFailure, self::Timeout];
    }

    public function isFailure(): bool
    {
        return in_array($this, self::failures(), true);
    }
}
