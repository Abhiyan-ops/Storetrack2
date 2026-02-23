<?php
// Staff auth guard — include at top of every employee page
session_start();

if (!isset($_SESSION['employee_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit;
}