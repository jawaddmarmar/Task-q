<?php
include '../includes/connectDB.php';
include '../includes/auth.php';

if (isLoggedIn()) {
    redirectTo('dashboard/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
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

        if (!$user) {
            $error = 'Account not found.';
        } else if ((int)$user['MustChangePassword'] === 1 || $user['Password'] === '') {
            redirectTo('auth/setup_password.php?username=' . urlencode($username));
        } else if (!password_verify($password, $user['Password'])) {
            $error = 'Wrong password.';
        } else {
            loginUser($user);
            redirectTo('dashboard/');
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
    <title>Login - Task-Q</title>
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
                <h1>Login</h1>
            </div>
        </div>
        <p>Access your project workspace.</p>
        <form method="POST" class="auth-form">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>" maxlength="100" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <?php if ($error !== '') { ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <button type="submit">Login</button>
        </form>
        <a href="register.php">Create admin account</a>
        <a href="setup_password.php">First time member? Set password</a>
        <a href="forgot_password.php">Forgot password? Reset password</a>
    </main>
</body>
</html>


