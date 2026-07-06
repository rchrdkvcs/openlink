<?php

namespace App\Http\Controllers;

use App\Actions\Settings\UpdateInstanceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstanceSettingsController extends Controller
{
    public function update(Request $request, UpdateInstanceSettings $updater): RedirectResponse
    {
        $data = $request->validate([
            'registration_mode' => ['required', 'in:closed,invite_only,open'],
            'default_domain' => ['required', 'string', 'max:255'],
            'slug_length' => ['required', 'integer', 'min:4', 'max:32'],
            'analytics_retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'reserved_slugs' => ['nullable', 'string'],
            'reserved_prefixes' => ['nullable', 'string'],
            'public_unavailable_title' => ['required', 'string', 'max:120'],
            'public_unavailable_message' => ['required', 'string', 'max:500'],
        ]);

        $updater->handle($request, $data);

        return back();
    }
}
