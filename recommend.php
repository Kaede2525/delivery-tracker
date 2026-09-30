<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$platformNames = [
    'uber_eats'  => 'Uber Eats',
    'demae_can'  => '出前館',
    'rocket_now' => 'Rocket Now',
];
$platformColors = [
    'uber_eats'  => '#06C167',
    'demae_can'  => '#e60012',
    'rocket_now' => '#1e90ff',
];

// 現在の曜日・時間帯を判定
$now = new DateTime();
$dayNum = (int)$now->format('w') + 1; // DAYOFWEEKと合わせる(1=日曜)
$hour = (int)$now->format('G');

if ($hour >= 5 && $hour < 11) {
    $currentSlot = 'morning';
} elseif ($hour >= 11 && $hour < 16) {
    $currentSlot = 'afternoon';
} elseif ($hour >= 16 && $hour < 20) {
    $currentSlot = 'evening';
} else {
    $currentSlot = 'night';
}

$slotLabels = ['morning' => '朝', 'afternoon' => '昼', 'evening' => '夕方', 'night' => '夜'];
$dayLabels = ['', '日', '月', '火', '水', '木', '金', '土'];

// 今の曜日・時間帯における、プラットフォームごとの時給を集計
$stmt = $pdo->prepare(
    "SELECT platform, SUM(amount + bonus_amount) AS total_amount, SUM(work_hours) AS total_hours, COUNT(*) AS record_count
     FROM deliveries
     WHERE user_id = :user_id
       AND DAYOFWEEK(work_date) = :day_num
       AND time_slot = :time_slot
     GROUP BY platform"
);
$stmt->execute([
    'user_id'   => $_SESSION['user_id'],
    'day_num'   => $dayNum,
    'time_slot' => $currentSlot,
]);
$rows = $stmt->fetchAll();

$results = [];
foreach ($rows as $row) {
    $hourlyRate = $row['total_hours'] > 0 ? $row['total_amount'] / $row['total_hours'] : 0;
    $results[$row['platform']] = [
        'hourly_rate'  => round($hourlyRate),
        'record_count' => (int)$row['record_count'],
    ];
}

// 時給が高い順に並び替え
uasort($results, function ($a, $b) {
    return $b['hourly_rate'] <=> $a['hourly_rate'];
});

$bestPlatform = array_key_first($results);

// 先週の同じ曜日・時間帯との比較(直近7日 vs それ以前)
$comparisonText = null;
if ($bestPlatform) {
    $stmt = $pdo->prepare(
        "SELECT SUM(amount + bonus_amount) AS total_amount, SUM(work_hours) AS total_hours
         FROM deliveries
         WHERE user_id = :user_id
           AND platform = :platform
           AND DAYOFWEEK(work_date) = :day_num
           AND time_slot = :time_slot
           AND work_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
    );
    $stmt->execute([
        'user_id'   => $_SESSION['user_id'],
        'platform'  => $bestPlatform,
        'day_num'   => $dayNum,
        'time_slot' => $currentSlot,
    ]);
    $past = $stmt->fetch();

    if ($past && $past['total_hours'] > 0) {
        $pastRate = $past['total_amount'] / $past['total_hours'];
        $currentRate = $results[$bestPlatform]['hourly_rate'];
        $diff = $currentRate - $pastRate;
        if (abs($diff) >= 50) {
            $direction = $diff > 0 ? '上がっています' : '下がっています';
            $comparisonText = '先週までと比べて、時給が約' . number_format(abs($diff)) . '円' . $direction;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>今日のおすすめ稼働プラン</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .recommend-box {
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 24px;
            max-width: 500px;
        }
        .best-badge {
            display: inline-block;
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 12px;
        }
        .rate-list {
            margin-top: 16px;
        }
        .rate-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .comparison-note {
            margin-top: 16px;
            padding: 10px 14px;
            background-color: #fff3e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .no-data {
            color: #888;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
    <h1>🧭 今日のおすすめ稼働プラン</h1>

    <div class="recommend-box">
        <p><?= $dayLabels[$dayNum] ?>曜日・<?= $slotLabels[$currentSlot] ?>(現在時刻:<?= $now->format('H:i') ?>)の過去データに基づく提案です。</p>

        <?php if (empty($results)): ?>
            <p class="no-data">この曜日・時間帯のデータがまだありません。記録が貯まると、おすすめが表示されます。</p>
        <?php else: ?>
            <div class="best-badge" style="background-color: <?= $platformColors[$bestPlatform] ?>;">
                今は <?= htmlspecialchars($platformNames[$bestPlatform]) ?> がおすすめ
            </div>

            <div class="rate-list">
                <?php foreach ($results as $platform => $data): ?>
                    <div class="rate-row">
                        <span><?= htmlspecialchars($platformNames[$platform]) ?></span>
                        <span>¥<?= number_format($data['hourly_rate']) ?>/h(<?= $data['record_count'] ?>件のデータ)</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($comparisonText): ?>
                <div class="comparison-note">📈 <?= htmlspecialchars($comparisonText) ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>