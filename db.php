<?php
// Database connection. The real settings live in config.php (never uploaded to GitHub).
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    die('Missing config.php. Copy config.sample.php to config.php and fill in your database details.');
}
$config = require $configFile;

date_default_timezone_set($config['timezone'] ?? 'Africa/Freetown');

if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

try {
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
        $config['user'],
        $config['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed.');
}
