<?php

namespace App\Services;

use App\Models\AnalyticsDailyAggregate;
use App\Models\AnalyticsTotal;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Builds the workspace-scoped payloads shared by the Inertia dashboard
 * pages and the JSON API, so both surfaces expose the same data.
 */
class WorkspaceData
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly DomainVerificationService $domainVerifier,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function links(Workspace $workspace, User $user): Collection
    {
        $isManager = $this->context->canManageWorkspace($user, $workspace);
        $accessibleFolderIds = $isManager ? collect() : $this->folders($workspace, $user)->pluck('id');

        return $workspace->shortLinks()
            ->with(['domain', 'folder', 'tags', 'qrCodes'])
            ->when(! $isManager, fn ($query) => $query->where(fn ($query) => $query->whereNull('folder_id')->orWhereIn('folder_id', $accessibleFolderIds)))
            ->latest()
            ->get()
            ->map(fn (ShortLink $link) => $this->linkPayload($link));
    }

    /** @return array<string, mixed> */
    public function linkPayload(ShortLink $link): array
    {
        $link->loadMissing(['domain', 'folder', 'tags', 'qrCodes']);

        $totals = AnalyticsTotal::query()
            ->where('short_link_id', $link->id)
            ->where('outcome', 'success')
            ->pluck('count', 'metric');

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
            'visits' => (int) ($totals['visit'] ?? 0),
            'scans' => (int) ($totals['scan'] ?? 0),
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
        $isManager = $this->context->canManageWorkspace($user, $workspace);

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

    /** @return array<string, mixed> */
    public function analytics(Workspace $workspace): array
    {
        return [
            'daily' => AnalyticsDailyAggregate::query()
                ->where('workspace_id', $workspace->id)
                ->where('date', '>=', now()->subDays(30)->toDateString())
                ->selectRaw('date, metric, outcome, sum(count) as count')
                ->groupBy('date', 'metric', 'outcome')
                ->orderBy('date')
                ->get(),
            'outcomes' => AnalyticsTotal::query()
                ->where('workspace_id', $workspace->id)
                ->selectRaw('metric, outcome, sum(count) as count')
                ->groupBy('metric', 'outcome')
                ->orderByDesc('count')
                ->get(),
            'devices' => AnalyticsDailyAggregate::query()
                ->where('workspace_id', $workspace->id)
                ->selectRaw('device_type, sum(count) as count')
                ->groupBy('device_type')
                ->orderByDesc('count')
                ->get(),
            'countries' => AnalyticsDailyAggregate::query()
                ->where('workspace_id', $workspace->id)
                ->whereNotNull('country')
                ->selectRaw('country, sum(count) as count')
                ->groupBy('country')
                ->orderByDesc('count')
                ->limit(8)
                ->get(),
            'browsers' => AnalyticsDailyAggregate::query()
                ->where('workspace_id', $workspace->id)
                ->selectRaw('browser, sum(count) as count')
                ->groupBy('browser')
                ->orderByDesc('count')
                ->limit(8)
                ->get(),
            'operatingSystems' => AnalyticsDailyAggregate::query()
                ->where('workspace_id', $workspace->id)
                ->selectRaw('os, sum(count) as count')
                ->groupBy('os')
                ->orderByDesc('count')
                ->limit(8)
                ->get(),
            'referrers' => AnalyticsDailyAggregate::query()
                ->where('workspace_id', $workspace->id)
                ->whereNotNull('referrer_host')
                ->selectRaw('referrer_host, sum(count) as count')
                ->groupBy('referrer_host')
                ->orderByDesc('count')
                ->limit(8)
                ->get(),
        ];
    }
}
