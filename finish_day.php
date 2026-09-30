<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function determineTimeSlot(DateTime $time) {
    $hour = (int)$time->format('G');
    if ($hour >= 5 && $hour < 11) return 'morning';
    if ($hour >= 11 && $hour < 16) return 'afternoon';
    if ($hour >= 16 && $hour < 20) return 'evening';
    return 'night';
}

$platformNames = [
    'uber_eats' => 'Uber Eats',
    'demae_can' => '出前館',
    'rocket_now' => 'Rocket Now',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }

    $amounts = $_POST['amount'] ?? [];
    $bonusAmounts = $_POST['bonus_amount'] ?? [];

    foreach ($amounts as $id => $amount) {
        $bonus = $bonusAmounts[$id] ?? 0;

        if ($amount === '' || !is_numeric($amount) || $amount < 0) {
            continue; // 空欄や不正値はスキップ(後で編集画面から直せる)
        }
        if ($bonus === '' || !is_numeric($bonus) || $bonus < 0) {
            $bonus = 0;
        }

        $stmt = $pdo->prepare('UPDATE deliveries SET amount = :amount, bonus_amount = :bonus_amount WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            'amount'       => $amount,
            'bonus_amount' => $bonus,
            'id'           => $id,
            'user_id'      => $_SESSION['user_id'],
        ]);
    }

    // 進行中の区間が残っていれば、ここで確定させる
    $stmt = $pdo->prepare(
        "SELECT * FROM deliveries WHERE user_id = :user_id AND status = 'in_progress' ORDER BY start_time DESC LIMIT 1"
    );
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $activeShift = $stmt->fetch();

    if ($activeShift) {
        $endTime = new DateTime();
        $startTime = new DateTime($activeShift['start_time']);
        $workHours = round(($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600, 2);
        $timeSlot = determineTimeSlot($endTime);

        $stmt = $pdo->prepare(
            'UPDATE deliveries SET time_slot = :time_slot, work_hours = :work_hours, end_time = :end_time, status = :status
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'time_slot'  => $timeSlot,
            'work_hours' => $workHours,
            'end_time'   => $endTime->format('Y-m-d H:i:s'),
            'status'     => 'completed',
            'id'         => $activeShift['id'],
            'user_id'    => $_SESSION['user_id'],
        ]);
    }

    header('Location: dashboard.php');
    exit;
}

//今日の全区間(完了済み+進行中)を取得
$stmt = $pdo->prepare(
    "SELECT * FROM deliveries
     WHERE user_id = :user_id AND work_date = CURDATE()
     ORDER BY start_time ASC"
);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$segments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>本日の稼働まとめ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="shift_active.php" class="back-link">← 戻る</a>
    <h1>📝 本日の稼働まとめ</h1>
    <p style="color: #666; font-size: 14px;">各プラットフォームアプリの履歴を見ながら、金額を入力してください。</p>

    <?php if (empty($segments)): ?>
        <p>本日の稼働記録がありません。</p>
    <?php else: ?>
        <form method="POST" action="finish_day.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

            <?php foreach ($segments as $seg): ?>
                <div class="card">
                    <p>
                        <strong><?= htmlspecialchars($platformNames[$seg['platform']] ?? $seg['platform']) ?></strong>
                        <?= htmlspecialchars(substr($seg['start_time'], 11, 5)) ?>
                        〜
                        <?= $seg['end_time'] ? htmlspecialchars(substr($seg['end_time'], 11, 5)) : '(稼働中)' ?>
                        (<?= (int)($seg['order_count'] ?? 0) ?>件)
                    </p>
                    <label>売り上げ金額(円):</label>
                    <input type="number" name="amount[<?= $seg['id'] ?>]" min="0" value="<?= $seg['amount'] > 0 ? htmlspecialchars($seg['amount']) : '' ?>">

                    <label>クエスト・ボーナス報酬（円）:</label>
                    <input type="number" name="bonus_amount[<?= $seg['id'] ?>]" min="0" value="<?= $seg['bonus_amount'] > 0 ? htmlspecialchars($seg['bonus_amount']) : '' ?>">
                    
                </div>
            <?php endforeach; ?>

            <button type="submit">この内容で本日の稼働を終了する</button>
        </form>
    <?php endif; ?>
</body>
</html>