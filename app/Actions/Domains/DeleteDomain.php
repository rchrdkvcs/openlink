<?php

namespace App\Actions\Domains;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;

class DeleteDomain
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $actor, Domain $domain): void
    {
        $this->gate->forUser($actor)->authorize('manage', $domain);
        abort_if($domain->is_default, 403);

        $domain->delete();
    }
}
