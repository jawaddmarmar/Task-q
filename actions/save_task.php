<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireAdmin();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    $projectId = (int)($_POST['project_id'] ?? 0);
    $teamId = ($_POST['team_id'] ?? '') === '' ? null : (int)$_POST['team_id'];
    $memberId = ($_POST['user_id'] ?? '') === '' ? null : (int)$_POST['user_id'];
    $dueDate = $_POST['due_date'] ?? '';
    $priority = strtolower($_POST['priority'] ?? 'medium');
    $status = strtolower($_POST['status'] ?? 'pending');
    $createdBy = (int)currentUser()['id'];

    $validationError = validateTaskInput($title, $desc, $projectId, $memberId, $dueDate, $priority, $status);
    if ($validationError !== '') {
        echo $validationError;
        $conn->close();
        exit;
    }

    $projectCheck = $conn->prepare("SELECT ProjectID FROM project WHERE ProjectID = ? AND (CreatedByUserID = ? OR CreatedByUserID IS NULL)");
    $projectCheck->bind_param("ii", $projectId, $createdBy);
    $projectCheck->execute();
    if ($projectCheck->get_result()->num_rows === 0) {
        echo 'Please select one of your projects.';
        $conn->close();
        exit;
    }

    $sql = "INSERT INTO task (TaskName, Description, Status, Priority, DueDate, ProjectID, TeamID, MemberID, CreatedByUserID)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssiiii", $title, $desc, $status, $priority, $dueDate, $projectId, $teamId, $memberId, $createdBy);

    echo $stmt->execute() ? "success" : "Error: " . $stmt->error;
}

$conn->close();
?>
