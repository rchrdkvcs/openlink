<?php

namespace App\Http\Controllers;

use App\Services\UpdateStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InstanceUpdateController extends Controller
{
    public function store(Request $request, UpdateStatus $updates): RedirectResponse
    {
        Gate::authorize('administer-instance');

        $status = $updates->get();
        abort_unless($status['canUpdate'], 403);
        abort_unless($status['available'], 409);
        abort_if(in_array($status['state'], ['pending', 'running'], true), 409);

        abort_if(file_put_contents(storage_path('app/update-request'), "update\n", LOCK_EX) === false, 500);

        return back();
    }
}
