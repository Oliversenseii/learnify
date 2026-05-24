<?php
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}
?>