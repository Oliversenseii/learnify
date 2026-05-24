<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';

$userID = $_SESSION['userID'];

if (isset($_POST['resetEnrollmentCount']) && $_POST['resetEnrollmentCount'] == 1) {
    try {
        $stmt = $dbConnection->prepare("UPDATE users SET enrollment_notification_seen = 1 WHERE userID = :userID");
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $dbConnection->prepare("UPDATE enrollments SET archived = 1 WHERE archived = 0 AND DATE(dateEnrolled) = CURDATE()");
        $stmt->execute();

        echo "Enrollment count reset successfully.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
