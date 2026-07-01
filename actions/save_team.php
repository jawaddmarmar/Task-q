<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamName = trim($_POST['team_name'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    $members = $_POST['members'] ?? [];
    $createdBy = (int)currentUser()['id'];

    if ($teamName === '' || !preg_match('/^[\p{L}\p{N}]/u', $teamName)) {
        echo 'Team name is required and must start with a letter or number.';
        exit;
    }
    if (strlen($teamName) > 120) {
        echo 'Team name must be 120 characters or less.';
        exit;
    }
    if (strlen($desc) > 240) {
        echo 'Description must be 240 characters or less.';
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO team (TeamName, Description, CreatedByUserID) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $teamName, $desc, $createdBy);
        $stmt->execute();
        $teamId = $conn->insert_id;

        $memberStmt = $conn->prepare("INSERT INTO team_membership (TeamID, MemberID) VALUES (?, ?)");
        foreach ($members as $memberId) {
            $memberId = (int)$memberId;
            if ($memberId > 0) {
                $memberStmt->bind_param("ii", $teamId, $memberId);
                $memberStmt->execute();
            }
        }

        $conn->commit();
        echo 'success';
    } catch (Throwable $e) {
        $conn->rollback();
        echo 'Error: ' . $e->getMessage();
    }
}

$conn->close();
?>
