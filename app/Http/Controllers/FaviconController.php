<?php

namespace App\Http\Controllers;

use App\Services\Favicons\Favicons;
use App\Services\Favicons\PublicUrl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FaviconController extends Controller
{
    public function show(Request $request, PublicUrl $urls, Favicons $favicons): Response
    {
        $url = $request->validate(['url' => ['required', 'url']])['url'];

        abort_unless($urls->isAllowed($url), 404);

        $favicon = $favicons->forUrl($url);

        if ($favicon === null) {
            return response('', 404, ['Cache-Control' => 'private, max-age='.Favicons::MISSING_TTL]);
        }

        return response($favicon->body, 200, [
            'Cache-Control' => 'private, max-age='.Favicons::FOUND_TTL,
            'Content-Type' => $favicon->contentType,
        ]);
    }
}
