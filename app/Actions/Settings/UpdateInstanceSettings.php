<?php

namespace App\Actions\Settings;

use App\Models\Domain;
use App\Services\InstanceSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UpdateInstanceSettings
{
    public function __construct(private readonly InstanceSettings $settings) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Request $request, array $data): void
    {
        abort_unless($request->user()?->is_instance_admin, 403);

        foreach (['registration_mode', 'default_domain', 'slug_length', 'analytics_retention_days', 'public_unavailable_title', 'public_unavailable_message'] as $key) {
            $this->settings->set($key, $data[$key]);
        }

        $this->settings->set('dns_target', trim((string) ($data['dns_target'] ?? '')));

        Domain::query()->where('is_default', true)->update(['is_default' => false]);
        Domain::query()->updateOrCreate([
            'hostname' => strtolower(trim($data['default_domain'])),
        ], [
            'workspace_id' => null,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => Str::random(40),
            'is_default' => true,
            'verified_at' => now(),
            'dns_pointed_at' => now(),
            'disabled_at' => null,
        ]);

        $this->settings->set('reserved_slugs', $this->lines($data['reserved_slugs'] ?? ''));
        $this->settings->set('reserved_prefixes', $this->lines($data['reserved_prefixes'] ?? ''));
    }

    /** @return array<int, string> */
    private function lines(string $value): array
    {
        return collect(preg_split('/\R/', $value) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
