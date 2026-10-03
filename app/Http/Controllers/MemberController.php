<?php

namespace App\Http\Controllers;

use App\Actions\Members\LeaveWorkspace;
use App\Actions\Members\RemoveWorkspaceMember;
use App\Actions\Members\TransferWorkspaceOwnership;
use App\Actions\Members\UpdateMemberRole;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Requests\Members\UpdateMemberRoleRequest;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function update(UpdateMemberRoleRequest $request, WorkspaceMember $member, UpdateMemberRole $roles): RedirectResponse
    {
        $roles->handle($request->user(), $member, $request->role());

        return back();
    }

    public function destroy(Request $request, WorkspaceMember $member, RemoveWorkspaceMember $remover): RedirectResponse
    {
        $remover->handle($request->user(), $member);

        return back();
    }

    public function leave(Request $request, CurrentWorkspace $current, LeaveWorkspace $leaver): RedirectResponse
    {
        $leaver->handle($request->user(), $current->require());
        $current->forget();

        return redirect()->route('dashboard');
    }

    public function transferOwnership(Request $request, WorkspaceMember $member, TransferWorkspaceOwnership $transfer): RedirectResponse
    {
        $transfer->handle($request->user(), $member);

        return back();
    }
}
