<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, CurrentWorkspace $current, BuildAnalyticsReport $reporter): Response
    {
        $workspace = $current->require();
        $filters = AnalyticsFilters::fromRequest($request);

        return Inertia::render('Analytics/Index', [
            'report' => $reporter->report($workspace, $filters),
            'filters' => $filters->toQuery() + ['range' => $filters->range],
            'filterOptions' => $this->filterOptions($workspace),
        ]);
    }

    public function export(Request $request, CurrentWorkspace $current, BuildAnalyticsReport $reporter): StreamedResponse
    {
        $workspace = $current->require();
        $filters = AnalyticsFilters::fromRequest($request);
        $filename = sprintf('openlink-analytics-%s-%s.csv', $workspace->slug, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($reporter, $workspace, $filters): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'occurred_at', 'metric', 'outcome', 'link_slug', 'qr_code', 'domain',
                'routing_rule', 'routing_variant',
                'referrer_host', 'referrer_channel', 'country', 'language',
                'device_type', 'browser', 'os', 'is_bot',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            ]);

            foreach ($reporter->exportRows($workspace, $filters) as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filterOptions(Workspace $workspace): array
    {
        $links = $workspace->shortLinks()
            ->with('domain:id,hostname')
            ->latest('id')
            ->get(['id', 'slug', 'domain_id', 'destination_url'])
            ->map(fn ($link) => [
                'id' => $link->id,
                'slug' => $link->slug,
                'hostname' => $link->domain?->hostname,
                'short_url' => $link->domain ? $link->shortUrl() : null,
                'destination_url' => $link->destination_url,
            ]);

        $rules = $workspace->shortLinks()
            ->with('routingRules.variants')
            ->get()
            ->flatMap(fn ($link) => $link->routingRules)
            ->values();

        return [
            'links' => $links,
            'qrCodes' => $workspace->qrCodes()->orderBy('name')->get(['id', 'name']),
            'domains' => $workspace->domains()->orderBy('hostname')->get(['id', 'hostname']),
            'folders' => $workspace->folders()->orderBy('name')->get()->map->only(['id', 'name'])->values(),
            'tags' => $workspace->tags()->orderBy('name')->get(['id', 'name']),
            'routingRules' => $rules->map(fn ($rule) => ['id' => $rule->id, 'name' => $rule->name])->values(),
            'routingVariants' => $rules
                ->flatMap(fn ($rule) => $rule->variants->map(fn ($variant) => ['id' => $variant->id, 'name' => $rule->name.' / '.$variant->name]))
                ->values(),
        ];
    }
}
