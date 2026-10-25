<?php
// logout.php
require_once __DIR__ . '/config/functions.php';
if (isset($_SESSION['user_id'])) {
    log_audit('LOGOUT', 'AUTH', $_SESSION['user_id'], 'User logged out');
}
$_SESSION = [];
if (session_id() != "" || isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 2592000, '/');
}
session_destroy();
header("Location: login.php");
exit;
