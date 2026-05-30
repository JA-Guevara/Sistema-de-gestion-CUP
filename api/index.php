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

$databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: null;
$neonDatabaseUrl = $_SERVER['DATABASE_POSTGRES_URL']
    ?? $_ENV['DATABASE_POSTGRES_URL']
    ?? getenv('DATABASE_POSTGRES_URL')
    ?: ($_SERVER['DATABASE_URL_UNPOOLED'] ?? $_ENV['DATABASE_URL_UNPOOLED'] ?? getenv('DATABASE_URL_UNPOOLED') ?: null);

if ($databaseUrl === null && is_string($neonDatabaseUrl) && $neonDatabaseUrl !== '') {
    $_SERVER['DATABASE_URL'] = $neonDatabaseUrl;
    $_ENV['DATABASE_URL'] = $neonDatabaseUrl;
    putenv('DATABASE_URL='.$neonDatabaseUrl);
}

$env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'prod';
$debug = filter_var($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL);

$kernel = new Kernel($env, $debug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);

$response->send();
$kernel->terminate($request, $response);
