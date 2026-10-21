<?php

namespace App\Actions\AppAssociations;

use App\Models\AppAssociation;
use App\Models\Domain;
use App\Models\User;
use App\Services\AppAssociations\AssociationFiles;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Validation\ValidationException;

class ManageAppAssociations
{
    public function __construct(
        private readonly Gate $gate,
        private readonly AssociationFiles $files,
    ) {}

    public function add(User $actor, Domain $domain, array $attributes): AppAssociation
    {
        $this->gate->forUser($actor)->authorize('manage', $domain);

        if (! $this->files->canPublish($domain)) {
            throw ValidationException::withMessages([
                'domain' => __('openlink.validation.app_association_domain_unavailable'),
            ]);
        }

        return $domain->appAssociations()->create($attributes);
    }

    public function remove(User $actor, AppAssociation $association): void
    {
        $this->gate->forUser($actor)->authorize('manage', $association->domain);

        $association->delete();
    }
}
