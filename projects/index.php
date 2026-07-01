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
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../css/project.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
        <title>Projects</title>
        <style>
            .project-link { text-decoration: none; transition: color 0.2s; }
            .project-link:hover p { color: #1358ec !important; }
        </style>
    </head>
    <body>
        <div class="landing">
            
            <?php include '../includes/sidebar.php'; ?>

            <div class="project_content">          
                <div class="project_header">
                    <div class="project_title">
                        <h1>Projects</h1>
                        <p>Overview of all active and historical enterprise initiatives</p>
                    </div>
                    <div class="headerbtn">
                        <?php if (isAdmin()) { ?>
                            <button id="add_project_btn">Add New Project</button>
                        <?php } ?>
                    </div>   
                </div>
                
                <?php
                $statsResult = $conn->query("
                    SELECT 
                        COUNT(*) AS total_projects,
                        SUM(CASE WHEN LOWER(Status) = 'active' THEN 1 ELSE 0 END) AS active_projects,
                        COALESCE(SUM(Budget), 0) AS total_budget
                    FROM project p
                    WHERE $projectScope
                ");
                $stats = $statsResult->fetch_assoc();
                ?>

                <div class="project-stats">
                    <div class="stat-box">
                        <p>Total Projects</p>
                        <h3><?php echo number_format($stats['total_projects']); ?></h3>
                    </div>
                    <div class="stat-box">
                        <p>Active Projects</p>
                        <h3><?php echo number_format($stats['active_projects']); ?></h3>
                    </div>
                    <div class="stat-box">
                        <p>Total Budget</p>
                        <h3>$<?php echo number_format($stats['total_budget'], 2); ?></h3>
                    </div>
                </div>

                <div class="projectlist_section">
                    <p class="project_list">Project List</p>
                    <table>
                        <thead>
                            <tr id="table_titles">
                                <td style="width: 14%;">Project Name</td>
                                <td style="width: 20%;">Description</td>
                                <td style="width: 13%;">Budget</td>
                                <td style="width: 14%;">Start Date</td>
                                <td style="width: 14%;">Status</td>
                                <td style="width: 14%;">Actions</td>
                            </tr>
                        </thead>
                        <tbody id="taskBody">
                            <?php
                            function truncateText($text, $wordLimit, $charLimit) {
                                $text = trim($text);
                                $words = preg_split('/\s+/', trim($text));

                                if (count($words) > $wordLimit) {
                                    return implode(' ', array_slice($words, 0, $wordLimit)) . '...';
                                }

                                if (strlen($text) > $charLimit) {
                                    return substr($text, 0, $charLimit) . '...';
                                }

                                return $text;
                            }

                            $result = $conn->query("SELECT p.* FROM project p WHERE $projectScope ORDER BY p.ProjectID DESC");
                        
                            while($row = $result->fetch_assoc()) {
                                $statusClass = strtolower($row['Status']);
                                $projectId = $row['ProjectID'];
                                $fullName = $row['ProjectName'];
                                $fullDesc = $row['Description'];
                                $shortName = truncateText($fullName, 2, 14);
                                $shortDesc = truncateText($fullDesc, 8, 55);
                                
                                echo "<tr data-id='" . $projectId . "' data-name='" . htmlspecialchars($fullName, ENT_QUOTES) . "' data-desc='" . htmlspecialchars($fullDesc, ENT_QUOTES) . "'>
                                        <td>
                                            <a href='details.php?id=$projectId' class='project-link'>
                                                <p style='color: #111827; font-weight: bold;'>" . htmlspecialchars($shortName) . "</p>
                                            </a>
                                        </td>
                                        <td><p class='grey'>" . htmlspecialchars($shortDesc) ."</p></td>
                                        <td>$" . number_format($row['Budget']) . "</td>
                                        <td>" . $row['StartDate'] . "</td>
                                        <td><p class='statusColor $statusClass'>" . $row['Status'] . "</p></td>
                                        <td class='action-icons'>
                                            <a href='details.php?id=$projectId'>
                                                <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#949494' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='lucide lucide-eye'><path d='M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z'/><circle cx='12' cy='12' r='3'/></svg>
                                            </a>

                                            " . (isAdmin() ? "
                                                <span class='edit-trigger' style='cursor:pointer; display:flex; align-items:center;'>
                                                    <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='#d2ce00' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='lucide lucide-pencil'><path d='M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z'/><path d='m15 5 4 4'/></svg>
                                                </span>
                                                <svg xmlns='http://www.w3.org/2000/svg' id='delete-btn' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='#ff0022' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='lucide lucide-trash-2'><path d='M10 11v6'/><path d='M14 11v6'/><path d='M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6'/><path d='M3 6h18'/><path d='M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2'/></svg>
                                            " : "") . "
                                        </td>
                                      </tr>";
                            }
                            $conn->close();
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
       </div>

       <div class="modal-overlay" id="modal-overlay">
            <div id="modal-box" class="modal-box">
                <div class="modal-header">
                    <h2>Add New Project</h2>
                    <span id="close-x" class="close-x">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="field-group">
                        <label for="Pname">Project Name</label>
                        <input type="text" name="Pname" class="Pname" maxlength="80" placeholder="e.g. Q4 Marketing Campaign">
                    </div>
                    <div class="field-group">
                        <label for="Pdesc">Description</label>
                        <textarea name="Pdesc" class="Pdesc" maxlength="240" placeholder="Briefly describe the project goals and scope..."></textarea>
                    </div>
                    <div class="row">
                        <div class="field-group">
                            <label for="startDate">Start Date</label>
                            <input type="date" name="startDate" class="startDate" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="field-group">
                            <label for="budget">Budget</label>
                            <input type="number" name="budget" class="budget" min="1" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="status">Status</label>
                        <select name="status" class="status" disabled>
                            <option value="pending" selected>Pending</option>
                            <option value="active">Active</option>
                            <option value="done">done</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="btn-cancel">Cancel</button>
                    <button type="button" id="btn-create" class="btn-create">Create Project</button>
                </div>
           </div>
       </div>

       <script src="../js/project.js?v=<?php echo time(); ?>"></script>
    </body>
</html>
