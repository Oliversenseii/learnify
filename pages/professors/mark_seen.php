<?php
// mark_seen.php
session_start();
require_once '../../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $eventIDs = $input['eventIDs'] ?? [];
    $userID = $_SESSION['userID'] ?? 0;

    if ($userID && !empty($eventIDs)) {
        try {
            $dbConnection->beginTransaction();
            $stmt = $dbConnection->prepare("
                INSERT IGNORE INTO seen_academic_events (userID, eventID) 
                VALUES (:userID, :eventID)
            ");
            foreach ($eventIDs as $eventID) {
                $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $stmt->bindParam(':eventID', $eventID, PDO::PARAM_INT);
                $stmt->execute();
            }
            $dbConnection->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $dbConnection->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>