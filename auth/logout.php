<?php
include '../includes/auth.php';

$_SESSION = [];
session_destroy();
redirectTo('auth/login.php');
?>
