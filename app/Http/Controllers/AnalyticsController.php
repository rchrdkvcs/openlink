<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\AnalyticsReporter;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, WorkspaceContext $context, WorkspaceData $data, AnalyticsReporter $reporter): Response
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        $user = $request->user();
        $filters = AnalyticsFilters::fromRequest($request);
        $accessibleLinkIds = $data->accessibleLinkIds($workspace, $user);

        return Inertia::render('Analytics/Index', [
            'currentWorkspace' => $workspace->only(['id', 'name', 'slug', 'preferred_domain_id']),
            'workspaces' => $user->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name', 'workspaces.slug']),
            'role' => $context->role($user, $workspace),
            'report' => $reporter->report($workspace, $filters, $accessibleLinkIds),
            'filters' => $filters->toQuery() + ['range' => $filters->range],
            'filterOptions' => $this->filterOptions($workspace, $accessibleLinkIds, $data, $user),
        ]);
    }

    public function export(Request $request, WorkspaceContext $context, WorkspaceData $data, AnalyticsReporter $reporter): StreamedResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        $filters = AnalyticsFilters::fromRequest($request);
        $accessibleLinkIds = $data->accessibleLinkIds($workspace, $request->user());

        $query = $reporter->eventsQuery($workspace, $filters, $accessibleLinkIds)
            ->with(['shortLink:id,slug', 'qrCode:id,name', 'domain:id,hostname']);

        $filename = sprintf('openlink-analytics-%s-%s.csv', $workspace->slug, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'occurred_at', 'metric', 'outcome', 'link_slug', 'qr_code', 'domain',
                'referrer_host', 'referrer_channel', 'country', 'language',
                'device_type', 'browser', 'os', 'is_bot',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            ]);

            foreach ($query->lazy(1000) as $event) {
                fputcsv($out, [
                    $event->occurred_at->toIso8601String(),
                    $event->metric,
                    $event->outcome,
                    $event->shortLink?->slug,
                    $event->qrCode?->name,
                    $event->domain?->hostname,
                    $event->referrer_host,
                    $event->referrer_channel,
                    $event->country,
                    $event->language,
                    $event->device_type,
                    $event->browser,
                    $event->os,
                    $event->is_bot ? '1' : '0',
                    $event->utm_source,
                    $event->utm_medium,
                    $event->utm_campaign,
                    $event->utm_term,
                    $event->utm_content,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function filterOptions(Workspace $workspace, ?array $accessibleLinkIds, WorkspaceData $data, $user): array
    {
        $links = $workspace->shortLinks()
            ->with('domain:id,hostname')
            ->when($accessibleLinkIds !== null, fn ($query) => $query->whereIn('id', $accessibleLinkIds))
            ->orderBy('slug')
            ->get(['id', 'slug', 'domain_id'])
            ->map(fn ($link) => [
                'id' => $link->id,
                'slug' => $link->slug,
                'hostname' => $link->domain?->hostname,
            ]);

        return [
            'links' => $links,
            'domains' => $workspace->domains()->orderBy('hostname')->get(['id', 'hostname']),
            'folders' => $data->folders($workspace, $user)->map->only(['id', 'name'])->values(),
            'tags' => $workspace->tags()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
