<?php
require_once './sessions/session_admin.php'; 
require_once '../../config/db_connection.php';

if (isset($_GET['strandID'])) {
    $strandID = $_GET['strandID'];

    try {
        $stmt = $dbConnection->prepare("UPDATE track_strands SET archived = 1 WHERE strandID = :strandID");
        $stmt->bindValue(':strandID', $strandID, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success_message'] = "Strand archived successfully!";
    } catch (PDOException $e) {

        $_SESSION['error_message'] = "Error archiving strand: " . $e->getMessage(); 
    }

    header("Location: data_track_strands.php"); 
    exit();
} else {
    
    header("Location: data_track_strands.php"); 
    exit();
}
?>