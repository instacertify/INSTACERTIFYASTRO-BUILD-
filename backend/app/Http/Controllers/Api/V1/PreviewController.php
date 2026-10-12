<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Preview;
use Illuminate\Http\JsonResponse;

class PreviewController extends Controller
{
    /** An admin draft for the website's preview server; links expire after Preview::MINUTES. */
    public function show(string $token): JsonResponse
    {
        $preview = Preview::get($token);
        abort_unless($preview, 404);

        return response()->json(['data' => $preview])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
