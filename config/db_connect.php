<?php
// db_connect.php

$servername = "localhost";
$username = "logi_final"; 
$password = "123";     
$dbname = "logi_final"; 

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Set character set to utf8mb4 for full Unicode support
$conn->set_charset("utf8mb4");

?>