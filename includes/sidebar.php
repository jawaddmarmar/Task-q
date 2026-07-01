<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$sectionFolders = ['dashboard', 'projects', 'tasks', 'teams', 'members', 'reports'];
$appBase = in_array($currentDir, $sectionFolders, true)
    ? rtrim(dirname($scriptDir), '/\\') . '/'
    : rtrim($scriptDir, '/\\') . '/';

if ($appBase === '//') {
    $appBase = '/';
}

function navStyle($isActive) {
    $base = "display: flex; align-items: center; padding: 12px 15px; text-decoration: none; border-radius: 8px;";
    return $isActive
        ? $base . " color: #1358ec; background: #f1f5f9; font-weight: 600;"
        : $base . " color: #64748b;";
}

$isProjectPage = $currentDir === 'projects';
$isTaskPage = $currentDir === 'tasks';
$isTeamPage = in_array($currentDir, ['teams', 'members'], true);
$isDashboardPage = $currentDir === 'dashboard';
$isReportPage = $currentDir === 'reports';
?>

<div class="sidebar">
    <div class="sidebar-logo taskq-sidebar-logo">
        <span class="taskq-mark" aria-hidden="true">
            <svg viewBox="0 0 64 64" role="img">
                <path d="M32 4 54 4c4 0 6 5 3 8L38 31c-3 3-8 3-11 0L8 12C5 9 7 4 11 4h21Z"/>
                <path d="M7 22h18c4 0 6 5 3 8L14 44c-2 2-6 2-8-1L1 29c-1-3 2-7 6-7Z"/>
                <path d="M57 22H39c-4 0-6 5-3 8l14 14c2 2 6 2 8-1l5-14c1-3-2-7-6-7Z"/>
                <path d="M25 40h14c4 0 6 4 4 7l-7 11c-2 3-6 3-8 0l-7-11c-2-3 0-7 4-7Z"/>
            </svg>
        </span>
        <span class="taskq-word">Task-Q</span>
    </div>
    
    <nav class="sidebar-nav">
        <ul style="list-style: none; padding: 0 10px;">
            <li style="margin-bottom: 10px;">
                <a href="<?php echo $appBase; ?>dashboard/" style="<?php echo navStyle($isDashboardPage); ?>">
                    <span style="margin-right: 10px;"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 24 24" fill="#1d245e" stroke="#1d245e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg></span>
                    Dashboard
                </a>
            </li>
            <li style="margin-bottom: 10px;">
                <a href="<?php echo $appBase; ?>projects/" style="<?php echo navStyle($isProjectPage); ?>">
                    <span style="margin-right: 10px;"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 24 24" fill="#1d245e" stroke="#1d245e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-tree-icon lucide-folder-tree"><path d="M20 10a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2.5a1 1 0 0 1-.8-.4l-.9-1.2A1 1 0 0 0 15 3h-2a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1Z"/><path d="M20 21a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1h-2.9a1 1 0 0 1-.88-.55l-.42-.85a1 1 0 0 0-.92-.6H13a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1Z"/><path d="M3 5a2 2 0 0 0 2 2h3"/><path d="M3 3v13a2 2 0 0 0 2 2h3"/></svg></span>
                    Projects
                </a>
            </li>
            <li style="margin-bottom: 10px;">
                <a href="<?php echo $appBase; ?>tasks/" style="<?php echo navStyle($isTaskPage); ?>">
                    <span style="margin-right: 10px;"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="#1d245e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-checks-icon lucide-list-checks"><path d="M13 5h8"/><path d="M13 12h8"/><path d="M13 19h8"/><path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/></svg></span>
                    Tasks
                </a>
            </li>
            <li style="margin-bottom: 10px;">
                <a href="<?php echo $appBase; ?>teams/" style="<?php echo navStyle($isTeamPage); ?>">
                    <span style="margin-right: 10px;"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 24 24" fill="#1d245e" stroke="#1d245e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg></span>
                    Team
                </a>
            </li>
            <li style="margin-bottom: 10px;">
                <a href="<?php echo $appBase; ?>reports/" style="<?php echo navStyle($isReportPage); ?>">
                    <span style="margin-right: 10px;"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="#1d245e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-area-icon lucide-chart-area"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 11.207a.5.5 0 0 1 .146-.353l2-2a.5.5 0 0 1 .708 0l3.292 3.292a.5.5 0 0 0 .708 0l4.292-4.292a.5.5 0 0 1 .854.353V16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1z"/></svg></span>
                    Reports
                </a>
            </li>
        </ul>
    </nav>
    <?php if (function_exists('currentUser') && currentUser()) { ?>
        <div class="sidebar-user">
            <strong><?php echo htmlspecialchars(currentUser()['full_name'] ?? currentUser()['username']); ?></strong>
            <span class="role-badge <?php echo htmlspecialchars(currentUser()['role']); ?>"><?php echo htmlspecialchars(ucfirst(currentUser()['role'])); ?></span>
            <a href="<?php echo $appBase; ?>auth/logout.php">Logout</a>
        </div>
    <?php } ?>
</div>



