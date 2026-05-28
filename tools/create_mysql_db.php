<?php
// Creates the database specified in the .env file (or default name below).
$envFile = __DIR__ . '/../.env';
$defaults = [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'prevmaincheckdb',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
];
if (file_exists($envFile)) {
    $contents = file_get_contents($envFile);
    foreach (explode("\n", $contents) as $line) {
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (array_key_exists($k, $defaults) && $v !== '') {
            $defaults[$k] = $v;
        }
    }
}
$host = $defaults['DB_HOST'];
$port = $defaults['DB_PORT'];
$db = $defaults['DB_DATABASE'];
$user = $defaults['DB_USERNAME'];
$pass = $defaults['DB_PASSWORD'];
try {
    $dsn = "mysql:host=$host;port=$port";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Created/verified database: $db\n";
    exit(0);
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR creating DB: " . $e->getMessage() . "\n");
    exit(1);
}
