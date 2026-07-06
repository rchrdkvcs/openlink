<?php

namespace App\Actions\Domains;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use Illuminate\Http\Request;

class DeleteDomain
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, Domain $domain): void
    {
        $this->access->requireManagedDomain($request, $domain);
        abort_if($domain->is_default, 403);

        $domain->delete();
    }
}
