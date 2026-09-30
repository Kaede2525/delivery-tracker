<?php

session_start();

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config/db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }
    // ここから既存の処理
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

// 簡単なバリデーション
if ($username === '') {
    $errors[] = 'ユーザー名を入力してください。';
}
if (strlen($password) < 8) {
    $errors[] = 'パスワードは8文字以上にしてください。';
}
if ($password !== $passwordConfirm) {
    $errors[] = 'パスワードが一致しません。';
}

// ユーザー名の重複チェック
if (empty($errors)) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username');
    $stmt->execute(['username' => $username]);
    if ($stmt->fetch()) {
        $errors[] = 'このユーザー名は既に使われています。';
    }
}

// エラーがなければ登録処理
if (empty($errors)) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)'
    );
    $stmt->execute([
        'username' => $username,
        'password_hash' => $hashedPassword,
    ]);

    header('Location: login.php');
    exit;
}
}
?>

<!-- HTMLはここから -->
<!DOCTYPE html>
<html lang="ja">
    <head>
        <link rel="stylesheet" href="style.css">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ユーザー登録</title>
</head>
<body>
    <h1>ユーザー登録</h1>

    <?php if (!empty($errors)): ?>
        <ul style="color: red;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<form method="POST" action="register.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
    <label for="username">ユーザー名:</label>
    <input type="text" name="username" value="<?= htmlspecialchars($username ?? '') ?>"><br>

    <label for="password">パスワード:</label>
    <input type="password" name="password"><br>

    <label for="password">パスワード(確認用):</label>
    <input type="password" name="password_confirm"><br>

    <button type="submit">登録する</button>
</form>
<p>すでにアカウントをお持ちの方は <a href="login.php">こちらからログイン</a></p>
</body>
</html>
