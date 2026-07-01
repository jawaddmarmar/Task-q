<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
include '../includes/smtp_mailer.php';

if (isLoggedIn()) {
    redirectTo('dashboard/');
}

$error = '';
$success = false;
$username = trim($_POST['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '') {
        $error = 'Username is required.';
    } else {
        $stmt = $conn->prepare("SELECT u.UserID, u.UserName, u.Email, r.RoleName, m.FullName FROM user u LEFT JOIN role r ON u.RoleID = r.RoleID LEFT JOIN team_member m ON u.MemberID = m.MemberID WHERE u.UserName = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && !empty($user['Email']) && filter_var($user['Email'], FILTER_VALIDATE_EMAIL)) {
            $resetCode = (string) random_int(100000, 999999);
            $displayName = $user['FullName'] ?: $user['UserName'];

            if (taskqSendResetEmail($user['Email'], $displayName, $resetCode)) {
                $_SESSION['password_reset'] = [
                    'user_id' => (int)$user['UserID'],
                    'username' => $user['UserName'],
                    'code_hash' => password_hash($resetCode, PASSWORD_DEFAULT),
                    'expires' => time() + 600,
                    'attempts' => 0
                ];
                $success = true;
            } else {
                unset($_SESSION['password_reset']);
                $error = 'Could not send reset code. Please check SMTP settings.';
            }
        } else {
            $success = true;
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
    <title>Reset Password - Task-Q</title>
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
                <h1>Reset Password</h1>
            </div>
        </div>
        <p>Enter your username and we will send a private 6 digit code to the registered email.</p>

        <form method="POST" class="auth-form">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" maxlength="100" required>
            <?php if ($error !== '') { ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <button type="submit">Send Reset Code</button>
        </form>

        <?php if ($success) { ?>
            <section class="reset-mail-preview reset-mail-sent">
                <p class="reset-mail-title">Email Sent</p>
                <p>If the account exists, a 6 digit reset code was sent to its registered email.</p>
                <small>Check the inbox, then continue to reset your password.</small>
            </section>
            <a class="auth-action-link" href="reset_password.php">Enter Reset Code</a>
        <?php } ?>

        <a href="login.php">Back to login</a>
    </main>
</body>
</html>
<?php $conn->close(); ?>