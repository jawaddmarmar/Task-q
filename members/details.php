<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();
$userId = (int)currentUser()['id'];
$memberId = (int)(currentUser()['member_id'] ?? 0);
$memberAccess = isAdmin()
    ? "(CreatedByUserID = $userId OR CreatedByUserID IS NULL)"
    : "MemberID = $memberId";

$member = null;
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM team_member WHERE MemberID = ? AND $memberAccess");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
}

if (!$member) {
    echo "<div style='padding: 50px; text-align: center; font-family: sans-serif;'><h2>Member not found!</h2><a href='../teams/'>Back to Team</a></div>";
    exit;
}

$teams = $conn->prepare("
    SELECT t.TeamID, t.TeamName, t.Description
    FROM team_membership tm
    INNER JOIN team t ON tm.TeamID = t.TeamID
    WHERE tm.MemberID = ?
    ORDER BY t.TeamName ASC
");
$teams->bind_param("i", $member['MemberID']);
$teams->execute();
$teamsResult = $teams->get_result();

$tasks = $conn->prepare("
    SELECT t.TaskID, t.TaskName, t.Status, t.Priority, t.DueDate, p.ProjectID, p.ProjectName
    FROM task t
    LEFT JOIN project p ON t.ProjectID = p.ProjectID
    WHERE t.MemberID = ?
    ORDER BY t.DueDate ASC
");
$tasks->bind_param("i", $member['MemberID']);
$tasks->execute();
$tasksResult = $tasks->get_result();

$projects = $conn->prepare("
    SELECT
        p.ProjectID,
        p.ProjectName,
        p.Status,
        GROUP_CONCAT(DISTINCT team.TeamName ORDER BY team.TeamName SEPARATOR ', ') AS TeamNames,
        MAX(CASE WHEN pm.MemberID IS NOT NULL THEN 1 ELSE 0 END) AS DirectMember
    FROM project p
    LEFT JOIN project_member pm ON p.ProjectID = pm.ProjectID
    LEFT JOIN project_team pt ON p.ProjectID = pt.ProjectID
    LEFT JOIN team_membership tm ON pt.TeamID = tm.TeamID
    LEFT JOIN team team ON tm.TeamID = team.TeamID AND tm.MemberID = ?
    LEFT JOIN task t ON p.ProjectID = t.ProjectID
    WHERE pm.MemberID = ? OR tm.MemberID = ? OR t.MemberID = ?
    GROUP BY p.ProjectID, p.ProjectName, p.Status
    ORDER BY p.ProjectName ASC
");
$projects->bind_param("iiii", $member['MemberID'], $member['MemberID'], $member['MemberID'], $member['MemberID']);
$projects->execute();
$projectsResult = $projects->get_result();
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
    <title><?php echo htmlspecialchars($member['FullName']); ?> - Member Details</title>
</head>
<body>
    <div class="landing">
        <?php include '../includes/sidebar.php'; ?>

        <div class="project_content">
            <div class="project_header">
                <div class="project_title">
                    <a href="../teams/" class="breadcrumb">Back to Team</a>
                    <h1 style="font-size: 28px; color: #111827;"><?php echo htmlspecialchars($member['FullName']); ?></h1>
                    <p>@<?php echo htmlspecialchars($member['UserName']); ?> - <?php echo htmlspecialchars($member['RoleTitle']); ?></p>
                </div>
            </div>

            <div class="details-grid">
                <div>
                    <div class="card">
                        <h2 class="section-title">Tasks</h2>
                        <div class="assignment-list">
                            <?php if ($tasksResult->num_rows === 0) { ?>
                                <p class="grey">No tasks assigned to this member yet.</p>
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

                    <div class="card team-detail-block">
                        <h2 class="section-title">Projects</h2>
                        <div class="assignment-list">
                            <?php if ($projectsResult->num_rows === 0) { ?>
                                <p class="grey">No projects found for this member.</p>
                            <?php } ?>
                            <?php while($project = $projectsResult->fetch_assoc()) { ?>
                                <a class="assignment-row" href="../projects/details.php?id=<?php echo $project['ProjectID']; ?>">
                                    <span class="team-avatar"><?php echo htmlspecialchars(strtoupper(substr($project['ProjectName'], 0, 1))); ?></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($project['ProjectName']); ?></strong>
                                        <p>
                                            <?php echo htmlspecialchars($project['Status']); ?>
                                            <?php if (!empty($project['TeamNames'])) { ?>
                                                - via <?php echo htmlspecialchars($project['TeamNames']); ?>
                                            <?php } elseif (!empty($project['DirectMember'])) { ?>
                                                - direct member
                                            <?php } ?>
                                        </p>
                                    </div>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="side-stack">
                    <div class="card task-assignee-panel">
                        <p class="stat-label">Member Info</p>
                        <div class="assignee-card">
                            <span class="avatar-dot large"><?php echo htmlspecialchars(strtoupper(substr($member['FullName'], 0, 1))); ?></span>
                            <div>
                                <h3 class="task-assignee-name"><?php echo htmlspecialchars($member['FullName']); ?></h3>
                                <p class="task-assignee-detail">@<?php echo htmlspecialchars($member['UserName']); ?></p>
                                <p class="task-assignee-detail"><?php echo htmlspecialchars($member['Email']); ?></p>
                                <p class="task-assignee-detail"><?php echo htmlspecialchars($member['RoleTitle']); ?> - <?php echo htmlspecialchars($member['Department']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <p class="stat-label">Teams</p>
                        <div class="assignment-list">
                            <?php if ($teamsResult->num_rows === 0) { ?>
                                <p class="grey">Not assigned to any team.</p>
                            <?php } ?>
                            <?php while($team = $teamsResult->fetch_assoc()) { ?>
                                <a class="assignment-row" href="../teams/details.php?id=<?php echo $team['TeamID']; ?>">
                                    <span class="team-avatar"><?php echo htmlspecialchars(strtoupper(substr($team['TeamName'], 0, 1))); ?></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($team['TeamName']); ?></strong>
                                        <p><?php echo htmlspecialchars($team['Description'] ?: 'No description.'); ?></p>
                                    </div>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>
