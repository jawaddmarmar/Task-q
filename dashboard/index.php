<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();

$user = currentUser();
$userId = (int)$user['id'];
$memberId = (int)($user['member_id'] ?? 0);

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
$memberScope = isAdmin()
    ? "(m.CreatedByUserID = $userId OR m.CreatedByUserID IS NULL)"
    : "m.MemberID = $memberId";

function dashboardStatusClass($status) {
    $status = strtolower($status ?? 'pending');
    return in_array($status, ['active', 'done', 'pending'], true) ? $status : 'pending';
}

function dashboardPriorityClass($priority) {
    $priority = strtolower($priority ?? 'medium');
    return in_array($priority, ['low', 'medium', 'high', 'critical'], true) ? $priority : 'medium';
}

$projectStats = $conn->query("
    SELECT
        COUNT(*) AS total_projects,
        AVG(COALESCE(Progress, 0)) AS avg_progress
    FROM project p
    WHERE $projectScope
")->fetch_assoc();

$taskStats = $conn->query("
    SELECT
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN LOWER(Status) = 'done' THEN 1 ELSE 0 END) AS completed_tasks,
        SUM(CASE WHEN LOWER(Status) != 'done' THEN 1 ELSE 0 END) AS pending_tasks
    FROM task t
    WHERE $taskScope
")->fetch_assoc();

$memberStats = $conn->query("
    SELECT COUNT(*) AS total_members
    FROM team_member m
    WHERE $memberScope
")->fetch_assoc();

$recentProjects = $conn->query("
    SELECT p.ProjectID, p.ProjectName, p.Status, COALESCE(p.Progress, 0) AS Progress
    FROM project p
    WHERE $projectScope
    ORDER BY p.ProjectID DESC
    LIMIT 5
");

$pendingTasks = $conn->query("
    SELECT t.TaskID, t.TaskName, t.Priority, t.DueDate, p.ProjectName, m.FullName
    FROM task t
    LEFT JOIN project p ON t.ProjectID = p.ProjectID
    LEFT JOIN team_member m ON t.MemberID = m.MemberID
    WHERE $taskScope AND LOWER(t.Status) != 'done'
    ORDER BY
        CASE WHEN t.DueDate IS NULL THEN 1 ELSE 0 END,
        t.DueDate ASC,
        t.TaskID DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../css/project.css">
        <link rel="stylesheet" href="../css/task.css">
        <link rel="stylesheet" href="../css/dashboard.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
        <title>Dashboard</title>
    </head>
    <body>
        <div class="landing">
            <?php include '../includes/sidebar.php'; ?>

            <main class="project_content">
                <div class="project_header dashboard-header">
                    <div class="project_title">
                        <h1>Dashboard</h1>
                        <p>Welcome back, <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></p>
                    </div>
                </div>

                <section class="dashboard-stats">
                    <article class="dashboard-stat-card">
                        <div>
                            <p>Total Projects</p>
                            <h2><?php echo number_format($projectStats['total_projects'] ?? 0); ?></h2>
                        </div>
                        <span class="stat-icon blue">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#1358ec"><path d="M3 6a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"/></svg>
                        </span>
                    </article>
                    <article class="dashboard-stat-card">
                        <div>
                            <p>Total Tasks</p>
                            <h2><?php echo number_format($taskStats['total_tasks'] ?? 0); ?></h2>
                            <span class="trend positive"><?php echo number_format($taskStats['pending_tasks'] ?? 0); ?> still open</span>
                        </div>
                        <span class="stat-icon cyan">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0891b2" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="18" x="4" y="3" rx="2"/><path d="M9 7h6"/><path d="M9 12h6"/><path d="M9 17h4"/></svg>
                        </span>
                    </article>
                    <article class="dashboard-stat-card">
                        <div>
                            <p>Team Members</p>
                            <h2><?php echo number_format($memberStats['total_members'] ?? 0); ?></h2>
                            <span class="trend muted">In your workspace</span>
                        </div>
                        <span class="stat-icon red">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#dc2626"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.31 0-6 1.57-6 3.5V20h12v-2.5c0-1.93-2.69-3.5-6-3.5Z"/></svg>
                        </span>
                    </article>
                    <article class="dashboard-stat-card">
                        <div>
                            <p>Completed Tasks</p>
                            <h2><?php echo number_format($taskStats['completed_tasks'] ?? 0); ?></h2>
                            <span class="trend positive">Done and delivered</span>
                        </div>
                        <span class="stat-icon gold">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ca8a04" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-card recent-projects-card">
                        <div class="dashboard-card-header">
                            <h2>Recent Projects</h2>
                            <a href="../projects/">View All</a>
                        </div>
                        <div class="dashboard-table-wrap">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentProjects->num_rows === 0) { ?>
                                        <tr><td colspan="3" class="dashboard-empty">No projects yet.</td></tr>
                                    <?php } ?>
                                    <?php while ($project = $recentProjects->fetch_assoc()) {
                                        $progress = max(0, min(100, (int)$project['Progress']));
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="../projects/details.php?id=<?php echo (int)$project['ProjectID']; ?>" class="dashboard-main-link">
                                                    <?php echo htmlspecialchars($project['ProjectName']); ?>
                                                </a>
                                            </td>
                                            <td><span class="statusColor <?php echo dashboardStatusClass($project['Status']); ?>"><?php echo htmlspecialchars($project['Status'] ?? 'Pending'); ?></span></td>
                                            <td>
                                                <div class="dashboard-progress">
                                                    <span class="progress-track"><span style="width: <?php echo $progress; ?>%;"></span></span>
                                                    <strong><?php echo $progress; ?>%</strong>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <aside class="dashboard-card pending-card">
                        <div class="dashboard-card-header">
                            <h2>Pending Tasks</h2>
                            <span><?php echo number_format($taskStats['pending_tasks'] ?? 0); ?> open</span>
                        </div>
                        <div class="pending-list">
                            <?php if ($pendingTasks->num_rows === 0) { ?>
                                <p class="dashboard-empty">No pending tasks.</p>
                            <?php } ?>
                            <?php while ($task = $pendingTasks->fetch_assoc()) {
                                $initial = strtoupper(substr($task['FullName'] ?: $task['TaskName'], 0, 1));
                            ?>
                                <a href="../tasks/details.php?id=<?php echo (int)$task['TaskID']; ?>" class="pending-item">
                                    <div>
                                        <h3><?php echo htmlspecialchars($task['TaskName']); ?></h3>
                                        <p><?php echo htmlspecialchars($task['ProjectName'] ?? 'No Project'); ?></p>
                                        <small>Due: <?php echo htmlspecialchars($task['DueDate'] ?? 'No date'); ?></small>
                                    </div>
                                    <div class="pending-meta">
                                        <span class="priority-pill <?php echo dashboardPriorityClass($task['Priority']); ?>"><?php echo htmlspecialchars($task['Priority'] ?? 'Medium'); ?></span>
                                        <span class="avatar-dot"><?php echo htmlspecialchars($initial); ?></span>
                                    </div>
                                </a>
                            <?php } ?>
                        </div>
                        <a href="../tasks/" class="view-tasks-link">View all tasks</a>
                    </aside>
                </section>
            </main>
        </div>
    </body>
</html>
<?php $conn->close(); ?>

