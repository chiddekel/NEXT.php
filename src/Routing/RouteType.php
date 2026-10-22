<?php

declare(strict_types=1);

namespace Next\Routing;

enum RouteType: string
{
    case PAGE = 'page';
    case API = 'api';
}
