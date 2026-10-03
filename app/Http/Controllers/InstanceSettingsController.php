<?php

namespace App\Http\Controllers;

use App\Actions\Settings\UpdateInstanceSettings;
use App\Http\Requests\Settings\UpdateInstanceSettingsRequest;
use Illuminate\Http\RedirectResponse;

class InstanceSettingsController extends Controller
{
    public function update(UpdateInstanceSettingsRequest $request, UpdateInstanceSettings $updater): RedirectResponse
    {
        $updater->handle($request->user(), $request->validated());

        return back();
    }
}
