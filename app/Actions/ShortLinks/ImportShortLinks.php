<?php

namespace App\Actions\ShortLinks;

use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ShortLinks\Csv\LinkCsvFile;
use App\Services\ShortLinks\Csv\LinkCsvFormat;
use App\Services\ShortLinks\ShortUrlAddress;
use App\Services\SlugService;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportShortLinks
{
    private const MAX_VISIT_LIMIT = 2147483647;

    private array $domains = [];

    public function __construct(
        private readonly Gate $gate,
        private readonly AvailableDomains $available,
        private readonly SlugService $slugs,
        private readonly ShortLinkMutation $shortLinks,
    ) {}

    public function preview(User $user, Workspace $workspace, LinkCsvFile $file, ?array $mapping, ?int $domainId): array
    {
        $this->gate->forUser($user)->authorize('editContent', $workspace);

        $mapping = $file->mapping($mapping);
        $plans = $this->plan($workspace, $file, $mapping, $domainId);
        $folders = $workspace->folders()->pluck('name')->all();
        $tags = $workspace->tags()->pluck('name')->all();
        $ready = array_filter($plans, fn (array $plan) => $plan['errors'] === []);

        return [
            'headers' => $file->headers,
            'mapping' => $mapping,
            'rows' => array_map(fn (array $plan) => $this->outcome($plan, $plan['errors'] === [] ? 'ready' : 'invalid'), $plans),
            'summary' => [
                'total' => count($plans),
                'ready' => count($ready),
                'invalid' => count($plans) - count($ready),
                'new_folders' => array_values(array_diff(array_unique(array_filter(array_column(array_column($ready, 'values'), 'folder'))), $folders)),
                'new_tags' => array_values(array_diff(array_unique(array_merge(...array_column($ready, 'tags'))), $tags)),
            ],
        ];
    }

    public function import(User $user, Workspace $workspace, LinkCsvFile $file, ?array $mapping, ?int $domainId): array
    {
        $this->gate->forUser($user)->authorize('editContent', $workspace);

        $mapping = $file->mapping($mapping);
        $plans = $this->plan($workspace, $file, $mapping, $domainId);

        $rows = DB::transaction(fn () => array_map(
            fn (array $plan) => $plan['errors'] === [] ? $this->create($user, $workspace, $plan) : $this->outcome($plan, 'skipped'),
            $plans,
        ));

        $created = count(array_filter($rows, fn (array $row) => $row['status'] === 'created'));

        return [
            'rows' => $rows,
            'summary' => ['total' => count($rows), 'created' => $created, 'skipped' => count($rows) - $created],
        ];
    }

    private function plan(Workspace $workspace, LinkCsvFile $file, array $mapping, ?int $domainId): array
    {
        $this->domains = [];
        $addresses = [];
        $fingerprints = [];
        $plans = [];

        foreach ($file->rows as $offset => $row) {
            $plan = $this->planRow($workspace, $file->values($row, $mapping), $domainId);
            $plan['row'] = $offset + LinkCsvFile::FIRST_ROW_NUMBER;

            if ($plan['errors'] === []) {
                $key = $plan['slug'] !== null ? $plan['domain']->id.'/'.$plan['slug'] : null;
                $earlier = $key !== null ? ($addresses[$key] ?? null) : ($fingerprints[$plan['fingerprint']] ?? null);

                if ($earlier !== null) {
                    $plan['errors'][] = sprintf('Duplicate of row %d.', $earlier);
                } elseif ($key !== null) {
                    $addresses[$key] = $plan['row'];
                } else {
                    $fingerprints[$plan['fingerprint']] = $plan['row'];
                }
            }

            $plans[] = $plan;
        }

        return $plans;
    }

    private function planRow(Workspace $workspace, array $values, ?int $domainId): array
    {
        $plan = ['values' => $values, 'domain' => null, 'slug' => null, 'fingerprint' => null, 'tags' => LinkCsvFormat::parseTags($values['tags']), 'errors' => []];
        $errors = $this->fieldErrors($values);
        $enabled = $values['enabled'] === '' ? true : LinkCsvFormat::parseBoolean($values['enabled']);
        $archived = $values['archived'] === '' ? false : LinkCsvFormat::parseBoolean($values['archived']);
        $activatesAt = $this->date($values['activates_at'], 'Activation', $errors);
        $expiresAt = $this->date($values['expires_at'], 'Expiration', $errors);

        if ($values['format_version'] !== '' && $values['format_version'] !== LinkCsvFormat::VERSION) {
            $errors[] = sprintf('Format version %s is not supported. Use version %s.', $values['format_version'], LinkCsvFormat::VERSION);
        }

        if ($enabled === null) {
            $errors[] = 'Enabled must be true or false.';
        }

        if ($archived === null) {
            $errors[] = 'Archived must be true or false.';
        }

        if ($activatesAt && $expiresAt && $expiresAt->lessThanOrEqualTo($activatesAt)) {
            $errors[] = 'Expiration must be after activation.';
        }

        try {
            $plan['domain'] = $this->domain($workspace, $values['domain'], $domainId);

            if ($values['slug'] !== '') {
                $plan['slug'] = $this->slugs->validateCustom($plan['domain'], $values['slug']);
                $address = new ShortUrlAddress($plan['domain'], $plan['slug']);

                if ($values['destination_url'] !== '' && $address->isTargetedBy($values['destination_url'])) {
                    $errors[] = 'Destination URL cannot point to the same short URL.';
                }
            }
        } catch (ValidationException $exception) {
            $errors = [...$errors, ...Arr::flatten($exception->errors())];
        }

        $plan['data'] = [
            'domain_id' => $plan['domain']?->id,
            'slug' => $plan['slug'],
            'destination_url' => $values['destination_url'],
            'fallback_url' => $values['fallback_url'] !== '' ? $values['fallback_url'] : null,
            'is_enabled' => $enabled,
            'activates_at' => $activatesAt,
            'expires_at' => $expiresAt,
            'visit_limit' => $values['visit_limit'] !== '' ? (int) $values['visit_limit'] : null,
            'tags' => implode(',', $plan['tags']),
        ];
        $plan['archived'] = $archived;
        $plan['folder'] = $values['folder'] !== '' ? $values['folder'] : null;

        if ($errors === [] && $plan['slug'] === null) {
            $plan['fingerprint'] = $this->fingerprint($plan);

            if ($workspace->shortLinks()->where('import_fingerprint', $plan['fingerprint'])->exists()) {
                $errors[] = 'This link was already imported.';
            }
        }

        $plan['errors'] = array_values(array_unique($errors));

        return $plan;
    }

    private function fieldErrors(array $values): array
    {
        return Arr::flatten(Validator::make($values, [
            'destination_url' => ['required', 'url:http,https', 'max:2048'],
            'fallback_url' => ['nullable', 'url:http,https', 'max:2048'],
            'folder' => ['nullable', 'string', 'max:120', 'not_regex:/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/'],
            'tags' => ['nullable', 'string', 'max:500', 'not_regex:/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/'],
            'visit_limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_VISIT_LIMIT],
        ], [], [
            'destination_url' => 'destination URL',
            'fallback_url' => 'fallback URL',
            'visit_limit' => 'visit limit',
        ])->errors()->toArray());
    }

    private function date(string $value, string $label, array &$errors): mixed
    {
        if ($value === '') {
            return null;
        }

        $date = LinkCsvFormat::parseDate($value);

        if ($date === null) {
            $errors[] = sprintf('%s date must use ISO 8601, for example 2026-10-21T09:00:00Z.', $label);
        }

        return $date;
    }

    private function domain(Workspace $workspace, string $hostname, ?int $domainId): Domain
    {
        if ($hostname === '') {
            return $this->domains[''] ??= $this->available->requireUsable($workspace, $domainId);
        }

        $hostname = Domain::normalizeHostname($hostname);
        $domain = $this->domains[$hostname] ??= Domain::query()
            ->where('hostname', $hostname)
            ->where(fn ($query) => $query->where('workspace_id', $workspace->id)->orWhere('is_default', true))
            ->first() ?? false;

        if ($domain === false) {
            throw ValidationException::withMessages(['domain' => sprintf(
                'The domain %s is not connected to this workspace. Leave the domain empty or choose a domain for the import to use one you control.',
                $hostname,
            )]);
        }

        if (! $domain->isUsable()) {
            throw ValidationException::withMessages(['domain' => sprintf('The domain %s is not active yet.', $hostname)]);
        }

        return $domain;
    }

    private function fingerprint(array $plan): string
    {
        $data = $plan['data'];

        return hash('sha256', json_encode([
            $data['domain_id'],
            $data['destination_url'],
            $data['fallback_url'],
            $plan['folder'],
            $data['tags'],
            $data['is_enabled'],
            $plan['archived'],
            LinkCsvFormat::date($data['activates_at']),
            LinkCsvFormat::date($data['expires_at']),
            $data['visit_limit'],
        ]));
    }

    private function create(User $user, Workspace $workspace, array $plan): array
    {
        try {
            $link = DB::transaction(function () use ($user, $workspace, $plan): ShortLink {
                $folder = $plan['folder'] !== null
                    ? Folder::query()->firstOrCreate(['workspace_id' => $workspace->id, 'name' => $plan['folder']])
                    : null;

                $link = $this->shortLinks->create($user, $workspace, [...$plan['data'], 'folder_id' => $folder?->id]);
                $link->forceFill([
                    'archived_at' => $plan['archived'] ? now() : null,
                    'import_fingerprint' => $plan['fingerprint'],
                ])->save();

                return $link;
            });
        } catch (ValidationException $exception) {
            return $this->outcome([...$plan, 'errors' => Arr::flatten($exception->errors())], 'skipped');
        } catch (QueryException) {
            return $this->outcome([...$plan, 'errors' => ['Another link with this address or content was created at the same time.']], 'skipped');
        }

        return $this->outcome([...$plan, 'domain' => $link->domain, 'slug' => $link->slug], 'created');
    }

    private function outcome(array $plan, string $status): array
    {
        return [
            'row' => $plan['row'],
            'status' => $status,
            'domain' => $plan['domain']?->hostname ?? ($plan['values']['domain'] !== '' ? $plan['values']['domain'] : null),
            'slug' => $plan['slug'],
            'short_url' => $plan['slug'] !== null && $plan['domain'] ? 'https://'.$plan['domain']->hostname.'/'.$plan['slug'] : null,
            'destination_url' => $plan['values']['destination_url'],
            'errors' => $plan['errors'],
        ];
    }
}
