<?php

namespace App\Services\Webhooks;

class PublicAddress
{
    private const BLOCKED_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
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
        '::/96',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/23',
        '2001:db8::/32',
        '2002::/16',
        '3fff::/20',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
        'ff00::/8',
    ];

    public function isPublic(string $address): bool
    {
        $packed = @inet_pton(trim($address, '[]'));

        if ($packed === false) {
            return false;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if ($this->inRange($packed, $range)) {
                return false;
            }
        }

        return true;
    }

    private function inRange(string $packed, string $range): bool
    {
        [$network, $bits] = explode('/', $range);
        $networkPacked = inet_pton($network);

        if (strlen($networkPacked) !== strlen($packed)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);

        if (substr($packed, 0, $bytes) !== substr($networkPacked, 0, $bytes)) {
            return false;
        }

        $remainder = $bits % 8;

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($networkPacked[$bytes]) & $mask);
    }
}
