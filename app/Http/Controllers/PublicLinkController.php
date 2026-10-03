<?php

namespace App\Http\Controllers;

use App\Actions\Resolution\PublicResolution;
use App\Actions\Resolution\ResolutionEntry;
use App\Actions\Resolution\ResolutionResult;
use App\Http\Responses\PublicResolutionResponder;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\Analytics\Outcome;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicLinkController extends Controller
{
    public function __construct(
        private readonly PublicResolution $resolution,
        private readonly PublicResolutionResponder $responder,
    ) {}

    public function unavailable(Request $request): Response
    {
        return $this->responder->toResponse($request, ResolutionResult::unavailable(Outcome::NOT_FOUND));
    }

    public function show(Request $request, string $slug): Response
    {
        return $this->respond($request, ResolutionEntry::shortUrl($slug));
    }

    public function qr(Request $request, QrCode $qrCode): Response
    {
        return $this->respond($request, ResolutionEntry::qrCode($qrCode));
    }

    public function password(Request $request, ShortLink $shortLink): Response
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'qr_code_id' => ['nullable', 'integer'],
        ]);

        return $this->respond($request, ResolutionEntry::passwordSubmission(
            $shortLink,
            $data['password'],
            filled($data['qr_code_id'] ?? null) ? (int) $data['qr_code_id'] : null,
        ));
    }

    private function respond(Request $request, ResolutionEntry $entry): Response
    {
        return $this->responder->toResponse($request, $this->resolution->resolve($request, $entry));
    }
}
