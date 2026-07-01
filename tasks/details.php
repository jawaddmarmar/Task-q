<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();
$userId = (int)currentUser()['id'];
$memberId = (int)(currentUser()['member_id'] ?? 0);
$taskAccess = isAdmin()
    ? "(t.CreatedByUserID = $userId OR t.CreatedByUserID IS NULL)"
    : "(t.MemberID = $memberId OR EXISTS (SELECT 1 FROM team_membership tm WHERE tm.TeamID = t.TeamID AND tm.MemberID = $memberId))";

$task = null;
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("
        SELECT t.*, p.ProjectName, team.TeamName, m.FullName, m.UserName, m.Email, m.RoleTitle, m.Department
        FROM task t
        LEFT JOIN project p ON t.ProjectID = p.ProjectID
        LEFT JOIN team team ON t.TeamID = team.TeamID
        LEFT JOIN team_member m ON t.MemberID = m.MemberID
        WHERE t.TaskID = ? AND $taskAccess
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
}

if (!$task) {
    echo "<div style='padding: 50px; text-align: center; font-family: sans-serif;'><h2>Task not found!</h2><a href='index.php'>Back to Tasks</a></div>";
    exit;
}

$projects = $conn->query("SELECT ProjectID, ProjectName FROM project WHERE CreatedByUserID = $userId OR CreatedByUserID IS NULL ORDER BY ProjectName ASC");
$teams = $conn->query("SELECT TeamID, TeamName FROM team WHERE CreatedByUserID = $userId OR CreatedByUserID IS NULL ORDER BY TeamName ASC");
$members = $conn->query("SELECT MemberID, FullName, UserName FROM team_member WHERE CreatedByUserID = $userId OR CreatedByUserID IS NULL ORDER BY FullName ASC");
$comments = $conn->prepare("
    SELECT c.CommentText, c.CreatedAt, c.CommentedBy, c.CommentedRole
    FROM task_comment c
    WHERE c.TaskID = ?
    ORDER BY c.CreatedAt DESC
    LIMIT 5
");
$comments->bind_param("i", $task['TaskID']);
$comments->execute();
$commentsResult = $comments->get_result();

$status = strtolower($task['Status'] ?? 'pending');
$priority = strtolower($task['Priority'] ?? 'medium');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/project.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/task.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <title><?php echo htmlspecialchars($task['TaskName']); ?> - Task Details</title>
</head>
<body>
    <div class="landing">
        <?php include '../includes/sidebar.php'; ?>

        <div class="project_content">
            <div class="project_header">
                <div class="project_title">
                    <a href="index.php" class="breadcrumb">Back to Tasks</a>
                    <h1 style="font-size: 28px; color: #111827;"><?php echo htmlspecialchars($task['TaskName']); ?></h1>
                    <div class="task-detail-meta">
                        <span class="task-chip"><?php echo htmlspecialchars($task['ProjectName'] ?? 'No Project'); ?></span>
                        <span class="task-chip"><?php echo htmlspecialchars($task['TeamName'] ?? 'No Team'); ?></span>
                        <span class="priority-pill <?php echo htmlspecialchars($priority); ?>"><?php echo htmlspecialchars($task['Priority'] ?? 'medium'); ?></span>
                    </div>
                </div>
                <div class="headerbtn">
                    <?php if (isAdmin()) { ?>
                        <button id="edit_task_trigger" style="background: white; color: #64748b; border: 1px solid #e2e8f0; margin-right: 10px; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">Edit Task</button>
                    <?php } ?>
                </div>
            </div>

            <div class="details-grid">
                <div>
                    <div class="card task-description-card">
                        <h2>Description</h2>
                        <p><?php echo nl2br(htmlspecialchars($task['Description'] ?: 'No description added yet.')); ?></p>
                    </div>

                    <div class="card task-comments-card">
                        <h2>Comments</h2>
                        <?php if ($commentsResult->num_rows === 0) { ?>
                            <p class="grey">No comments yet.</p>
                        <?php } ?>
                        <?php while($comment = $commentsResult->fetch_assoc()) { ?>
                            <div class="comment-item">
                                <span class="avatar-dot"><?php echo htmlspecialchars(strtoupper(substr($comment['CommentedBy'] ?? 'U', 0, 1))); ?></span>
                                <div class="comment-content">
                                    <div class="comment-meta">
                                        <strong><?php echo htmlspecialchars($comment['CommentedBy'] ?? 'Unknown User'); ?></strong>
                                        <span><?php echo htmlspecialchars($comment['CommentedRole'] ?? ''); ?></span>
                                        <small><?php echo htmlspecialchars(date('M d, Y - h:i A', strtotime($comment['CreatedAt']))); ?></small>
                                    </div>
                                    <p><?php echo htmlspecialchars($comment['CommentText']); ?></p>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="comment-form" data-task-id="<?php echo (int)$task['TaskID']; ?>">
                            <label for="newTaskComment">Add Comment</label>
                            <textarea id="newTaskComment" maxlength="500" placeholder="Write a comment..."></textarea>
                            <button type="button" id="addTaskCommentBtn" class="btn-create">Add Comment</button>
                        </div>
                    </div>
                </div>

                <div class="side-stack">
                    <div class="card task-assignee-panel">
                        <p class="stat-label">Assigned User</p>
                        <div class="assignee-card">
                            <span class="avatar-dot large"><?php echo htmlspecialchars(strtoupper(substr($task['FullName'] ?? 'U', 0, 1))); ?></span>
                            <div class="assignee-info">
                                <h3 class="task-assignee-name"><?php echo htmlspecialchars($task['FullName'] ?? 'Unassigned'); ?></h3>
                                <?php if (!empty($task['UserName'])) { ?>
                                    <p class="task-assignee-detail">@<?php echo htmlspecialchars($task['UserName']); ?></p>
                                <?php } ?>
                                <?php if (!empty($task['Email'])) { ?>
                                    <p class="task-assignee-detail"><?php echo htmlspecialchars($task['Email']); ?></p>
                                <?php } ?>
                                <?php if (!empty($task['RoleTitle'])) { ?>
                                    <p class="task-assignee-detail"><?php echo htmlspecialchars($task['RoleTitle']); ?></p>
                                <?php } ?>
                                <?php if (empty($task['FullName'])) { ?>
                                    <p class="task-assignee-detail">No user assigned yet.</p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="stat-group">
                            <p class="stat-label">Due Date</p>
                            <p class="stat-value"><?php echo htmlspecialchars($task['DueDate'] ? date('F d, Y', strtotime($task['DueDate'])) : 'No date'); ?></p>
                        </div>
                        <div class="stat-group">
                            <p class="stat-label">Status</p>
                            <p class="statusColor <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($task['Status'] ?? 'pending'); ?></p>
                        </div>
                        <?php if (isMember()) { ?>
                            <div class="stat-group member-status-editor" data-task-id="<?php echo (int)$task['TaskID']; ?>">
                                <p class="stat-label">Update Status</p>
                                <select id="memberTaskStatus" class="task-status-input">
                                    <option value="pending" <?php if($status == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="active" <?php if($status == 'active') echo 'selected'; ?>>Active</option>
                                    <option value="done" <?php if($status == 'done') echo 'selected'; ?>>Done</option>
                                </select>
                                <button type="button" id="memberStatusSave" class="btn-create">Save Status</button>
                            </div>
                        <?php } ?>
                        <div class="stat-group">
                            <p class="stat-label">Priority</p>
                            <span class="priority-pill <?php echo htmlspecialchars($priority); ?>"><?php echo htmlspecialchars($task['Priority'] ?? 'medium'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="task-detail-modal-overlay">
        <div id="task-detail-modal-box" class="modal-box task-modal-box">
            <div class="modal-header">
                <h2>Edit Task</h2>
                <span id="task-detail-close-x" class="close-x">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_task_id" value="<?php echo $task['TaskID']; ?>">
                <div class="field-group">
                    <label>Task Title</label>
                    <input type="text" id="edit_task_title" class="task-title-input" maxlength="90" value="<?php echo htmlspecialchars($task['TaskName']); ?>">
                </div>
                <div class="field-group">
                    <label>Description</label>
                    <textarea id="edit_task_desc" class="task-desc-input" maxlength="300"><?php echo htmlspecialchars($task['Description']); ?></textarea>
                </div>
                <div class="row">
                    <div class="field-group">
                        <label>Project</label>
                        <select id="edit_task_project" class="task-project-input">
                            <option value="">Select project</option>
                            <?php while($project = $projects->fetch_assoc()) { ?>
                                <option value="<?php echo $project['ProjectID']; ?>" <?php if($task['ProjectID'] == $project['ProjectID']) echo 'selected'; ?>><?php echo htmlspecialchars($project['ProjectName']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>Assigned User</label>
                        <select id="edit_task_user" class="task-user-input">
                            <option value="">Unassigned</option>
                            <?php while($member = $members->fetch_assoc()) { ?>
                                <option value="<?php echo $member['MemberID']; ?>" <?php if($task['MemberID'] == $member['MemberID']) echo 'selected'; ?>><?php echo htmlspecialchars($member['FullName'] . ' / ' . $member['UserName']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="field-group">
                    <label>Assigned Team</label>
                    <select id="edit_task_team" class="task-team-input">
                        <option value="">No Team</option>
                        <?php while($team = $teams->fetch_assoc()) { ?>
                            <option value="<?php echo $team['TeamID']; ?>" <?php if($task['TeamID'] == $team['TeamID']) echo 'selected'; ?>><?php echo htmlspecialchars($team['TeamName']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="row">
                    <div class="field-group">
                        <label>Due Date</label>
                        <input type="date" id="edit_task_due" class="task-date-input" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($task['DueDate']); ?>">
                    </div>
                    <div class="field-group">
                        <label>Priority</label>
                        <select id="edit_task_priority" class="task-priority-input">
                            <option value="low" <?php if($priority == 'low') echo 'selected'; ?>>Low</option>
                            <option value="medium" <?php if($priority == 'medium') echo 'selected'; ?>>Medium</option>
                            <option value="high" <?php if($priority == 'high') echo 'selected'; ?>>High</option>
                            <option value="critical" <?php if($priority == 'critical') echo 'selected'; ?>>Critical</option>
                        </select>
                    </div>
                </div>
                <div class="field-group">
                    <label>Status</label>
                    <select id="edit_task_status" class="task-status-input">
                        <option value="pending" <?php if($status == 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="active" <?php if($status == 'active') echo 'selected'; ?>>Active</option>
                        <option value="done" <?php if($status == 'done') echo 'selected'; ?>>Done</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="task-detail-cancel">Cancel</button>
                <button type="button" id="task-detail-save" class="btn-create">Save Changes</button>
            </div>
        </div>
    </div>

    <script src="../js/task_details.js?v=<?php echo time(); ?>"></script>
</body>
</html>

<?php $conn->close(); ?>

