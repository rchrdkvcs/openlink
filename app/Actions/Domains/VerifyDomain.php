<?php

namespace App\Actions\Domains;

use App\Models\Domain;
use App\Services\Dns\DnsResolver;

class VerifyDomain
{
    public function __construct(private readonly DnsResolver $resolver) {}

    public function expectedTxtValue(Domain $domain): string
    {
        return 'openlink-verification='.$domain->verification_token;
    }

    public function handle(Domain $domain): Domain
    {
        $expected = $this->expectedTxtValue($domain);
        $found = in_array($expected, $this->resolver->txtValues($domain->hostname), true);

        // An active domain already proved ownership and serves traffic; a
        // missing TXT record later (records are often cleaned up) must not
        // demote it.
        $status = match (true) {
            $domain->status === Domain::STATUS_ACTIVE => Domain::STATUS_ACTIVE,
            $found => Domain::STATUS_OWNERSHIP_VERIFIED,
            default => Domain::STATUS_FAILED,
        };

        $domain->forceFill([
            'status' => $status,
            'verified_at' => $found ? ($domain->verified_at ?? now()) : $domain->verified_at,
            'last_checked_at' => now(),
            'failure_reason' => $found || $status === Domain::STATUS_ACTIVE ? null : 'Expected DNS TXT record was not found.',
        ])->save();

        return $domain;
    }
}
