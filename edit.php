<?php
session_start();

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? $_POST['id'] ?? null;
$errors = [];
$success = false;

if (!$id) {
    die('IDが指定されていません。');
}

// 対象データを取得(自分のデータのみ)
$stmt = $pdo->prepare('SELECT * FROM deliveries WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $id, 'user_id' => $_SESSION['user_id']]);
$delivery = $stmt->fetch();

if (!$delivery) {
    die('データが見つからないか、アクセス権がありません。');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }
    // ここから既存の処理
   $workDate   = $_POST['work_date'] ?? '';
    $platform   = $_POST['platform'] ?? '';
    $amount     = $_POST['amount'] ?? '';
    $bonusAmount = $_POST['bonus_amount'] ?? null;
    $orderCount = $_POST['order_count'] ?? null;
    $distance   = $_POST['distance'] ?? null;
    $weather    = $_POST['weather'] ?? null;
    $timeSlot   = $_POST['time_slot'] ?? null;
    $workHours  = $_POST['work_hours'] ?? null;

    if ($workDate === '') {
        $errors[] = '日付を入力してください。';
    }
    if (!in_array($platform, ['uber_eats', 'demae_can', 'rocket_now'], true)) {
        $errors[] = 'プラットフォームを選択してください。';
    }
    if (!is_numeric($amount) || $amount < 0) {
        $errors[] = '売り上げ金額を正しく入力してください。';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE deliveries SET
                work_date = :work_date,
                platform = :platform,
                amount = :amount,
                bonus_amount = :bonus_amount,
                order_count = :order_count,
                distance = :distance,
                weather = :weather,
                time_slot = :time_slot,
                work_hours = :work_hours
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'work_date'   => $workDate,
            'platform'    => $platform,
            'amount'      => $amount,
            'bonus_amount' => $bonusAmount !== '' ? $bonusAmount : null,
            'order_count' => $orderCount !== '' ? $orderCount : null,
            'distance'    => $distance !== '' ? $distance : null,
            'weather'     => $weather !== '' ? $weather : null,
            'time_slot'   => $timeSlot !== '' ? $timeSlot : null,
            'work_hours'  => $workHours !== '' ? $workHours : null,
            'id'          => $id,
            'user_id'     => $_SESSION['user_id'],
        ]);

        $success = true;
        // 更新後の内容を再取得して画面に反映
        $stmt = $pdo->prepare('SELECT * FROM deliveries WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $_SESSION['user_id']]);
        $delivery = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <title>配達データ編集</title>
</head>
<body>
    <h1>配達データ編集</h1>

    <?php if ($success): ?>
        <p style="color: green;">更新しました!</p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <ul style="color: red;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="edit.php?id=<?= htmlspecialchars($id) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

        <label>日付:</label>
        <input type="date" name="work_date" value="<?= htmlspecialchars($delivery['work_date']) ?>"><br>

        <label>プラットフォーム:</label>
        <select name="platform">
            <?php
            $platforms = ['uber_eats' => 'Uber Eats', 'demae_can' => '出前館', 'rocket_now' => 'Rocket Now'];
            foreach ($platforms as $value => $label):
            ?>
                <option value="<?= $value ?>" <?= $delivery['platform'] === $value ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select><br>

        <label>売り上げ金額(円):</label>
        <input type="number" name="amount" step="1" value="<?= htmlspecialchars($delivery['amount']) ?>"><br>

        <label>クエスト・ボーナス報酬(円):</label>
        <input type="number" name="bonus_amount" min="0" value="<?= htmlspecialchars($delivery['bonus_amount'] ?? '') ?>"><br>

        <label>件数:</label>
        <input type="number" name="order_count" value="<?= htmlspecialchars($delivery['order_count'] ?? '') ?>"><br>

        <label>走行距離(km):</label>
        <input type="number" name="distance" step="0.1" value="<?= htmlspecialchars($delivery['distance'] ?? '') ?>"><br>

        <label>天候:</label>
        <select name="weather">
            <?php
            $weathers = ['' => '選択してください', '晴れ' => '晴れ', '曇り' => '曇り', '雨' => '雨'];
            foreach ($weathers as $value => $label):
            ?>
                <option value="<?= $value ?>" <?= $delivery['weather'] === $value ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select><br>

        <label>時間帯:</label>
        <select name="time_slot">
            <?php
            $slots = ['' => '選択してください', 'morning' => '朝', 'afternoon' => '昼', 'evening' => '夕方', 'night' => '夜'];
            foreach ($slots as $value => $label):
            ?>
                <option value="<?= $value ?>" <?= $delivery['time_slot'] === $value ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select><br>

        <label>稼働時間(時間):</label>
        <input type="number" name="work_hours" step="0.5" value="<?= htmlspecialchars($delivery['work_hours'] ?? '') ?>"><br>

        <button type="submit">更新する</button>
    </form>

    <p><a href="list_delivery.php">一覧に戻る</a></p>
</body>
</html> 
