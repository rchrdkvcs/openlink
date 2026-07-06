<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Analytics\Outcome;
use App\Services\InstanceSettings;
use App\Services\PublicResolutionService;
use App\Services\ResolutionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicLinkController extends Controller
{
    public function unavailable(Request $request, InstanceSettings $settings): Response
    {
        return $this->toResponse($request, new ResolutionResult(Outcome::NOT_FOUND), $settings);
    }

    public function show(Request $request, string $slug, PublicResolutionService $resolver, InstanceSettings $settings): Response
    {
        return $this->toResponse($request, $resolver->resolve($request, $slug), $settings);
    }

    public function qr(Request $request, QrCode $qrCode, PublicResolutionService $resolver, InstanceSettings $settings): Response
    {
        $qrCode->load('shortLink.domain');

        return $this->toResponse($request, $resolver->resolve($request, $qrCode->shortLink->slug, $qrCode), $settings);
    }

    public function password(Request $request, ShortLink $shortLink, PublicResolutionService $resolver, AnalyticsRecorder $analytics, InstanceSettings $settings): Response
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'qr_code_id' => ['nullable', 'integer'],
        ]);

        $qrCode = filled($data['qr_code_id'] ?? null) ? QrCode::query()->find($data['qr_code_id']) : null;

        if (! $shortLink->password_hash || ! Hash::check($data['password'], $shortLink->password_hash)) {
            $analytics->record($request, $shortLink, $qrCode, $qrCode ? AnalyticsRecorder::METRIC_SCAN : AnalyticsRecorder::METRIC_VISIT, Outcome::PASSWORD_FAILED);

            return Inertia::render('Public/Password', [
                'shortLinkId' => $shortLink->id,
                'qrCodeId' => $qrCode?->id,
                'error' => 'The password is incorrect.',
            ])->toResponse($request)->setStatusCode(403);
        }

        $request->session()->put($resolver->passwordSessionKey($shortLink), true);

        return $this->toResponse($request, $resolver->resolveShortLink($request, $shortLink, $qrCode), $settings);
    }

    private function toResponse(Request $request, ResolutionResult $result, InstanceSettings $settings): Response
    {
        if ($result->requiresPassword) {
            return Inertia::render('Public/Password', [
                'shortLinkId' => $result->shortLink?->id,
                'qrCodeId' => $result->qrCode?->id,
                'error' => null,
            ])->toResponse($request);
        }

        if ($result->redirectUrl) {
            if ($request->header('X-Inertia')) {
                return Inertia::location($result->redirectUrl);
            }

            return redirect()->away($result->redirectUrl);
        }

        return Inertia::render('Public/Unavailable', [
            'title' => $settings->get('public_unavailable_title'),
            'message' => $settings->get('public_unavailable_message'),
        ])->toResponse($request)->setStatusCode(404);
    }
}
