<?php

namespace App\Http\Controllers;

use App\Actions\Resolution\LinkPreviewResolution;
use App\Models\ShortLink;
use App\Services\LinkPreviews\LinkPreviewImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LinkPreviewImageController extends Controller
{
    public function __construct(private readonly LinkPreviewImageStorage $images) {}

    public function public(Request $request, string $image, LinkPreviewResolution $previews): Response
    {
        $path = $this->images->pathFor($image);
        $shortLink = $path ? ShortLink::query()->with('domain')->where('preview_image_path', $path)->first() : null;

        abort_unless(
            $shortLink
                && $shortLink->domain?->hostname === $request->getHost()
                && $previews->isPublic($shortLink),
            404,
        );

        return $this->images->response($path, 'public, max-age=300');
    }

    public function show(ShortLink $shortLink): Response
    {
        Gate::authorize('view', $shortLink);
        abort_unless($shortLink->hasPreviewImage(), 404);

        return $this->images->response($shortLink->preview_image_path, 'private, max-age=300');
    }
}
