<?php

namespace App\Actions\Domains;

use App\Actions\ShortLinks\AvailableDomains;
use App\Enums\DomainEdgeMode;
use App\Enums\DomainStatus;
use App\Enums\EdgeStatus;
use App\Models\Domain;
use App\Models\Workspace;
use App\Services\Dns\DomainDnsTarget;
use Illuminate\Support\Collection;

class DomainPayload
{
    public function __construct(
        private readonly DomainDnsTarget $target,
        private readonly AvailableDomains $domains,
        private readonly DomainEdgeMode $edge,
    ) {}

    public function forWorkspace(Workspace $workspace): Collection
    {
        return $workspace->domains()
            ->orderBy('hostname')
            ->get()
            ->prepend($this->domains->instanceDefault())
            ->filter()
            ->values()
            ->map(fn (Domain $domain) => $this->handle($domain));
    }

    public function handle(Domain $domain): array
    {
        return [
            'id' => $domain->id,
            'hostname' => $domain->hostname,
            'status' => $domain->status->value,
            'is_default' => $domain->is_default,
            'workspace_id' => $domain->workspace_id,
            'expected_txt_name' => $domain->verificationTxtName(),
            'expected_txt' => $domain->verificationTxtValue(),
            'failure_reason' => $domain->failure_reason,
            'ownership_verified' => $domain->isOwnershipVerified(),
            'dns_pointed' => $domain->isPointed(),
            'dns_check_error' => $domain->dns_check_error,
            'edge_status' => $domain->edge_status?->value,
            'needs_dns_update' => $this->needsDnsUpdate($domain),
            'dns_record' => [
                'type' => $this->target->recordType(),
                'value' => $this->target->value(),
            ],
        ];
    }

    private function needsDnsUpdate(Domain $domain): bool
    {
        return $this->edge === DomainEdgeMode::Cloudflare
            && ! $domain->is_default
            && $domain->status === DomainStatus::Active
            && $domain->disabled_at === null
            && $domain->edge_status !== EdgeStatus::Connected;
    }
}
