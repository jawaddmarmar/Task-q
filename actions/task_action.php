<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();

function validateTaskInput($title, $desc, $projectId, $memberId, $dueDate, $priority, $status) {
    $allowedPriorities = ['low', 'medium', 'high', 'critical'];
    $allowedStatuses = ['pending', 'active', 'done'];
    $title = trim($title);
    $desc = trim($desc);

    if ($title === '') return 'Task title is required.';
    if (!preg_match('/^[\p{L}\p{N}]/u', $title)) return 'Task title must start with a letter or number.';
    if (strlen($title) > 90) return 'Task title must be 90 characters or less.';
    if (strlen($desc) > 300) return 'Description must be 300 characters or less.';
    if ($projectId <= 0) return 'Please select a project.';
    if ($dueDate === '' || $dueDate < date('Y-m-d')) return 'Due date cannot be empty or in the past.';
    if (!in_array(strtolower($priority), $allowedPriorities, true)) return 'Priority must be low, medium, high, or critical.';
    if (!in_array(strtolower($status), $allowedStatuses, true)) return 'Status must be pending, active, or done.';

    return '';
}

if (isset($_POST['member_status_update'])) {
    $id = (int)($_POST['id'] ?? 0);
    $status = strtolower($_POST['status'] ?? '');
    $allowedStatuses = ['pending', 'active', 'done'];

    if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
        echo 'Choose a valid status.';
        $conn->close();
        exit;
    }

    if (isMember()) {
        $memberId = (int)(currentUser()['member_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE task SET Status = ? WHERE TaskID = ? AND (MemberID = ? OR MemberID IS NULL)");
        $stmt->bind_param("sii", $status, $id, $memberId);
    } else {
        $stmt = $conn->prepare("UPDATE task SET Status = ? WHERE TaskID = ?");
        $stmt->bind_param("si", $status, $id);
    }

    echo $stmt->execute() ? "success" : "error";
} else if (isset($_POST['id']) && isset($_POST['title'])) {
    requireAdmin();
    $id = (int)$_POST['id'];
    $userId = (int)currentUser()['id'];
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    $projectId = (int)($_POST['project_id'] ?? 0);
    $teamId = ($_POST['team_id'] ?? '') === '' ? null : (int)$_POST['team_id'];
    $memberId = ($_POST['user_id'] ?? '') === '' ? null : (int)$_POST['user_id'];
    $dueDate = $_POST['due_date'] ?? '';
    $priority = strtolower($_POST['priority'] ?? 'medium');
    $status = strtolower($_POST['status'] ?? 'pending');

    $validationError = validateTaskInput($title, $desc, $projectId, $memberId, $dueDate, $priority, $status);
    if ($validationError !== '') {
        echo $validationError;
        $conn->close();
        exit;
    }

    $sql = "UPDATE task SET TaskName=?, Description=?, Status=?, Priority=?, DueDate=?, ProjectID=?, TeamID=?, MemberID=? WHERE TaskID=? AND (CreatedByUserID = ? OR CreatedByUserID IS NULL)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssiiiii", $title, $desc, $status, $priority, $dueDate, $projectId, $teamId, $memberId, $id, $userId);

    echo $stmt->execute() ? "success" : "error";
} else if (isset($_POST['id'])) {
    requireAdmin();
    $id = (int)$_POST['id'];
    $userId = (int)currentUser()['id'];
    $stmt = $conn->prepare("DELETE FROM task WHERE TaskID = ? AND (CreatedByUserID = ? OR CreatedByUserID IS NULL)");
    $stmt->bind_param("ii", $id, $userId);

    echo ($stmt->execute() && $stmt->affected_rows > 0) ? "success" : ($stmt->error ?: 'Task was not deleted.');
}

$conn->close();
?>
