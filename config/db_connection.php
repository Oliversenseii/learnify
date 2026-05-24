<?php

$host = 'localhost'; 
$dbname = 'db_lms'; 
$username = 'root'; 
$password = ''; 

try {
    $dbConnection = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
