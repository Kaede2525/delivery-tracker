<?php
// analysis.php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT 
        DAYOFWEEK(work_date) AS day_num,
        time_slot,
        platform,
        SUM(amount + bonus_amount) AS total_amount,
        SUM(work_hours) AS total_hours,
        COUNT(*) AS record_count
     FROM deliveries
     WHERE user_id = :user_id AND time_slot IS NOT NULL
     GROUP BY day_num, time_slot, platform"
);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$rows = $stmt->fetchAll();

// 曜日・時間帯のラベル
$dayLabels = ['', '日', '月', '火', '水', '木', '金', '土']; // DAYOFWEEK: 1=日曜
$timeSlots = ['morning' => '朝', 'afternoon' => '昼', 'evening' => '夕方', 'night' => '夜'];
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

// 曜日×時間帯 ごとに、稼ぎ最大のプラットフォームと時給を集計
$matrix = [];
foreach ($rows as $row) {
    $key = $row['day_num'] . '_' . $row['time_slot'];
    if (!isset($matrix[$key])) {
        $matrix[$key] = [];
    }
    $hourlyRate = $row['total_hours'] > 0 ? $row['total_amount'] / $row['total_hours'] : 0;
    $matrix[$key][$row['platform']] = [
        'total_amount' => (float)$row['total_amount'],
        'hourly_rate'  => round($hourlyRate),
        'record_count' => (int)$row['record_count'],
    ];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>曜日・時間帯別分析</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .analysis-table {
            border-collapse: collapse;
            width: 100%;
            background-color: #fff;
        }
        .analysis-table th, .analysis-table td {
            border: 1px solid #eee;
            padding: 10px;
            text-align: center;
            vertical-align: top;
            min-width: 110px;
        }
        .analysis-table th {
            background-color: #ff7a00;
            color: #fff;
        }
        .cell-empty {
            color: #ccc;
            font-size: 12px;
        }
        .platform-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 2px 4px;
            border-radius: 4px;
            margin-bottom: 3px;
            color: #fff;
        }
        .platform-row.best {
            font-weight: bold;
            box-shadow: 0 0 0 2px #333 inset;
        }
        .legend {
            margin-bottom: 16px;
            font-size: 13px;
        }
        .legend span {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 4px;
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
    <h1>🕒 曜日・時間帯別 プラットフォーム分析</h1>

    <div class="card">
        <p style="font-size: 14px; color: #666;">
            各マスは「時給換算(円/時間)」を表示しています。枠が太い項目が、その時間帯で一番稼げているプラットフォームです。
        </p>
        <div class="legend">
            <?php foreach ($platformNames as $key => $label): ?>
                <span style="background-color: <?= $platformColors[$key] ?>;"></span><?= htmlspecialchars($label) ?>&nbsp;&nbsp;
            <?php endforeach; ?>
        </div>

        <div style="overflow-x: auto;">
            <table class="analysis-table">
                <thead>
                    <tr>
                        <th>時間帯 ＼ 曜日</th>
                        <?php for ($d = 1; $d <= 7; $d++): ?>
                            <th><?= $dayLabels[$d] ?>曜日</th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timeSlots as $slotKey => $slotLabel): ?>
                        <tr>
                            <th><?= $slotLabel ?></th>
                            <?php for ($d = 1; $d <= 7; $d++):
                                $key = $d . '_' . $slotKey;
                                $cell = $matrix[$key] ?? [];
                                // このマスの中で時給最大のプラットフォームを特定
                                $bestPlatform = null;
                                $bestRate = -1;
                                foreach ($cell as $platform => $data) {
                                    if ($data['hourly_rate'] > $bestRate) {
                                        $bestRate = $data['hourly_rate'];
                                        $bestPlatform = $platform;
                                    }
                                }
                            ?>
                                <td>
                                    <?php if (empty($cell)): ?>
                                        <span class="cell-empty">データなし</span>
                                    <?php else: ?>
                                        <?php foreach ($cell as $platform => $data): ?>
                                            <div class="platform-row <?= $platform === $bestPlatform ? 'best' : '' ?>"
                                                 style="background-color: <?= $platformColors[$platform] ?>;">
                                                <span><?= htmlspecialchars($platformNames[$platform]) ?></span>
                                                <span>¥<?= number_format($data['hourly_rate']) ?>/h</span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
