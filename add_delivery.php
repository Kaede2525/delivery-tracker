<?php
session_start();

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config/db.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }
    // ここから既存の処理
    $work_Date = $_POST['work_date'] ?? '';
    $platform = $_POST['platform'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $bonusAmount = $_POST['bonus_amount'] ?? '';
    $orderCount = $_POST['order_count'] ?? null;
    $distance = $_POST['distance'] ?? null;
    $weather = $_POST['weather'] ?? null;
    $timeSlot = $_POST['time_slot'] ?? null;
    $workHours = $_POST['work_hours'] ?? null;

// バリデーション
if ($work_Date === '') {
    $errors[] = '日付を入力してください。';
}
if (!in_array($platform, ['uber_eats', 'demae_can', 'rocket_now', 'menu'])) {
    $errors[] = 'プラットフォームを選択してください。';
}
if (!is_numeric($amount) || $amount < 0) {
    $errors[] = '売上金額を正しく入力してください。';
}

// 登録処理
if (empty($errors)) {
    $stmt = $pdo->prepare(
        'INSERT INTO deliveries
            (user_id, work_date, platform, amount, bonus_amount, order_count, distance, weather, time_slot, work_hours)
         VALUES
            (:user_id, :work_date, :platform, :amount, :bonus_amount, :order_count, :distance, :weather, :time_slot, :work_hours)'
    );
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'work_date' => $work_Date,
        'platform' => $platform,
        'amount' => $amount,
        'bonus_amount' => $bonusAmount !== '' ? $bonusAmount : 0,
        'order_count' => $orderCount !== '' ? $orderCount : null,
        'distance' => $distance !== '' ? $distance : null,
        'weather' => $weather !== '' ? $weather : null,
        'time_slot' => $timeSlot !== '' ? $timeSlot : null,
        'work_hours' => $workHours !== '' ? $workHours : null,
    ]);

    $success = true;
}
}
?>

<!DOCTYPE html>
<html lang="ja">
    <head>
        <link rel="stylesheet" href="style.css">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>配達データ登録</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .error { color: red; }
            .success { color: green; }
        </style>
    </head>
    <body>
        <h1>配達データ登録</h1>

        <?php if ($success): ?>
            <p style="color: green;">登録完了！</p>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <ul class="error">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST" action="add_delivery.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <label>日付:</label>
            <input type="date" name="work_date" value="<?= htmlspecialchars($work_Date ?? date('Y-m-d')) ?>" required><br>

            <label>プラットフォーム:</label>
            <select name="platform" required>
                <option value="">選択してください</option>
                <option value="uber_eats">Uber Eats</option>
                <option value="demae_can">出前館</option>
                <option value="rocket_now">Rocket Now</option>
                <option value="menu">menu</option>
            </select><br>

            <label>売上金額(円):</label>
            <input type="number" name="amount" min="0" required><br>

            <label>クエスト・ボーナス報酬(円):</label>
            <input type="number" name="bonus_amount" min="0"><br>

            <label>注文件数:</label>
            <input type="number" name="order_count" min="0"><br>

            <label>走行距離 (km):</label>
            <input type="number" step="0.01" name="distance" min="0"><br>

            <label>天気:</label>
            <select name="weather">
                <option value="">選択してください</option>
                <option value="晴れ">晴れ</option>
                <option value="曇り">曇り</option>
                <option value="雨">雨</option>
                <option value="雪">雪</option>
            </select><br>

            <label>時間帯:</label>
            <select name="time_slot">
                <option value="">選択してください</option>
                <option value="morning">朝</option>
                <option value="afternoon">昼</option>
                <option value="evening">夕方</option>
                <option value="night">夜</option>
            </select><br>

            <label>稼働時間 (h):</label>
            <input type="number" step="0.01" name="work_hours" min="0"><br>

            <button type="submit">登録</button>
        </form>
        <br>
        <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
    </body>
</html>