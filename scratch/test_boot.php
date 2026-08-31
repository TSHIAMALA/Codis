<?php

ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('memory_limit', '512M');

chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

if (file_exists('.env')) {
    (new Dotenv())->bootEnv('.env');
}

echo "APP_ENV=" . ($_SERVER['APP_ENV'] ?? 'not set') . PHP_EOL;
echo "APP_DEBUG=" . ($_SERVER['APP_DEBUG'] ?? 'not set') . PHP_EOL;

try {
    $kernel = new App\Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool)($_SERVER['APP_DEBUG'] ?? true));

    // Simulate a real request to /
    $request = Request::create('/', 'GET');
    $response = $kernel->handle($request);

    echo "Response status: " . $response->getStatusCode() . PHP_EOL;
    echo "Response content length: " . strlen($response->getContent()) . PHP_EOL;
    echo "First 500 chars:" . PHP_EOL;
    echo substr($response->getContent(), 0, 500) . PHP_EOL;

    $kernel->terminate($request, $response);

} catch (Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
    echo "In: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo substr($e->getTraceAsString(), 0, 3000) . PHP_EOL;
}
