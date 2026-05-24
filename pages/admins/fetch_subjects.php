<?php
require_once '../../config/db_connection.php';

header('Content-Type: application/json');

if (isset($_POST['strandID']) && !empty($_POST['strandID'])) {
    $strandID = (int)$_POST['strandID'];

    try {
        $sql = "SELECT subjectName, subjectType 
                FROM subjects 
                WHERE strandID = :strandID AND archived = 0 
                ORDER BY subjectType, subjectName";
        $stmt = $dbConnection->prepare($sql);
        $stmt->bindValue(':strandID', $strandID, PDO::PARAM_INT);
        $stmt->execute();
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['subjects' => $subjects]);
    } catch (PDOException $e) {
        echo json_encode(['subjects' => [], 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['subjects' => [], 'error' => 'Invalid or missing strandID']);
}
?>