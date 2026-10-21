<?php

namespace App\Actions\AppAssociations;

use App\Models\AppAssociation;
use App\Models\Domain;
use App\Services\AppAssociations\AssociationFiles;

class AppAssociationPayload
{
    public function __construct(private readonly AssociationFiles $files) {}

    public function forDomain(Domain $domain): array
    {
        $publishing = $this->files->canPublish($domain);

        return [
            'domain' => [
                'id' => $domain->id,
                'hostname' => $domain->hostname,
                'status' => $domain->status->value,
                'can_publish' => $publishing,
            ],
            'associations' => $domain->appAssociations()
                ->orderBy('id')
                ->get()
                ->map(fn (AppAssociation $association) => [
                    'id' => $association->id,
                    'platform' => $association->platform,
                    'app_id' => $association->appId(),
                    'team_id' => $association->team_id,
                    'identifier' => $association->identifier,
                    'paths' => $association->paths,
                    'sha256_cert_fingerprints' => $association->sha256_cert_fingerprints,
                ])
                ->all(),
            'files' => [
                'apple' => [
                    'url' => 'https://'.$domain->hostname.'/.well-known/apple-app-site-association',
                    'content' => $publishing ? $this->files->apple($domain) : null,
                ],
                'android' => [
                    'url' => 'https://'.$domain->hostname.'/.well-known/assetlinks.json',
                    'content' => $publishing ? $this->files->android($domain) : null,
                ],
            ],
            'reserved_paths' => AssociationFiles::reservedPaths(),
        ];
    }
}
