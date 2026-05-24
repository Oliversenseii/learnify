<?php
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}

require_once '../../config/db_connection.php';

if (isset($_POST['id'])) {
    $strandID = $_POST['id'];

    $sql = "UPDATE track_strands SET archived = 1 WHERE strandID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $strandID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Track and Strand archived successfully!";
    } else {
        $_SESSION['error_message'] = "Error archiving track and strand. Please try again.";
    }

    $stmt->closeCursor();
    header("Location: track-strands.php");
    exit;
}
?>
