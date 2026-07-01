<?php
include '../includes/connectDB.php';
include '../includes/auth.php';
requireLogin();
$userId = (int)currentUser()['id'];
$memberId = (int)(currentUser()['member_id'] ?? 0);
$teamScope = isAdmin()
    ? "(t.CreatedByUserID = $userId OR t.CreatedByUserID IS NULL)"
    : "EXISTS (SELECT 1 FROM team_membership mine WHERE mine.TeamID = t.TeamID AND mine.MemberID = $memberId)";
$memberScope = isAdmin()
    ? "(CreatedByUserID = $userId OR CreatedByUserID IS NULL)"
    : "MemberID = $memberId";

$stats = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM team t WHERE $teamScope) AS total_teams,
        (SELECT COUNT(*) FROM team_member WHERE $memberScope) AS total_members,
        (SELECT COUNT(*) FROM team_membership tm INNER JOIN team t ON tm.TeamID = t.TeamID WHERE $teamScope) AS total_assignments
")->fetch_assoc();

$teams = $conn->query("
    SELECT t.TeamID, t.TeamName, t.Description, COUNT(tm.MemberID) AS MemberCount
    FROM team t
    LEFT JOIN team_membership tm ON t.TeamID = tm.TeamID
    WHERE $teamScope
    GROUP BY t.TeamID
    ORDER BY t.TeamID DESC
");

$members = $conn->query("SELECT * FROM team_member WHERE $memberScope ORDER BY FullName ASC");
$membersForTeam = $conn->query("SELECT MemberID, FullName, UserName, RoleTitle FROM team_member WHERE $memberScope ORDER BY FullName ASC");
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../css/project.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="../css/team.css?v=<?php echo time(); ?>">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <title>Team</title>
    </head>
    <body>
        <div class="landing">
            <?php include '../includes/sidebar.php'; ?>

            <div class="project_content">
                <div class="project_header">
                    <div class="project_title">
                        <h1>Team</h1>
                        <p>Organize members into teams and use them as task assignees.</p>
                    </div>
                    <div class="headerbtn team-actions">
                        <?php if (isAdmin()) { ?>
                            <button id="add_member_btn" class="secondary-btn">Add Member</button>
                            <button id="add_team_btn">Add Team</button>
                        <?php } ?>
                    </div>
                </div>

                <div class="project-stats">
                    <div class="stat-box">
                        <p>Total Teams</p>
                        <h3><?php echo number_format($stats['total_teams']); ?></h3>
                    </div>
                    <div class="stat-box">
                        <p>Total Members</p>
                        <h3><?php echo number_format($stats['total_members']); ?></h3>
                    </div>
                    <div class="stat-box">
                        <p>Team Assignments</p>
                        <h3><?php echo number_format($stats['total_assignments']); ?></h3>
                    </div>
                </div>

                <div class="team-grid">
                    <?php if ($teams->num_rows === 0) { ?>
                        <div class="team-card empty-team-card">No teams yet. Create your first team and assign members.</div>
                    <?php } ?>
                    <?php while($team = $teams->fetch_assoc()) {
                        $teamId = (int)$team['TeamID'];
                        $teamMembersStmt = $conn->prepare("
                            SELECT m.MemberID, m.FullName, m.UserName, m.Email, m.RoleTitle
                            FROM team_membership tm
                            INNER JOIN team_member m ON tm.MemberID = m.MemberID
                            WHERE tm.TeamID = ?
                            ORDER BY m.FullName ASC
                        ");
                        $teamMembersStmt->bind_param("i", $teamId);
                        $teamMembersStmt->execute();
                        $teamMembers = $teamMembersStmt->get_result();
                    ?>
                        <div class="team-card">
                            <div class="team-card-header">
                                <div>
                                    <h2><a href="details.php?id=<?php echo $teamId; ?>"><?php echo htmlspecialchars($team['TeamName']); ?></a></h2>
                                    <p><?php echo htmlspecialchars($team['Description'] ?: 'No description added.'); ?></p>
                                </div>
                                <div class="team-card-actions">
                                    <span><?php echo number_format($team['MemberCount']); ?> members</span>
                                    <?php if (isAdmin()) { ?>
                                        <button type="button" class="team-delete-btn" data-team-id="<?php echo $teamId; ?>">Delete</button>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="team-member-stack">
                                <?php if ($teamMembers->num_rows === 0) { ?>
                                    <p class="grey">No members assigned.</p>
                                <?php } ?>
                                <?php while($member = $teamMembers->fetch_assoc()) { ?>
                                    <a class="team-member-row member-link" href="../members/details.php?id=<?php echo $member['MemberID']; ?>">
                                        <span class="team-avatar"><?php echo htmlspecialchars(strtoupper(substr($member['FullName'], 0, 1))); ?></span>
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['FullName']); ?></strong>
                                            <p>@<?php echo htmlspecialchars($member['UserName']); ?> - <?php echo htmlspecialchars($member['RoleTitle']); ?></p>
                                        </div>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="projectlist_section team-directory">
                    <p class="project_list">Members Directory</p>
                    <table>
                        <thead>
                            <tr id="table_titles">
                                <td>Full Name</td>
                                <td>Username</td>
                                <td>Email</td>
                                <td>Role</td>
                                <td>Department</td>
                                <?php if (isAdmin()) { ?>
                                    <td>Actions</td>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($members->num_rows === 0) { ?>
                                <tr><td colspan="<?php echo isAdmin() ? 6 : 5; ?>" class="empty-state">No members yet.</td></tr>
                            <?php } ?>
                            <?php while($member = $members->fetch_assoc()) { ?>
                                <tr>
                                    <td><a class="member-link directory-cell" href="../members/details.php?id=<?php echo $member['MemberID']; ?>" title="<?php echo htmlspecialchars($member['FullName']); ?>"><strong><?php echo htmlspecialchars($member['FullName']); ?></strong></a></td>
                                    <td><span class="directory-cell" title="@<?php echo htmlspecialchars($member['UserName']); ?>">@<?php echo htmlspecialchars($member['UserName']); ?></span></td>
                                    <td><span class="directory-cell" title="<?php echo htmlspecialchars($member['Email']); ?>"><?php echo htmlspecialchars($member['Email']); ?></span></td>
                                    <td><span class="directory-cell" title="<?php echo htmlspecialchars($member['RoleTitle']); ?>"><?php echo htmlspecialchars($member['RoleTitle']); ?></span></td>
                                    <td><span class="directory-cell" title="<?php echo htmlspecialchars($member['Department']); ?>"><?php echo htmlspecialchars($member['Department']); ?></span></td>
                                    <?php if (isAdmin()) { ?>
                                        <td>
                                            <button type="button" class="member-delete-btn icon-delete-btn" data-member-id="<?php echo $member['MemberID']; ?>" title="Delete member" aria-label="Delete member">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ff0022" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="team-modal-overlay">
            <div class="modal-box team-modal-box">
                <div class="modal-header">
                    <h2>Add Team</h2>
                    <span id="team-close-x" class="close-x">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="field-group">
                        <label>Team Name</label>
                        <input type="text" id="teamName" class="team-input" maxlength="120">
                    </div>
                    <div class="field-group">
                        <label>Description</label>
                        <textarea id="teamDesc" class="team-input team-textarea" maxlength="240"></textarea>
                    </div>
                    <div class="field-group">
                        <label>Members</label>
                        <div class="member-picker">
                            <?php while($member = $membersForTeam->fetch_assoc()) { ?>
                                <label>
                                    <input type="checkbox" value="<?php echo $member['MemberID']; ?>">
                                    <span><?php echo htmlspecialchars($member['FullName']); ?></span>
                                    <small>@<?php echo htmlspecialchars($member['UserName']); ?> - <?php echo htmlspecialchars($member['RoleTitle']); ?></small>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="team-cancel">Cancel</button>
                    <button type="button" class="btn-create" id="team-create">Create Team</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="member-modal-overlay">
            <div class="modal-box team-modal-box">
                <div class="modal-header">
                    <h2>Add Member</h2>
                    <span id="member-close-x" class="close-x">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="field-group">
                            <label>Full Name</label>
                            <input type="text" id="memberFullName" class="team-input" maxlength="140">
                        </div>
                        <div class="field-group">
                            <label>Username</label>
                            <input type="text" id="memberUserName" class="team-input" maxlength="100">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Email</label>
                        <input type="email" id="memberEmail" class="team-input" maxlength="180">
                    </div>
                    <div class="row">
                        <div class="field-group">
                            <label>Role</label>
                            <input type="text" id="memberRole" class="team-input" maxlength="120">
                        </div>
                        <div class="field-group">
                            <label>Department</label>
                            <input type="text" id="memberDepartment" class="team-input" maxlength="120">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="member-cancel">Cancel</button>
                    <button type="button" class="btn-create" id="member-create">Create Member</button>
                </div>
            </div>
        </div>

        <script src="../js/team.js?v=<?php echo time(); ?>"></script>
    </body>
</html>

<?php $conn->close(); ?>


