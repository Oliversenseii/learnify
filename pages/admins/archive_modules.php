<?php
require_once './sessions/session_admin.php'; 
require_once '../../config/db_connection.php';

if (isset($_GET['moduleID'])) {
    $moduleID = $_GET['moduleID'];

    try {
        $stmt = $dbConnection->prepare("UPDATE strand_modules SET archived = 1 WHERE moduleID = :moduleID");
        $stmt->bindValue(':moduleID', $moduleID, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success_message'] = "Module archived successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error archiving module: " . $e->getMessage(); 
    }

    header("Location: data_modules.php"); 
    exit();
} else {
    header("Location: data_modules.php"); 
    exit();
}
?>