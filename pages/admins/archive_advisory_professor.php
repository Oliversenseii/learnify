<?php
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}

require_once '../../config/db_connection.php';

if (isset($_POST['id'])) {
    $advisoryID = $_POST['id'];  

    $sql = "UPDATE advisory_professor_section SET archived = 1 WHERE advisoryID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $advisoryID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Advisory teacher section archived successfully!";
    } else {
        $_SESSION['error_message'] = "Error archiving advisory teacher section. Please try again.";
    }

    $stmt->closeCursor();

    header("Location: enroll_advisory_professor.php");
    exit;
}
?>
