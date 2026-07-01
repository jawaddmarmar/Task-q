<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();

$userId = (int)currentUser()['id'];
$memberId = (int)(currentUser()['member_id'] ?? 0);

$projectScope = isAdmin()
    ? "(p.CreatedByUserID = $userId OR p.CreatedByUserID IS NULL)"
    : "(
        EXISTS (SELECT 1 FROM project_member pm WHERE pm.ProjectID = p.ProjectID AND pm.MemberID = $memberId)
        OR EXISTS (SELECT 1 FROM task t WHERE t.ProjectID = p.ProjectID AND t.MemberID = $memberId)
        OR EXISTS (
            SELECT 1
            FROM project_team pt
            INNER JOIN team_membership tm ON pt.TeamID = tm.TeamID
            WHERE pt.ProjectID = p.ProjectID AND tm.MemberID = $memberId
        )
    )";

$taskScope = isAdmin()
    ? "(t.CreatedByUserID = $userId OR t.CreatedByUserID IS NULL)"
    : "(t.MemberID = $memberId OR EXISTS (SELECT 1 FROM team_membership tm WHERE tm.TeamID = t.TeamID AND tm.MemberID = $memberId))";

function reportStatusClass($status) {
    $status = strtolower($status ?? 'pending');
    return in_array($status, ['pending', 'active', 'done'], true) ? $status : 'pending';
}

function reportPriorityClass($priority) {
    $priority = strtolower($priority ?? 'medium');
    return in_array($priority, ['low', 'medium', 'high', 'critical'], true) ? $priority : 'medium';
}

$projectsResult = $conn->query("
    SELECT p.ProjectID, p.ProjectName
    FROM project p
    WHERE $projectScope
    ORDER BY p.ProjectID DESC
");
$projects = [];
while ($row = $projectsResult->fetch_assoc()) {
    $projects[] = $row;
}

$selectedProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : (int)($projects[0]['ProjectID'] ?? 0);
$selectedProject = null;

if ($selectedProjectId > 0) {
    $projectStmt = $conn->prepare("
        SELECT
            p.*,
            COUNT(t.TaskID) AS TaskCount,
            SUM(CASE WHEN LOWER(t.Status) = 'done' THEN 1 ELSE 0 END) AS DoneCount,
            SUM(CASE WHEN LOWER(t.Status) != 'done' THEN 1 ELSE 0 END) AS OpenCount,
            COUNT(DISTINCT t.MemberID) AS AssigneeCount
        FROM project p
        LEFT JOIN task t ON t.ProjectID = p.ProjectID AND $taskScope
        WHERE p.ProjectID = ? AND $projectScope
        GROUP BY p.ProjectID
    ");
    $projectStmt->bind_param("i", $selectedProjectId);
    $projectStmt->execute();
    $selectedProject = $projectStmt->get_result()->fetch_assoc();
}

$tasks = null;
$comments = null;
$completedTasks = null;
$challengeTasks = null;

if ($selectedProject) {
    $taskStmt = $conn->prepare("
        SELECT t.*, m.FullName, m.UserName, team.TeamName
        FROM task t
        LEFT JOIN team_member m ON t.MemberID = m.MemberID
        LEFT JOIN team team ON t.TeamID = team.TeamID
        WHERE t.ProjectID = ? AND $taskScope
        ORDER BY
            CASE WHEN t.DueDate IS NULL THEN 1 ELSE 0 END,
            t.DueDate ASC,
            t.TaskID DESC
    ");
    $taskStmt->bind_param("i", $selectedProject['ProjectID']);
    $taskStmt->execute();
    $tasks = $taskStmt->get_result();

    $commentStmt = $conn->prepare("
        SELECT c.CommentText, c.CreatedAt, c.CommentedBy, c.CommentedRole, t.TaskName
        FROM task_comment c
        INNER JOIN task t ON c.TaskID = t.TaskID
        WHERE t.ProjectID = ? AND $taskScope
        ORDER BY c.CreatedAt DESC
        LIMIT 12
    ");
    $commentStmt->bind_param("i", $selectedProject['ProjectID']);
    $commentStmt->execute();
    $comments = $commentStmt->get_result();

    $completedStmt = $conn->prepare("
        SELECT TaskName
        FROM task t
        WHERE t.ProjectID = ? AND $taskScope AND LOWER(t.Status) = 'done'
        ORDER BY t.TaskID DESC
        LIMIT 4
    ");
    $completedStmt->bind_param("i", $selectedProject['ProjectID']);
    $completedStmt->execute();
    $completedTasks = $completedStmt->get_result();

    $challengeStmt = $conn->prepare("
        SELECT TaskName, Priority, DueDate
        FROM task t
        WHERE t.ProjectID = ? AND $taskScope
            AND (
                LOWER(t.Status) != 'done'
                OR LOWER(t.Priority) IN ('high', 'critical')
                OR (t.DueDate IS NOT NULL AND t.DueDate < CURDATE())
            )
        ORDER BY
            CASE WHEN LOWER(t.Priority) = 'critical' THEN 0 WHEN LOWER(t.Priority) = 'high' THEN 1 ELSE 2 END,
            t.DueDate ASC
        LIMIT 4
    ");
    $challengeStmt->bind_param("i", $selectedProject['ProjectID']);
    $challengeStmt->execute();
    $challengeTasks = $challengeStmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../css/project.css">
        <link rel="stylesheet" href="../css/task.css">
        <link rel="stylesheet" href="../css/report.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
        <title>Project Reports</title>
    </head>
    <body>
        <div class="landing">
            <?php include '../includes/sidebar.php'; ?>

            <main class="project_content">
                <div class="project_header report-page-header">
                    <div class="project_title">
                        <h1>Project Reports</h1>
                        <p>Deep dive into project performance, linked tasks, and task discussions.</p>
                    </div>
                    <div class="headerbtn report-header-actions">
                        <button type="button" class="report-new-btn" id="openReportModal">New Report</button>
                    </div>
                </div>

                <?php if (!$selectedProject) { ?>
                    <div class="report-empty">No projects available for reports yet.</div>
                <?php } else {
                    $progress = max(0, min(100, (int)($selectedProject['Progress'] ?? 0)));
                    $taskCount = (int)($selectedProject['TaskCount'] ?? 0);
                    $doneCount = (int)($selectedProject['DoneCount'] ?? 0);
                    $velocity = $taskCount > 0 ? round(($doneCount / $taskCount) * 100) : 0;
                ?>
                    <section class="report-workspace">
                        <aside class="report-comments-panel">
                            <div class="report-panel-header">
                                <div>
                                    <span class="panel-icon"></span>
                                    <h2>Task Comments</h2>
                                </div>
                                <form method="GET">
                                    <select name="project_id" onchange="this.form.submit()">
                                        <?php foreach ($projects as $projectOption) { ?>
                                            <option value="<?php echo (int)$projectOption['ProjectID']; ?>" <?php if ((int)$projectOption['ProjectID'] === (int)$selectedProject['ProjectID']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($projectOption['ProjectName']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </form>
                            </div>

                            <div class="report-comment-feed">
                                <?php if ($comments->num_rows === 0) { ?>
                                    <p class="report-note">No comments for this project yet.</p>
                                <?php } ?>
                                <?php while ($comment = $comments->fetch_assoc()) { ?>
                                    <div class="feed-comment">
                                        <span class="avatar-dot"><?php echo htmlspecialchars(strtoupper(substr($comment['CommentedBy'] ?? 'U', 0, 1))); ?></span>
                                        <div>
                                            <div class="feed-comment-meta">
                                                <strong><?php echo htmlspecialchars($comment['CommentedBy'] ?? 'Unknown User'); ?></strong>
                                                <small><?php echo htmlspecialchars(date('M d, Y', strtotime($comment['CreatedAt']))); ?></small>
                                            </div>
                                            <p><?php echo htmlspecialchars($comment['CommentText']); ?></p>
                                            <span><?php echo htmlspecialchars($comment['TaskName']); ?></span>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </aside>

                        <section class="report-detail-panel">
                            <div class="report-detail-header">
                                <div>
                                    <h2>Project Detailed Report</h2>
                                    <p>Comprehensive overview of project status and team metrics.</p>
                                </div>
                                <form method="GET">
                                    <select name="project_id" onchange="this.form.submit()">
                                        <?php foreach ($projects as $projectOption) { ?>
                                            <option value="<?php echo (int)$projectOption['ProjectID']; ?>" <?php if ((int)$projectOption['ProjectID'] === (int)$selectedProject['ProjectID']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($projectOption['ProjectName']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </form>
                            </div>

                            <div class="report-detail-grid">
                                <article class="report-info-card">
                                    <div class="report-info-title">
                                        <h3>Project Summary</h3>
                                        <span class="statusColor <?php echo reportStatusClass($selectedProject['Status']); ?>"><?php echo htmlspecialchars($selectedProject['Status'] ?? 'Pending'); ?></span>
                                    </div>
                                    <p><?php echo nl2br(htmlspecialchars($selectedProject['Description'] ?: 'No project summary added yet.')); ?></p>
                                    <dl class="report-mini-stats">
                                        <div><dt>Budget</dt><dd>$<?php echo number_format($selectedProject['Budget'] ?? 0, 2); ?></dd></div>
                                        <div><dt>Tasks</dt><dd><?php echo number_format($taskCount); ?></dd></div>
                                    </dl>
                                </article>

                                <article class="report-info-card">
                                    <h3>Achievements</h3>
                                    <ul class="report-check-list">
                                        <?php if ($completedTasks->num_rows === 0) { ?>
                                            <li>No completed tasks yet.</li>
                                        <?php } ?>
                                        <?php while ($doneTask = $completedTasks->fetch_assoc()) { ?>
                                            <li><?php echo htmlspecialchars($doneTask['TaskName']); ?></li>
                                        <?php } ?>
                                    </ul>
                                </article>

                                <article class="report-info-card">
                                    <h3>Challenges</h3>
                                    <ul class="report-warning-list">
                                        <?php if ($challengeTasks->num_rows === 0) { ?>
                                            <li>No blockers detected for this project.</li>
                                        <?php } ?>
                                        <?php while ($challenge = $challengeTasks->fetch_assoc()) { ?>
                                            <li>
                                                <?php echo htmlspecialchars($challenge['TaskName']); ?>
                                                <span><?php echo htmlspecialchars($challenge['Priority'] ?? 'medium'); ?></span>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </article>

                                <article class="report-info-card">
                                    <h3>Team Performance</h3>
                                    <div class="report-velocity">
                                        <div><span>Velocity</span><strong><?php echo $velocity; ?>%</strong></div>
                                        <div class="progress-track"><span style="width: <?php echo $velocity; ?>%;"></span></div>
                                    </div>
                                    <p><?php echo number_format((int)($selectedProject['AssigneeCount'] ?? 0)); ?> assigned members, <?php echo number_format((int)($selectedProject['OpenCount'] ?? 0)); ?> open tasks, <?php echo $progress; ?>% project progress.</p>
                                </article>
                            </div>

                            <div class="report-linked-tasks">
                                <div class="report-section-title">
                                    <h3>Project Tasks</h3>
                                    <span><?php echo number_format($tasks->num_rows); ?> tasks</span>
                                </div>
                                <?php if ($tasks->num_rows === 0) { ?>
                                    <p class="report-note">No tasks linked to this project.</p>
                                <?php } ?>
                                <?php while ($task = $tasks->fetch_assoc()) { ?>
                                    <a class="report-task-row" href="../tasks/details.php?id=<?php echo (int)$task['TaskID']; ?>">
                                        <div>
                                            <strong><?php echo htmlspecialchars($task['TaskName']); ?></strong>
                                            <p><?php echo htmlspecialchars($task['FullName'] ?: 'Unassigned'); ?> - <?php echo htmlspecialchars($task['TeamName'] ?: 'No Team'); ?></p>
                                        </div>
                                        <div class="report-task-badges">
                                            <span class="statusColor <?php echo reportStatusClass($task['Status']); ?>"><?php echo htmlspecialchars($task['Status'] ?? 'Pending'); ?></span>
                                            <span class="priority-pill <?php echo reportPriorityClass($task['Priority']); ?>"><?php echo htmlspecialchars($task['Priority'] ?? 'Medium'); ?></span>
                                        </div>
                                    </a>
                                <?php } ?>
                            </div>
                        </section>
                    </section>
                <?php } ?>
            </main>
        </div>

        <div class="modal-overlay" id="reportModal">
            <div class="modal-box report-modal-box">
                <div class="modal-header">
                    <div>
                        <h2>Create Project Report</h2>
                        <p class="modal-subtitle">Choose a project to open its full report.</p>
                    </div>
                    <span id="closeReportModal" class="close-x">&times;</span>
                </div>
                <form method="GET" class="modal-body">
                    <div class="field-group">
                        <label>Select Project</label>
                        <select name="project_id" class="report-modal-select">
                            <?php foreach ($projects as $projectOption) { ?>
                                <option value="<?php echo (int)$projectOption['ProjectID']; ?>">
                                    <?php echo htmlspecialchars($projectOption['ProjectName']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" id="cancelReportModal">Cancel</button>
                        <button type="submit" class="btn-create">Create Report</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            const reportModal = document.getElementById('reportModal');
            const openReportModal = document.getElementById('openReportModal');
            const closeReportModal = document.getElementById('closeReportModal');
            const cancelReportModal = document.getElementById('cancelReportModal');
            const toggleReportModal = (show) => reportModal.classList[show ? 'add' : 'remove']('view');

            if (openReportModal) openReportModal.addEventListener('click', () => toggleReportModal(true));
            if (closeReportModal) closeReportModal.addEventListener('click', () => toggleReportModal(false));
            if (cancelReportModal) cancelReportModal.addEventListener('click', () => toggleReportModal(false));
        </script>
    </body>
</html>
<?php $conn->close(); ?>
