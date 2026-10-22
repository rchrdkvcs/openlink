<?php

namespace App\Actions\ShortLinks;

use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\ShortLinks\Csv\LinkCsvFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportShortLinks
{
    private const BYTE_ORDER_MARK = "\u{FEFF}";

    private const CHUNK = 500;

    private const EXAMPLE_ROWS = [
        ['1', '', 'spring-sale', 'https://example.com/spring?utm_source=newsletter', '', 'Campaigns', 'spring,newsletter', 'true', 'false', '2026-03-01T09:00:00Z', '2026-03-31T23:59:59Z', ''],
        ['1', '', '', 'https://example.com/docs/getting-started', 'https://example.com/docs', 'Docs', '', 'true', 'false', '', '', '1000'],
    ];

    public function __construct(private readonly LinksQuery $links) {}

    public function download(Workspace $workspace, array $filters): StreamedResponse
    {
        $query = $this->links->matching($workspace, $filters)->with(['domain', 'folder', 'tags']);

        return $this->stream($this->filename($workspace->slug), function ($out) use ($query): void {
            foreach ($query->lazy(self::CHUNK) as $link) {
                $this->write($out, $this->row($link));
            }
        });
    }

    public function example(): StreamedResponse
    {
        return $this->stream('openlink-links-v1-example.csv', function ($out): void {
            foreach (self::EXAMPLE_ROWS as $row) {
                $this->write($out, $row);
            }
        });
    }

    private function stream(string $filename, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, self::BYTE_ORDER_MARK);
            $this->write($out, LinkCsvFormat::COLUMNS);
            $rows($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function row(ShortLink $link): array
    {
        return [
            LinkCsvFormat::VERSION,
            $link->domain->hostname,
            $link->slug,
            $link->destination_url,
            (string) $link->fallback_url,
            (string) $link->folder?->name,
            LinkCsvFormat::tags($link->tags->pluck('name')),
            LinkCsvFormat::boolean($link->is_enabled),
            LinkCsvFormat::boolean($link->isArchived()),
            LinkCsvFormat::date($link->activates_at),
            LinkCsvFormat::date($link->expires_at),
            (string) $link->visit_limit,
        ];
    }

    private function write($out, array $row): void
    {
        fputcsv($out, array_map(LinkCsvFormat::escapeCell(...), $row), escape: '');
    }

    private function filename(string $workspaceSlug): string
    {
        return sprintf('openlink-links-v1-%s-%s.csv', $workspaceSlug, now()->format('Y-m-d'));
    }
}
