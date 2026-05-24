<?php
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Student') {
    header("Location: ../../../index.php");
    exit;
}
?>