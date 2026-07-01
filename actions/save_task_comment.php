<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $user = currentUser();
    $commentedBy = $user['full_name'] ?? $user['username'] ?? 'User';
    $commentedRole = ucfirst($user['role'] ?? 'member');

    if ($taskId <= 0) {
        echo 'Invalid task.';
        exit;
    }

    if ($comment === '') {
        echo 'Comment cannot be empty.';
        exit;
    }

    if (strlen($comment) > 500) {
        echo 'Comment must be 500 characters or less.';
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO task_comment (TaskID, CommentText, CommentedBy, CommentedRole) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $taskId, $comment, $commentedBy, $commentedRole);

    echo $stmt->execute() ? 'success' : 'Error: ' . $stmt->error;
}

$conn->close();
?>
