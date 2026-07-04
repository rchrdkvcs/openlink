<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsTotal;
use App\Models\AnalyticsDailyAggregate;
use App\Models\Domain;
use App\Services\DomainVerificationService;
use App\Services\InstanceSettings;
use App\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function overview(Request $request, WorkspaceContext $context, InstanceSettings $settings, DomainVerificationService $domainVerifier): Response
    {
        return Inertia::render('Dashboard', $this->pageProps($request, $context, $settings, $domainVerifier));
    }

    public function links(Request $request, WorkspaceContext $context, InstanceSettings $settings, DomainVerificationService $domainVerifier): Response
    {
        return Inertia::render('Links/Index', $this->pageProps($request, $context, $settings, $domainVerifier));
    }

    public function domains(Request $request, WorkspaceContext $context, InstanceSettings $settings, DomainVerificationService $domainVerifier): Response
    {
        return Inertia::render('Domains/Index', $this->pageProps($request, $context, $settings, $domainVerifier));
    }

    public function members(Request $request, WorkspaceContext $context, InstanceSettings $settings, DomainVerificationService $domainVerifier): Response
    {
        return Inertia::render('Members/Index', $this->pageProps($request, $context, $settings, $domainVerifier));
    }

    public function workspaces(Request $request, WorkspaceContext $context, InstanceSettings $settings, DomainVerificationService $domainVerifier): Response
    {
        return Inertia::render('Workspaces/Index', $this->pageProps($request, $context, $settings, $domainVerifier));
    }

    public function settings(Request $request, WorkspaceContext $context, InstanceSettings $settings, DomainVerificationService $domainVerifier): Response
    {
        return Inertia::render('Settings/Index', $this->pageProps($request, $context, $settings, $domainVerifier));
    }

    private function pageProps(Request $request, WorkspaceContext $context, InstanceSettings $settings, DomainVerificationService $domainVerifier): array
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        $user = $request->user();
        $workspaces = $user->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name', 'workspaces.slug']);
        $role = $context->role($user, $workspace);

        $domains = $workspace->domains()
            ->orderBy('hostname')
            ->get()
            ->prepend($this->defaultDomain());

        $isManager = $context->canManageWorkspace($user, $workspace);
        $folders = $workspace->folders()
            ->when(! $isManager, fn ($query) => $query->whereHas('permissions', fn ($query) => $query->where('user_id', $user->id)))
            ->with('permissions.user:id,name,email')
            ->orderBy('name')
            ->get();
        $accessibleFolderIds = $folders->pluck('id');

        $links = $workspace->shortLinks()
            ->with(['domain', 'folder', 'tags', 'qrCodes'])
            ->primary()
            ->when(! $isManager, fn ($query) => $query->where(fn ($query) => $query->whereNull('folder_id')->orWhereIn('folder_id', $accessibleFolderIds)))
            ->latest()
            ->get()
            ->map(function ($link) {
                $totals = AnalyticsTotal::query()
                    ->where('short_link_id', $link->id)
                    ->where('outcome', 'success')
                    ->pluck('count', 'metric');

                return [
                    'id' => $link->id,
                    'slug' => $link->slug,
                    'short_url' => 'http://'.$link->domain->hostname.'/'.$link->slug,
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
            });

        $analytics = [
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

        return [
            'currentWorkspace' => $workspace->only(['id', 'name', 'slug', 'preferred_domain_id']),
            'workspaces' => $workspaces,
            'role' => $role,
            'canManageWorkspace' => $context->canManageWorkspace($user, $workspace),
            'canEditWorkspace' => $context->canEditWorkspace($user, $workspace),
            'domains' => $domains->filter()->values()->map(function ($domain) use ($domainVerifier) {
                if (is_array($domain)) {
                    return $domain;
                }

                return [
                    'id' => $domain->id,
                    'hostname' => $domain->hostname,
                    'status' => $domain->status,
                    'is_default' => $domain->is_default,
                    'expected_txt' => $domainVerifier->expectedTxtValue($domain),
                    'failure_reason' => $domain->failure_reason,
                ];
            }),
            'folders' => $folders,
            'members' => $workspace->members()->with('user:id,name,email')->orderBy('role')->get(),
            'invitations' => $workspace->invitations()->latest()->get(),
            'tags' => $workspace->tags()->orderBy('name')->get(),
            'links' => $links,
            'analytics' => $analytics,
            'settings' => $user->is_instance_admin ? $settings->all() : [],
        ];
    }

    private function defaultDomain(): ?Domain
    {
        return Domain::query()->where('is_default', true)->first();
    }

    private function status(\App\Models\ShortLink $link): string
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
}
