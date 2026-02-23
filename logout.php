<?php
session_start();      // start session so we can access it
session_unset();      // clear all session variables
session_destroy();    // destroy the session completely
header('Location: login.php');
exit;
?>