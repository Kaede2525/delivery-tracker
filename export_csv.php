<?php
// export_csv.php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// GETパラメータでダウンロード実行かどうか判定
$isExport = isset($_GET['export']);

$startDate = $_GET['start_date'] ?? date('Y') . '-01-01';
$endDate   = $_GET['end_date'] ?? date('Y') . '-12-31';

$platformNames = [
    'uber_eats'  => 'Uber Eats',
    'demae_can'  => '出前館',
    'rocket_now' => 'Rocket Now',
];

if ($isExport) {
    $stmt = $pdo->prepare(
        'SELECT work_date, platform, amount, order_count, distance, work_hours
         FROM deliveries
         WHERE user_id = :user_id
           AND work_date BETWEEN :start_date AND :end_date
         ORDER BY work_date ASC'
    );
    $stmt->execute([
        'user_id'    => $_SESSION['user_id'],
        'start_date' => $startDate,
        'end_date'   => $endDate,
    ]);
    $rows = $stmt->fetchAll();

    // CSVヘッダーを送信
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="delivery_income_' . $startDate . '_' . $endDate . '.csv"');

    // Excelで文字化けしないようBOMを付加
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // ヘッダー行(帳簿の勘定科目を意識した列名)
    fputcsv($output, ['日付', 'プラットフォーム', '売上金額(円)', '件数', '走行距離(km)', '稼働時間(h)', '摘要']);

    $totalAmount = 0;
    foreach ($rows as $row) {
        $platformLabel = $platformNames[$row['platform']] ?? $row['platform'];
        fputcsv($output, [
            $row['work_date'],
            $platformLabel,
            $row['amount'],
            $row['order_count'],
            $row['distance'],
            $row['work_hours'],
            $platformLabel . ' 配達報酬',
        ]);
        $totalAmount += $row['amount'];
    }

    // 合計行
    fputcsv($output, []);
    fputcsv($output, ['', '', '', '', '', '', '']);
    fputcsv($output, ['合計', '', $totalAmount, '', '', '', '']);

    fclose($output);
    exit;
}

// 画面表示用に、期間内の集計サマリーも出す
$stmt = $pdo->prepare(
    'SELECT platform, SUM(amount) AS total_amount, COUNT(*) AS record_count
     FROM deliveries
     WHERE user_id = :user_id
       AND work_date BETWEEN :start_date AND :end_date
     GROUP BY platform'
);
$stmt->execute([
    'user_id'    => $_SESSION['user_id'],
    'start_date' => $startDate,
    'end_date'   => $endDate,
]);
$summary = $stmt->fetchAll();

$grandTotal = 0;
foreach ($summary as $row) {
    $grandTotal += $row['total_amount'];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>確定申告用データ出力</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
    <h1>📑 確定申告用データ出力</h1>

    <div class="card">
        <form method="GET" action="export_csv.php">
            <label>開始日:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"><br>

            <label>終了日:</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"><br>

            <button type="submit">この期間で集計表示</button>
        </form>
    </div>

    <div class="card">
        <h3><?= htmlspecialchars($startDate) ?> 〜 <?= htmlspecialchars($endDate) ?> の売り上げ集計</h3>

        <?php if (empty($summary)): ?>
            <p>この期間のデータがありません。</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>プラットフォーム</th>
                        <th>件数</th>
                        <th>売上合計</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($platformNames[$row['platform']] ?? $row['platform']) ?></td>
                            <td><?= $row['record_count'] ?>件</td>
                            <td>¥<?= number_format($row['total_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold;">
                        <td>合計</td>
                        <td></td>
                        <td>¥<?= number_format($grandTotal) ?></td>
                    </tr>
                </tbody>
            </table>

            <p style="margin-top: 16px;">
                <a href="export_csv.php?export=1&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>">
                    <button type="button">📥 この内容をCSVでダウンロード</button>
                </a>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>