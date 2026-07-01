<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();
$userId = (int)currentUser()['id'];
$memberId = (int)(currentUser()['member_id'] ?? 0);
$taskScope = isAdmin()
    ? "(t.CreatedByUserID = $userId OR t.CreatedByUserID IS NULL)"
    : "(t.MemberID = $memberId OR EXISTS (SELECT 1 FROM team_membership tm WHERE tm.TeamID = t.TeamID AND tm.MemberID = $memberId))";
$projectScope = isAdmin()
    ? "(CreatedByUserID = $userId OR CreatedByUserID IS NULL)"
    : "ProjectID IN (SELECT ProjectID FROM task t WHERE $taskScope)";
$teamScope = isAdmin()
    ? "(CreatedByUserID = $userId OR CreatedByUserID IS NULL)"
    : "TeamID IN (SELECT TeamID FROM team_membership WHERE MemberID = $memberId)";
$memberScope = isAdmin()
    ? "(CreatedByUserID = $userId OR CreatedByUserID IS NULL)"
    : "MemberID = $memberId";

function truncateTaskText($text, $wordLimit, $charLimit) {
    $text = trim($text ?? '');
    $words = preg_split('/\s+/', $text);

    if (count($words) > $wordLimit) {
        return implode(' ', array_slice($words, 0, $wordLimit)) . '...';
    }

    if (strlen($text) > $charLimit) {
        return substr($text, 0, $charLimit) . '...';
    }

    return $text;
}

$statsResult = $conn->query("
    SELECT
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN LOWER(Status) = 'active' THEN 1 ELSE 0 END) AS active_tasks,
        SUM(CASE WHEN DueDate IS NOT NULL AND DueDate < CURDATE() AND LOWER(Status) != 'done' THEN 1 ELSE 0 END) AS overdue_tasks
    FROM task t
    WHERE $taskScope
");
$stats = $statsResult->fetch_assoc();

$projects = $conn->query("SELECT ProjectID, ProjectName FROM project WHERE $projectScope ORDER BY ProjectName ASC");
$teams = $conn->query("SELECT TeamID, TeamName FROM team WHERE $teamScope ORDER BY TeamName ASC");
$members = $conn->query("SELECT MemberID, FullName, UserName, Email FROM team_member WHERE $memberScope ORDER BY FullName ASC");
$tasks = $conn->query("
    SELECT t.*, p.ProjectName, team.TeamName, m.FullName, m.UserName, m.Email
    FROM task t
    LEFT JOIN project p ON t.ProjectID = p.ProjectID
    LEFT JOIN team team ON t.TeamID = team.TeamID
    LEFT JOIN team_member m ON t.MemberID = m.MemberID
    WHERE $taskScope
    ORDER BY t.TaskID DESC
");
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../css/project.css">
        <link rel="stylesheet" href="../css/task.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <title>Tasks</title>
    </head>
    <body>
        <div class="landing">
            <?php include '../includes/sidebar.php'; ?>

            <div class="project_content">
                <div class="project_header">
                    <div class="project_title">
                        <h1>Tasks</h1>
                        <p>Monitor project work, deadlines, priorities, and assignees.</p>
                    </div>
                    <div class="headerbtn">
                        <?php if (isAdmin()) { ?>
                            <button id="add_task_btn">Add New Task</button>
                        <?php } ?>
                    </div>
                </div>

                <div class="project-stats">
                    <div class="stat-box">
                        <p>Total Tasks</p>
                        <h3><?php echo number_format($stats['total_tasks']); ?></h3>
                    </div>
                    <div class="stat-box">
                        <p>Active Tasks</p>
                        <h3><?php echo number_format($stats['active_tasks']); ?></h3>
                    </div>
                    <div class="stat-box">
                        <p>Overdue Tasks</p>
                        <h3><?php echo number_format($stats['overdue_tasks']); ?></h3>
                    </div>
                </div>

                <div class="projectlist_section tasklist_section">
                    <p class="project_list">Task List</p>
                    <table class="task-table">
                        <thead>
                            <tr id="table_titles">
                                <td style="width: 16%;">Title</td>
                                <td style="width: 13%;">Project</td>
                                <td style="width: 12%;">Team</td>
                                <td style="width: 17%;">Description</td>
                                <td style="width: 14%;">Assigned User</td>
                                <td style="width: 12%;">Status</td>
                                <td style="width: 10%;">Priority</td>
                                <td style="width: 13%;">Due Date</td>
                                <td style="width: 12%;">Actions</td>
                            </tr>
                        </thead>
                        <tbody id="taskTableBody">
                            <?php if ($tasks->num_rows === 0) { ?>
                                <tr>
                                    <td colspan="9" class="empty-state">No tasks yet. Add your first task to start tracking work.</td>
                                </tr>
                            <?php } ?>
                            <?php while($row = $tasks->fetch_assoc()) {
                                $taskId = (int)$row['TaskID'];
                                $status = strtolower($row['Status'] ?? 'pending');
                                $priority = strtolower($row['Priority'] ?? 'medium');
                                $fullTitle = $row['TaskName'] ?? '';
                                $fullDesc = $row['Description'] ?? '';
                                $shortTitle = truncateTaskText($fullTitle, 2, 16);
                                $shortDesc = truncateTaskText($fullDesc, 7, 50);
                                $projectName = $row['ProjectName'] ?? 'No Project';
                                $teamName = $row['TeamName'] ?? 'No Team';
                                $memberName = $row['FullName'] ?? 'Unassigned';
                                $memberUsername = $row['UserName'] ?? '';
                                $memberEmail = $row['Email'] ?? '';
                            ?>
                                <tr
                                    data-id="<?php echo $taskId; ?>"
                                    data-title="<?php echo htmlspecialchars($fullTitle, ENT_QUOTES); ?>"
                                    data-desc="<?php echo htmlspecialchars($fullDesc, ENT_QUOTES); ?>"
                                    data-project-id="<?php echo htmlspecialchars($row['ProjectID'] ?? '', ENT_QUOTES); ?>"
                                    data-team-id="<?php echo htmlspecialchars($row['TeamID'] ?? '', ENT_QUOTES); ?>"
                                    data-user-id="<?php echo htmlspecialchars($row['MemberID'] ?? '', ENT_QUOTES); ?>"
                                >
                                    <td>
                                        <a href="details.php?id=<?php echo $taskId; ?>" class="project-link">
                                            <p class="task-title"><?php echo htmlspecialchars($shortTitle); ?></p>
                                        </a>
                                    </td>
                                    <td><span class="task-chip"><?php echo htmlspecialchars(truncateTaskText($projectName, 2, 18)); ?></span></td>
                                    <td><span class="task-chip"><?php echo htmlspecialchars(truncateTaskText($teamName, 2, 16)); ?></span></td>
                                    <td><p class="grey"><?php echo htmlspecialchars($shortDesc); ?></p></td>
                                    <td>
                                        <div class="assignee-cell">
                                            <span class="avatar-dot"><?php echo htmlspecialchars(strtoupper(substr($memberName, 0, 1))); ?></span>
                                            <div>
                                                <p><?php echo htmlspecialchars($memberName); ?></p>
                                                <small><?php echo htmlspecialchars($memberUsername ?: $memberEmail); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><p class="statusColor <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($row['Status'] ?? 'pending'); ?></p></td>
                                    <td><span class="priority-pill <?php echo htmlspecialchars($priority); ?>"><?php echo htmlspecialchars($row['Priority'] ?? 'medium'); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['DueDate'] ?? ''); ?></td>
                                    <td class="action-icons">
                                        <a href="details.php?id=<?php echo $taskId; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#949494" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                        <?php if (isAdmin()) { ?>
                                            <span class="task-edit-trigger" style="cursor:pointer; display:flex; align-items:center;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d2ce00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pencil"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                                            </span>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="task-delete-btn lucide lucide-trash-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ff0022" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="task-modal-overlay">
            <div id="task-modal-box" class="modal-box task-modal-box">
                <div class="modal-header">
                    <div>
                        <h2>Add New Task</h2>
                        <p class="modal-subtitle">Fill task details and assign ownership.</p>
                    </div>
                    <span id="task-close-x" class="close-x">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="field-group">
                        <label for="taskTitle">Task Title</label>
                        <input type="text" id="taskTitle" class="task-title-input" maxlength="90" placeholder="e.g. Design System Audit">
                    </div>
                    <div class="field-group">
                        <label for="taskDesc">Description</label>
                        <textarea id="taskDesc" class="task-desc-input" maxlength="300" placeholder="Describe task requirements and deliverables..."></textarea>
                    </div>
                    <div class="row">
                        <div class="field-group">
                            <label for="taskProject">Project</label>
                            <select id="taskProject" class="task-project-input">
                                <option value="">Select project</option>
                                <?php while($project = $projects->fetch_assoc()) { ?>
                                    <option value="<?php echo $project['ProjectID']; ?>"><?php echo htmlspecialchars($project['ProjectName']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="taskTeam">Assigned Team</label>
                            <select id="taskTeam" class="task-team-input">
                                <option value="">No Team</option>
                                <?php while($team = $teams->fetch_assoc()) { ?>
                                    <option value="<?php echo $team['TeamID']; ?>"><?php echo htmlspecialchars($team['TeamName']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="taskUser">Assigned User</label>
                            <select id="taskUser" class="task-user-input">
                                <option value="">Unassigned</option>
                                <?php while($member = $members->fetch_assoc()) { ?>
                                    <option value="<?php echo $member['MemberID']; ?>"><?php echo htmlspecialchars($member['FullName'] . ' / ' . $member['UserName']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="field-group">
                            <label for="taskDueDate">Due Date</label>
                            <input type="date" id="taskDueDate" class="task-date-input" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="taskPriority">Priority</label>
                            <select id="taskPriority" class="task-priority-input">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="taskStatus">Status</label>
                        <select id="taskStatus" class="task-status-input">
                            <option value="pending" selected>Pending</option>
                            <option value="active">Active</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="task-btn-cancel">Cancel</button>
                    <button type="button" id="task-btn-create" class="btn-create">Create Task</button>
                </div>
            </div>
        </div>

        <script src="../js/task.js?v=<?php echo time(); ?>"></script>
    </body>
</html>

<?php $conn->close(); ?>
