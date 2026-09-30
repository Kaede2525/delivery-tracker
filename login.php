<?php

SESSION_start();

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

    if ($username === '' || $password === '') {
        $errors[] = 'ユーザー名とパスワードを入力してください。';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // ログイン成功: セッションにユーザー情報を保存
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        header('Location: dashboard.php'); // 仮のログイン後のページ
        exit;

    } else {
        $errors[] = 'ユーザー名またはパスワードが間違っています。';
    }
    }
}
?>

<! -- HTMLはここから -- >
<!DOCTYPE html>
<html lang="ja">
    <head>
        <link rel="stylesheet" href="style.css">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ログイン</title>
</head>
<body>
    <h1>ログイン</h1>

    <?php if (!empty($errors)): ?>
        <ul style="color: red;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
        <label>ユーザー名:</label>
        <input type="text" name="username"><br>

        <label>パスワード:</label>
        <input type="password" name="password"><br>

        <button type="submit">ログイン</button>
    </form>
    <p>アカウントをお持ちでない方は <a href="register.php">こちらから新規登録</a></p>
</body>
</html>