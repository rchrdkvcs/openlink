<?php

namespace App\Services\Dns;

class DnsResolver
{
    /** @return array<int, string> */
    public function txtValues(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_TXT) ?: [];

        return collect($records)
            ->flatMap(function (array $record): array {
                $values = [];

                if (($record['txt'] ?? '') !== '') {
                    $values[] = $record['txt'];
                }

                if (isset($record['entries']) && is_array($record['entries'])) {
                    $values[] = implode('', array_map('strval', $record['entries']));
                }

                return $values;
            })
            ->map(fn (string $value): string => trim($value, " \n\r\t\v\0\"'"))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function ipAddresses(string $hostname): array
    {
        $ips = [];

        foreach (@dns_get_record($hostname, DNS_A) ?: [] as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }
        }

        foreach (@dns_get_record($hostname, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = strtolower($record['ipv6']);
            }
        }

        return array_values(array_unique($ips));
    }
}
