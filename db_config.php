<?php
// db_config.php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'marketplace_db');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    error_log("Failed to connect to MySQL: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}
// NO CLOSING PHP TAG HERE