<?php
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'SuperAdmin') {
    header("Location: ../../../index.php");
    exit;
}
?>
