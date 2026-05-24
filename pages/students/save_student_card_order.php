<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['order']) || !is_array($input['order'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    $order = $input['order'];
    $userID = $_SESSION['userID'];

    // Verify that all teacherSectionIDs are enrolled by the user
    $stmt = $dbConnection->prepare("
        SELECT ts.teacherSectionID 
        FROM teacher_section ts
        JOIN student_section ss ON ts.sectionID = ss.sectionID
        WHERE ss.userID = :userID AND ss.status = 'Enrolled' AND ss.archived = 0
    ");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $validIDs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Validate input IDs
    if (array_diff($order, $validIDs)) {
        echo json_encode(['success' => false, 'error' => 'Invalid teacherSectionID']);
        exit;
    }

    // Update card_order
    $dbConnection->beginTransaction();
    $stmt = $dbConnection->prepare("
        UPDATE student_section ss
        JOIN teacher_section ts ON ss.sectionID = ts.sectionID
        SET ss.card_order = :card_order 
        WHERE ts.teacherSectionID = :teacherSectionID AND ss.userID = :userID AND ss.status = 'Enrolled'
    ");
    
    foreach ($order as $index => $teacherSectionID) {
        $stmt->bindParam(':card_order', $index, PDO::PARAM_INT);
        $stmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $stmt->execute();
    }

    $dbConnection->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $dbConnection->rollBack();
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

$dbConnection = null;
?>