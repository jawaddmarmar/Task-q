<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);
    $userId = (int)currentUser()['id'];

    if ($projectId <= 0 || $targetId <= 0 || !in_array($type, ['team', 'member'], true)) {
        echo 'Invalid assignment request.';
        $conn->close();
        exit;
    }

    $projectCheck = $conn->prepare("SELECT ProjectID FROM project WHERE ProjectID = ? AND (CreatedByUserID = ? OR CreatedByUserID IS NULL)");
    $projectCheck->bind_param("ii", $projectId, $userId);
    $projectCheck->execute();
    if ($projectCheck->get_result()->num_rows === 0) {
        echo 'You do not have permission to update this project.';
        $conn->close();
        exit;
    }

    if ($type === 'team') {
        $targetCheck = $conn->prepare("SELECT TeamID FROM team WHERE TeamID = ? AND (CreatedByUserID = ? OR CreatedByUserID IS NULL)");
        $stmt = $conn->prepare("INSERT IGNORE INTO project_team (ProjectID, TeamID) VALUES (?, ?)");
    } else {
        $targetCheck = $conn->prepare("SELECT MemberID FROM team_member WHERE MemberID = ? AND (CreatedByUserID = ? OR CreatedByUserID IS NULL)");
        $stmt = $conn->prepare("INSERT IGNORE INTO project_member (ProjectID, MemberID) VALUES (?, ?)");
    }

    $targetCheck->bind_param("ii", $targetId, $userId);
    $targetCheck->execute();
    if ($targetCheck->get_result()->num_rows === 0) {
        echo 'You do not have permission to add this assignment.';
        $conn->close();
        exit;
    }

    $stmt->bind_param("ii", $projectId, $targetId);
    echo $stmt->execute() ? 'success' : 'Error: ' . $stmt->error;
}

$conn->close();
?>
