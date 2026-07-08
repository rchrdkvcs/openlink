<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Actions\Pages\WorkspaceShellPayload;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Actions\Workspaces\WorkspaceView;
use App\Actions\Workspaces\WorkspaceViewFactory;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access, WorkspacePayloads $data, WorkspaceViewFactory $views, BuildAnalyticsReport $reporter, WorkspaceShellPayload $shell): Response
    {
        $workspace = $access->requireCurrent($request);

        $user = $request->user();
        $filters = AnalyticsFilters::fromRequest($request);
        $accessibleLinkIds = $reporter->accessibleLinkIds($workspace, $user);

        return Inertia::render('Analytics/Index', [
            ...$shell->handle($workspace, $user),
            'report' => $reporter->report($workspace, $filters, $accessibleLinkIds),
            'filters' => $filters->toQuery() + ['range' => $filters->range],
            'filterOptions' => $this->filterOptions($workspace, $accessibleLinkIds, $data, $views->make($workspace, $user)),
        ]);
    }

    public function export(Request $request, WorkspaceAccess $access, BuildAnalyticsReport $reporter): StreamedResponse
    {
        $workspace = $access->requireCurrent($request);

        $filters = AnalyticsFilters::fromRequest($request);
        $accessibleLinkIds = $reporter->accessibleLinkIds($workspace, $request->user());

        $filename = sprintf('openlink-analytics-%s-%s.csv', $workspace->slug, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($reporter, $workspace, $filters, $accessibleLinkIds): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'occurred_at', 'metric', 'outcome', 'link_slug', 'qr_code', 'domain',
                'routing_rule', 'routing_variant',
                'referrer_host', 'referrer_channel', 'country', 'language',
                'device_type', 'browser', 'os', 'is_bot',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            ]);

            foreach ($reporter->exportRows($workspace, $filters, $accessibleLinkIds) as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function filterOptions(Workspace $workspace, ?array $accessibleLinkIds, WorkspacePayloads $data, WorkspaceView $view): array
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

        $rules = $workspace->shortLinks()
            ->with('routingRules.variants')
            ->when($accessibleLinkIds !== null, fn ($query) => $query->whereIn('id', $accessibleLinkIds))
            ->get()
            ->flatMap(fn ($link) => $link->routingRules)
            ->values();

        return [
            'links' => $links,
            'domains' => $workspace->domains()->orderBy('hostname')->get(['id', 'hostname']),
            'folders' => $data->folders($view)->map->only(['id', 'name'])->values(),
            'tags' => $workspace->tags()->orderBy('name')->get(['id', 'name']),
            'routingRules' => $rules->map(fn ($rule) => ['id' => $rule->id, 'name' => $rule->name])->values(),
            'routingVariants' => $rules
                ->flatMap(fn ($rule) => $rule->variants->map(fn ($variant) => ['id' => $variant->id, 'name' => $rule->name.' / '.$variant->name]))
                ->values(),
        ];
    }
}
