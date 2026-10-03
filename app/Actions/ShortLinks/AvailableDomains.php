<?php

namespace App\Actions\ShortLinks;

use App\Models\Domain;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

class AvailableDomains
{
    public function instanceDefault(): ?Domain
    {
        return Domain::query()->where('is_default', true)->first();
    }

    public function fallbackFor(Workspace $workspace): ?Domain
    {
        return $workspace->preferredDomain ?? $this->instanceDefault();
    }

    public function find(Workspace $workspace, int $domainId): ?Domain
    {
        return Domain::query()
            ->whereKey($domainId)
            ->where(fn ($query) => $query->where('workspace_id', $workspace->id)->orWhere('is_default', true))
            ->first();
    }

    public function requireUsable(Workspace $workspace, ?int $domainId): Domain
    {
        $domainId = $domainId ?: $this->fallbackFor($workspace)?->id;

        if (! $domainId) {
            $this->fail('No domain available for this workspace.');
        }

        $domain = $this->find($workspace, $domainId);

        if ($domain === null) {
            $this->fail('Domain does not belong to this workspace.');
        }

        if (! $domain->isUsable()) {
            $this->fail('Domain is not active or is disabled.');
        }

        return $domain;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['domain_id' => $message]);
    }
}
