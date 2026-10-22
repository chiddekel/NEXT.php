<?php

declare(strict_types=1);

namespace Next\Rendering;

use Next\Http\RequestInterface;
use Next\Routing\RouteMatch;

interface RendererInterface
{
    public function render(RouteMatch $match, RequestInterface $request): string;

    public function renderNotFound(RequestInterface $request, ?string $notFoundFile = null, array $layoutFiles = []): string;

    public function renderError(\Throwable $error, RequestInterface $request, ?string $errorFile = null, array $layoutFiles = [], bool $isProduction = false): string;
}
