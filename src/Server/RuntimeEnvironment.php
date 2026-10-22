<?php

declare(strict_types=1);

namespace Next\Server;

enum RuntimeEnvironment: string
{
    case DEVELOPMENT = 'development';
    case PRODUCTION = 'production';
    case TEST = 'test';
}
