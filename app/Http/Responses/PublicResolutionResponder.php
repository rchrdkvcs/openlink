<?php

namespace App\Http\Responses;

use App\Actions\Resolution\ResolutionResult;
use App\Actions\Resolution\ResolutionView;
use App\Services\InstanceSettings;
use App\Services\QrCodes\QrCodeContent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicResolutionResponder
{
    public function __construct(private readonly InstanceSettings $settings) {}

    public function toResponse(Request $request, ResolutionResult $result): Response
    {
        return match ($result->view) {
            ResolutionView::Redirect => $this->redirect($request, (string) $result->redirectUrl),
            ResolutionView::PasswordForm => $this->passwordForm($request, $result),
            ResolutionView::Scheduled => $this->scheduled($request, $result),
            ResolutionView::QrPayload => $this->qrPayload($request, $result),
            ResolutionView::Unavailable => $this->unavailable($request),
        };
    }

    private function redirect(Request $request, string $url): Response
    {
        return $request->header('X-Inertia')
            ? Inertia::location($url)
            : redirect()->away($url);
    }

    private function passwordForm(Request $request, ResolutionResult $result): Response
    {
        $response = Inertia::render('Public/Password', [
            'shortLinkId' => $result->shortLink->id,
            'qrCodeId' => $result->qrCode?->id,
            'passwordUrl' => route('public.password', $result->shortLink, false),
            'error' => $result->passwordRejected() ? 'The password is incorrect.' : null,
        ])->toResponse($request);

        return $result->passwordRejected() ? $response->setStatusCode(403) : $response;
    }

    private function scheduled(Request $request, ResolutionResult $result): Response
    {
        $shortLink = $result->shortLink;

        return Inertia::render('Public/Scheduled', [
            'shortUrl' => $shortLink->domain->hostname.'/'.$shortLink->slug,
            'activatesAt' => $shortLink->activates_at->toIso8601String(),
        ])->toResponse($request);
    }

    private function qrPayload(Request $request, ResolutionResult $result): Response
    {
        $qrCode = $result->qrCode;

        return Inertia::render('Public/QrCodePayload', [
            'name' => $qrCode->name,
            'payloadType' => $qrCode->payload_type,
            'payloadTypeLabel' => QrCodeContent::types()[$qrCode->payload_type] ?? 'QR code',
            'content' => $qrCode->content,
        ])->toResponse($request);
    }

    private function unavailable(Request $request): Response
    {
        return Inertia::render('Public/Unavailable', [
            'title' => $this->settings->get('public_unavailable_title'),
            'message' => $this->settings->get('public_unavailable_message'),
        ])->toResponse($request)->setStatusCode(404);
    }
}
