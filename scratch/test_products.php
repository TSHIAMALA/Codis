<?php

ini_set('display_errors', '1');
error_reporting(E_ALL);

chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

if (file_exists('.env')) {
    (new Dotenv())->bootEnv('.env');
}

try {
    $kernel = new App\Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool)($_SERVER['APP_DEBUG'] ?? true));

    // Simulate request to /api/products
    $request = Request::create('/api/products', 'GET');
    $response = $kernel->handle($request);

    echo "Response status: " . $response->getStatusCode() . PHP_EOL;
    echo "Content:" . PHP_EOL;
    echo $response->getContent() . PHP_EOL;

    $kernel->terminate($request, $response);

} catch (Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
    echo "In: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
