<?php

namespace App\Services\AppAssociations;

use App\Models\AppAssociation;
use App\Models\Domain;
use App\Services\ApplicationHost;
use Illuminate\Support\Collection;

class AssociationFiles
{
    public const RESERVED_PATHS = ['/.well-known/*', '/qr/*', '/password/*'];

    public function __construct(private readonly ApplicationHost $applicationHost) {}

    public function canPublish(Domain $domain): bool
    {
        return $domain->workspace_id !== null
            && ! $domain->is_default
            && $domain->isUsable()
            && ! $this->applicationHost->isApplicationDomain($domain);
    }

    public function publishingDomain(string $host): ?Domain
    {
        $domain = Domain::query()->where('hostname', Domain::normalizeHostname($host))->first();

        return $domain && $this->canPublish($domain) ? $domain : null;
    }

    public function apple(Domain $domain): ?array
    {
        $associations = $this->associations($domain, AppAssociation::PLATFORM_IOS);

        if ($associations->isEmpty()) {
            return null;
        }

        return [
            'applinks' => [
                'details' => $associations->map(fn (AppAssociation $association) => [
                    'appIDs' => [$association->appId()],
                    'components' => [
                        ...array_map(fn (string $path) => ['/' => $path, 'exclude' => true], self::RESERVED_PATHS),
                        ...array_map(fn (string $path) => ['/' => $path], $association->paths ?? []),
                    ],
                ])->all(),
            ],
        ];
    }

    public function android(Domain $domain): ?array
    {
        $associations = $this->associations($domain, AppAssociation::PLATFORM_ANDROID);

        if ($associations->isEmpty()) {
            return null;
        }

        return $associations->map(fn (AppAssociation $association) => [
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $association->identifier,
                'sha256_cert_fingerprints' => $association->sha256_cert_fingerprints ?? [],
            ],
        ])->all();
    }

    private function associations(Domain $domain, string $platform): Collection
    {
        return $domain->appAssociations()
            ->where('platform', $platform)
            ->orderBy('id')
            ->get();
    }
}
