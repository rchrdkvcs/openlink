<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\RecordAnalytics;
use App\Actions\BioPages\BioPagePayload;
use App\Actions\BioPages\ResolvePublicBioPage;
use App\Actions\Resolution\ResolvePublicLink;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\Analytics\Outcome;
use App\Services\InstanceSettings;
use App\Services\QrCodes\QrCodeContent;
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

    public function show(
        Request $request,
        string $slug,
        ResolvePublicLink $resolver,
        ResolvePublicBioPage $bioPages,
        BioPagePayload $bioPagePayload,
        InstanceSettings $settings,
    ): Response {
        if ($bioPage = $bioPages->resolve($request, $slug)) {
            $published = $bioPagePayload->published($bioPage);
            $response = Inertia::render('Public/BioPage', [
                'bioPage' => $published,
                'bioUrl' => 'https://'.$bioPage->publishedDomain->hostname.'/'.$bioPage->published_slug,
                'shareTitle' => $published['shareTitle'] ?: $published['displayName'],
                'shareDescription' => $published['shareDescription'] ?: $published['biography'],
                'openGraphImageUrl' => $published['profileImageUrl'] ?: route('public.bio.open-graph', [
                    'bioPage' => $bioPage,
                    'v' => $bioPage->published_at?->timestamp,
                ]),
            ])->toResponse($request);

            if (! $published['isIndexable']) {
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            }

            return $response;
        }

        return $this->toResponse($request, $resolver->resolve($request, $slug), $settings);
    }

    public function qr(Request $request, QrCode $qrCode, ResolvePublicLink $resolver, RecordAnalytics $analytics, InstanceSettings $settings, QrCodeContent $content): Response
    {
        if ($qrCode->bio_page_id) {
            $qrCode->loadMissing('bioPage.publishedDomain');
            $bioPage = $qrCode->bioPage;

            if (! $bioPage?->isPublished() || ! $bioPage->publishedDomain?->isUsable()) {
                return $this->toResponse($request, new ResolutionResult(Outcome::NOT_FOUND), $settings);
            }

            $analytics->recordBio(
                $request,
                $bioPage,
                RecordAnalytics::METRIC_SCAN,
                Outcome::SUCCESS,
                qrCode: $qrCode,
            );

            $bioUrl = 'https://'.$bioPage->publishedDomain->hostname.'/'.$bioPage->published_slug;

            return $request->header('X-Inertia')
                ? Inertia::location($bioUrl)
                : redirect()->away($bioUrl);
        }

        if ($qrCode->hasDirectPayload()) {
            if ($content->shouldRedirect($qrCode)) {
                if ($request->header('X-Inertia')) {
                    return Inertia::location($qrCode->content);
                }

                return redirect()->away($qrCode->content);
            }

            return Inertia::render('Public/QrCodePayload', [
                'name' => $qrCode->name,
                'payloadType' => $qrCode->payload_type,
                'payloadTypeLabel' => QrCodeContent::types()[$qrCode->payload_type] ?? 'QR code',
                'content' => $qrCode->content,
            ])->toResponse($request);
        }

        $qrCode->load('shortLink.domain');

        return $this->toResponse($request, $resolver->resolve($request, $qrCode->shortLink->slug, $qrCode), $settings);
    }

    public function password(Request $request, ShortLink $shortLink, ResolvePublicLink $resolver, RecordAnalytics $analytics, InstanceSettings $settings): Response
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'qr_code_id' => ['nullable', 'integer'],
        ]);

        $qrCode = filled($data['qr_code_id'] ?? null) ? QrCode::query()->find($data['qr_code_id']) : null;

        if (! $shortLink->password_hash || ! Hash::check($data['password'], $shortLink->password_hash)) {
            $analytics->record($request, $shortLink, $qrCode, $qrCode ? RecordAnalytics::METRIC_SCAN : RecordAnalytics::METRIC_VISIT, Outcome::PASSWORD_FAILED);

            return Inertia::render('Public/Password', [
                'shortLinkId' => $shortLink->id,
                'qrCodeId' => $qrCode?->id,
                'passwordUrl' => route('public.password', $shortLink, false),
                'error' => 'The password is incorrect.',
            ])->toResponse($request)->setStatusCode(403);
        }

        $request->session()->put($resolver->passwordSessionKey($shortLink), true);

        return $this->toResponse($request, $resolver->resolveShortLink($request, $shortLink, $qrCode), $settings);
    }

    private function toResponse(Request $request, ResolutionResult $result, InstanceSettings $settings): Response
    {
        if ($result->requiresPassword && $result->shortLink) {
            return Inertia::render('Public/Password', [
                'shortLinkId' => $result->shortLink->id,
                'qrCodeId' => $result->qrCode?->id,
                'passwordUrl' => route('public.password', $result->shortLink, false),
                'error' => null,
            ])->toResponse($request);
        }

        if ($result->outcome === Outcome::SCHEDULED && $result->shortLink?->activates_at) {
            $shortLink = $result->shortLink;

            return Inertia::render('Public/Scheduled', [
                'shortUrl' => $shortLink->domain->hostname.'/'.$shortLink->slug,
                'activatesAt' => $shortLink->activates_at->toIso8601String(),
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
