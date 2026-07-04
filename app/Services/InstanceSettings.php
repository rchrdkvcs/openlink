<?php

namespace App\Services;

use App\Models\InstanceSetting;

class InstanceSettings
{
    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'instance_name' => 'Openlink',
        'registration_mode' => 'invite_only',
        'default_domain' => 'localhost',
        'reserved_slugs' => [
            'admin',
            'app',
            'dashboard',
            'login',
            'register',
            'settings',
            'api',
            'qr',
            'u',
            'health',
            'assets',
            'password',
            'profile',
        ],
        'reserved_prefixes' => [
            'app/',
            'api/',
            'qr/',
            'assets/',
        ],
        'slug_length' => 6,
        'analytics_retention_days' => 365,
        'public_unavailable_title' => 'This link is unavailable',
        'public_unavailable_message' => 'The link cannot be opened right now.',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = InstanceSetting::query()->where('key', $key)->first();

        if ($setting) {
            return $setting->value;
        }

        return self::DEFAULTS[$key] ?? $default;
    }

    public function set(string $key, mixed $value): mixed
    {
        InstanceSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        return $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $settings = self::DEFAULTS;

        InstanceSetting::query()
            ->get()
            ->each(function (InstanceSetting $setting) use (&$settings): void {
                $settings[$setting->key] = $setting->value;
            });

        return $settings;
    }
}
