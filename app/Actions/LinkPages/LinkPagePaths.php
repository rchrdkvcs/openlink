<?php

namespace App\Actions\LinkPages;

use App\Models\Domain;
use App\Models\LinkPage;
use App\Models\ShortLink;
use App\Services\ApplicationHost;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkPagePaths
{
    public function __construct(private readonly ApplicationHost $applicationHost) {}

    public function ensureAvailable(string $path, ?LinkPage $ignore = null): string
    {
        $path = strtolower(trim($path));

        if (! preg_match(LinkPage::PATH_PATTERN, $path)) {
            throw ValidationException::withMessages(['path' => __('openlink.validation.link_page_path_invalid')]);
        }

        $taken = LinkPage::query()
            ->where('path', $path)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($taken || $this->usedByShortLink($path)) {
            throw $this->taken();
        }

        return $path;
    }

    public function claim(callable $write): mixed
    {
        try {
            return DB::transaction($write);
        } catch (UniqueConstraintViolationException) {
            throw $this->taken();
        }
    }

    private function taken(): ValidationException
    {
        return ValidationException::withMessages(['path' => __('openlink.validation.link_page_path_taken')]);
    }

    private function usedByShortLink(string $path): bool
    {
        return ShortLink::query()
            ->whereIn('domain_id', Domain::query()->select('id')->where('hostname', $this->applicationHost->host()))
            ->whereRaw('LOWER(slug) = ?', [LinkPage::PATH_PREFIX.'/'.$path])
            ->exists();
    }
}
