<?php

namespace App\Http\Controllers;

use App\Actions\ShortLinks\ArchiveShortLink;
use App\Actions\ShortLinks\CreateShortLink;
use App\Actions\ShortLinks\DeleteShortLink;
use App\Actions\ShortLinks\MoveShortLink;
use App\Actions\ShortLinks\UpdateShortLink;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\ShortLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShortLinkController extends Controller
{
    public function store(Request $request, WorkspaceAccess $access, CreateShortLink $shortLinks): RedirectResponse
    {
        $workspace = $access->requireEditableWorkspace($request);

        $data = $request->validate([
            'domain_id' => ['required', 'integer'],
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'slug' => ['nullable', 'string', 'max:512'],
            'destination_url' => ['required', 'url:http,https'],
            'fallback_url' => ['nullable', 'url:http,https'],
            'is_enabled' => ['nullable', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'visit_limit' => ['nullable', 'integer', 'min:1'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $shortLinks->handle($workspace, $request->user(), $data);

        return back();
    }

    public function update(Request $request, ShortLink $shortLink, WorkspaceAccess $access, UpdateShortLink $shortLinks): RedirectResponse
    {
        $workspace = $access->requireEditableShortLink($request, $shortLink);

        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'destination_url' => ['required', 'url:http,https'],
            'fallback_url' => ['nullable', 'url:http,https'],
            'is_enabled' => ['required', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'visit_limit' => ['nullable', 'integer', 'min:1'],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
        ]);

        $shortLinks->handle($request, $shortLink, $data);

        return back();
    }

    public function archive(Request $request, ShortLink $shortLink, ArchiveShortLink $archive): RedirectResponse
    {
        $archive->handle($request, $shortLink);

        return back();
    }

    public function move(Request $request, ShortLink $shortLink, WorkspaceAccess $access, MoveShortLink $move): RedirectResponse
    {
        $workspace = $access->requireEditableShortLink($request, $shortLink);

        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $move->handle($request, $shortLink, $data['folder_id'] ?? null);

        return back();
    }

    public function destroy(Request $request, ShortLink $shortLink, DeleteShortLink $delete): RedirectResponse
    {
        $delete->handle($request, $shortLink);

        return back();
    }
}
