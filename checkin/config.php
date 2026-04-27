<?php
// Database configuratie
// Laad credentials van buiten webroot
$db_config = require_once __DIR__ . '/../config-secure.php';

define('DB_HOST', $db_config['DB_HOST']);
define('DB_USER', $db_config['DB_USER']);
define('DB_PASS', $db_config['DB_PASS']);
define('DB_NAME', $db_config['DB_NAME']);

// Maak database connectie
function getDbConnection()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Database connectie mislukt: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Timezone instellen
date_default_timezone_set('Europe/Amsterdam');
