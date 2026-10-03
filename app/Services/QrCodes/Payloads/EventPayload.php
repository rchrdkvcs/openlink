<?php

namespace App\Services\QrCodes\Payloads;

use Illuminate\Support\Carbon;

class EventPayload extends PayloadType
{
    public function key(): string
    {
        return 'event';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'Calendar event',
            'hint' => 'Add a calendar event',
            'defaults' => ['title' => '', 'starts_at' => '', 'ends_at' => '', 'location' => '', 'description' => ''],
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'control' => 'text'],
                ['key' => 'starts_at', 'label' => 'Starts at', 'control' => 'datetime-local'],
                ['key' => 'ends_at', 'label' => 'Ends at', 'control' => 'datetime-local'],
                ['key' => 'location', 'label' => 'Location', 'control' => 'text'],
                ['key' => 'description', 'label' => 'Description', 'control' => 'textarea', 'rows' => 4],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'payload.title' => ['required', 'string', 'max:255'],
            'payload.starts_at' => ['required', 'date'],
            'payload.ends_at' => ['nullable', 'date', 'after_or_equal:payload.starts_at'],
            'payload.location' => ['nullable', 'string', 'max:500'],
            'payload.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function encode(array $payload): string
    {
        $start = Carbon::parse((string) $payload['starts_at'])->utc();
        $end = filled($payload['ends_at'] ?? null)
            ? Carbon::parse((string) $payload['ends_at'])->utc()
            : $start->copy()->addHour();

        return implode("\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Openlink//QR Code//EN',
            'BEGIN:VEVENT',
            'SUMMARY:'.PayloadText::vcard((string) $payload['title']),
            'DTSTART:'.$start->format('Ymd\THis\Z'),
            'DTEND:'.$end->format('Ymd\THis\Z'),
            ...PayloadText::optionalLines($payload, ['location' => 'LOCATION', 'description' => 'DESCRIPTION']),
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
    }
}
