<?php
include '../includes/connectDB.php';
include '../includes/auth.php';

if (isLoggedIn()) {
    redirectTo('dashboard/');
}

$error = '';
$reset = $_SESSION['password_reset'] ?? null;

if (!$reset || ($reset['expires'] ?? 0) < time()) {
    unset($_SESSION['password_reset']);
    $error = 'Reset code expired. Please request a new code.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $reset = $_SESSION['password_reset'] ?? null;

    if (!$reset || ($reset['expires'] ?? 0) < time()) {
        unset($_SESSION['password_reset']);
        $error = 'Reset code expired. Please request a new code.';
    } else if (($reset['attempts'] ?? 0) >= 3) {
        unset($_SESSION['password_reset']);
        $error = 'Too many attempts. Please request a new code.';
    } else if ($code === '' || !password_verify($code, $reset['code_hash'])) {
        $_SESSION['password_reset']['attempts'] = ($reset['attempts'] ?? 0) + 1;
        $error = 'Invalid reset code.';
    } else if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = (int)$reset['user_id'];
        $stmt = $conn->prepare("UPDATE user SET Password = ?, MustChangePassword = 0 WHERE UserID = ?");
        $stmt->bind_param('si', $hash, $userId);

        if ($stmt->execute()) {
            $username = $reset['username'] ?? '';
            unset($_SESSION['password_reset']);
            redirectTo('auth/login.php?username=' . urlencode($username));
        } else {
            $error = 'Could not reset password.';
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
    <title>New Password - Task-Q</title>
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
                <h1>New Password</h1>
            </div>
        </div>
        <p>Enter the 6 digit code and choose a new password.</p>

        <form method="POST" class="auth-form">
            <label>6 Digit Code</label>
            <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required>
            <label>New Password</label>
            <input type="password" name="password" required>
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
            <?php if ($error !== '') { ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <button type="submit">Reset Password</button>
        </form>

        <a href="forgot_password.php">Request new code</a>
        <a href="login.php">Back to login</a>
    </main>
</body>
</html>
<?php $conn->close(); ?>