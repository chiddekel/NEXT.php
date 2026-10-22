<?php

declare(strict_types=1);

use Next\Http\NextRequest;
use Next\Http\NextResponse;

return [
    'matcher' => [
        '/api/:path*',
        '/blog/:path*',
    ],
    'handler' => function (NextRequest $request, callable $next): NextResponse {
        // Add custom tracking / latency header
        $start = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $start) * 1000, 2);

        return $response->withHeader('X-Response-Time', "{$duration}ms");
    },
];
