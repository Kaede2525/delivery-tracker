<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 表示対象月(見指定なら今月)
$targetMonth = $_GET['month'] ?? date('Y-m');

// 目標金額を取得
$stmt = $pdo->prepare(
    'SELECT target_amount FROM goals WHERE user_id = :user_id AND target_month = :target_month'
);
$stmt->execute(['user_id' => $_SESSION['user_id'], 'target_month' => $targetMonth]);
$goal = $stmt->fetch();
$targetAmount = $goal ? (float)$goal['target_amount'] : 0;

// その月の実績合計を取得
$stmt = $pdo->prepare(
    "SELECT SUM(amount) AS total FROM deliveries
     WHERE user_id = :user_id AND DATE_FORMAT(work_date, '%Y-%m') = :target_month"
);
$stmt->execute(['user_id' => $_SESSION['user_id'], 'target_month' => $targetMonth]);
$result = $stmt->fetch();
$currentAmount = (float)($result['total'] ?? 0);

$progressRate = $targetAmount > 0 ? min(100, round($currentAmount / $targetAmount * 100, 1)) : 0;
?>

<!DOCTYPE html>
<html lang="ja">
    <head>
        <link rel="stylesheet" href="style.css">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>目標進捗</title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    </head>
    <div class="card">
    <body>
        <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
        <h1><?= htmlspecialchars($targetMonth) ?> の目標進捗</h1>

        <form method="GET" action="goal_progress.php">
            <input type="month" name="month" value="<?= htmlspecialchars($targetMonth) ?>">
            <button type="submit">表示</button>
        </form>

        <?php if ($targetAmount === 0.0): ?>
            <p>この月の目標が設定されていません。<a href="set_goal.php">目標を設定する</a></p>
        <?php else: ?>
            <p>目標: ¥<?= number_format($targetAmount) ?> / 実績: ¥<?= number_format($currentAmount) ?>(<?= $progressRate ?>%)</p>
            <div class="chart-wrapper">
            <canvas id="progressChart"></canvas>
            </div>

            <script>
                new Chart(document.getElementById('progressChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['達成分', '残り'],
                        datasets: [{
                            data: [<?= $progressRate ?>, <?= max(0, 100 - $progressRate) ?>],
                            backgroundColor: ['#06c167', '#e0e0e0']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { display: true, text: '達成率 <?= $progressRate ?>%'}
                        }
                    }
                });
            </script>
        <?php endif; ?>
    </body>
    </div>
</html>