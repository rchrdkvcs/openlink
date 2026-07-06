<?php

namespace App\Actions\Domains;

use App\Models\Domain;

class DomainPayload
{
    public function __construct(private readonly VerifyDomain $verifier) {}

    /** @return array<string, mixed> */
    public function handle(Domain $domain): array
    {
        return [
            'id' => $domain->id,
            'hostname' => $domain->hostname,
            'status' => $domain->status,
            'is_default' => $domain->is_default,
            'workspace_id' => $domain->workspace_id,
            'expected_txt' => $this->verifier->expectedTxtValue($domain),
            'failure_reason' => $domain->failure_reason,
        ];
    }
}
