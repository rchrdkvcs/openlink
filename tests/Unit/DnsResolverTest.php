<?php

namespace App\Services\Dns {
    class DnsResolverTestRecords
    {
        /** @var array<string, array<int, array<int, array<string, mixed>>>> */
        public static array $records = [];
    }

    function dns_get_record(string $hostname, int $type): array
    {
        return DnsResolverTestRecords::$records[$hostname][$type] ?? [];
    }
}

namespace Tests\Unit {
    use App\Services\Dns\DnsResolver;
    use App\Services\Dns\DnsResolverTestRecords;
    use PHPUnit\Framework\TestCase;

    class DnsResolverTest extends TestCase
    {
        protected function tearDown(): void
        {
            DnsResolverTestRecords::$records = [];

            parent::tearDown();
        }

        public function test_txt_values_include_split_record_entries(): void
        {
            DnsResolverTestRecords::$records = [
                'go.example.test' => [
                    DNS_TXT => [
                        ['entries' => ['openlink-verification=', 'test-token']],
                    ],
                ],
            ];

            $this->assertSame(
                ['openlink-verification=test-token'],
                (new DnsResolver)->txtValues('go.example.test')
            );
        }
    }
}
