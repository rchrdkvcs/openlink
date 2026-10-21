<?php

namespace App\Http\Responses;

use App\Actions\Resolution\LinkPreview;
use Symfony\Component\HttpFoundation\Response;

class LinkPreviewResponder
{
    public function toResponse(LinkPreview $preview): Response
    {
        return response()
            ->view('link-preview', ['preview' => $preview])
            ->header('Cache-Control', 'no-cache, private')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
