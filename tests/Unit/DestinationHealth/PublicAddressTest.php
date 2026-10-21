<?php

namespace Tests\Unit\DestinationHealth;

use App\Services\DestinationHealth\PublicAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PublicAddressTest extends TestCase
{
    public static function publicAddresses(): array
    {
        return [
            ['93.184.216.34'],
            ['8.8.8.8'],
            ['2606:4700:4700::1111'],
            ['2a00:1450:4007:80c::200e'],
        ];
    }

    public static function nonPublicAddresses(): array
    {
        return [
            'IPv4 loopback' => ['127.0.0.1'],
            'IPv4 loopback range' => ['127.45.0.9'],
            'unspecified IPv4' => ['0.0.0.0'],
            'this network' => ['0.1.2.3'],
            'private 10/8' => ['10.0.0.4'],
            'private 172.16/12' => ['172.20.1.1'],
            'private 192.168/16' => ['192.168.1.10'],
            'carrier-grade NAT' => ['100.64.0.1'],
            'Alibaba metadata' => ['100.100.100.200'],
            'link-local metadata' => ['169.254.169.254'],
            'Azure wireserver' => ['168.63.129.16'],
            'IETF protocol assignments' => ['192.0.0.8'],
            'documentation' => ['192.0.2.10'],
            'benchmarking' => ['198.18.0.1'],
            'multicast' => ['224.0.0.1'],
            'reserved' => ['240.0.0.1'],
            'broadcast' => ['255.255.255.255'],
            'IPv6 loopback' => ['::1'],
            'unspecified IPv6' => ['::'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv6 unique local' => ['fd00:ec2::254'],
            'IPv6 multicast' => ['ff02::1'],
            'IPv6 documentation' => ['2001:db8::1'],
            'IPv4-mapped loopback' => ['::ffff:127.0.0.1'],
            'IPv4-mapped loopback hex' => ['::ffff:7f00:1'],
            'IPv4-mapped metadata' => ['::ffff:169.254.169.254'],
            'IPv4-compatible loopback' => ['::127.0.0.1'],
            'NAT64 private' => ['64:ff9b::10.0.0.1'],
            '6to4 loopback' => ['2002:7f00:1::'],
            'not an address' => ['example.com'],
        ];
    }

    #[DataProvider('publicAddresses')]
    public function test_public_addresses_are_allowed(string $address): void
    {
        $this->assertTrue((new PublicAddress)->isPublic($address));
    }

    #[DataProvider('nonPublicAddresses')]
    public function test_loopback_private_reserved_link_local_and_metadata_addresses_are_rejected(string $address): void
    {
        $this->assertFalse((new PublicAddress)->isPublic($address));
    }

    public function test_ip_literals_are_recognised_with_brackets(): void
    {
        $addresses = new PublicAddress;

        $this->assertSame('::1', $addresses->literal('[::1]'));
        $this->assertSame('93.184.216.34', $addresses->literal('93.184.216.34'));
        $this->assertNull($addresses->literal('example.com'));
    }

    public function test_alternate_numeric_host_forms_are_detected(): void
    {
        $addresses = new PublicAddress;

        foreach (['2130706433', '0x7f000001', '0177.0.0.1', '127.1', '0x7f.0.0.1', '127.0.0.1.'] as $host) {
            $this->assertTrue($addresses->isAmbiguousNumericHost($host), $host);
        }

        foreach (['example.com', '123.example.com', '93.184.216.34', 'cafe.be'] as $host) {
            $this->assertFalse($addresses->isAmbiguousNumericHost($host), $host);
        }
    }
}
