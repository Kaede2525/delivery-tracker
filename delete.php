<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;

if ($id) {
    // 自分のデータのみ削除できるようにする
    $stmt = $pdo->prepare('DELETE FROM deliveries WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $id, 'user_id' => $_SESSION['user_id']]);
}

header('Location: list_delivery.php');
exit;