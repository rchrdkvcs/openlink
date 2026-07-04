<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\ShortLink;
use Illuminate\Validation\ValidationException;

class SlugService
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public function __construct(private readonly InstanceSettings $settings)
    {
    }

    public function generate(Domain $domain): string
    {
        $length = max(4, (int) $this->settings->get('slug_length', 6));

        do {
            $slug = collect(range(1, $length))
                ->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
                ->implode('');
        } while ($this->existsForDomain($domain, $slug) || $this->isReserved($slug));

        return $slug;
    }

    public function validateCustom(Domain $domain, string $slug): string
    {
        $slug = trim($slug, "/ \t\n\r\0\x0B");

        if ($slug === '' || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9_\/-]*$/', $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'Use letters, numbers, dashes, underscores, and path separators only.',
            ]);
        }

        if ($this->isReserved($slug)) {
            throw ValidationException::withMessages([
                'slug' => 'This slug is reserved by the application.',
            ]);
        }

        if ($this->existsForDomain($domain, $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'This slug is already reserved for this domain.',
            ]);
        }

        return $slug;
    }

    public function isReserved(string $slug): bool
    {
        $slug = strtolower(trim($slug, '/'));
        $reserved = collect($this->settings->get('reserved_slugs', []))
            ->map(fn (string $value) => strtolower(trim($value, '/')))
            ->all();

        if (in_array($slug, $reserved, true)) {
            return true;
        }

        foreach ($this->settings->get('reserved_prefixes', []) as $prefix) {
            if (str_starts_with($slug.'/', strtolower(trim((string) $prefix, '/')).'/')) {
                return true;
            }
        }

        return false;
    }

    private function existsForDomain(Domain $domain, string $slug): bool
    {
        return ShortLink::query()
            ->where('domain_id', $domain->id)
            ->where('slug', $slug)
            ->exists();
    }
}
