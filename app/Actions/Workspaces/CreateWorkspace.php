<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspace
{
    public function handle(User $owner, string $name, ?string $icon = null, ?string $color = null): Workspace
    {
        return DB::transaction(function () use ($owner, $name, $icon, $color) {
            $slug = Str::slug($name);
            $base = $slug ?: 'workspace';
            $i = 1;

            while (Workspace::query()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$i++;
            }

            $workspace = Workspace::create([
                'owner_id' => $owner->id,
                'name' => $name,
                'slug' => $slug,
                'icon' => $icon,
                'color' => $color,
                'settings' => [],
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $owner->id,
                'role' => WorkspaceMember::ROLE_OWNER,
            ]);

            return $workspace;
        });
    }
}
