<?php

namespace App\Policies;

use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\User;

class QrCodePolicy
{
    public function __construct(
        private readonly WorkspacePolicy $workspaces,
        private readonly ShortLinkPolicy $shortLinks,
    ) {}

    public function view(User $user, QrCode $qrCode): bool
    {
        if ($qrCode->short_link_id) {
            $shortLink = $this->linkedShortLink($qrCode);

            return $shortLink !== null && $this->shortLinks->view($user, $shortLink);
        }

        return $qrCode->workspace !== null && $this->workspaces->view($user, $qrCode->workspace);
    }

    public function update(User $user, QrCode $qrCode): bool
    {
        if ($qrCode->short_link_id) {
            $shortLink = $this->linkedShortLink($qrCode);

            return $shortLink !== null && $this->shortLinks->update($user, $shortLink);
        }

        return $qrCode->workspace !== null && $this->workspaces->editContent($user, $qrCode->workspace);
    }

    public function delete(User $user, QrCode $qrCode): bool
    {
        return $this->update($user, $qrCode);
    }

    private function linkedShortLink(QrCode $qrCode): ?ShortLink
    {
        return ShortLink::query()
            ->whereKey($qrCode->short_link_id)
            ->where('workspace_id', $qrCode->workspace_id)
            ->first();
    }
}
