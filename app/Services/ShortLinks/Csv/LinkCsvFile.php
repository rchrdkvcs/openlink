<?php

namespace App\Services\ShortLinks\Csv;

use Illuminate\Validation\ValidationException;

final class LinkCsvFile
{
    public const FIRST_ROW_NUMBER = 2;

    public function __construct(
        public readonly array $headers,
        public readonly array $rows,
    ) {}

    public function mapping(?array $requested): array
    {
        $mapping = $requested === null ? $this->detectedMapping() : $this->validatedMapping($requested);

        if (! isset($mapping['destination_url'])) {
            throw ValidationException::withMessages(['mapping' => 'Choose the column that holds the destination URL.']);
        }

        return $mapping;
    }

    public function values(array $row, array $mapping): array
    {
        $values = array_fill_keys(LinkCsvFormat::COLUMNS, '');

        foreach ($mapping as $column => $index) {
            $values[$column] = LinkCsvFormat::unescapeCell(trim($row[$index] ?? ''));
        }

        return $values;
    }

    private function detectedMapping(): array
    {
        $mapping = [];

        foreach ($this->headers as $index => $header) {
            $column = LinkCsvFormat::columnFor($header);

            if ($column !== null && ! isset($mapping[$column])) {
                $mapping[$column] = $index;
            }
        }

        return $mapping;
    }

    private function validatedMapping(array $requested): array
    {
        $mapping = [];

        foreach ($requested as $column => $index) {
            if ($index === null || $index === '') {
                continue;
            }

            if (! in_array($column, LinkCsvFormat::COLUMNS, true) || ! is_numeric($index) || ! array_key_exists((int) $index, $this->headers)) {
                throw ValidationException::withMessages(['mapping' => 'The column mapping does not match this file.']);
            }

            $mapping[$column] = (int) $index;
        }

        return $mapping;
    }
}
