<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Settings\UpdateInstanceSettings;
use App\Http\Controllers\Controller;
use App\Services\InstanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstanceSettingsController extends Controller
{
    public function show(Request $request, InstanceSettings $settings): JsonResponse
    {
        abort_unless($request->user()?->is_instance_admin, 403);

        return response()->json(['data' => $settings->all()]);
    }

    public function update(Request $request, InstanceSettings $settings, UpdateInstanceSettings $updater): JsonResponse
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

        return response()->json(['data' => $settings->all()]);
    }
}
