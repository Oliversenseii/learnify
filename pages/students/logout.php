<?php
session_start();
require_once '../../config/db_connection.php';

date_default_timezone_set('Asia/Manila');

if (isset($_SESSION['userID'])) {
    $logoutTime = date('Y-m-d H:i:s'); 
    
    $updateHistoryQuery = "UPDATE login_history SET logout_time = :logoutTime WHERE userID = :userID AND logout_time IS NULL";
    $updateStmt = $dbConnection->prepare($updateHistoryQuery);
    $updateStmt->bindParam(':logoutTime', $logoutTime, PDO::PARAM_STR);
    $updateStmt->bindParam(':userID', $_SESSION['userID'], PDO::PARAM_INT);
    $updateStmt->execute();
    
    session_unset();
    session_destroy();
}

setcookie('userID', '', time() - 3600, '/');
setcookie('lrn', '', time() - 3600, '/');
setcookie('userType', '', time() - 3600, '/');
setcookie('status', '', time() - 3600, '/');
setcookie("last_activity_time", "", time() - 3600, "/");

header("Location: ../../index.php");
exit;
?>
