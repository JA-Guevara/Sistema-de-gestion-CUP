<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

$defaultUri = $_SERVER['DEFAULT_URI'] ?? $_ENV['DEFAULT_URI'] ?? getenv('DEFAULT_URI') ?: null;
$vercelUrl = $_SERVER['VERCEL_URL'] ?? $_ENV['VERCEL_URL'] ?? getenv('VERCEL_URL') ?: null;

if ($defaultUri === null && is_string($vercelUrl) && $vercelUrl !== '') {
    $defaultUri = str_starts_with($vercelUrl, 'http') ? $vercelUrl : 'https://'.$vercelUrl;
    $_SERVER['DEFAULT_URI'] = $defaultUri;
    $_ENV['DEFAULT_URI'] = $defaultUri;
    putenv('DEFAULT_URI='.$defaultUri);
}

$env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'prod';
$debug = filter_var($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL);

$kernel = new Kernel($env, $debug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);

$response->send();
$kernel->terminate($request, $response);
