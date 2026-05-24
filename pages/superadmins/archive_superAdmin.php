<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';

if (!isset($_GET['userID'])) {
    header('Location: data_prof.php');
    exit();
}

$userID = $_GET['userID'];

$archiveSql = "UPDATE users SET archived = 1 WHERE userID = :userID";
$archiveStmt = $dbConnection->prepare($archiveSql);
$archiveStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
$archiveStmt->execute();

if ($archiveStmt->execute()) {
    $_SESSION['success_message'] = "Super Admin user archived successfully!";
} else {
    $_SESSION['error_message'] = "Error archiving super admin user. Please try again.";
}

$archiveStmt->closeCursor();
header('Location: data_superAdmin.php');
exit();
?>