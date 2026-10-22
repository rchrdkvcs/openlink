<?php

namespace App\Services\ShortLinks\Csv;

use Illuminate\Validation\ValidationException;

final class LinkCsvReader
{
    public const MAX_ROWS = 1000;

    private const DELIMITERS = [',', ';', "\t"];

    public function read(string $contents): LinkCsvFile
    {
        $contents = $this->withoutByteOrderMark($contents);

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $this->fail('The file must be encoded as UTF-8.');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $delimiter = $this->delimiter($contents);
        $headers = null;
        $rows = [];
        $lines = [];
        $line = 1;
        $position = 0;

        while (($cells = fgetcsv($stream, separator: $delimiter, escape: '')) !== false) {
            $startLine = $line;
            $next = ftell($stream);
            $line += substr_count($contents, "\n", $position, $next - $position);
            $position = $next;

            if ($cells === [null]) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn (?string $cell) => trim((string) $cell), $cells);

                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($stream);
                $this->fail(sprintf('The file has more than %d links. Split it into smaller files.', self::MAX_ROWS));
            }

            $rows[] = array_map(fn (?string $cell) => (string) $cell, $cells);
            $lines[] = $startLine;
        }

        fclose($stream);

        if ($headers === null || $rows === []) {
            $this->fail('The file needs a header row and at least one link.');
        }

        return new LinkCsvFile($headers, $rows, $lines);
    }

    private function withoutByteOrderMark(string $contents): string
    {
        return str_starts_with($contents, "\u{FEFF}") ? substr($contents, 3) : $contents;
    }

    private function delimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n") ?: '';
        $counts = array_map(fn (string $delimiter) => substr_count($firstLine, $delimiter), self::DELIMITERS);

        return max($counts) > 0 ? self::DELIMITERS[array_search(max($counts), $counts, true)] : ',';
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
