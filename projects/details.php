<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();
$userId = (int)currentUser()['id'];
$memberId = (int)(currentUser()['member_id'] ?? 0);
$projectAccess = isAdmin()
    ? "(p.CreatedByUserID = $userId OR p.CreatedByUserID IS NULL)"
    : "(
        EXISTS (SELECT 1 FROM project_member pm WHERE pm.ProjectID = p.ProjectID AND pm.MemberID = $memberId)
        OR EXISTS (SELECT 1 FROM task t WHERE t.ProjectID = p.ProjectID AND t.MemberID = $memberId)
        OR EXISTS (
            SELECT 1 FROM project_team pt
            INNER JOIN team_membership tm ON pt.TeamID = tm.TeamID
            WHERE pt.ProjectID = p.ProjectID AND tm.MemberID = $memberId
        )
    )";

$project = null;
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT p.* FROM project p WHERE p.ProjectID = ? AND $projectAccess");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
}

if (!$project) {
    echo "<div style='padding: 50px; text-align: center; font-family: sans-serif;'><h2>Project not found!</h2><a href='index.php'>Back to Projects</a></div>";
    exit;
}

$progress = isset($project['Progress']) ? max(0, min(100, (int)$project['Progress'])) : 0;
$allTeams = $conn->query("SELECT TeamID, TeamName FROM team WHERE CreatedByUserID = $userId OR CreatedByUserID IS NULL ORDER BY TeamName ASC");
$allMembers = $conn->query("SELECT MemberID, FullName, UserName, RoleTitle FROM team_member WHERE CreatedByUserID = $userId OR CreatedByUserID IS NULL ORDER BY FullName ASC");
$assignedTeams = $conn->prepare("
    SELECT t.TeamID, t.TeamName, t.Description, COUNT(tm.MemberID) AS MemberCount
    FROM project_team pt
    INNER JOIN team t ON pt.TeamID = t.TeamID
    LEFT JOIN team_membership tm ON t.TeamID = tm.TeamID
    WHERE pt.ProjectID = ?
    GROUP BY t.TeamID
    ORDER BY t.TeamName ASC
");
$assignedTeams->bind_param("i", $project['ProjectID']);
$assignedTeams->execute();
$assignedTeamsResult = $assignedTeams->get_result();
$assignedMembers = $conn->prepare("
    SELECT
        m.MemberID,
        m.FullName,
        m.UserName,
        m.Email,
        m.RoleTitle,
        GROUP_CONCAT(DISTINCT team.TeamName ORDER BY team.TeamName SEPARATOR ', ') AS TeamNames,
        MAX(CASE WHEN pm.MemberID IS NOT NULL THEN 1 ELSE 0 END) AS DirectMember
    FROM team_member m
    LEFT JOIN project_member pm ON pm.MemberID = m.MemberID AND pm.ProjectID = ?
    LEFT JOIN team_membership tm ON tm.MemberID = m.MemberID
    LEFT JOIN project_team pt ON pt.TeamID = tm.TeamID AND pt.ProjectID = ?
    LEFT JOIN team team ON team.TeamID = tm.TeamID AND pt.ProjectID IS NOT NULL
    WHERE pm.MemberID IS NOT NULL OR pt.TeamID IS NOT NULL
    GROUP BY m.MemberID, m.FullName, m.UserName, m.Email, m.RoleTitle
    ORDER BY m.FullName ASC
");
$assignedMembers->bind_param("ii", $project['ProjectID'], $project['ProjectID']);
$assignedMembers->execute();
$assignedMembersResult = $assignedMembers->get_result();
$projectTasks = $conn->prepare("
    SELECT t.TaskID, t.TaskName, t.Status, t.Priority, t.DueDate, m.FullName, m.UserName, team.TeamName
    FROM task t
    LEFT JOIN team_member m ON t.MemberID = m.MemberID
    LEFT JOIN team team ON t.TeamID = team.TeamID
    WHERE t.ProjectID = ?
    ORDER BY t.DueDate ASC
");
$projectTasks->bind_param("i", $project['ProjectID']);
$projectTasks->execute();
$projectTasksResult = $projectTasks->get_result();
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
    <title><?php echo htmlspecialchars($project['ProjectName']); ?> - Details</title>
    

</head>
<body>

    <div class="landing">
        <?php include '../includes/sidebar.php'; ?>

        <div class="project_content">
            <div class="project_header">
                <div class="project_title">
                    <a href="index.php" class="breadcrumb">← Back to Projects</a>
                    <h1 style="font-size: 28px; color: #111827;"><?php echo htmlspecialchars($project['ProjectName']); ?></h1>
                </div>
                <div class="headerbtn">
                    <?php if (isAdmin()) { ?>
                        <button id="edit_btn_trigger" style="background: white; color: #64748b; border: 1px solid #e2e8f0; margin-right: 10px; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">Edit Project</button>
                    <?php } ?>
                </div>
            </div>

            <div class="details-grid">
                <div class="card">
                    <div class="statusColor <?php echo strtolower($project['Status']); ?>" style="margin-bottom: 20px;">
                        <?php echo ucfirst($project['Status']); ?>
                    </div>
                    
                    <h2 style="font-size: 18px; color: #1e293b; margin-bottom: 15px;">Description</h2>
                    <p style="color: #475569; line-height: 1.8; font-size: 15px;">
                        <?php echo nl2br(htmlspecialchars($project['Description'])); ?>
                    </p>

                    <div class="progress-box">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600; font-size: 14px; color: #1e293b;">Project Completion</span>
                            <span id="detail-progress-value" style="color: #1358ec; font-weight: 700;"><?php echo $progress; ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                    </div>
                </div>

                <div class="side-stack" style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="card">
                        <div class="stat-group">
                            <p class="stat-label">Total Budget</p>
                            <p class="budget-value">$<?php echo number_format($project['Budget'], 2); ?></p>
                        </div>
                        <div class="stat-group">
                            <p class="stat-label">Created On</p>
                            <p class="stat-value"><?php echo date("F d, Y", strtotime($project['StartDate'])); ?></p>
                        </div>
                    </div>
                     <div class="card" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                        <p style="font-size: 13px; color: #64748b; line-height: 1.5;">Enterprise Strategy Tracking Enabled.</p>
                    </div>
                </div>
            </div>

            <div class="project-assignment-grid project-task-grid">
                <div class="card assignment-card project-task-card">
                    <div class="assignment-card-header">
                        <div>
                            <h2>Project Tasks</h2>
                            <p>Tasks linked to this project and who is working on each one.</p>
                        </div>
                    </div>
                    <div class="assignment-list">
                        <?php if ($projectTasksResult->num_rows === 0) { ?>
                            <p class="grey">No tasks linked to this project yet.</p>
                        <?php } ?>
                        <?php while($task = $projectTasksResult->fetch_assoc()) { ?>
                            <a class="assignment-row" href="../tasks/details.php?id=<?php echo $task['TaskID']; ?>">
                                <span class="priority-pill <?php echo htmlspecialchars(strtolower($task['Priority'])); ?>"><?php echo htmlspecialchars($task['Priority']); ?></span>
                                <div>
                                    <strong><?php echo htmlspecialchars($task['TaskName']); ?></strong>
                                    <p>
                                        <?php echo htmlspecialchars($task['FullName'] ?: 'Unassigned'); ?>
                                        <?php if (!empty($task['TeamName'])) { ?>
                                            - <?php echo htmlspecialchars($task['TeamName']); ?>
                                        <?php } ?>
                                        - <?php echo htmlspecialchars($task['Status']); ?>
                                    </p>
                                </div>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="project-assignment-grid" data-project-id="<?php echo (int)$project['ProjectID']; ?>">
                <div class="card assignment-card">
                    <div class="assignment-card-header">
                        <div>
                            <h2>Project Teams</h2>
                            <p>Add a team from the Team section to this project.</p>
                        </div>
                    </div>
                    <?php if (isAdmin()) { ?>
                        <div class="assignment-control">
                            <select id="projectTeamSelect" class="team-input">
                                <option value="">Select team</option>
                                <?php while($team = $allTeams->fetch_assoc()) { ?>
                                    <option value="<?php echo $team['TeamID']; ?>"><?php echo htmlspecialchars($team['TeamName']); ?></option>
                                <?php } ?>
                            </select>
                            <button type="button" class="btn-create" id="assignTeamBtn">Add Team</button>
                        </div>
                    <?php } ?>
                    <div class="assignment-list">
                        <?php if ($assignedTeamsResult->num_rows === 0) { ?>
                            <p class="grey">No teams assigned yet.</p>
                        <?php } ?>
                        <?php while($team = $assignedTeamsResult->fetch_assoc()) { ?>
                            <a class="assignment-row" href="../teams/details.php?id=<?php echo $team['TeamID']; ?>">
                                <span class="team-avatar"><?php echo htmlspecialchars(strtoupper(substr($team['TeamName'], 0, 1))); ?></span>
                                <div>
                                    <strong><?php echo htmlspecialchars($team['TeamName']); ?></strong>
                                    <p><?php echo number_format($team['MemberCount']); ?> members</p>
                                </div>
                            </a>
                        <?php } ?>
                    </div>
                </div>

                <div class="card assignment-card">
                    <div class="assignment-card-header">
                        <div>
                            <h2>Project Members</h2>
                            <p>Add individual members who work on this project.</p>
                        </div>
                    </div>
                    <?php if (isAdmin()) { ?>
                        <div class="assignment-control">
                            <select id="projectMemberSelect" class="team-input">
                                <option value="">Select member</option>
                                <?php while($member = $allMembers->fetch_assoc()) { ?>
                                    <option value="<?php echo $member['MemberID']; ?>"><?php echo htmlspecialchars($member['FullName'] . ' / ' . $member['UserName']); ?></option>
                                <?php } ?>
                            </select>
                            <button type="button" class="btn-create" id="assignMemberBtn">Add Member</button>
                        </div>
                    <?php } ?>
                    <div class="assignment-list">
                        <?php if ($assignedMembersResult->num_rows === 0) { ?>
                            <p class="grey">No members assigned yet.</p>
                        <?php } ?>
                        <?php while($member = $assignedMembersResult->fetch_assoc()) { ?>
                            <a class="assignment-row" href="../members/details.php?id=<?php echo $member['MemberID']; ?>">
                                <span class="team-avatar"><?php echo htmlspecialchars(strtoupper(substr($member['FullName'], 0, 1))); ?></span>
                                <div>
                                    <strong><?php echo htmlspecialchars($member['FullName']); ?></strong>
                                    <p>
                                        @<?php echo htmlspecialchars($member['UserName']); ?> - <?php echo htmlspecialchars($member['RoleTitle']); ?>
                                        <?php if (!empty($member['TeamNames'])) { ?>
                                            - via <?php echo htmlspecialchars($member['TeamNames']); ?>
                                        <?php } elseif (!empty($member['DirectMember'])) { ?>
                                            - direct member
                                        <?php } ?>
                                    </p>
                                </div>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isAdmin()) { ?>
    <div class="modal-overlay" id="modal-overlay">
        <div id="modal-box" class="modal-box">
            <div class="modal-header">
                <h2>Edit Project Information</h2>
                <span id="close-x" class="close-x">&times;</span>
            </div>
        
            <div class="modal-body">
                <input type="hidden" id="edit_id" value="<?php echo $project['ProjectID']; ?>">
                
                <div class="field-group">
                    <label>Project Name</label>
                    <input type="text" id="edit_name" class="Pname" maxlength="80" value="<?php echo htmlspecialchars($project['ProjectName']); ?>">
                </div>
            
                <div class="field-group">
                    <label>Description</label>
                    <textarea id="edit_desc" class="Pdesc" maxlength="240" style="height: 100px;"><?php echo htmlspecialchars($project['Description']); ?></textarea>
                </div>
            
                <div style="display: flex; gap: 15px;">
                    <div class="field-group" style="flex: 1;">
                        <label>Start Date</label>
                        <input type="date" id="edit_date" class="startDate" max="<?php echo date('Y-m-d'); ?>" value="<?php echo $project['StartDate']; ?>">
                    </div>
                    <div class="field-group" style="flex: 1;">
                        <label>Budget</label>
                        <input type="number" id="edit_budget" class="budget" min="1" step="0.01" value="<?php echo $project['Budget']; ?>">
                    </div>
                </div>

                <div class="field-group">
                    <label>Progress</label>
                    <div class="progress-edit">
                        <span class="progress-percent-label" id="edit_progress_label"><?php echo $progress; ?>%</span>
                        <div class="mini-progress-bar">
                            <div class="mini-progress-fill" id="edit_progress_fill" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                        <input type="range" id="edit_progress" class="edit-input progress-range" min="0" max="100" value="<?php echo $progress; ?>">
                        <input type="number" id="edit_progress_number" class="edit-input progress-number" min="0" max="100" value="<?php echo $progress; ?>">
                    </div>
                </div>
            
                <div class="field-group">
                    <label>Status</label>
                    <select id="edit_status" class="status">
                        <option value="pending" <?php if($project['Status'] == 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="active" <?php if($project['Status'] == 'active') echo 'selected'; ?>>Active</option>
                        <option value="done" <?php if($project['Status'] == 'done') echo 'selected'; ?>>Done</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="btn-cancel" class="btn-cancel">Cancel</button>
                <button type="button" id="btn-update-confirm" class="btn-create">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        const modalOverlay = document.getElementById('modal-overlay');
        const editBtn = document.getElementById('edit_btn_trigger');
        const closeX = document.getElementById('close-x');
        const cancelBtn = document.getElementById('btn-cancel');
        const updateBtn = document.getElementById('btn-update-confirm');
        const progressRange = document.getElementById('edit_progress');
        const progressNumber = document.getElementById('edit_progress_number');
        const progressLabel = document.getElementById('edit_progress_label');
        const progressFill = document.getElementById('edit_progress_fill');
        const allowedStatuses = ['pending', 'active', 'done'];

        const clampProgress = (value) => Math.max(0, Math.min(100, Number(value) || 0));
        const validateProject = ({ name, desc, budget, date, status }) => {
            const cleanName = String(name).trim();
            const cleanDesc = String(desc).trim();
            const cleanBudget = Number(budget);
            const today = new Date();
            const offset = today.getTimezoneOffset() * 60000;
            const todayText = new Date(today.getTime() - offset).toISOString().split('T')[0];
            const errors = {};

            if (!cleanName) {
                errors.name = 'Project title is required.';
            } else if (!/^[\p{L}\p{N}]/u.test(cleanName)) {
                errors.name = 'Project title must start with a letter or number.';
            } else if (cleanName.length > 80) {
                errors.name = 'Project title must be 80 characters or less.';
            }

            if (cleanDesc.length > 240) {
                errors.desc = 'Description must be 240 characters or less.';
            }

            if (!date) {
                errors.date = 'Start date is required.';
            } else if (date > todayText) {
                errors.date = 'Start date cannot be in the future.';
            }

            if (!Number.isFinite(cleanBudget) || cleanBudget <= 0) {
                errors.budget = 'Budget must be greater than zero.';
            }

            if (!allowedStatuses.includes(String(status).toLowerCase())) {
                errors.status = 'Status must be pending, active, or done.';
            }

            return errors;
        };

        const hasErrors = (errors) => Object.keys(errors).length > 0;
        const clearFieldErrors = () => {
            modalOverlay.querySelectorAll('.field-error').forEach(error => error.remove());
            modalOverlay.querySelectorAll('.input-error').forEach(input => input.classList.remove('input-error'));
        };
        const showFieldError = (field, message) => {
            if (!field || !message) return;

            field.classList.add('input-error');
            const error = document.createElement('div');
            error.className = 'field-error';
            error.textContent = message;
            field.insertAdjacentElement('afterend', error);
        };
        const showModalErrors = (errors) => {
            clearFieldErrors();
            showFieldError(document.getElementById('edit_name'), errors.name);
            showFieldError(document.getElementById('edit_desc'), errors.desc);
            showFieldError(document.getElementById('edit_date'), errors.date);
            showFieldError(document.getElementById('edit_budget'), errors.budget);
            showFieldError(document.getElementById('edit_status'), errors.status);
        };
        const updateProgressPreview = (value) => {
            const progress = clampProgress(value);
            progressRange.value = progress;
            progressNumber.value = progress;
            progressLabel.textContent = `${progress}%`;
            progressFill.style.width = `${progress}%`;
        };

        // Toggle Modal
        const toggleModal = (show) => modalOverlay.classList[show ? 'add' : 'remove']('view');

        editBtn.addEventListener('click', () => {
            clearFieldErrors();
            toggleModal(true);
        });
        closeX.addEventListener('click', () => toggleModal(false));
        cancelBtn.addEventListener('click', () => toggleModal(false));
        progressRange.addEventListener('input', () => updateProgressPreview(progressRange.value));
        progressNumber.addEventListener('input', () => updateProgressPreview(progressNumber.value));

        // Update Project
        updateBtn.addEventListener('click', () => {
            const formData = new FormData();
            formData.append('id', document.getElementById('edit_id').value);
            formData.append('name', document.getElementById('edit_name').value);
            formData.append('desc', document.getElementById('edit_desc').value);
            formData.append('budget', document.getElementById('edit_budget').value);
            formData.append('date', document.getElementById('edit_date').value);
            formData.append('progress', progressNumber.value);
            formData.append('status', document.getElementById('edit_status').value);

            const validationErrors = validateProject({
                name: document.getElementById('edit_name').value,
                desc: document.getElementById('edit_desc').value,
                budget: document.getElementById('edit_budget').value,
                date: document.getElementById('edit_date').value,
                status: document.getElementById('edit_status').value
            });

            if (hasErrors(validationErrors)) {
                showModalErrors(validationErrors);
                return;
            }

            fetch('../actions/project_action.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                if (data.trim() === "success") {
                    location.reload();
                } else {
                    showModalErrors({ name: data.trim() || 'Update failed.' });
                }
            });
        });
    </script>
    <?php } ?>
    <script src="../js/project_relations.js?v=<?php echo time(); ?>"></script>
</body>
</html>
