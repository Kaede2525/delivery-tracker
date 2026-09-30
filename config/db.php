<?php

// 本番かどうかを判定(サーバー名で判定する例)

$isProduction = ($_SERVER['SERVER_NAME'] ?? '') !== 'localhost';

if ($isProduction) {
    ini_set('display_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../error.log');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

$secrets = require __DIR__ . '/secrets.php';
$config = $isProduction ? $secrets['production'] : $secrets['local'];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    die($isProduction
        ? 'データベース接続に失敗しました。'
        : 'データベース接続に失敗しました: ' . $e->getMessage());
}