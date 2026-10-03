<?php

namespace App\Actions\Settings;

use App\Actions\Domains\DomainLifecycle;
use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Contracts\Auth\Access\Gate;

class UpdateInstanceSettings
{
    private const SCALAR_KEYS = [
        'registration_mode',
        'require_email_verification',
        'slug_length',
        'analytics_retention_days',
        'public_unavailable_title',
        'public_unavailable_message',
    ];

    private const LIST_KEYS = ['reserved_slugs', 'reserved_prefixes'];

    public function __construct(
        private readonly InstanceSettings $settings,
        private readonly DomainLifecycle $domains,
        private readonly Gate $gate,
    ) {}

    public function handle(User $actor, array $data): void
    {
        $this->gate->forUser($actor)->authorize('administer-instance');

        foreach (self::SCALAR_KEYS as $key) {
            $this->settings->set($key, $data[$key]);
        }

        if (array_key_exists('dns_target', $data)) {
            $this->settings->set('dns_target', trim((string) $data['dns_target']));
        }

        foreach (self::LIST_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $this->settings->set($key, $this->lines((string) $data[$key]));
            }
        }

        $this->domains->ensureDefaultDomain($data['default_domain']);
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\R/', $value) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
