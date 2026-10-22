<x-mail::message>
# {{ $summary['workspace_text'] }}

Weekly summary for {{ $summary['period'] }} ({{ $summary['timezone'] }}). Bots are excluded.

<x-mail::table>
| Metric | This week | vs previous week |
| :----- | --------: | ---------------: |
@foreach ($summary['metrics'] as $metric)
| {{ $metric['label'] }} | {{ number_format($metric['value']) }} | {{ $metric['change'] === null ? '—' : ($metric['change'] > 0 ? '+' : '').$metric['change'].'%' }} |
@endforeach
</x-mail::table>

@if ($summary['is_empty'])
No visits or scans were recorded this week.
@endif

@if (! $summary['is_complete'])
Part of this week is older than the analytics retention window, so these totals only include the days still retained.
@elseif (! $summary['is_comparable'])
The previous week is outside the analytics retention window, so no comparison is shown.
@endif

@if (count($summary['top_links']) > 0)
## Top links

<x-mail::table>
| Link | Visits and scans |
| :--- | ---------------: |
@foreach ($summary['top_links'] as $link)
| {{ $link['label'] }} | {{ number_format($link['total']) }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="$summary['report_url']">
Open the full report
</x-mail::button>

You receive this summary because an Owner or Admin of this workspace added you as a recipient. Signing in is required to open the report.
</x-mail::message>
