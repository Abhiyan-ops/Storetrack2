<?php
// ============================================================
//  auth_check.php — Protects pages from non-logged-in users
//  Add this at the TOP of every protected page
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Figure out redirect path based on where the file is
    $redirect = (strpos($_SERVER['PHP_SELF'], '/pages/') !== false)
        ? '../login.php'
        : 'login.php';
    header('Location: ' . $redirect);
    exit;
}