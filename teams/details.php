<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();
$userId = (int)currentUser()['id'];
$memberId = (int)(currentUser()['member_id'] ?? 0);
$teamAccess = isAdmin()
    ? "(CreatedByUserID = $userId OR CreatedByUserID IS NULL)"
    : "TeamID IN (SELECT TeamID FROM team_membership WHERE MemberID = $memberId)";

$team = null;
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM team WHERE TeamID = ? AND $teamAccess");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
}

if (!$team) {
    echo "<div style='padding: 50px; text-align: center; font-family: sans-serif;'><h2>Team not found!</h2><a href='index.php'>Back to Team</a></div>";
    exit;
}

$members = $conn->prepare("
    SELECT m.*
    FROM team_membership tm
    INNER JOIN team_member m ON tm.MemberID = m.MemberID
    WHERE tm.TeamID = ?
    ORDER BY m.FullName ASC
");
$members->bind_param("i", $team['TeamID']);
$members->execute();
$membersResult = $members->get_result();

$projects = $conn->prepare("
    SELECT p.ProjectID, p.ProjectName, p.Status
    FROM project_team pt
    INNER JOIN project p ON pt.ProjectID = p.ProjectID
    WHERE pt.TeamID = ?
    ORDER BY p.ProjectName ASC
");
$projects->bind_param("i", $team['TeamID']);
$projects->execute();
$projectsResult = $projects->get_result();

$tasks = $conn->prepare("
    SELECT DISTINCT t.TaskID, t.TaskName, t.Status, t.Priority, t.DueDate, p.ProjectName
    FROM task t
    LEFT JOIN team_membership tm ON tm.MemberID = t.MemberID
    LEFT JOIN project p ON t.ProjectID = p.ProjectID
    WHERE t.TeamID = ? OR tm.TeamID = ?
    ORDER BY t.DueDate ASC
");
$tasks->bind_param("ii", $team['TeamID'], $team['TeamID']);
$tasks->execute();
$tasksResult = $tasks->get_result();

$availableMembers = $conn->prepare("
    SELECT m.MemberID, m.FullName, m.UserName, m.RoleTitle
    FROM team_member m
    WHERE NOT EXISTS (
        SELECT 1 FROM team_membership tm
        WHERE tm.TeamID = ? AND tm.MemberID = m.MemberID
    )
    ORDER BY m.FullName ASC
");
$availableMembers->bind_param("i", $team['TeamID']);
$availableMembers->execute();
$availableMembersResult = $availableMembers->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/project.css">
    <link rel="stylesheet" href="../css/team.css">
    <link rel="stylesheet" href="../css/task.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <title><?php echo htmlspecialchars($team['TeamName']); ?> - Team Details</title>
</head>
<body>
    <div class="landing">
        <?php include '../includes/sidebar.php'; ?>

        <div class="project_content">
            <div class="project_header">
                <div class="project_title">
                    <a href="index.php" class="breadcrumb">Back to Team</a>
                    <h1 style="font-size: 28px; color: #111827;"><?php echo htmlspecialchars($team['TeamName']); ?></h1>
                    <p><?php echo htmlspecialchars($team['Description'] ?: 'No description added.'); ?></p>
                </div>
                <div class="headerbtn team-actions">
                    <?php if (isAdmin()) { ?>
                        <button id="edit_team_btn" class="secondary-btn">Edit Team</button>
                        <button id="add_team_member_btn">Add Member</button>
                    <?php } ?>
                </div>
            </div>

            <div class="details-grid">
                <div>
                    <div class="card">
                        <h2 class="section-title">Members</h2>
                        <div class="team-member-stack">
                            <?php if ($membersResult->num_rows === 0) { ?>
                                <p class="grey">No members in this team yet.</p>
                            <?php } ?>
                            <?php while($member = $membersResult->fetch_assoc()) { ?>
                                <div class="assignment-row member-management-row">
                                    <a class="member-link member-management-main" href="../members/details.php?id=<?php echo $member['MemberID']; ?>">
                                        <span class="team-avatar"><?php echo htmlspecialchars(strtoupper(substr($member['FullName'], 0, 1))); ?></span>
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['FullName']); ?></strong>
                                            <p>@<?php echo htmlspecialchars($member['UserName']); ?> - <?php echo htmlspecialchars($member['RoleTitle']); ?></p>
                                        </div>
                                    </a>
                                    <?php if (isAdmin()) { ?>
                                        <button type="button" class="remove-member-btn" data-member-id="<?php echo $member['MemberID']; ?>">Remove</button>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="card team-detail-block">
                        <h2 class="section-title">Tasks From This Team</h2>
                        <div class="assignment-list">
                            <?php if ($tasksResult->num_rows === 0) { ?>
                                <p class="grey">No tasks assigned to this team members yet.</p>
                            <?php } ?>
                            <?php while($task = $tasksResult->fetch_assoc()) { ?>
                                <a class="assignment-row" href="../tasks/details.php?id=<?php echo $task['TaskID']; ?>">
                                    <span class="priority-pill <?php echo htmlspecialchars(strtolower($task['Priority'])); ?>"><?php echo htmlspecialchars($task['Priority']); ?></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($task['TaskName']); ?></strong>
                                        <p><?php echo htmlspecialchars($task['ProjectName'] ?? 'No project'); ?> - <?php echo htmlspecialchars($task['Status']); ?></p>
                                    </div>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="side-stack">
                    <div class="card">
                        <p class="stat-label">Projects</p>
                        <div class="assignment-list">
                            <?php if ($projectsResult->num_rows === 0) { ?>
                                <p class="grey">No projects assigned.</p>
                            <?php } ?>
                            <?php while($project = $projectsResult->fetch_assoc()) { ?>
                                <a class="assignment-row" href="../projects/details.php?id=<?php echo $project['ProjectID']; ?>">
                                    <span class="team-avatar"><?php echo htmlspecialchars(strtoupper(substr($project['ProjectName'], 0, 1))); ?></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($project['ProjectName']); ?></strong>
                                        <p><?php echo htmlspecialchars($project['Status']); ?></p>
                                    </div>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="edit-team-overlay">
        <div class="modal-box team-modal-box">
            <div class="modal-header">
                <h2>Edit Team</h2>
                <span id="edit-team-close" class="close-x">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="detailTeamId" value="<?php echo (int)$team['TeamID']; ?>">
                <div class="field-group">
                    <label>Team Name</label>
                    <input type="text" id="detailTeamName" class="team-input" maxlength="120" value="<?php echo htmlspecialchars($team['TeamName']); ?>">
                </div>
                <div class="field-group">
                    <label>Description</label>
                    <textarea id="detailTeamDesc" class="team-input team-textarea" maxlength="240"><?php echo htmlspecialchars($team['Description']); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="edit-team-cancel">Cancel</button>
                <button type="button" class="btn-create" id="edit-team-save">Save Changes</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="add-team-member-overlay">
        <div class="modal-box team-modal-box">
            <div class="modal-header">
                <h2>Add Member To Team</h2>
                <span id="add-team-member-close" class="close-x">&times;</span>
            </div>
            <div class="modal-body">
                <div class="field-group">
                    <label>Member</label>
                    <select id="teamMemberSelect" class="team-input">
                        <option value="">Select member</option>
                        <?php while($member = $availableMembersResult->fetch_assoc()) { ?>
                            <option value="<?php echo $member['MemberID']; ?>"><?php echo htmlspecialchars($member['FullName'] . ' / ' . $member['UserName'] . ' - ' . $member['RoleTitle']); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="add-team-member-cancel">Cancel</button>
                <button type="button" class="btn-create" id="add-team-member-save">Add Member</button>
            </div>
        </div>
    </div>
    <script src="../js/team_details.js?v=<?php echo time(); ?>"></script>
</body>
</html>

<?php $conn->close(); ?>
