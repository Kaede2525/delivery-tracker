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
    'uber_eats'  => 'Uber Eats',
    'demae_can'  => '出前館',
    'rocket_now' => 'Rocket Now',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>稼働中</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .shift-status {
            text-align: center;
            padding: 20px;
        }
        .count-display {
            font-size: 64px;
            font-weight: bold;
            color: #ff7a00;
            margin: 20px 0;
        }
        .count-button {
            width: 100%;
            padding: 24px;
            font-size: 20px;
            font-weight: bold;
            background-color: #ff7a00;
            color: #fff;
            border: none;
            border-radius: 16px;
            margin-bottom: 16px;
        }
        .sub-buttons {
            display: flex;
            gap: 10px;
        }
        .sub-buttons button, .sub-buttons a button {
            flex: 1;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #ccc;
            background-color: #fff;
            font-size: 14px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="shift-status">
        <p style="font-size: 16px; color: #666;">現在稼働中</p>
        <h1 id="platformLabel"><?= htmlspecialchars($platformNames[$activeShift['platform']] ?? $activeShift['platform']) ?></h1>

        <div class="count-display" id="countDisplay"><?= (int)($activeShift['order_count'] ?? 0) ?></div>
        <p style="color: #888;">配達件数</p>

        <button class="count-button" id="incrementBtn">+1件 配達完了</button>

        <div class="sub-buttons">
            <a href="switch_platform.php" style="width:100%"><button type="button">プラットフォーム切り替え</button></a>
            <a href="finish_day.php" style="width:100%"><button type="button">本日の稼働を終了</button></a>
        </div>
    </div>

    <script>
        const csrfToken = <?= json_encode(generateCsrfToken()) ?>;
        const incrementBtn = document.getElementById('incrementBtn');
        const countDisplay = document.getElementById('countDisplay');

        incrementBtn.addEventListener('click', async () => {
            incrementBtn.disabled = true;
            try {
                const response = await fetch('increment_count.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'csrf_token=' + encodeURIComponent(csrfToken)
                });
                const data = await response.json();
                if (data.success) {
                    countDisplay.textContent = data.count;
                } else {
                    alert('更新に失敗しました: ' + (data.message || ''));
                }
            } catch (e) {
                alert('通信エラーが発生しました。');
            } finally {
                incrementBtn.disabled = false;
            }
        });
    </script>
</body>
</html>