<?php

declare(strict_types=1);

use Next\Http\NextRequest;
use Next\Http\NextResponse;

return [
    'GET' => function (NextRequest $request, array $context): NextResponse {
        $id = $context['params']['id'] ?? 'unknown';

        return NextResponse::json([
            'post' => [
                'id' => $id,
                'title' => "Post #{$id}",
                'content' => "This is dynamic API content for post {$id}.",
            ],
        ]);
    },
    'DELETE' => function (NextRequest $request, array $context): NextResponse {
        $id = $context['params']['id'] ?? 'unknown';

        return NextResponse::json([
            'deleted' => true,
            'id' => $id,
        ]);
    },
];
