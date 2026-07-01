<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers;

use Illuminate\Http\Response;

final class CollectorScriptController
{
    public function __invoke(): Response
    {
        $script = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/collector.js');

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
