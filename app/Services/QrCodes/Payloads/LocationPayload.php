<?php

namespace App\Services\QrCodes\Payloads;

class LocationPayload extends PayloadType
{
    public function key(): string
    {
        return 'location';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'Location',
            'hint' => 'Open a map location',
            'defaults' => ['latitude' => '', 'longitude' => '', 'label' => ''],
            'fields' => [
                ['key' => 'latitude', 'label' => 'Latitude', 'control' => 'number', 'step' => 'any', 'placeholder' => '48.8584'],
                ['key' => 'longitude', 'label' => 'Longitude', 'control' => 'number', 'step' => 'any', 'placeholder' => '2.2945'],
                ['key' => 'label', 'label' => 'Label', 'control' => 'text', 'placeholder' => 'Optional place name'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'payload.latitude' => ['required', 'numeric', 'between:-90,90'],
            'payload.longitude' => ['required', 'numeric', 'between:-180,180'],
            'payload.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function encode(array $payload): string
    {
        $lat = trim((string) $payload['latitude']);
        $lng = trim((string) $payload['longitude']);
        $label = trim((string) ($payload['label'] ?? ''));

        if ($label === '') {
            return "geo:$lat,$lng";
        }

        return "geo:$lat,$lng?q=$lat,$lng(".rawurlencode($label).')';
    }

    public function redirects(string $content): bool
    {
        return true;
    }
}
