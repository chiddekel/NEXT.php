<?php

declare(strict_types=1);

use Next\Http\NextRequest;
use Next\Http\NextResponse;

return [
    'GET' => function (NextRequest $request, array $context): NextResponse {
        $name = $request->getQuery('name', 'World');
        return NextResponse::json([
            'message' => "Hello, {$name} from NEXT.php API Route!",
            'timestamp' => time(),
            'method' => 'GET',
        ]);
    },
    'POST' => function (NextRequest $request, array $context): NextResponse {
        $payload = $request->json() ?? [];
        return NextResponse::json([
            'status' => 'created',
            'data' => $payload,
        ], 201);
    },
];
