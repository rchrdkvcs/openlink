<?php

namespace Tests\Support;

trait CreatesQrCodes
{
    use CreatesWorkspaces;

    protected function linkWithQrCode(): array
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'promo', ['destination_url' => 'https://example.com/destination']);
        $qrCode = $link->qrCodes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Poster',
            'token' => 'studio-qr-token',
        ]);

        return [$workspace, $domain, $user, $qrCode->fresh(), $link];
    }
}
