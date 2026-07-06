<?php

namespace App\Actions\Domains;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateDomain
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, string $hostname): Domain
    {
        $workspace = $this->access->requireManagedWorkspace($request);
        $hostname = strtolower(preg_replace('/^https?:\/\//', '', trim($hostname)));
        $hostname = trim($hostname, '/');

        return Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_PENDING,
            'verification_token' => Str::random(40),
        ]);
    }
}
