<?php
// ============================================================
//  config.php — All your settings in one place
//  Change these values to match your local setup
// ============================================================

define('DB_HOST',     'localhost');   // usually localhost
define('DB_USER',     'root');        // your MySQL username
define('DB_PASS',     '');            // your MySQL password (blank for XAMPP default)
define('DB_NAME',     'storetrack2');  // your database name

define('APP_NAME',    'StoreTrack2');
define('APP_URL',     'http://localhost/StoreTrack2');

// Low stock threshold — items below this qty trigger alert
define('LOW_STOCK_THRESHOLD', 5);