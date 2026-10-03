<?php

namespace App\Actions\ShortLinks;

use App\Actions\QrCodes\ShortLinkSearch;
use App\Enums\LinkStatus;
use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\ShortLinks\ShortLinkLifecycle;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class LinksQuery
{
    public const ALL_STATUSES = 'all';

    private const PER_PAGE = 50;

    public function __construct(
        private readonly ShortLinkPayload $payload,
        private readonly ShortLinkLifecycle $lifecycle,
    ) {}

    public static function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(LinkStatus::class)],
            'tag' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'regex:/^(unfiled|\d+)$/'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public static function filterState(array $filters): array
    {
        return [
            'search' => $filters['search'] ?? '',
            'status' => $filters['status'] ?? '',
            'tag' => $filters['tag'] ?? '',
            'folder' => (string) ($filters['folder'] ?? ''),
        ];
    }

    public static function pagination(LengthAwarePaginator $page): array
    {
        return [
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'total' => $page->total(),
            'perPage' => $page->perPage(),
        ];
    }

    public function paginate(Workspace $workspace, array $filters = []): LengthAwarePaginator
    {
        return $this->filtered($workspace, $filters)
            ->paginate(self::PER_PAGE, ['*'], 'page', $filters['page'] ?? 1)
            ->through(fn (ShortLink $link) => $this->payload->handle($link));
    }

    public function recent(Workspace $workspace, int $limit): Collection
    {
        return $this->filtered($workspace)
            ->limit($limit)
            ->get()
            ->map(fn (ShortLink $link) => $this->payload->handle($link));
    }

    public function counts(Workspace $workspace): array
    {
        $active = $workspace->shortLinks();
        $this->lifecycle->whereStatus($active, LinkStatus::Active);

        return ['total' => $workspace->shortLinks()->count(), 'active' => $active->count()];
    }

    private function filtered(Workspace $workspace, array $filters = []): HasMany
    {
        $query = $workspace->shortLinks()
            ->with(ShortLinkPayload::RELATIONS)
            ->withCount('qrCodes')
            ->withCount(ShortLinkPayload::analyticsCounts());

        ShortLinkSearch::apply($query, (string) ($filters['search'] ?? ''));
        $this->filterFolder($query, (string) ($filters['folder'] ?? ''));
        $this->filterStatus($query, (string) ($filters['status'] ?? ''));

        $tag = (string) ($filters['tag'] ?? '');
        if ($tag !== '') {
            $query->whereHas('tags', fn ($tags) => $tags->where('name', $tag));
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function filterFolder(HasMany $query, string $folder): void
    {
        if ($folder === 'unfiled') {
            $query->whereNull('folder_id');
        } elseif ($folder !== '') {
            $query->where('folder_id', (int) $folder);
        }
    }

    private function filterStatus(HasMany $query, string $status): void
    {
        if ($status === self::ALL_STATUSES) {
            return;
        }

        if ($status === '') {
            $query->whereNull('archived_at');

            return;
        }

        $linkStatus = LinkStatus::tryFrom($status);

        $linkStatus ? $this->lifecycle->whereStatus($query, $linkStatus) : $query->whereRaw('1 = 0');
    }
}
