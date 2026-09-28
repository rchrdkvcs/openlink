<?php

namespace App\Actions\Workspaces;

use App\Actions\Domains\DomainPayload;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\InviteLink;
use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\ShortLinks\ShortLinkLifecycle;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WorkspacePayloads
{
    public function __construct(
        private readonly DomainPayload $domainPayload,
        private readonly ShortLinkLifecycle $lifecycle,
    ) {}

    /** @param array{search?: string, status?: string, tag?: string, page?: int} $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function linksPage(WorkspaceView $view, array $filters = []): LengthAwarePaginator
    {
        $query = $view->workspace->shortLinks()
            ->with(['domain', 'folder', 'tags', 'routingRules.variants'])
            ->withCount('qrCodes')
            ->withCount($this->analyticsCounts());

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->whereRaw('LOWER(slug) LIKE LOWER(?)', [$term])
                    ->orWhereRaw('LOWER(destination_url) LIKE LOWER(?)', [$term])
                    ->orWhereHas('domain', fn ($domain) => $domain
                        ->whereRaw("LOWER('https://' || domains.hostname || '/' || short_links.slug) LIKE LOWER(?)", [$term]));
            });
        }

        $tag = $filters['tag'] ?? null;
        if ($tag !== null && $tag !== '') {
            $query->whereHas('tags', fn ($tags) => $tags->where('name', $tag));
        }

        $status = $filters['status'] ?? '';
        if ($status === '') {
            $query->whereNull('archived_at');
        } elseif ($status !== 'all') {
            $this->filterStatus($query, $status);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(50, ['*'], 'page', $filters['page'] ?? 1)
            ->through(fn (ShortLink $link) => $this->linkPayload($link));
    }

    /** @return array{total: int, active: int} */
    public function linkCounts(Workspace $workspace): array
    {
        $query = $workspace->shortLinks();
        $total = (clone $query)->count();
        $activeQuery = clone $query;
        $this->filterStatus($activeQuery, 'active');

        return ['total' => $total, 'active' => $activeQuery->count()];
    }

    private function filterStatus(HasMany $query, string $status): void
    {
        // Keep the SQL precedence aligned with ShortLinkLifecycle::status().
        $now = now()->toDateTimeString();
        $expression = "CASE WHEN archived_at IS NOT NULL THEN 'archived' "
            ."WHEN is_enabled = false THEN 'disabled' "
            ."WHEN activates_at IS NOT NULL AND activates_at > ? THEN 'scheduled' "
            .'WHEN (expires_at IS NOT NULL AND expires_at < ?) '
            ."OR (visit_limit IS NOT NULL AND successful_visits >= visit_limit) THEN 'expired' "
            ."ELSE 'active' END";
        $query->whereRaw("{$expression} = ?", [$now, $now, $status]);
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
        $link->loadMissing(['domain', 'folder', 'tags', 'routingRules.variants']);
        if (! isset($link->qr_codes_count)) {
            $link->loadCount('qrCodes');
        }

        if (! isset($link->visits_count) || ! isset($link->scans_count)) {
            $link->loadCount($this->analyticsCounts());
        }

        return [
            'id' => $link->id,
            'slug' => $link->slug,
            'short_url' => 'https://'.$link->domain->hostname.'/'.$link->slug,
            'destination_url' => $link->destination_url,
            'fallback_url' => $link->fallback_url,
            'status' => $this->lifecycle->status($link),
            'domain' => $link->domain?->only(['id', 'hostname', 'status', 'is_default']),
            'folder' => $link->folder?->only(['id', 'name']),
            'tags' => $link->tags->map->only(['id', 'name'])->values(),
            'qr_code_count' => (int) $link->qr_codes_count,
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

    /** @return Collection<int, Folder> */
    public function folders(WorkspaceView $view): Collection
    {
        return $view->folders;
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
