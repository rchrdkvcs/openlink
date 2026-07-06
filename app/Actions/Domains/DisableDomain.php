<?php

namespace App\Actions\Domains;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use Illuminate\Http\Request;

class DisableDomain
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, Domain $domain): Domain
    {
        $this->access->requireManagedDomain($request, $domain);

        $domain->update([
            'status' => Domain::STATUS_DISABLED,
            'disabled_at' => now(),
        ]);

        return $domain;
    }
}
