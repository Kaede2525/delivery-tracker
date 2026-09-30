<?php

session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 並び替えの設定
$allowedSorts = ['work_date', 'amount', 'platform'];
$sort = $_GET['sort'] ?? 'work_date';
$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'work_date';
}

// 絞り込みの設定
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// SQL組み立て
$sql = 'SELECT id, work_date, platform, amount, bonus_amount, order_count, distance, weather, time_slot, work_hours
        FROM deliveries
        WHERE user_id = :user_id';

$params = ['user_id' => $_SESSION['user_id']];

if ($startDate !== '') {
    $sql .= ' AND work_date >= :start_date';
    $params['start_date'] = $startDate;
}
if ($endDate !== '') {
    $sql .= ' AND work_date <= :end_date';
    $params['end_date'] = $endDate;
}

$sql .= " ORDER BY {$sort} {$order}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deliveries = $stmt->fetchAll();

$platformNames = [
    'uber_eats'  => 'Uber Eats',
    'demae_can'  => '出前館',
    'rocket_now' => 'Rocket Now',
];

// 並び替えリンク用のヘルパー関数
function sortLink($column, $label, $currentSort, $currentOrder, $startDate, $endDate) {
    $nextOrder = ($currentSort === $column && $currentOrder === 'DESC') ? 'asc' : 'desc';
    $arrow = '';
    if ($currentSort === $column) {
        $arrow = $currentOrder === 'DESC' ? ' ▼' : ' ▲';
    }
    $query = http_build_query([
        'sort' => $column,
        'order' => $nextOrder,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);
    return '<a href="list_delivery.php?' . $query . '">' . htmlspecialchars($label) . $arrow . '</a>';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <title>配達記録一覧</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        th a { text-decoration: none; color: #333; }
        .filter-form { margin-bottom: 16px; }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
    <h1>配達記録一覧</h1>
    <p><a href="add_delivery.php">+ 新しく登録する</a></p>

    <form method="GET" action="list_delivery.php" class="filter-form">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <input type="hidden" name="order" value="<?= htmlspecialchars(strtolower($order)) ?>">

        開始日: <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
        終了日: <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
        <button type="submit">絞り込む</button>
        <a href="list_delivery.php">条件クリア</a>
    </form>

    <?php if (empty($deliveries)): ?>
        <p>該当する記録がありません。</p>
    <?php else: ?>
        <div class="card">
        <table>
            <thead>
                <tr>
                    <th><?= sortLink('work_date', '日付', $sort, $order, $startDate, $endDate) ?></th>
                    <th><?= sortLink('platform', 'プラットフォーム', $sort, $order, $startDate, $endDate) ?></th>
                    <th><?= sortLink('amount', '売り上げ', $sort, $order, $startDate, $endDate) ?></th>
                    <th><?= sortLink('bonus_amount', 'クエスト・ボーナス報酬', $sort, $order, $startDate, $endDate) ?></th>
                    <th>件数</th>
                    <th>距離(km)</th>
                    <th>天候</th>
                    <th>時間帯</th>
                    <th>稼働時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deliveries as $delivery): ?>
                    <tr>
                        <td><?= htmlspecialchars($delivery['work_date']) ?></td>
                        <td><?= htmlspecialchars($platformNames[$delivery['platform']] ?? $delivery['platform']) ?></td>
                        <td><?= htmlspecialchars(number_format((float)($delivery['amount'] ?? 0))) ?>円</td>
                        <td><?= htmlspecialchars(number_format((float)($delivery['bonus_amount'] ?? 0))) ?>円</td>
                        <td><?= htmlspecialchars($delivery['order_count'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(number_format((float)$delivery['distance'], 2)) ?> km</td>
                        <td><?= htmlspecialchars($delivery['weather'] ?? '未入力') ?></td>
                        <td><?= htmlspecialchars($delivery['time_slot'] ?? '未入力') ?></td>
                        <td><?= htmlspecialchars(number_format((float)($delivery['work_hours'] ?? 0), 2)) ?> h</td>
                        <td>
                            <a href="edit.php?id=<?= $delivery['id'] ?>">編集</a>
                            |
                            <a href="delete.php?id=<?= $delivery['id'] ?>" onclick="return confirm('本当に削除しますか?');">削除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</body>
</html>

