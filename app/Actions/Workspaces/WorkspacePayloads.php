<?php

namespace App\Actions\Workspaces;

use App\Actions\Domains\VerifyDomain;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class WorkspacePayloads
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly VerifyDomain $domainVerifier,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function links(Workspace $workspace, User $user): Collection
    {
        $isManager = $this->access->canManageWorkspace($user, $workspace);
        $accessibleFolderIds = $isManager ? collect() : $this->folders($workspace, $user)->pluck('id');

        return $workspace->shortLinks()
            ->with(['domain', 'folder', 'tags', 'qrCodes'])
            ->withCount($this->analyticsCounts())
            ->when(! $isManager, fn ($query) => $query->where(fn ($query) => $query->whereNull('folder_id')->orWhereIn('folder_id', $accessibleFolderIds)))
            ->latest()
            ->get()
            ->map(fn (ShortLink $link) => $this->linkPayload($link));
    }

    /** @return array<string, \Closure> */
    private function analyticsCounts(): array
    {
        return [
            'analyticsEvents as visits_count' => fn ($query) => $query->successful()->where('metric', 'visit'),
            'analyticsEvents as scans_count' => fn ($query) => $query->successful()->where('metric', 'scan'),
        ];
    }

    /** @return array<string, mixed> */
    public function linkPayload(ShortLink $link): array
    {
        $link->loadMissing(['domain', 'folder', 'tags', 'qrCodes']);

        if (! isset($link->visits_count) || ! isset($link->scans_count)) {
            $link->loadCount($this->analyticsCounts());
        }

        return [
            'id' => $link->id,
            'slug' => $link->slug,
            'short_url' => 'https://'.$link->domain->hostname.'/'.$link->slug,
            'destination_url' => $link->destination_url,
            'fallback_url' => $link->fallback_url,
            'status' => $this->status($link),
            'domain' => $link->domain?->only(['id', 'hostname', 'status', 'is_default']),
            'folder' => $link->folder?->only(['id', 'name']),
            'tags' => $link->tags->map->only(['id', 'name'])->values(),
            'qr_codes' => $link->qrCodes->map->only(['id', 'name', 'token'])->values(),
            'visits' => (int) $link->visits_count,
            'scans' => (int) $link->scans_count,
            'is_enabled' => $link->is_enabled,
            'archived_at' => $link->archived_at,
            'activates_at' => $link->activates_at,
            'expires_at' => $link->expires_at,
            'visit_limit' => $link->visit_limit,
            'successful_visits' => $link->successful_visits,
            'has_password' => $link->hasPassword(),
        ];
    }

    public function status(ShortLink $link): string
    {
        if ($link->isArchived()) {
            return 'archived';
        }

        if (! $link->is_enabled) {
            return 'disabled';
        }

        if ($link->activates_at?->isFuture()) {
            return 'scheduled';
        }

        if ($link->expires_at?->isPast() || ($link->visit_limit !== null && $link->successful_visits >= $link->visit_limit)) {
            return 'expired';
        }

        return 'active';
    }

    /** @return Collection<int, Folder> */
    public function folders(Workspace $workspace, User $user): Collection
    {
        $isManager = $this->access->canManageWorkspace($user, $workspace);

        return $workspace->folders()
            ->when(! $isManager, fn ($query) => $query->whereHas('permissions', fn ($query) => $query->where('user_id', $user->id)))
            ->with('permissions.user:id,name,email')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function domains(Workspace $workspace): Collection
    {
        return $workspace->domains()
            ->orderBy('hostname')
            ->get()
            ->prepend($this->defaultDomain())
            ->filter()
            ->values()
            ->map(fn (Domain $domain) => [
                'id' => $domain->id,
                'hostname' => $domain->hostname,
                'status' => $domain->status,
                'is_default' => $domain->is_default,
                'expected_txt' => $this->domainVerifier->expectedTxtValue($domain),
                'failure_reason' => $domain->failure_reason,
            ]);
    }

    public function defaultDomain(): ?Domain
    {
        return Domain::query()->where('is_default', true)->first();
    }
}
