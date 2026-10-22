<?php

declare(strict_types=1);

namespace Next\Routing;

use Next\Http\RequestInterface;

interface RouterInterface
{
    public function match(RequestInterface $request): ?RouteMatch;

    /**
     * @return list<RouteMatch>
     */
    public function getRoutes(): array;
}
