<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'sils_db';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>