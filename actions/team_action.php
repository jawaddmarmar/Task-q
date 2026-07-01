<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $teamId = (int)($_POST['team_id'] ?? 0);
    $userId = (int)currentUser()['id'];

    if ($teamId <= 0 && $action !== 'delete_member') {
        echo 'Invalid team.';
        $conn->close();
        exit;
    }

    if ($action === 'update') {
        $name = trim($_POST['team_name'] ?? '');
        $desc = trim($_POST['desc'] ?? '');

        if ($name === '' || !preg_match('/^[\p{L}\p{N}]/u', $name)) {
            echo 'Team name is required and must start with a letter or number.';
            exit;
        }
        if (strlen($name) > 120) {
            echo 'Team name must be 120 characters or less.';
            exit;
        }
        if (strlen($desc) > 240) {
            echo 'Description must be 240 characters or less.';
            exit;
        }

        $stmt = $conn->prepare("UPDATE team SET TeamName = ?, Description = ? WHERE TeamID = ?");
        $stmt->bind_param("ssi", $name, $desc, $teamId);
        echo $stmt->execute() ? 'success' : 'Error: ' . $stmt->error;
    } else if ($action === 'add_member') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId <= 0) {
            echo 'Please select a member.';
            exit;
        }

        $stmt = $conn->prepare("INSERT IGNORE INTO team_membership (TeamID, MemberID) VALUES (?, ?)");
        $stmt->bind_param("ii", $teamId, $memberId);
        echo $stmt->execute() ? 'success' : 'Error: ' . $stmt->error;
    } else if ($action === 'remove_member') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId <= 0) {
            echo 'Invalid member.';
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM team_membership WHERE TeamID = ? AND MemberID = ?");
        $stmt->bind_param("ii", $teamId, $memberId);
        echo $stmt->execute() ? 'success' : 'Error: ' . $stmt->error;
    } else if ($action === 'delete') {
        $conn->begin_transaction();
        try {
            $teamCheck = $conn->prepare("SELECT TeamID FROM team WHERE TeamID = ?");
            $teamCheck->bind_param("i", $teamId);
            $teamCheck->execute();
            if ($teamCheck->get_result()->num_rows === 0) {
                throw new Exception('Team not found.');
            }

            $deleteTasks = $conn->prepare("DELETE FROM task WHERE TeamID = ?");
            $deleteTasks->bind_param("i", $teamId);
            $deleteTasks->execute();

            $stmt = $conn->prepare("DELETE FROM team WHERE TeamID = ?");
            $stmt->bind_param("i", $teamId);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                throw new Exception('Team was not deleted.');
            }

            $conn->commit();
            echo 'success';
        } catch (Throwable $e) {
            $conn->rollback();
            echo 'Error: ' . $e->getMessage();
        }
    } else if ($action === 'delete_member') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId <= 0) {
            echo 'Invalid member.';
            exit;
        }

        $conn->begin_transaction();
        try {
            $memberCheck = $conn->prepare("SELECT MemberID FROM team_member WHERE MemberID = ?");
            $memberCheck->bind_param("i", $memberId);
            $memberCheck->execute();
            if ($memberCheck->get_result()->num_rows === 0) {
                throw new Exception('Member not found.');
            }

            $deleteTasks = $conn->prepare("DELETE FROM task WHERE MemberID = ?");
            $deleteTasks->bind_param("i", $memberId);
            $deleteTasks->execute();

            $deleteUser = $conn->prepare("DELETE FROM user WHERE MemberID = ?");
            $deleteUser->bind_param("i", $memberId);
            $deleteUser->execute();

            $stmt = $conn->prepare("DELETE FROM team_member WHERE MemberID = ?");
            $stmt->bind_param("i", $memberId);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                throw new Exception('Member was not deleted.');
            }

            $conn->commit();
            echo 'success';
        } catch (Throwable $e) {
            $conn->rollback();
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        echo 'Invalid action.';
    }
}

$conn->close();
?>
