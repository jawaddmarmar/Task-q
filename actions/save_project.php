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

// Check if data is received
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Pname'] ?? '');
    $desc = trim($_POST['Pdesc'] ?? '');
    $date = $_POST['startDate'] ?? '';
    $budget = str_replace(['$', ',', ' '], '', $_POST['budget'] ?? '');
    $status = strtolower($_POST['status'] ?? 'pending');
    $progress = 0;
    $createdBy = (int)currentUser()['id'];
    $validationError = validateProjectInput($name, $desc, $date, $budget, $status);

    if ($validationError !== '') {
        echo $validationError;
        $conn->close();
        exit;
    }

    // SQL Query
    $sql = "INSERT INTO project (ProjectName, Description, StartDate, Budget, Status, Progress, CreatedByUserID) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssdsii", $name, $desc, $date, $budget, $status, $progress, $createdBy);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "Error: " . $stmt->error;
    }
}




$conn->close();
?>
