<?php
// ranking.php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$groupId = $_GET['group_id'] ?? null;
if (!$groupId) {
    die('グループが指定されていません。');
}

// 自分がこのグループのメンバーか確認
$stmt = $pdo->prepare('SELECT * FROM group_members WHERE group_id = :group_id AND user_id = :user_id');
$stmt->execute(['group_id' => $groupId, 'user_id' => $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    die('このグループのメンバーではありません。');
}

$stmt = $pdo->prepare('SELECT group_name FROM groups_table WHERE id = :group_id');
$stmt->execute(['group_id' => $groupId]);
$group = $stmt->fetch();

// 今週(直近7日)の時給ランキングを集計
$stmt = $pdo->prepare(
    "SELECT gm.nickname, SUM(d.amount) AS total_amount, SUM(d.work_hours) AS total_hours
     FROM group_members gm
     JOIN deliveries d ON d.user_id = gm.user_id
     WHERE gm.group_id = :group_id
       AND d.work_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY gm.user_id, gm.nickname"
);
$stmt->execute(['group_id' => $groupId]);
$rows = $stmt->fetchAll();

$ranking = [];
foreach ($rows as $row) {
    $hourlyRate = $row['total_hours'] > 0 ? $row['total_amount'] / $row['total_hours'] : 0;
    $ranking[] = [
        'nickname'     => $row['nickname'],
        'total_amount' => (float)$row['total_amount'],
        'hourly_rate'  => round($hourlyRate),
    ];
}

usort($ranking, function ($a, $b) {
    return $b['hourly_rate'] <=> $a['hourly_rate'];
});
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>グループランキング</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .rank-row {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .rank-num {
            font-size: 20px;
            font-weight: bold;
            width: 40px;
            color: #ff7a00;
        }
        .rank-info {
            flex: 1;
        }
        .rank-nickname {
            font-weight: bold;
        }
        .rank-rate {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <a href="group_setup.php" class="back-link">← グループ管理に戻る</a>
    <h1>🏆 <?= htmlspecialchars($group['group_name']) ?> の週間ランキング</h1>
    <p style="color: #666; font-size: 14px;">直近7日間の時給(円/時間)ランキングです。</p>

    <div class="card">
        <?php if (empty($ranking)): ?>
            <p>まだデータがありません。</p>
        <?php else: ?>
            <?php foreach ($ranking as $i => $entry): ?>
                <div class="rank-row">
                    <div class="rank-num"><?= $i + 1 ?>位</div>
                    <div class="rank-info">
                        <div class="rank-nickname"><?= htmlspecialchars($entry['nickname']) ?></div>
                        <div class="rank-rate">¥<?= number_format($entry['hourly_rate']) ?>/h(週合計 ¥<?= number_format($entry['total_amount']) ?>)</div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>