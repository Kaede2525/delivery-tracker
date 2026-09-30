<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT * FROM deliveries
     WHERE user_id = :user_id AND status = 'in_progress'
     ORDER BY start_time DESC LIMIT 1"
);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$activeShift = $stmt->fetch();

if (!$activeShift) {
    header('Location: start_shift.php');
    exit;
}

$platformNames = [
    'uber_eats' => 'Uber Eats',
    'demae_can' => '出前館',
    'rocket_now' => 'Rocket Now',
];

function determineTimeSlot(DateTime $time) {
    $hour = (int)$time->format('G');
    if ($hour >= 5 && $hour < 11) return 'morning';
    if ($hour >= 11 && $hour < 17) return 'afternoon';
    if ($hour >= 17 && $hour < 21) return 'evening';
    return 'night';
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }

    $nextPlatform = $_POST['next_platform'] ?? '';

    if (!in_array($nextPlatform, array_keys($platformNames), true)) {
        $errors[] = '次のプラットフォームを選択してください。';
    }
    if ($nextPlatform === $activeShift['platform']) {
        $errors[] = '同じプラットフォームには切り替えられません。';
    }

    if (empty($errors)) {
        $endTime = new DateTime();
        $startTime = new DateTime($activeShift['start_time']);
        $workHours = round(($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600, 2);
        $timeSlot = determineTimeSlot($endTime);

        // 今の区間を確定（金額は後でまとめて入力するのでnullのまま）
        $stmt = $pdo->prepare(
            'UPDATE deliveries SET
               time_slot = :time_slot,
               work_hours = :work_hours,
               end_time = :end_time,
               status = :status
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            ':time_slot' => $timeSlot,
            ':work_hours' => $workHours,
            ':end_time' => $endTime->format('Y-m-d H:i:s'),
            ':status' => 'completed',
            ':id' => $activeShift['id'],
            ':user_id' => $_SESSION['user_id']
        ]);

        //新しいプラットフォームで開始
        $stmt = $pdo->prepare(
            'INSERT INTO deliveries
                (user_id, work_date, platform, amount, order_count, status, start_time)
             VALUES
                (:user_id, :work_date, :platform, 0, 0, :status, :start_time)'
        );
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':work_date' => $endTime->format('Y-m-d'),
            ':platform' => $nextPlatform,
            ':status' => 'in_progress',
            ':start_time' => $endTime->format('Y-m-d H:i:s')
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プラットフォーム切り替え</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="shift_active.php" class="back-link">← 戻る</a>
    <h1>🔄 プラットフォーム切り替え</h1>

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
        <p>現在:<strong><?= htmlspecialchars($platformNames[$activeShift['platform']] ?? $activeShift['platform']) ?></strong>(<?= (int)($activeShift['order_count'] ?? 0) ?>件)</p>

        <form method="POST" action="switch_platform.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

            <label>次に使うプラットフォーム:</label>
            <select name="next_platform" required>
                <option value="">選択してください</option>
                <?php foreach ($platformNames as $key => $label): if ($key !== $activeShift['platform']): ?>
                    <option value="<?= $key ?>"><?= $label ?></option>
                <?php endif; endforeach; ?>
            </select><br>

            <button type="submit">切り替える</button>
        </form>
    </div>
</body>
</html>