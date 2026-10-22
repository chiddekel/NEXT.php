<?php

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

$loaded = false;
foreach ($autoloadCandidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    http_response_code(500);
    echo "Vendor autoload not found. Run 'composer install'.";
    exit(1);
}

use Next\Cache\FileCacheStore;
use Next\Server\NextServer;
use Next\Server\RuntimeEnvironment;

$projectDir = dirname(__DIR__);
$env = (getenv('APP_ENV') === 'development') ? RuntimeEnvironment::DEVELOPMENT : RuntimeEnvironment::PRODUCTION;
$cache = new FileCacheStore($projectDir . '/.next/cache');

$server = new NextServer(
    projectDir: $projectDir,
    environment: $env,
    cache: $cache
);

$server->run();
