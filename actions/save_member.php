<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $userName = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $createdBy = (int)currentUser()['id'];

    if ($fullName === '' || !preg_match('/^[\p{L}\p{N}]/u', $fullName)) {
        echo 'Full name is required and must start with a letter or number.';
        exit;
    }
    if ($userName === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $userName)) {
        echo 'Username is required. Use letters, numbers, dot, dash, or underscore.';
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'Valid email is required.';
        exit;
    }
    if ($role === '') {
        echo 'Role is required.';
        exit;
    }
    if ($department === '') {
        echo 'Department is required.';
        exit;
    }

    $roleId = roleIdByName($conn, 'member');
    if ($roleId <= 0) {
        echo 'Member role is not available.';
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO team_member (FullName, UserName, Email, RoleTitle, Department, CreatedByUserID) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $fullName, $userName, $email, $role, $department, $createdBy);
        $stmt->execute();
        $memberId = $conn->insert_id;

        $emptyPassword = '';
        $userStmt = $conn->prepare("INSERT INTO user (UserName, Email, Password, RoleID, MemberID, MustChangePassword) VALUES (?, ?, ?, ?, ?, 1)");
        $userStmt->bind_param("sssii", $userName, $email, $emptyPassword, $roleId, $memberId);
        $userStmt->execute();

        $conn->commit();
        echo 'success';
    } catch (Throwable $e) {
        $conn->rollback();
        echo str_contains($e->getMessage(), 'Duplicate') ? 'Username or email already exists.' : 'Error: ' . $e->getMessage();
    }
}

$conn->close();
?>
