<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CollectorScriptController
{
    public function __invoke(Request $request): Response
    {
        $path = dirname(__DIR__, 3).'/resources/js/collector.js';

        if (! is_file($path)) {
            abort(404);
        }

        $script = (string) file_get_contents($path);
        $etag = '"'.md5($script).'"';

        $headers = [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => $etag,
        ];

        if (trim((string) $request->headers->get('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        return response($script, 200, $headers);
    }
}
