<?php

namespace App\Actions\Pages;

use App\Models\User;
use App\Models\Workspace;
use App\Services\InstanceSettings;

class SettingsPagePayload
{
    public function __construct(
        private readonly WorkspaceShellPayload $shell,
        private readonly InstanceSettings $settings,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Workspace $workspace, User $user): array
    {
        return [
            ...$this->shell->handle($workspace, $user),
            'settings' => $user->is_instance_admin ? $this->settings->all() : [],
        ];
    }
}
