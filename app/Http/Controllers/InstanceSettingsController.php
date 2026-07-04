<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\InstanceSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstanceSettingsController extends Controller
{
    public function update(Request $request, InstanceSettings $settings): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()?->is_instance_admin, 403);

        $data = $request->validate([
            'registration_mode' => ['required', 'in:closed,invite_only,open'],
            'default_domain' => ['required', 'string', 'max:255'],
            'slug_length' => ['required', 'integer', 'min:4', 'max:32'],
            'analytics_retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'reserved_slugs' => ['nullable', 'string'],
            'reserved_prefixes' => ['nullable', 'string'],
            'public_unavailable_title' => ['required', 'string', 'max:120'],
            'public_unavailable_message' => ['required', 'string', 'max:500'],
        ]);

        foreach (['registration_mode', 'default_domain', 'slug_length', 'analytics_retention_days', 'public_unavailable_title', 'public_unavailable_message'] as $key) {
            $settings->set($key, $data[$key]);
        }

        Domain::query()->where('is_default', true)->update(['is_default' => false]);
        Domain::query()->updateOrCreate([
            'hostname' => strtolower(trim($data['default_domain'])),
        ], [
            'workspace_id' => null,
            'status' => Domain::STATUS_VERIFIED,
            'verification_token' => Str::random(40),
            'is_default' => true,
            'verified_at' => now(),
            'disabled_at' => null,
        ]);

        $settings->set('reserved_slugs', $this->lines($data['reserved_slugs'] ?? ''));
        $settings->set('reserved_prefixes', $this->lines($data['reserved_prefixes'] ?? ''));

        return back();
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
