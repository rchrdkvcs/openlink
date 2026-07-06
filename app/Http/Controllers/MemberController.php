<?php

namespace App\Http\Controllers;

use App\Actions\Members\LeaveWorkspace;
use App\Actions\Members\RemoveWorkspaceMember;
use App\Actions\Members\TransferWorkspaceOwnership;
use App\Actions\Members\UpdateMemberRole;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function update(Request $request, WorkspaceMember $member, UpdateMemberRole $roles): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([
                WorkspaceMember::ROLE_ADMIN,
                WorkspaceMember::ROLE_EDITOR,
                WorkspaceMember::ROLE_VIEWER,
            ])],
        ]);

        $roles->handle($request, $member, $data['role']);

        return back();
    }

    public function destroy(Request $request, WorkspaceMember $member, RemoveWorkspaceMember $remover): RedirectResponse
    {
        $remover->handle($request, $member);

        return back();
    }

    public function leave(Request $request, LeaveWorkspace $leaver): RedirectResponse
    {
        $leaver->handle($request);

        return redirect()->route('dashboard');
    }

    public function transferOwnership(Request $request, WorkspaceMember $member, TransferWorkspaceOwnership $transfer): RedirectResponse
    {
        $transfer->handle($request, $member);

        return back();
    }
}
