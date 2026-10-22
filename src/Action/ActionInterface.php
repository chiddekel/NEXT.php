<?php

declare(strict_types=1);

namespace Next\Action;

use Next\Http\NextRequest;
use Next\Http\NextResponse;

interface ActionInterface
{
    public function execute(array $data, NextRequest $request): mixed;
}
