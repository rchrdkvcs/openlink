<?php

namespace App\Services\Dns {
    class SystemDnsLookupTestRecords
    {
        public static array $records = [];
    }

    function dns_get_record(string $hostname, int $type): array
    {
        return SystemDnsLookupTestRecords::$records[$hostname][$type] ?? [];
    }
}

namespace Tests\Unit {
    use App\Services\Dns\SystemDnsLookup;
    use App\Services\Dns\SystemDnsLookupTestRecords;
    use PHPUnit\Framework\TestCase;

    class SystemDnsLookupTest extends TestCase
    {
        protected function tearDown(): void
        {
            SystemDnsLookupTestRecords::$records = [];

            parent::tearDown();
        }

        public function test_txt_values_include_split_record_entries(): void
        {
            SystemDnsLookupTestRecords::$records = [
                'go.example.test' => [
                    DNS_TXT => [
                        ['entries' => ['openlink-verification=', 'test-token']],
                    ],
                ],
            ];

            $this->assertSame(
                ['openlink-verification=test-token'],
                (new SystemDnsLookup)->txtValues('go.example.test')
            );
        }

        public function test_ip_addresses_merge_ipv4_and_lowercased_ipv6_records(): void
        {
            SystemDnsLookupTestRecords::$records = [
                'go.example.test' => [
                    DNS_A => [['ip' => '203.0.113.10'], ['ip' => '203.0.113.10']],
                    DNS_AAAA => [['ipv6' => '2001:DB8::1']],
                ],
            ];

            $this->assertSame(
                ['203.0.113.10', '2001:db8::1'],
                (new SystemDnsLookup)->ipAddresses('go.example.test')
            );
        }

        public function test_cname_targets_are_lowercased_without_the_trailing_dot(): void
        {
            SystemDnsLookupTestRecords::$records = [
                'go.example.test' => [
                    DNS_CNAME => [['target' => 'CNAME.Openlink.test.'], ['target' => 'cname.openlink.test']],
                ],
            ];

            $this->assertSame(['cname.openlink.test'], (new SystemDnsLookup)->cnameTargets('go.example.test'));
            $this->assertSame([], (new SystemDnsLookup)->cnameTargets('other.example.test'));
        }
    }
}
