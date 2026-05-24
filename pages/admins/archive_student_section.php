<?php
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}

require_once '../../config/db_connection.php';

if (isset($_POST['id'])) {
    $studentSectionID = $_POST['id'];

    $sql = "UPDATE student_section SET archived = 1 WHERE studentSectionID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $studentSectionID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Student section archived successfully!";
    } else {
        $_SESSION['error_message'] = "Error archiving student section. Please try again.";
    }

    $stmt->closeCursor();
    header("Location: enroll_student_section.php");
    exit;
}
?>
