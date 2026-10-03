<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Settings\UpdateInstanceSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateInstanceSettingsRequest;
use App\Services\InstanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class InstanceSettingsController extends Controller
{
    public function show(InstanceSettings $settings): JsonResponse
    {
        Gate::authorize('administer-instance');

        return response()->json(['data' => $settings->all()]);
    }

    public function update(UpdateInstanceSettingsRequest $request, InstanceSettings $settings, UpdateInstanceSettings $updater): JsonResponse
    {
        $updater->handle($request->user(), $request->validated());

        return response()->json(['data' => $settings->all()]);
    }
}
