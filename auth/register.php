<?php
include '../includes/connectDB.php';
include '../includes/auth.php';

if (isLoggedIn()) {
    redirectTo('projects/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
        $error = 'Username is required. Use letters, numbers, dot, dash, or underscore.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email is required.';
    } else if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $roleId = roleIdByName($conn, 'admin');
        if ($roleId <= 0) {
            $error = 'Admin role is not available.';
        } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO user (UserName, Email, Password, RoleID, MustChangePassword) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param('sssi', $username, $email, $hash, $roleId);

        if ($stmt->execute()) {
            redirectTo('auth/login.php?username=' . urlencode($username));
        } else {
            $error = str_contains($stmt->error, 'Duplicate') ? 'Username or email already exists.' : 'Could not create account.';
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
    <link rel="stylesheet" href="../css/project.css">
    <link rel="stylesheet" href="../css/auth.css">
    <title>Register Admin</title>
</head>
<body class="auth-page">
    <main class="auth-card">
        <h1>Register</h1>
        <p>Create an admin account.</p>
        <form method="POST" class="auth-form">
            <label>Username</label>
            <input type="text" name="username" maxlength="100" required>
            <label>Email</label>
            <input type="email" name="email" maxlength="100" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
            <?php if ($error !== '') { ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <button type="submit">Create Account</button>
        </form>
        <a href="login.php">Back to login</a>
    </main>
</body>
</html>
