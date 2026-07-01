<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireAdmin();

function validateProjectInput($name, $desc, $date, $budget, $status) {
    $allowedStatuses = ['pending', 'active', 'done'];
    $name = trim($name);
    $desc = trim($desc);

    if ($name === '') {
        return 'Project title is required.';
    }

    if (!preg_match('/^[\p{L}\p{N}]/u', $name)) {
        return 'Project title must start with a letter or number.';
    }

    if (strlen($name) > 80) {
        return 'Project title must be 80 characters or less.';
    }

    if (strlen($desc) > 240) {
        return 'Description must be 240 characters or less.';
    }

    if ($date === '' || $date > date('Y-m-d')) {
        return 'Start date cannot be empty or in the future.';
    }

    if (!is_numeric($budget) || (float)$budget <= 0) {
        return 'Budget must be a number greater than zero.';
    }

    if (!in_array(strtolower($status), $allowedStatuses, true)) {
        return 'Status must be pending, active, or done.';
    }

    return '';
}

if (isset($_POST['id']) && isset($_POST['name'])) {
    $id = (int)$_POST['id'];
    $userId = (int)currentUser()['id'];
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    $budget = str_replace(['$', ',', ' '], '', $_POST['budget'] ?? '');
    $date = $_POST['date'] ?? '';
    $status = strtolower($_POST['status'] ?? '');
    $validationError = validateProjectInput($name, $desc, $date, $budget, $status);

    if ($validationError !== '') {
        echo $validationError;
        $conn->close();
        exit;
    }

    if (isset($_POST['progress'])) {
        $progress = max(0, min(100, (int)$_POST['progress']));
        $sql = "UPDATE project SET 
                ProjectName=?, 
                Description=?, 
                Budget=?, 
                StartDate=?, 
                Status=?,
                Progress=?
                WHERE ProjectID=? AND (CreatedByUserID = ? OR CreatedByUserID IS NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdssiii", $name, $desc, $budget, $date, $status, $progress, $id, $userId);
    } else {
        $sql = "UPDATE project SET 
                ProjectName=?, 
                Description=?, 
                Budget=?, 
                StartDate=?, 
                Status=?
                WHERE ProjectID=? AND (CreatedByUserID = ? OR CreatedByUserID IS NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdssii", $name, $desc, $budget, $date, $status, $id, $userId);
    }

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
} 

else if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $userId = (int)currentUser()['id'];
    $sql = "DELETE FROM project WHERE ProjectID = ? AND (CreatedByUserID = ? OR CreatedByUserID IS NULL)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $userId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo "success";
    } else {
        echo $conn->error ?: 'Project was not deleted.';
    }
}

$conn->close();
?>
