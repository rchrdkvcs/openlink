<?php

namespace App\Services\DestinationHealth;

class PublicAddress
{
    private const BLOCKED_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '168.63.129.16/32',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/23',
        '2001:db8::/32',
        '3fff::/20',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
        'ff00::/8',
    ];

    public function isPublic(string $address): bool
    {
        $address = $this->literal($address);

        if ($address === null) {
            return false;
        }

        $embedded = $this->embeddedIpv4($address);

        if ($embedded !== null) {
            return $this->isPublic($embedded);
        }

        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
            return false;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if ($this->inRange($address, $range)) {
                return false;
            }
        }

        return true;
    }

    public function literal(string $host): ?string
    {
        $host = trim($host, '[]');

        return filter_var($host, FILTER_VALIDATE_IP) ? strtolower($host) : null;
    }

    public function isAmbiguousNumericHost(string $host): bool
    {
        if ($this->literal($host) !== null) {
            return false;
        }

        $labels = explode('.', rtrim(strtolower($host), '.'));

        foreach ($labels as $label) {
            if (! preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', $label)) {
                return false;
            }
        }

        return true;
    }

    private function embeddedIpv4(string $address): ?string
    {
        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return null;
        }

        $bytes = inet_pton($address);
        $prefix = substr($bytes, 0, 12);

        $embedded = match (true) {
            $prefix === str_repeat("\0", 10)."\xff\xff" => substr($bytes, 12, 4),
            $prefix === str_repeat("\0", 12) && substr($bytes, 12, 4) !== "\0\0\0\0" && substr($bytes, 12, 4) !== "\0\0\0\1" => substr($bytes, 12, 4),
            $prefix === "\0\x64\xff\x9b".str_repeat("\0", 8) => substr($bytes, 12, 4),
            substr($bytes, 0, 2) === "\x20\x02" => substr($bytes, 2, 4),
            default => null,
        };

        return $embedded === null ? null : inet_ntop($embedded);
    }

    private function inRange(string $address, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);
        $address = inet_pton($address);
        $subnet = inet_pton($subnet);

        if ($address === false || $subnet === false || strlen($address) !== strlen($subnet)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);

        if (substr($address, 0, $bytes) !== substr($subnet, 0, $bytes)) {
            return false;
        }

        $remainder = $bits % 8;

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($address[$bytes]) & $mask) === (ord($subnet[$bytes]) & $mask);
    }
}
