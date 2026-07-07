<?php

namespace App\Actions\Workspaces;

use App\Actions\Domains\DomainPayload;
use App\Actions\QrCodes\QrCodePayload;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\InviteLink;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class WorkspacePayloads
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly DomainPayload $domainPayload,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function links(Workspace $workspace, User $user): Collection
    {
        $isManager = $this->access->canManageWorkspace($user, $workspace);
        $accessibleFolderIds = $isManager ? collect() : $this->folders($workspace, $user)->pluck('id');

        return $workspace->shortLinks()
            ->with(['domain', 'folder', 'tags', 'routingRules.variants', 'qrCodes' => fn ($query) => $query->withCount([
                'analyticsEvents as scans_count' => fn ($events) => $events->successful()->where('metric', 'scan'),
            ])])
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
        $link->loadMissing(['domain', 'folder', 'tags', 'routingRules.variants', 'qrCodes']);

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
            'qr_codes' => $link->qrCodes->map(fn ($qrCode) => QrCodePayload::make($qrCode->setRelation('shortLink', $link)))->values(),
            'visits' => (int) $link->visits_count,
            'scans' => (int) $link->scans_count,
            'is_enabled' => $link->is_enabled,
            'archived_at' => $link->archived_at,
            'activates_at' => $link->activates_at,
            'expires_at' => $link->expires_at,
            'visit_limit' => $link->visit_limit,
            'successful_visits' => $link->successful_visits,
            'has_password' => $link->hasPassword(),
            'routing_rules' => $link->routingRules->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'type' => $rule->type,
                'position' => $rule->position,
                'is_enabled' => $rule->is_enabled,
                'match_mode' => $rule->match_mode,
                'conditions' => $rule->conditions ?? [],
                'destination_url' => $rule->destination_url,
                'variants' => $rule->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'position' => $variant->position,
                    'is_enabled' => $variant->is_enabled,
                    'destination_url' => $variant->destination_url,
                    'weight' => $variant->weight,
                ])->values(),
            ])->values(),
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
            ->map(fn (Domain $domain) => $this->domainPayload->handle($domain));
    }

    public function defaultDomain(): ?Domain
    {
        return Domain::query()->where('is_default', true)->first();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function inviteLinks(Workspace $workspace): Collection
    {
        return $workspace->inviteLinks()
            ->whereNull('revoked_at')
            ->latest()
            ->get()
            ->map(fn (InviteLink $link) => $this->inviteLinkPayload($link));
    }

    /** @return array<string, mixed> */
    public function inviteLinkPayload(InviteLink $link): array
    {
        return [
            'id' => $link->id,
            'role' => $link->role,
            'token' => $link->token,
            'url' => $link->url(),
            'expires_at' => $link->expires_at,
            'max_uses' => $link->max_uses,
            'uses' => $link->uses,
            'is_usable' => $link->isUsable(),
            'created_at' => $link->created_at,
        ];
    }
}
