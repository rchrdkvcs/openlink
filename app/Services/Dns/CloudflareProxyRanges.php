<?php

namespace App\Services\Dns;

class CloudflareProxyRanges
{
    private const CIDRS = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    public function coverAll(array $ips): bool
    {
        return $ips !== [] && collect($ips)->every(fn (string $ip): bool => $this->covers($ip));
    }

    private function covers(string $ip): bool
    {
        foreach (self::CIDRS as $cidr) {
            if ($this->matches($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);

        $ipBytes = @inet_pton($ip);
        $networkBytes = @inet_pton($network);

        if ($ipBytes === false || $networkBytes === false || strlen($ipBytes) !== strlen($networkBytes)) {
            return false;
        }

        $prefix = (int) $prefix;
        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainingBits)) & 0xFF);

        return ($ipBytes[$fullBytes] & $mask) === ($networkBytes[$fullBytes] & $mask);
    }
}
