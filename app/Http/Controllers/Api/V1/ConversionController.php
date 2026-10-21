<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conversions\RecordConversion;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConversionController extends Controller
{
    public function store(Request $request, CurrentWorkspace $current, RecordConversion $conversions): JsonResponse
    {
        $workspace = $current->require('editContent');
        $this->rejectExtraFields($request);
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        [$status, $attribution] = $conversions->handle($request->user(), $workspace, $data['token']);

        return response()->json([
            'data' => [
                'status' => $status,
                'short_link_id' => $attribution->short_link_id,
                'conversion_goal' => $attribution->shortLink->conversion_goal,
                'qr_code_id' => $attribution->qr_code_id,
                'routing_rule_id' => $attribution->routing_rule_id,
                'routing_variant_id' => $attribution->routing_variant_id,
                'converted_at' => $attribution->converted_at->toIso8601String(),
            ],
        ], $status === RecordConversion::RECORDED ? 201 : 200);
    }

    private function rejectExtraFields(Request $request): void
    {
        $extra = array_diff(array_keys($request->all()), ['token']);

        if ($extra !== []) {
            throw ValidationException::withMessages(array_fill_keys(
                array_values($extra),
                'Conversions accept only an attribution token.',
            ));
        }
    }
}
