<?php
 ini_set('display_errors', '1');
 ini_set('display_startup_errors', '1');
 error_reporting(E_ALL);


$dbConfig = new \Config\Database();
$defaultDB = $dbConfig->default;

$dbhostname = $defaultDB['hostname'];
$dbdatabase = $defaultDB['database'];
$dbUsername = $defaultDB['username'];
$dbPassword = $defaultDB['password'];


global $conn;

$conn = new mysqli($dbhostname, $dbUsername, $dbPassword, $dbdatabase);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>