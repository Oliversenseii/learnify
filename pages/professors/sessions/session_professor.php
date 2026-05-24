<?php
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Professor') {
    header("Location: ../../../index.php");
    exit;
}
?>