<?php

namespace Tests\Unit\ShortLinks;

use App\Services\ShortLinks\Csv\LinkCsvFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LinkCsvFormatTest extends TestCase
{
    public static function cells(): array
    {
        return [
            ['=SUM(A1)', "'=SUM(A1)"],
            ['+1', "'+1"],
            ['-1', "'-1"],
            ['@cmd', "'@cmd"],
            ["\tx", "'\tx"],
            ["'=x", "''=x"],
            ["'90s", "'90s"],
            ['plain', 'plain'],
            ['', ''],
        ];
    }

    #[DataProvider('cells')]
    public function test_formula_cells_are_escaped_and_round_trip(string $value, string $escaped): void
    {
        $this->assertSame($escaped, LinkCsvFormat::escapeCell($value));
        $this->assertSame($value, LinkCsvFormat::unescapeCell($escaped));
    }

    public function test_dates_are_written_in_utc_and_read_with_offsets(): void
    {
        $this->assertSame('2026-10-21T07:00:00Z', LinkCsvFormat::date(LinkCsvFormat::parseDate('2026-10-21T09:00:00+02:00')));
        $this->assertSame('2026-10-21T09:00:00Z', LinkCsvFormat::date(LinkCsvFormat::parseDate('2026-10-21 09:00')));
        $this->assertSame('2026-10-21T00:00:00Z', LinkCsvFormat::date(LinkCsvFormat::parseDate('2026-10-21')));
        $this->assertNull(LinkCsvFormat::parseDate('2026-02-31'));
        $this->assertNull(LinkCsvFormat::parseDate('2026-10-21T25:00'));
        $this->assertNull(LinkCsvFormat::parseDate('21/10/2026'));
        $this->assertSame('', LinkCsvFormat::date(null));
    }

    public function test_tags_are_comma_separated_sorted_and_deduplicated(): void
    {
        $this->assertSame('alpha,Beta,gamma', LinkCsvFormat::tags(['gamma', 'Beta', 'alpha']));
        $this->assertSame(['a', 'b'], LinkCsvFormat::parseTags(' a, b ,,a '));
    }

    public function test_headers_map_to_columns_through_aliases(): void
    {
        $this->assertSame('destination_url', LinkCsvFormat::columnFor('Long URL'));
        $this->assertSame('slug', LinkCsvFormat::columnFor('key'));
        $this->assertSame('visit_limit', LinkCsvFormat::columnFor(' Visit Limit '));
        $this->assertNull(LinkCsvFormat::columnFor('successful_visits'));
    }

    public function test_booleans_accept_common_spellings(): void
    {
        $this->assertTrue(LinkCsvFormat::parseBoolean('Yes'));
        $this->assertFalse(LinkCsvFormat::parseBoolean('0'));
        $this->assertNull(LinkCsvFormat::parseBoolean('maybe'));
    }
}
