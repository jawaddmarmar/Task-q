<?php
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);
    session_start();
}

function appBasePath() {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $sectionFolders = ['dashboard', 'projects', 'tasks', 'teams', 'members', 'reports', 'auth', 'actions'];
    $currentDir = basename($scriptDir);
    $base = in_array($currentDir, $sectionFolders, true)
        ? rtrim(dirname($scriptDir), '/\\') . '/'
        : rtrim($scriptDir, '/\\') . '/';

    return $base === '//' ? '/' : $base;
}

function redirectTo($path) {
    header('Location: ' . appBasePath() . ltrim($path, '/'));
    exit;
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn() {
    return currentUser() !== null;
}

function userRole() {
    return $_SESSION['user']['role'] ?? '';
}

function isAdmin() {
    return userRole() === 'admin';
}

function isMember() {
    return userRole() === 'member';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirectTo('auth/login.php');
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        echo 'You do not have permission to do this action.';
        exit;
    }
}

function loginUser($user) {
    $_SESSION['user'] = [
        'id' => (int)$user['UserID'],
        'username' => $user['UserName'],
        'email' => $user['Email'],
        'role' => strtolower($user['RoleName'] ?? ''),
        'member_id' => isset($user['MemberID']) ? (int)$user['MemberID'] : null,
        'full_name' => $user['FullName'] ?? $user['UserName']
    ];
}

function roleIdByName($conn, $roleName) {
    $stmt = $conn->prepare("
        INSERT INTO role (RoleName)
        SELECT ?
        WHERE NOT EXISTS (
            SELECT 1 FROM role WHERE RoleName = ?
        )
    ");

    $stmt->bind_param("ss", $roleName, $roleName);
    $stmt->execute();


    $stmt = $conn->prepare("
        SELECT RoleID
        FROM role
        WHERE RoleName = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $roleName);
    $stmt->execute();

    $role = $stmt->get_result()->fetch_assoc();

    return (int)($role['RoleID'] ?? 0);
}
?>
