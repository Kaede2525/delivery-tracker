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

$host = '127.0.0.1';
$dbname = 'delivery';
$user = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // 本番では詳細エラーを表示しないほうが安全
    die('データベース接続に失敗しました: ' . $e->getMessage());
}
