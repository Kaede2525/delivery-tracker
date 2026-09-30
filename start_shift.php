<?php
// start_shift.php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// すでに進行中の稼働があるか確認
$stmt = $pdo->prepare(
    "SELECT id, start_time, platform FROM deliveries
     WHERE user_id = :user_id AND status = 'in_progress'
     ORDER BY start_time DESC LIMIT 1"
);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$activeShift = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }

    $platform = $_POST['platform'] ?? '';

    if (!in_array($platform, ['uber_eats', 'demae_can', 'rocket_now'], true)) {
        $errors[] = 'プラットフォームを選択してください。';
    }

    if (empty($errors) && !$activeShift) {
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO deliveries
                (user_id, work_date, platform, amount, status, start_time)
             VALUES
                (:user_id, :work_date, :platform, 0, :status, :start_time)'
        );
        $stmt->execute([
            'user_id'    => $_SESSION['user_id'],
            'work_date'  => date('Y-m-d'),
            'platform'   => $platform,
            'status'     => 'in_progress',
            'start_time' => $now,
        ]);

        header('Location: shift_active.php');
        exit;
    }
}

$platformNames = [
    'uber_eats'  => 'Uber Eats',
    'demae_can'  => '出前館',
    'rocket_now' => 'Rocket Now',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>稼働開始</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
    <h1>⏱ 稼働開始</h1>

    <?php if (!empty($errors)): ?>
        <div class="message-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php if ($activeShift): ?>
            <p style="font-size: 16px;">
                現在、<strong><?= htmlspecialchars($platformNames[$activeShift['platform']] ?? $activeShift['platform']) ?></strong>
                で稼働中です(開始: <?= htmlspecialchars($activeShift['start_time']) ?>)
            </p>
            <p><a href="shift_active.php"><button type="button">稼働を終了する</button></a></p>
        <?php else: ?>
            <form method="POST" action="start_shift.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <label>プラットフォーム:</label>
                <select name="platform" required>
                    <option value="">選択してください</option>
                    <option value="uber_eats">Uber Eats</option>
                    <option value="demae_can">出前館</option>
                    <option value="rocket_now">Rocket Now</option>
                </select><br>

                <button type="submit">稼働を開始する</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>