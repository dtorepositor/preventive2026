<?php
require __DIR__ . '/../vendor/autoload.php';
$env = __DIR__ . '/../.env';
if (file_exists($env)) {
    $lines = explode("\n", file_get_contents($env));
    foreach ($lines as $line) {
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if ($k === 'DB_HOST') $host = $v;
        if ($k === 'DB_PORT') $port = $v;
        if ($k === 'DB_DATABASE') $db = $v;
        if ($k === 'DB_USERNAME') $user = $v;
        if ($k === 'DB_PASSWORD') $pass = $v;
    }
}
$host = $host ?? '127.0.0.1';
$port = $port ?? '3306';
$db = $db ?? 'prevmaincheckdb';
$user = $user ?? 'root';
$pass = $pass ?? '';
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in $db:\n" . implode("\n", $tables) . "\n\n";
    if (in_array('psm', $tables)) {
        $psm = $pdo->query('SELECT COUNT(*) FROM psm')->fetchColumn();
        echo "psm rows: $psm\n";
    } else {
        echo "psm table not found\n";
    }
    if (in_array('psm_values', $tables)) {
        $vals = $pdo->query('SELECT COUNT(*) FROM psm_values')->fetchColumn();
        echo "psm_values rows: $vals\n";
    } else {
        echo "psm_values table not found\n";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
