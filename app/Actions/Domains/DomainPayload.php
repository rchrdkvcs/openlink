<?php

namespace App\Actions\Domains;

use App\Models\Domain;
use App\Services\Dns\DomainDnsTarget;

class DomainPayload
{
    public function __construct(
        private readonly VerifyDomain $verifier,
        private readonly DomainDnsTarget $target,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Domain $domain): array
    {
        return [
            'id' => $domain->id,
            'hostname' => $domain->hostname,
            'status' => $domain->status,
            'is_default' => $domain->is_default,
            'workspace_id' => $domain->workspace_id,
            'expected_txt_name' => $this->verifier->expectedTxtName($domain),
            'expected_txt' => $this->verifier->expectedTxtValue($domain),
            'failure_reason' => $domain->failure_reason,
            'ownership_verified' => $domain->isOwnershipVerified(),
            'dns_pointed' => $domain->status === Domain::STATUS_ACTIVE || $domain->dns_pointed_at !== null,
            'dns_check_error' => $domain->dns_check_error,
            'dns_record' => [
                'type' => $this->target->recordType(),
                'value' => $this->target->value(),
            ],
        ];
    }
}
