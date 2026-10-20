<?php
// chart_daily.php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$range = $_GET['range'] ?? 'daily'; // daily / weekly / monthly
if (!in_array($range, ['daily', 'weekly', 'monthly'], true)) {
    $range = 'daily';
}

$platformNames = [
    'uber_eats'  => 'Uber Eats',
    'demae_can'  => '出前館',
    'rocket_now' => 'Rocket Now',
];
$colors = [
    'uber_eats'  => '#06C167',
    'demae_can'  => '#e60012',
    'rocket_now' => '#1e90ff',
];

if ($range === 'daily') {
    // 直近30日分
    $stmt = $pdo->prepare(
        'SELECT work_date, platform, SUM(amount + bonus_amount) AS total_amount
         FROM deliveries
         WHERE user_id = :user_id
           AND work_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY work_date, platform
         ORDER BY work_date ASC'
    );
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $rows = $stmt->fetchAll();

    // 日付の一覧(直近30日、データが無い日も0で埋める)
    $labels = [];
    $period = new DatePeriod(
        new DateTime('-29 days'),
        new DateInterval('P1D'),
        new DateTime('+1 day')
    );
    foreach ($period as $date) {
        $labels[] = $date->format('Y-m-d');
    }

    $platformData = ['uber_eats' => [], 'demae_can' => [], 'rocket_now' => []];
    foreach ($labels as $date) {
        foreach (array_keys($platformData) as $platform) {
            $total = 0;
            foreach ($rows as $row) {
                if ($row['work_date'] === $date && $row['platform'] === $platform) {
                    $total = (float)$row['total_amount'];
                    break;
                }
            }
            $platformData[$platform][] = $total;
        }
    }

    // 日ごとの合計(移動平均の計算用)
    $dailyTotals = [];
    foreach ($labels as $i => $date) {
        $sum = 0;
        foreach ($platformData as $values) {
            $sum += $values[$i];
        }
        $dailyTotals[] = $sum;
    }

    // 7日移動平均を計算
    $movingAverage = [];
    foreach ($dailyTotals as $i => $value) {
        $start = max(0, $i - 6);
        $slice = array_slice($dailyTotals, $start, $i - $start + 1);
        $movingAverage[] = round(array_sum($slice) / count($slice), 1);
    }

    $lineLabel = '7日移動平均';
    $lineData = $movingAverage;

} else {
    // 週ごと・月ごと
    $groupFormat = $range === 'weekly' ? '%x-W%v' : '%Y-%m';

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(work_date, '{$groupFormat}') AS period_label, platform, SUM(amount + bonus_amount) AS total_amount
         FROM deliveries
         WHERE user_id = :user_id
         GROUP BY period_label, platform
         ORDER BY MIN(work_date) ASC"
    );
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $rows = $stmt->fetchAll();

    $labels = [];
    foreach ($rows as $row) {
        if (!in_array($row['period_label'], $labels, true)) {
            $labels[] = $row['period_label'];
        }
    }

    $platformData = ['uber_eats' => [], 'demae_can' => [], 'rocket_now' => []];
    foreach ($labels as $label) {
        foreach (array_keys($platformData) as $platform) {
            $total = 0;
            foreach ($rows as $row) {
                if ($row['period_label'] === $label && $row['platform'] === $platform) {
                    $total = (float)$row['total_amount'];
                    break;
                }
            }
            $platformData[$platform][] = $total;
        }
    }

    // 累積売り上げを計算
    $dailyTotals = [];
    foreach ($labels as $i => $label) {
        $sum = 0;
        foreach ($platformData as $values) {
            $sum += $values[$i];
        }
        $dailyTotals[] = $sum;
    }
    $cumulative = [];
    $runningTotal = 0;
    foreach ($dailyTotals as $value) {
        $runningTotal += $value;
        $cumulative[] = $runningTotal;
    }

    $lineLabel = '累積売り上げ';
    $lineData = $cumulative;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <title>売り上げグラフ</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        .range-buttons a {
            display: inline-block;
            padding: 6px 14px;
            margin-right: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .range-buttons a.active {
            background-color: #333;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="card">
    
    <h1>売り上げグラフ</h1>

    <div class="range-buttons">
        <a href="chart_daily.php?range=daily" class="<?= $range === 'daily' ? 'active' : '' ?>">日ごと(直近30日)</a>
        <a href="chart_daily.php?range=weekly" class="<?= $range === 'weekly' ? 'active' : '' ?>">週ごと</a>
        <a href="chart_daily.php?range=monthly" class="<?= $range === 'monthly' ? 'active' : '' ?>">月ごと</a>
    </div>
    <br>
    <div class="chart-wrapper">
    <canvas id="salesChart"></canvas>
    </div>
    <script>
        const labels = <?= json_encode($labels) ?>;
        const platforms = <?= json_encode($platformData) ?>;
        const platformLabels = <?= json_encode($platformNames) ?>;
        const colors = <?= json_encode($colors) ?>;
        const lineLabel = <?= json_encode($lineLabel) ?>;
        const lineData = <?= json_encode($lineData) ?>;

        const barDatasets = Object.keys(platforms).map(key => ({
            type: 'bar',
            label: platformLabels[key],
            data: platforms[key],
            backgroundColor: colors[key],
            stack: 'sales'
        }));

        const lineDataset = {
            type: 'line',
            label: lineLabel,
            data: lineData,
            borderColor: '#ff9800',
            backgroundColor: '#ff9800',
            borderWidth: 2,
            fill: false,
            tension: 0.3,
            pointRadius: 2,
            yAxisID: 'y'
        };

        const canvas = document.getElementById('salesChart');
            canvas.height = 600;   // ← ここで縦長を強制する
            canvas.width = 900;    // ← 必要なら横も広げる


    new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [...barDatasets, lineDataset]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        // ← ここを追加すると高さが確実に変わる
        layout: {
            padding: {
                top: 20,
                bottom: 20
            }
        },
        scales: {
            x: { stacked: true },
            y: {
                stacked: true,
                beginAtZero: true,
                ticks: {
                    maxticksLimit: 8,
                    callback: function(value) {
                        if (value >= 10000) {
                            return (value / 10000) + '万円';
                        }
                        return '¥' + value.toLocaleString();
                    }
                },
                suggestedMax: Math.ceil(Math.max(...lineData) / 1000) * 1000 + 3000
            }
        },
        plugins: {
            legend: { position: 'top' }
        }
    },
    // ← これが最重要
    height: 600   // 好きな高さに変更できる
});

    </script>
    </br>
    <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
</div>
</body>
</html>
