<?php
include '../includes/connectDB.php';
include '../includes/auth.php';

$error = '';
$username = trim($_GET['username'] ?? $_POST['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '') {
        $error = 'Username is required.';
    } else if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare("
            SELECT u.*, r.RoleName, m.FullName
            FROM user u
            LEFT JOIN role r ON u.RoleID = r.RoleID
            LEFT JOIN team_member m ON u.MemberID = m.MemberID
            WHERE u.UserName = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || strtolower($user['RoleName'] ?? '') !== 'member') {
            $error = 'Member account not found.';
        } else if ((int)$user['MustChangePassword'] !== 1 && $user['Password'] !== '') {
            $error = 'Password is already set. Please login.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE user SET Password = ?, MustChangePassword = 0 WHERE UserID = ?");
            $update->bind_param('si', $hash, $user['UserID']);
            if ($update->execute()) {
                $user['Password'] = $hash;
                $user['MustChangePassword'] = 0;
                loginUser($user);
                redirectTo('projects/');
            } else {
                $error = 'Could not save password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/project.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth.css?v=<?php echo time(); ?>">
    <title>Set Password - Task-Q</title>
</head>
<body class="auth-page">
    <main class="auth-card taskq-auth-card">
        <div class="auth-brand">
            <span class="auth-logo" aria-hidden="true">
                <svg viewBox="0 0 64 64" role="img">
                    <path d="M32 4 54 4c4 0 6 5 3 8L38 31c-3 3-8 3-11 0L8 12C5 9 7 4 11 4h21Z"/>
                    <path d="M7 22h18c4 0 6 5 3 8L14 44c-2 2-6 2-8-1L1 29c-1-3 2-7 6-7Z"/>
                    <path d="M57 22H39c-4 0-6 5-3 8l14 14c2 2 6 2 8-1l5-14c1-3-2-7-6-7Z"/>
                    <path d="M25 40h14c4 0 6 4 4 7l-7 11c-2 3-6 3-8 0l-7-11c-2-3 0-7 4-7Z"/>
                </svg>
            </span>
            <div>
                <strong>Task-Q</strong>
                <h1>Set Password</h1>
            </div>
        </div>
        <p>First time members create their private password here.</p>
        <form method="POST" class="auth-form">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" maxlength="100" required>
            <label>New Password</label>
            <input type="password" name="password" required>
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
            <?php if ($error !== '') { ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <button type="submit">Save Password</button>
        </form>
        <a href="login.php">Back to login</a>
    </main>
</body>
</html>
