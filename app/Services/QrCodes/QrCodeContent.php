<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class QrCodeContent
{
    /** @return array<string, string> */
    public static function types(): array
    {
        return collect(self::descriptors())->map(fn (array $descriptor) => $descriptor['label'])->all();
    }

    /** @return array<string, array{label: string, hint: string, defaults: array<string, mixed>, fields: list<array<string, mixed>>}> */
    public static function descriptors(): array
    {
        return [
            'url' => [
                'label' => 'URL',
                'hint' => 'Open a web page',
                'defaults' => ['url' => ''],
                'fields' => [
                    ['key' => 'url', 'label' => 'URL', 'control' => 'url', 'placeholder' => 'https://example.com'],
                ],
            ],
            'text' => [
                'label' => 'Text',
                'hint' => 'Show plain text',
                'defaults' => ['text' => ''],
                'fields' => [
                    ['key' => 'text', 'label' => 'Text', 'control' => 'textarea', 'rows' => 6, 'placeholder' => 'Plain text shown after scan'],
                ],
            ],
            'email' => [
                'label' => 'Email',
                'hint' => 'Compose an email',
                'defaults' => ['email' => '', 'subject' => '', 'body' => ''],
                'fields' => [
                    ['key' => 'email', 'label' => 'Email address', 'control' => 'email', 'placeholder' => 'hello@example.com'],
                    ['key' => 'subject', 'label' => 'Subject', 'control' => 'text', 'placeholder' => 'Optional subject'],
                    ['key' => 'body', 'label' => 'Body', 'control' => 'textarea', 'rows' => 4, 'placeholder' => 'Optional message body'],
                ],
            ],
            'phone' => [
                'label' => 'Phone',
                'hint' => 'Start a phone call',
                'defaults' => ['phone' => ''],
                'fields' => [
                    ['key' => 'phone', 'label' => 'Phone number', 'control' => 'tel', 'placeholder' => '+15551234567'],
                ],
            ],
            'sms' => [
                'label' => 'SMS',
                'hint' => 'Prefill a text message',
                'defaults' => ['phone' => '', 'message' => ''],
                'fields' => [
                    ['key' => 'phone', 'label' => 'Phone number', 'control' => 'tel', 'placeholder' => '+15551234567'],
                    ['key' => 'message', 'label' => 'Message', 'control' => 'textarea', 'rows' => 4, 'placeholder' => 'Optional SMS body'],
                ],
            ],
            'wifi' => [
                'label' => 'Wi-Fi',
                'hint' => 'Join a Wi-Fi network',
                'defaults' => ['ssid' => '', 'encryption' => 'WPA', 'password' => '', 'hidden' => false],
                'fields' => [
                    ['key' => 'ssid', 'label' => 'Network name', 'control' => 'text', 'placeholder' => 'SSID'],
                    ['key' => 'encryption', 'label' => 'Security', 'control' => 'select', 'options' => [['value' => 'WPA', 'label' => 'WPA/WPA2'], ['value' => 'WEP', 'label' => 'WEP'], ['value' => 'nopass', 'label' => 'No password']]],
                    ['key' => 'password', 'label' => 'Password', 'control' => 'text', 'placeholder' => 'Network password', 'disabledWhen' => ['key' => 'encryption', 'value' => 'nopass']],
                    ['key' => 'hidden', 'label' => 'Hidden network', 'control' => 'checkbox'],
                ],
            ],
            'vcard' => [
                'label' => 'vCard',
                'hint' => 'Share a contact card',
                'defaults' => ['full_name' => '', 'organization' => '', 'title' => '', 'phone' => '', 'email' => '', 'url' => '', 'address' => ''],
                'fields' => [
                    ['key' => 'full_name', 'label' => 'Full name', 'control' => 'text', 'placeholder' => 'Jane Doe'],
                    ['key' => 'organization', 'label' => 'Organization', 'control' => 'text'],
                    ['key' => 'title', 'label' => 'Title', 'control' => 'text'],
                    ['key' => 'phone', 'label' => 'Phone', 'control' => 'tel'],
                    ['key' => 'email', 'label' => 'Email', 'control' => 'email'],
                    ['key' => 'url', 'label' => 'Website', 'control' => 'url', 'placeholder' => 'https://example.com'],
                    ['key' => 'address', 'label' => 'Address', 'control' => 'textarea', 'rows' => 3],
                ],
            ],
            'event' => [
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
            ],
            'location' => [
                'label' => 'Location',
                'hint' => 'Open a map location',
                'defaults' => ['latitude' => '', 'longitude' => '', 'label' => ''],
                'fields' => [
                    ['key' => 'latitude', 'label' => 'Latitude', 'control' => 'number', 'step' => 'any', 'placeholder' => '48.8584'],
                    ['key' => 'longitude', 'label' => 'Longitude', 'control' => 'number', 'step' => 'any', 'placeholder' => '2.2945'],
                    ['key' => 'label', 'label' => 'Label', 'control' => 'text', 'placeholder' => 'Optional place name'],
                ],
            ],
            'raw' => [
                'label' => 'Raw payload',
                'hint' => 'Any custom QR payload',
                'defaults' => ['content' => ''],
                'fields' => [
                    ['key' => 'content', 'label' => 'Raw QR payload', 'control' => 'textarea', 'rows' => 8, 'placeholder' => 'BEGIN:VCARD...', 'class' => 'font-mono text-[13px]'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(string $type, array $payload): string
    {
        Validator::make(['payload' => $payload], $this->rulesFor($type))->validate();

        return match ($type) {
            'url' => trim((string) $payload['url']),
            'text' => trim((string) $payload['text']),
            'email' => $this->email($payload),
            'phone' => 'tel:'.$this->phone((string) $payload['phone']),
            'sms' => $this->sms($payload),
            'wifi' => $this->wifi($payload),
            'vcard' => $this->vcard($payload),
            'event' => $this->event($payload),
            'location' => $this->location($payload),
            default => trim((string) $payload['content']),
        };
    }

    public function shouldRedirect(QrCode $qrCode): bool
    {
        if (in_array($qrCode->payload_type, ['url', 'email', 'phone', 'sms', 'location'], true)) {
            return true;
        }

        return $qrCode->payload_type === 'raw'
            && preg_match('/^[a-z][a-z0-9+.-]*:/i', $qrCode->content) === 1
            && ! str_starts_with(strtoupper($qrCode->content), 'WIFI:')
            && ! str_starts_with(strtoupper($qrCode->content), 'BEGIN:');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rulesFor(string $type): array
    {
        return match ($type) {
            'url' => ['payload.url' => ['required', 'url', 'max:2048']],
            'text' => ['payload.text' => ['required', 'string', 'max:8000']],
            'email' => [
                'payload.email' => ['required', 'email:rfc', 'max:255'],
                'payload.subject' => ['nullable', 'string', 'max:255'],
                'payload.body' => ['nullable', 'string', 'max:4000'],
            ],
            'phone' => ['payload.phone' => ['required', 'string', 'max:80']],
            'sms' => [
                'payload.phone' => ['required', 'string', 'max:80'],
                'payload.message' => ['nullable', 'string', 'max:1000'],
            ],
            'wifi' => [
                'payload.ssid' => ['required', 'string', 'max:255'],
                'payload.encryption' => ['required', Rule::in(['WPA', 'WEP', 'nopass'])],
                'payload.password' => ['nullable', 'string', 'max:255'],
                'payload.hidden' => ['nullable', 'boolean'],
            ],
            'vcard' => [
                'payload.full_name' => ['required', 'string', 'max:255'],
                'payload.organization' => ['nullable', 'string', 'max:255'],
                'payload.title' => ['nullable', 'string', 'max:255'],
                'payload.phone' => ['nullable', 'string', 'max:80'],
                'payload.email' => ['nullable', 'email:rfc', 'max:255'],
                'payload.url' => ['nullable', 'url', 'max:2048'],
                'payload.address' => ['nullable', 'string', 'max:500'],
            ],
            'event' => [
                'payload.title' => ['required', 'string', 'max:255'],
                'payload.starts_at' => ['required', 'date'],
                'payload.ends_at' => ['nullable', 'date', 'after_or_equal:payload.starts_at'],
                'payload.location' => ['nullable', 'string', 'max:500'],
                'payload.description' => ['nullable', 'string', 'max:2000'],
            ],
            'location' => [
                'payload.latitude' => ['required', 'numeric', 'between:-90,90'],
                'payload.longitude' => ['required', 'numeric', 'between:-180,180'],
                'payload.label' => ['nullable', 'string', 'max:255'],
            ],
            default => ['payload.content' => ['required', 'string', 'max:8000']],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function email(array $payload): string
    {
        $query = collect([
            'subject' => $payload['subject'] ?? null,
            'body' => $payload['body'] ?? null,
        ])->filter(fn ($value) => filled($value))->all();

        return 'mailto:'.trim((string) $payload['email'])
            .($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sms(array $payload): string
    {
        $message = trim((string) ($payload['message'] ?? ''));

        return 'sms:'.$this->phone((string) $payload['phone'])
            .($message === '' ? '' : '?body='.rawurlencode($message));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function wifi(array $payload): string
    {
        $encryption = (string) $payload['encryption'];
        $password = $encryption === 'nopass' ? '' : (string) ($payload['password'] ?? '');
        $hidden = filter_var($payload['hidden'] ?? false, FILTER_VALIDATE_BOOL) ? 'true' : 'false';

        return 'WIFI:T:'.$encryption
            .';S:'.$this->wifiEscape((string) $payload['ssid'])
            .';P:'.$this->wifiEscape($password)
            .';H:'.$hidden
            .';;';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function vcard(array $payload): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:'.$this->vcardEscape((string) $payload['full_name']),
        ];

        foreach ([
            'organization' => 'ORG',
            'title' => 'TITLE',
            'phone' => 'TEL',
            'email' => 'EMAIL',
            'url' => 'URL',
            'address' => 'ADR',
        ] as $key => $label) {
            if (filled($payload[$key] ?? null)) {
                $lines[] = $label.':'.$this->vcardEscape((string) $payload[$key]);
            }
        }

        $lines[] = 'END:VCARD';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(array $payload): string
    {
        $start = Carbon::parse((string) $payload['starts_at'])->utc();
        $end = filled($payload['ends_at'] ?? null)
            ? Carbon::parse((string) $payload['ends_at'])->utc()
            : $start->copy()->addHour();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Openlink//QR Code//EN',
            'BEGIN:VEVENT',
            'SUMMARY:'.$this->vcardEscape((string) $payload['title']),
            'DTSTART:'.$start->format('Ymd\THis\Z'),
            'DTEND:'.$end->format('Ymd\THis\Z'),
        ];

        foreach (['location' => 'LOCATION', 'description' => 'DESCRIPTION'] as $key => $label) {
            if (filled($payload[$key] ?? null)) {
                $lines[] = $label.':'.$this->vcardEscape((string) $payload[$key]);
            }
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function location(array $payload): string
    {
        $lat = trim((string) $payload['latitude']);
        $lng = trim((string) $payload['longitude']);
        $label = trim((string) ($payload['label'] ?? ''));

        if ($label === '') {
            return "geo:$lat,$lng";
        }

        return "geo:$lat,$lng?q=$lat,$lng(".rawurlencode($label).')';
    }

    private function phone(string $phone): string
    {
        return preg_replace('/[^0-9+*#;,]/', '', $phone) ?: trim($phone);
    }

    private function wifiEscape(string $value): string
    {
        return str_replace(['\\', ';', ',', ':'], ['\\\\', '\;', '\,', '\:'], $value);
    }

    private function vcardEscape(string $value): string
    {
        return str_replace(['\\', "\n", "\r", ';', ','], ['\\\\', '\n', '', '\;', '\,'], $value);
    }
}
