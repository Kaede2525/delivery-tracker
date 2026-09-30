<?php
session_start();

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }
    // ここから既存の処理
    $targetMonth = $_POST['target_month'] ?? ''; // 例: 2026-08
    $targetAmount = $_POST['target_amount'] ?? '';

    if ($targetMonth === '') {
        $errors[] = '対象月を選択してください。';
    }

    if (empty($errors)) {
        // すでに同じ月の目標があるかチェック
        $stmt = $pdo->prepare(
            'SELECT id FROM goals WHERE user_id = :user_id AND target_month = :target_month'
        );
        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'target_month' => $targetMonth,
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            // 既存なら更新
            $stmt = $pdo->prepare(
                'UPDATE goals SET target_amount = :target_amount WHERE id = :id'
            );
            $stmt->execute([
                'target_amount' => $targetAmount,
                'id' => $existing['id'],
            ]);
        }else {
            // なければ新規登録
            $stmt = $pdo->prepare(
                'INSERT INTO goals (user_id, target_month, target_amount) VALUES (:user_id, :target_month, :target_amount)'
            );
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'target_month' => $targetMonth,
                'target_amount' => $targetAmount,
            ]);
        }

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
    <head>
        <link rel="stylesheet" href="style.css">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>月間目標設定</title>
    </head>
    <body>
        <h1>月間目標設定</h1>

        <?php if (!empty($errors)): ?>
            <ul style="color: red;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST" action="set_goal.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <label>対象月:</label>
            <input type="month" name="target_month"><br>

            <label>目標金額(円):</label>
            <input type="number" name="target_amount" step="1000"><br>

            <button type="submit">設定する</button>
        </form>
            </br>
            <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
    </body>
</html>

