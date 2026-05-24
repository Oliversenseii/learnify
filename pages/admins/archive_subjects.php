<?php
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}

require_once '../../config/db_connection.php';

if (isset($_POST['id'])) {
    $subjectID = $_POST['id'];  

    $sql = "UPDATE subjects SET archived = 1 WHERE subjectID = ?";  
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $subjectID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Subject archived successfully!";  
    } else {
        $_SESSION['error_message'] = "Error archiving subject. Please try again.";  
    }

    $stmt->closeCursor();
    header("Location: subjects.php");  
    exit;
}
?>
