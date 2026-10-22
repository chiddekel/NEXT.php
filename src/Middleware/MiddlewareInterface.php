<?php

declare(strict_types=1);

namespace Next\Middleware;

use Next\Http\NextRequest;
use Next\Http\NextResponse;

interface MiddlewareInterface
{
    /**
     * Process an incoming server request.
     *
     * @param callable(NextRequest): NextResponse $next
     */
    public function process(NextRequest $request, callable $next): NextResponse;
}
