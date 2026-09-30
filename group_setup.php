<?php

session_start();

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config/db.php';

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
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $groupName = trim($_POST['group_name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');

        if ($groupName === '' || $nickname === '') {
            $errors[] = 'グループ名とニックネームを入力してください。';
        }

        if (empty($errors)) {
            $inviteCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            $stmt = $pdo->prepare(
                'INSERT INTO groups_table (group_name, invite_code, created_by) VALUES (:group_name, :invite_code, :created_by)'
            );
            $stmt->execute([
                'group_name'  => $groupName,
                'invite_code' => $inviteCode,
                'created_by'  => $_SESSION['user_id'],
            ]);
            $groupId = $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO group_members (group_id, user_id, nickname) VALUES (:group_id, :user_id, :nickname)'
            );
            $stmt->execute([
                'group_id' => $groupId,
                'user_id'  => $_SESSION['user_id'],
                'nickname' => $nickname,
            ]);

            $success = true;
            $createdInviteCode = $inviteCode;
        }
    } elseif ($action === 'join') {
        $inviteCode = strtoupper(trim($_POST['invite_code'] ?? ''));
        $nickname = trim($_POST['nickname'] ?? '');

        if ($inviteCode === '' || $nickname === '') {
            $errors[] = '招待コードとニックネームを入力してください。';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM groups_table WHERE invite_code = :invite_code');
            $stmt->execute(['invite_code' => $inviteCode]);
            $group = $stmt->fetch();

            if (!$group) {
                $errors[] = '招待コードが見つかりません。';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT IGNORE INTO group_members (group_id, user_id, nickname) VALUES (:group_id, :user_id, :nickname)'
                );
                $stmt->execute([
                    'group_id' => $group['id'],
                    'user_id'  => $_SESSION['user_id'],
                    'nickname' => $nickname,
                ]);
                $success = true;
            }
        }
    }
}

// 自分が所属しているグループ一覧
$stmt = $pdo->prepare(
    'SELECT g.id, g.group_name, g.invite_code, gm.nickname
     FROM group_members gm
     JOIN groups_table g ON g.id = gm.group_id
     WHERE gm.user_id = :user_id'
);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$myGroups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>グループ管理</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="dashboard.php" class="back-link">← ダッシュボードに戻る</a>
    <h1>👥 グループ管理</h1>

    <?php if ($success): ?>
        <p class="message-success">
            成功しました!
            <?php if (isset($createdInviteCode)): ?>
                招待コード: <strong><?= htmlspecialchars($createdInviteCode) ?></strong>(このコードを身内に共有してください)
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="message-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>参加中のグループ</h3>
        <?php if (empty($myGroups)): ?>
            <p>まだどのグループにも参加していません。</p>
        <?php else: ?>
            <ul>
                <?php foreach ($myGroups as $group): ?>
                    <li>
                        <a href="ranking.php?group_id=<?= $group['id'] ?>">
                            <?= htmlspecialchars($group['group_name']) ?>
                        </a>
                        (あなたのニックネーム: <?= htmlspecialchars($group['nickname']) ?>, 招待コード: <?= htmlspecialchars($group['invite_code']) ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>新しいグループを作る</h3>
        <form method="POST" action="group_setup.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="create">
            <label>グループ名:</label>
            <input type="text" name="group_name"><br>
            <label>あなたのニックネーム:</label>
            <input type="text" name="nickname"><br>
            <button type="submit">作成する</button>
        </form>
    </div>

    <div class="card">
        <h3>招待コードで参加する</h3>
        <form method="POST" action="group_setup.php">
            <input type="hidden" name="action" value="join">
            <label>招待コード:</label>
            <input type="text" name="invite_code"><br>
            <label>あなたのニックネーム:</label>
            <input type="text" name="nickname"><br>
            <button type="submit">参加する</button>
        </form>
    </div>
</body>
</html>