<?php
declare(strict_types=1);

require_once '/home/developer/Projekty/NEXT.js/vendor/autoload.php';

use Next\Server\NextServer;
use Next\Server\RuntimeEnvironment;

// Handle static files directly in PHP dev server
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicFile = '/home/developer/Projekty/NEXT.js/example/public' . $path;
if ($path !== '/' && file_exists($publicFile) && !is_dir($publicFile)) {
    return false;
}

$server = new NextServer('/home/developer/Projekty/NEXT.js/example', RuntimeEnvironment::DEVELOPMENT);
$server->run();