<?php
require_once './sessions/session_admin.php'; 
require_once '../../config/db_connection.php'; 

if (isset($_GET['sectionID'])) {
    $sectionID = $_GET['sectionID'];

    try {
        $stmt = $dbConnection->prepare("UPDATE sections SET archived = 1 WHERE sectionID = :sectionID");
        $stmt->bindValue(':sectionID', $sectionID, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success_message'] = "Section archived successfully!";  
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error archiving section: " . $e->getMessage(); 
    }

    header("Location: data_sections.php"); 
    exit();
} else {
    $_SESSION['error_message'] = "No section ID provided."; 
    header("Location: data_sections.php");
    exit();
}
?>