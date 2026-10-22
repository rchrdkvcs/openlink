<?php

namespace App\Actions\ShortLinks;

use App\Models\ShortLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevisionPrecondition
{
    public function expected(Request $request, ShortLink $shortLink, ?int $fromBody = null): ?int
    {
        $header = $request->header('If-Match');

        if ($header === null) {
            return $fromBody;
        }

        abort_unless(preg_match('/^(?:W\/)?"(\d+)"$/', trim($header), $match) === 1, 412, 'If-Match must be the link revision ETag, such as "3".');
        abort_unless((int) $match[1] === $shortLink->revision, 412, "This link is at revision {$shortLink->revision}. Fetch it again, then retry with its current ETag.");

        return (int) $match[1];
    }

    public function tag(JsonResponse $response, ShortLink $shortLink): JsonResponse
    {
        return $response->setEtag((string) $shortLink->revision);
    }
}
