<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Reset App-Specific Environment Variables
|--------------------------------------------------------------------------
|
| XAMPP/mod_php can keep environment variables alive while serving multiple
| Laravel copies from the same Apache process. Clear the app-specific keys so
| each copy loads its own .env file before the framework boots.
|
*/

$laravelEnvKeys = [
    'ALLOW_CREATING_SUPERADMINS',
    'APP_DEBUG',
    'APP_ENV',
    'APP_KEY',
    'APP_NAME',
    'APP_URL',
    'ASSET_URL',
    'AWS_ACCESS_KEY_ID',
    'AWS_BUCKET',
    'AWS_DEFAULT_REGION',
    'AWS_SECRET_ACCESS_KEY',
    'AWS_USE_PATH_STYLE_ENDPOINT',
    'BROADCAST_DRIVER',
    'CACHE_DRIVER',
    'DB_CONNECTION',
    'DB_DATABASE',
    'DB_HOST',
    'DB_PASSWORD',
    'DB_PORT',
    'DB_USERNAME',
    'FILESYSTEM_DISK',
    'HMR_HOST',
    'HMR_PORT',
    'HMR_PROTOCOL',
    'LOG_CHANNEL',
    'LOG_DEPRECATIONS_CHANNEL',
    'LOG_LEVEL',
    'MAIL_ENCRYPTION',
    'MAIL_FROM_ADDRESS',
    'MAIL_FROM_NAME',
    'MAIL_HOST',
    'MAIL_MAILER',
    'MAIL_PASSWORD',
    'MAIL_PORT',
    'MAIL_USERNAME',
    'MEMCACHED_HOST',
    'PT210_PRINTER_SHARE',
    'PUSHER_APP_CLUSTER',
    'PUSHER_APP_ID',
    'PUSHER_APP_KEY',
    'PUSHER_APP_SECRET',
    'PUSHER_HOST',
    'PUSHER_PORT',
    'PUSHER_SCHEME',
    'QUEUE_CONNECTION',
    'REDIS_HOST',
    'REDIS_PASSWORD',
    'REDIS_PORT',
    'SANCTUM_STATEFUL_DOMAINS',
    'SESSION_COOKIE',
    'SESSION_DOMAIN',
    'SESSION_DRIVER',
    'SESSION_LIFETIME',
    'SESSION_SECURE_COOKIE',
    'VITE_APP_NAME',
    'VITE_BASE_URL',
    'VITE_PUSHER_APP_CLUSTER',
    'VITE_PUSHER_APP_KEY',
    'VITE_PUSHER_HOST',
    'VITE_PUSHER_PORT',
    'VITE_PUSHER_SCHEME',
];

foreach ($laravelEnvKeys as $laravelEnvKey) {
    putenv($laravelEnvKey);
    unset($_ENV[$laravelEnvKey], $_SERVER[$laravelEnvKey]);
}

$configuredAppUrl = '';
$envPath = __DIR__.'/../.env';
$loadedEnv = [];

if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
        $envLine = trim($envLine);

        if ($envLine === '' || str_starts_with($envLine, '#') || ! str_contains($envLine, '=')) {
            continue;
        }

        [$envKey, $envValue] = explode('=', $envLine, 2);
        $envKey = trim($envKey);
        $envValue = trim($envValue, " \t\n\r\0\x0B\"'");

        if ($envKey !== '' && in_array($envKey, $laravelEnvKeys, true)) {
            $loadedEnv[$envKey] = $envValue;
        }
    }
}

$configuredAppUrl = $loadedEnv['APP_URL'] ?? '';

foreach ($loadedEnv as $envKey => $envValue) {
    putenv($envKey . '=' . $envValue);
    $_ENV[$envKey] = $envValue;
    $_SERVER[$envKey] = $envValue;
}

$configuredAppPath = trim((string) parse_url($configuredAppUrl, PHP_URL_PATH), '/');

if ($configuredAppPath !== '') {
    $configuredBaseUri = '/' . $configuredAppPath;
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    if ($requestUri === $configuredBaseUri) {
        $_SERVER['REQUEST_URI'] = '/';
    } elseif (str_starts_with($requestUri, $configuredBaseUri . '/')) {
        $_SERVER['REQUEST_URI'] = substr($requestUri, strlen($configuredBaseUri));
    }

    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
}

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
