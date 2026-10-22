<?php

namespace App\Services\ShortLinks\Csv;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

final class LinkCsvFormat
{
    public const VERSION = '1';

    public const COLUMNS = [
        'format_version',
        'domain',
        'slug',
        'destination_url',
        'fallback_url',
        'folder',
        'tags',
        'enabled',
        'archived',
        'activates_at',
        'expires_at',
        'visit_limit',
    ];

    public const ALIASES = [
        'version' => 'format_version',
        'hostname' => 'domain',
        'domain_name' => 'domain',
        'key' => 'slug',
        'short_code' => 'slug',
        'path' => 'slug',
        'url' => 'destination_url',
        'destination' => 'destination_url',
        'long_url' => 'destination_url',
        'original_url' => 'destination_url',
        'target_url' => 'destination_url',
        'folder_name' => 'folder',
        'tag' => 'tags',
        'is_enabled' => 'enabled',
        'is_archived' => 'archived',
        'expires' => 'expires_at',
        'expiration' => 'expires_at',
    ];

    private const FORMULA_PATTERN = "/^'*[=+\\-@\t\r]/";

    private const TRUE_VALUES = ['true', '1', 'yes', 'y'];

    private const FALSE_VALUES = ['false', '0', 'no', 'n'];

    public static function escapeCell(string $value): string
    {
        return preg_match(self::FORMULA_PATTERN, $value) ? "'".$value : $value;
    }

    public static function unescapeCell(string $value): string
    {
        return str_starts_with($value, "'") && preg_match(self::FORMULA_PATTERN, $value) ? substr($value, 1) : $value;
    }

    public static function boolean(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    public static function parseBoolean(string $value): ?bool
    {
        $value = strtolower(trim($value));

        return match (true) {
            in_array($value, self::TRUE_VALUES, true) => true,
            in_array($value, self::FALSE_VALUES, true) => false,
            default => null,
        };
    }

    public static function date(?DateTimeInterface $value): string
    {
        return $value ? CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s\Z') : '';
    }

    public static function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?)?(Z|[+-]\d{2}:?\d{2})?$/i', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value, 'UTC');
        } catch (Throwable) {
            return null;
        }

        $written = substr(preg_replace('/(Z|[+-]\d{2}:?\d{2})$/i', '', $value), 0, 16);
        $expected = strlen($written) === 16 ? $date->format('Y-m-d\TH:i') : $date->format('Y-m-d');

        return strtoupper(str_replace(' ', 'T', $written)) === $expected ? $date->utc() : null;
    }

    public static function tags(iterable $names): string
    {
        return collect($names)->sort(SORT_NATURAL | SORT_FLAG_CASE)->implode(',');
    }

    public static function parseTags(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $tag) => trim($tag))
            ->filter(fn (string $tag) => $tag !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function columnFor(string $header): ?string
    {
        $key = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header))), '_');

        if (in_array($key, self::COLUMNS, true)) {
            return $key;
        }

        return self::ALIASES[$key] ?? null;
    }
}
