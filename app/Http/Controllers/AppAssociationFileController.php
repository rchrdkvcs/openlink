<?php

namespace App\Http\Controllers;

use App\Services\AppAssociations\AssociationFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppAssociationFileController extends Controller
{
    public function __construct(private readonly AssociationFiles $files) {}

    public function apple(Request $request): JsonResponse
    {
        $domain = $this->files->publishingDomain($request->getHost());

        return $this->respond($domain ? $this->files->apple($domain) : null);
    }

    public function android(Request $request): JsonResponse
    {
        $domain = $this->files->publishingDomain($request->getHost());

        return $this->respond($domain ? $this->files->android($domain) : null);
    }

    private function respond(?array $file): JsonResponse
    {
        if ($file === null) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($file, 200, [], JSON_UNESCAPED_SLASHES);
    }
}
