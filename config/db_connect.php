<?php
// db_connect.php

$servername = "localhost:3307";
$username = "root"; // Palitan mo ito ng iyong database username
$password = "";     // Palitan mo ito ng iyong database password
$dbname = "logistics_db"; // Pangalan ng database na ginawa sa database.sql

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Optional: Set character set to utf8mb4 for full Unicode support
$conn->set_charset("utf8mb4");

?>