<?php
// increment_count.php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未ログインです']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => '不正なリクエストです']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, order_count FROM deliveries
     WHERE user_id = :user_id AND status = 'in_progress'
     ORDER BY start_time DESC LIMIT 1"
);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$activeShift = $stmt->fetch();

if (!$activeShift) {
    echo json_encode(['success' => false, 'message' => '進行中の稼働がありません']);
    exit;
}

$newCount = (int)($activeShift['order_count'] ?? 0) + 1;

$stmt = $pdo->prepare('UPDATE deliveries SET order_count = :count WHERE id = :id AND user_id = :user_id');
$stmt->execute([
    'count'   => $newCount,
    'id'      => $activeShift['id'],
    'user_id' => $_SESSION['user_id'],
]);

echo json_encode(['success' => true, 'count' => $newCount]);