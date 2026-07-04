<?php

namespace App\Services;

use App\Models\Domain;

class DomainVerificationService
{
    public function expectedTxtValue(Domain $domain): string
    {
        return 'openlink-verification='.$domain->verification_token;
    }

    public function verify(Domain $domain): Domain
    {
        $expected = $this->expectedTxtValue($domain);
        $records = @dns_get_record($domain->hostname, DNS_TXT) ?: [];
        $found = collect($records)->contains(function (array $record) use ($expected): bool {
            return ($record['txt'] ?? null) === $expected;
        });

        $domain->forceFill([
            'status' => $found ? Domain::STATUS_VERIFIED : Domain::STATUS_FAILED,
            'verified_at' => $found ? now() : $domain->verified_at,
            'last_checked_at' => now(),
            'failure_reason' => $found ? null : 'Expected DNS TXT record was not found.',
        ])->save();

        return $domain;
    }
}
