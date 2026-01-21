<?php
// Simulan ang session
session_start();
 
// Alisin lahat ng session variables
$_SESSION = array();
 
// Wasakin ang session
session_destroy();
 
// I-redirect sa login page
header("location: login.php");
exit;
?>