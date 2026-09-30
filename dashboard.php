<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>ダッシュボード</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Noto Sans JP", "Hiragino Kaku Gothic ProN", sans-serif;
            background-color: #fff8f0;
            margin: 0;
            padding: 24px;
            color: #333;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 22px;
            margin: 0;
        }
        .welcome {
            font-size: 15px;
            color: #666;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            max-width: 700px;
        }
        .menu-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            padding: 24px 12px;
            text-decoration: none;
            color: #333;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
        }
        .menu-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .menu-card .label {
            font-size: 14px;
            font-weight: bold;
        }
        .menu-card.primary {
            background-color: #ff7a00;
            color: #fff;
        }
        .menu-card.primary .label {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚴 配達記録アプリ</h1>
        <div class="welcome"><?= htmlspecialchars($_SESSION['username']) ?>さん、お疲れ様です</div>
    </div>

    <div class="menu-grid">
        <a href="add_delivery.php" class="menu-card primary">
            <div class="icon">📦</div>
            <div class="label">配達データを登録</div>
        </a>
        <a href="list_delivery.php" class="menu-card">
            <div class="icon">📋</div>
            <div class="label">記録一覧を見る</div>
        </a>
        <a href="chart_daily.php" class="menu-card">
            <div class="icon">📊</div>
            <div class="label">売り上げグラフ</div>
        </a>
        <a href="set_goal.php" class="menu-card">
            <div class="icon">🎯</div>
            <div class="label">目標を設定</div>
        </a>
        <a href="goal_progress.php" class="menu-card">
            <div class="icon">🍔</div>
            <div class="label">目標進捗を見る</div>
        </a>
        <a href="analysis.php" class="menu-card">
            <div class="icon">🕒</div>
            <div class="label">曜日・時間帯分析</div>
        </a>
        <a href="recommend.php" class="menu-card primary">
            <div class="icon">🧭</div>
            <div class="label">今日のおすすめ</div>
        </a>
        <a href="group_setup.php" class="menu-card">
            <div class="icon">👥</div>
            <div class="label">グループ・ランキング</div>
        </a>
        <a href="export_csv.php" class="menu-card">
            <div class="icon">📑</div>
            <div class="label">確定申告用データ</div>
        </a>
        <a href="start_shift.php" class="menu-card primary">
            <div class="icon">⏱</div>
            <div class="label">稼働開始/終了</div>
        </a>
    </div>
</body>
</html>