<?php

namespace App\Actions\BioPages;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\BioElement;
use App\Models\BioPage;
use App\Models\Domain;
use App\Models\PublicSlug;
use App\Models\ShortLink;
use App\Services\BioPages\BioTheme;
use App\Services\PublicSlugRegistry;
use App\Services\SlugService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BioPageMutation
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly SlugService $slugs,
        private readonly PublicSlugRegistry $registry,
        private readonly BioTheme $themes,
    ) {}

    public function create(Request $request): BioPage
    {
        $workspace = $this->access->requireEditableWorkspace($request);
        $data = $this->validateDraft($request);
        $domain = $this->domainFor($workspace->id, $data['domainId'] ?? null)
            ?? $workspace->preferredDomain
            ?? Domain::query()->where('is_default', true)->first();

        abort_unless($domain, 422, 'No domain available for this workspace.');

        $slug = filled($data['slug'] ?? null)
            ? $this->slugs->validateCustom($domain, (string) $data['slug'])
            : $this->slugs->generate($domain);

        return DB::transaction(function () use ($workspace, $domain, $slug, $data): BioPage {
            $bioPage = BioPage::create([
                'workspace_id' => $workspace->id,
                'draft_domain_id' => $domain->id,
                'draft_slug' => $slug,
                'draft' => $this->draftFrom($data),
            ]);

            $this->syncElements($bioPage, $data['elements'] ?? []);

            return $bioPage;
        });
    }

    public function update(Request $request, BioPage $bioPage): BioPage
    {
        $workspace = $this->access->requireCurrent($request);
        abort_unless($bioPage->workspace_id === $workspace->id, 403);
        $request->user()->cannot('update', $bioPage) && abort(403);

        $data = $this->validateDraft($request, updating: true);
        $domain = array_key_exists('domainId', $data)
            ? $this->domainFor($workspace->id, $data['domainId'])
            : $bioPage->draftDomain;
        abort_unless($domain, 422, 'Domain does not belong to this workspace.');

        $slug = trim((string) ($data['slug'] ?? $bioPage->draft_slug), '/');
        if ($domain->id !== $bioPage->draft_domain_id || $slug !== $bioPage->draft_slug) {
            $slug = $this->slugs->validateCustom($domain, $slug, PublicSlug::TYPE_BIO_PAGE, $bioPage->id);
        }

        return DB::transaction(function () use ($bioPage, $domain, $slug, $data): BioPage {
            $bioPage->update([
                'draft_domain_id' => $domain->id,
                'draft_slug' => $slug,
                'draft' => $this->draftFrom($data, $bioPage->draft),
            ]);

            if (array_key_exists('elements', $data)) {
                $this->syncElements($bioPage, $data['elements']);
            }

            return $bioPage->refresh();
        });
    }

    public function publish(Request $request, BioPage $bioPage): BioPage
    {
        $this->requireManageable($request, $bioPage, 'publish');
        $bioPage->load('draftDomain', 'elements');

        $errors = [];
        if (! $bioPage->draftDomain?->isUsable()) {
            $errors['domainId'] = 'The Domain must be active before publication.';
        }
        if (! filled($bioPage->draft['displayName'] ?? null)) {
            $errors['displayName'] = 'A display name is required before publication.';
        }
        $visibleElements = $bioPage->elements->filter(fn (BioElement $element) => ($element->draft['visible'] ?? false) === true);
        if ($visibleElements->isEmpty()) {
            $errors['elements'] = 'At least one visible usable Bio Element is required before publication.';
        }
        foreach ($visibleElements as $index => $element) {
            $errors = array_merge($errors, $this->publicationElementErrors($element->draft, $index, $bioPage->workspace_id));
        }
        $theme = $this->themes->withDefaults($bioPage->draft['theme'] ?? []);
        if ($this->themes->contrastRatio($theme['textColor'], $theme['backgroundColor']) < 4.5
            || ($theme['backgroundType'] === 'gradient' && $this->themes->contrastRatio($theme['textColor'], $theme['gradientColor']) < 4.5)) {
            $errors['theme.textColor'] = 'Text and background colors must have a contrast ratio of at least 4.5:1.';
        }
        $destinationBackgrounds = in_array($theme['destinationStyle'], ['outline', 'transparent'], true)
            ? [$theme['backgroundColor'], ...($theme['backgroundType'] === 'gradient' ? [$theme['gradientColor']] : [])]
            : [$theme['destinationColor']];
        if (collect($destinationBackgrounds)->contains(
            fn (string $background) => $this->themes->contrastRatio($theme['destinationTextColor'], $background) < 4.5
        )) {
            $errors['theme.destinationTextColor'] = 'Destination text and destination colors must have a contrast ratio of at least 4.5:1.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($bioPage): BioPage {
            $bioPage->forceFill([
                'published_domain_id' => $bioPage->draft_domain_id,
                'published_slug' => $bioPage->draft_slug,
                'published' => $bioPage->draft,
                'published_at' => now(),
            ])->save();

            foreach ($bioPage->elements as $element) {
                $element->forceFill([
                    'published' => $element->draft,
                    'published_position' => $element->draft === null ? null : $element->position,
                ])->save();
            }

            $bioPage->elements()
                ->whereNull('draft')
                ->whereNull('published')
                ->delete();

            $this->registry->syncBioPage($bioPage);

            return $bioPage->refresh();
        });
    }

    public function unpublish(Request $request, BioPage $bioPage): BioPage
    {
        $this->requireManageable($request, $bioPage, 'unpublish');
        $bioPage->update(['published_at' => null]);

        return $bioPage;
    }

    public function delete(Request $request, BioPage $bioPage): void
    {
        $this->requireManageable($request, $bioPage, 'delete');

        $paths = collect([$bioPage->draft, $bioPage->published])
            ->filter()
            ->flatMap(fn (array $version) => Arr::only($version, ['profileImagePath', 'backgroundImagePath']))
            ->filter()
            ->unique();

        DB::transaction(fn () => $bioPage->delete());
        Storage::disk('public')->delete($paths->all());
    }

    /** @return array<string, mixed> */
    public function storeMedia(Request $request, BioPage $bioPage): array
    {
        $workspace = $this->access->requireCurrent($request);
        abort_unless($bioPage->workspace_id === $workspace->id && $request->user()->can('update', $bioPage), 403);
        $data = $request->validate([
            'profileImage' => ['nullable', 'image', 'max:5120'],
            'backgroundImage' => ['nullable', 'image', 'max:10240'],
        ]);

        $draft = $bioPage->draft;
        foreach (['profileImage' => 'profileImagePath', 'backgroundImage' => 'backgroundImagePath'] as $input => $key) {
            if ($request->hasFile($input)) {
                $oldPath = $draft[$key] ?? null;
                $draft[$key] = $request->file($input)->store('bio-pages/'.$bioPage->id, 'public');
                if ($oldPath && $oldPath !== ($bioPage->published[$key] ?? null)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }
        $bioPage->update(['draft' => $draft]);

        return $draft;
    }

    public function deleteMedia(Request $request, BioPage $bioPage, string $type): void
    {
        $workspace = $this->access->requireCurrent($request);
        abort_unless($bioPage->workspace_id === $workspace->id && $request->user()->can('update', $bioPage), 403);
        abort_unless(in_array($type, ['profile', 'background'], true), 404);
        $key = $type === 'profile' ? 'profileImagePath' : 'backgroundImagePath';
        $draft = $bioPage->draft;
        $oldPath = $draft[$key] ?? null;
        $draft[$key] = null;
        $bioPage->update(['draft' => $draft]);

        if ($oldPath && $oldPath !== ($bioPage->published[$key] ?? null)) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    /** @return array<string, mixed> */
    private function validateDraft(Request $request, bool $updating = false): array
    {
        $sometimes = $updating ? 'sometimes' : 'nullable';
        $data = $request->validate([
            'domainId' => [$sometimes, 'nullable', 'integer'],
            'slug' => [$sometimes, 'nullable', 'string', 'max:512'],
            'displayName' => [$updating ? 'sometimes' : 'required', 'string', 'max:80'],
            'publicHandle' => ['sometimes', 'nullable', 'string', 'max:30'],
            'biography' => ['sometimes', 'nullable', 'string', 'max:160'],
            'theme' => ['sometimes', 'array'],
            'theme.appearance' => ['sometimes', Rule::in(['light', 'dark', 'auto'])],
            'theme.backgroundType' => ['sometimes', Rule::in(['color', 'gradient', 'image'])],
            'theme.backgroundColor' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.gradientColor' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.textColor' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.destinationColor' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.destinationTextColor' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.destinationStyle' => ['sometimes', Rule::in(['solid', 'outline', 'soft', 'transparent'])],
            'theme.destinationRadius' => ['sometimes', Rule::in(['square', 'rounded', 'large', 'pill'])],
            'theme.destinationShadow' => ['sometimes', 'boolean'],
            'theme.profileShape' => ['sometimes', Rule::in(['circle', 'rounded', 'square'])],
            'theme.fontFamily' => ['sometimes', Rule::in(['sans', 'serif', 'rounded', 'mono'])],
            'theme.imageFit' => ['sometimes', Rule::in(['cover', 'contain'])],
            'theme.overlayOpacity' => ['sometimes', 'integer', 'between:0,100'],
            'shareTitle' => ['sometimes', 'nullable', 'string', 'max:80'],
            'shareDescription' => ['sometimes', 'nullable', 'string', 'max:160'],
            'isIndexable' => ['sometimes', 'boolean'],
            'showBranding' => ['sometimes', 'boolean'],
            'elements' => ['sometimes', 'array', 'max:50'],
            'elements.*.clientId' => ['required', 'string', 'max:100', 'distinct'],
            'elements.*.type' => ['required', Rule::in(['destination', 'social', 'heading', 'text'])],
            'elements.*.label' => ['nullable', 'string', 'max:80'],
            'elements.*.text' => ['nullable', 'string', 'max:300'],
            'elements.*.sourceType' => ['nullable', Rule::in(['external', 'short_link', 'email', 'telephone'])],
            'elements.*.url' => ['nullable', 'string', 'max:2048'],
            'elements.*.shortLinkId' => ['nullable', 'integer'],
            'elements.*.socialService' => ['nullable', 'string', 'max:50'],
            'elements.*.presentation' => ['nullable', Rule::in(['icon', 'button'])],
            'elements.*.visible' => ['required', 'boolean'],
            'elements.*.openInNewTab' => ['required', 'boolean'],
        ]);

        $this->validateElements($data['elements'] ?? [], $this->access->requireCurrent($request)->id);

        return $data;
    }

    /** @param list<array<string, mixed>> $elements */
    private function validateElements(array $elements, int $workspaceId): void
    {
        $errors = [];
        foreach ($elements as $index => $element) {
            if (($element['type'] ?? null) === BioElement::TYPE_HEADING && mb_strlen((string) ($element['text'] ?? '')) > 80) {
                $errors["elements.$index.text"] = 'Section headings may not be greater than 80 characters.';
            }
            $sourceType = $element['sourceType'] ?? 'external';
            if ($sourceType === 'short_link' && filled($element['shortLinkId'] ?? null)) {
                $valid = ShortLink::query()->whereKey($element['shortLinkId'] ?? 0)->where('workspace_id', $workspaceId)->exists();
                if (! $valid) {
                    $errors["elements.$index.shortLinkId"] = 'Select a Short Link from this Workspace.';
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $existing */
    private function draftFrom(array $data, array $existing = []): array
    {
        return array_merge([
            'displayName' => '',
            'publicHandle' => '',
            'biography' => '',
            'profileImagePath' => null,
            'backgroundImagePath' => null,
            'theme' => [],
            'shareTitle' => '',
            'shareDescription' => '',
            'isIndexable' => true,
            'showBranding' => true,
        ], $existing, Arr::only($data, [
            'displayName', 'publicHandle', 'biography', 'theme', 'shareTitle', 'shareDescription', 'isIndexable', 'showBranding',
        ]));
    }

    /** @param list<array<string, mixed>> $elements */
    private function syncElements(BioPage $bioPage, array $elements): void
    {
        $clientIds = collect($elements)->pluck('clientId');
        $bioPage->elements()->whereNotIn('client_id', $clientIds)->update(['draft' => null, 'position' => null]);

        foreach ($elements as $position => $element) {
            $bioPage->elements()->updateOrCreate(
                ['client_id' => $element['clientId']],
                ['position' => $position, 'draft' => $element],
            );
        }

        $bioPage->elements()->whereNull('draft')->whereNull('published')->delete();
    }

    private function domainFor(int $workspaceId, mixed $domainId): ?Domain
    {
        if (! filled($domainId)) {
            return null;
        }

        return Domain::query()->whereKey((int) $domainId)
            ->where(fn ($query) => $query->where('workspace_id', $workspaceId)->orWhere('is_default', true))
            ->first();
    }

    private function requireManageable(Request $request, BioPage $bioPage, string $ability): void
    {
        $workspace = $this->access->requireCurrent($request);
        abort_unless($bioPage->workspace_id === $workspace->id && $request->user()->can($ability, $bioPage), 403);
    }

    /** @param array<string, mixed> $element @return array<string, string> */
    private function publicationElementErrors(array $element, int $index, int $workspaceId): array
    {
        if (in_array($element['type'] ?? null, ['heading', 'text'], true)) {
            return filled($element['text'] ?? null)
                ? []
                : ["elements.$index.text" => 'This Bio Element requires text before publication.'];
        }

        $errors = [];
        if (! filled($element['label'] ?? null)) {
            $errors["elements.$index.label"] = 'A destination label is required before publication.';
        }
        $sourceType = $element['sourceType'] ?? 'external';
        $url = (string) ($element['url'] ?? '');
        if ($sourceType === 'short_link') {
            if (! ShortLink::query()->whereKey($element['shortLinkId'] ?? 0)->where('workspace_id', $workspaceId)->exists()) {
                $errors["elements.$index.shortLinkId"] = 'Select a Short Link from this Workspace.';
            }
        } elseif ($sourceType === 'external' && (! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true))) {
            $errors["elements.$index.url"] = 'Enter a valid HTTP or HTTPS URL.';
        } elseif ($sourceType === 'email' && ! filter_var($url, FILTER_VALIDATE_EMAIL)) {
            $errors["elements.$index.url"] = 'Enter a valid email address.';
        } elseif ($sourceType === 'telephone' && ! preg_match('/^\+?[0-9(). -]{5,30}$/', $url)) {
            $errors["elements.$index.url"] = 'Enter a valid telephone number.';
        }

        return $errors;
    }
}
