<?php

namespace App\Http\Controllers;

use App\Actions\LinkPages\LinkPagePayload;
use App\Actions\Resolution\PublicResolution;
use App\Actions\Resolution\ResolutionEntry;
use App\Http\Responses\PublicResolutionResponder;
use App\Models\LinkPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicLinkPageController extends Controller
{
    public function __invoke(
        Request $request,
        string $path,
        LinkPagePayload $payload,
        PublicResolution $resolution,
        PublicResolutionResponder $responder,
    ): Response {
        $linkPage = LinkPage::query()->where('path', strtolower($path))->whereNotNull('published_at')->first();

        if (! $linkPage) {
            $entry = ResolutionEntry::shortUrl(LinkPage::PATH_PREFIX.'/'.$path);

            return $responder->toResponse($request, $resolution->resolve($request, $entry));
        }

        return Inertia::render('Public/LinkPage', ['page' => $payload->visitor($linkPage)])
            ->toResponse($request)
            ->header('X-Robots-Tag', 'noarchive');
    }
}
