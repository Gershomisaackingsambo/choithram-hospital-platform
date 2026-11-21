<?php
session_start();

// Clear all session data
$_SESSION = [];

// Remove the session cookie
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Destroy the session on the server
session_destroy();

header("Location: login.php?logged_out=1");
exit;