<?php
require_once '../../config/db_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['sectionID'])) {
    echo json_encode(['error' => 'No section ID provided']);
    exit;
}

$sectionID = (int)$_GET['sectionID'];

$sql = "SELECT ss.studentSectionID, ss.enrollmentDate, ss.status, u.firstName, u.lastName, u.image, s.sectionName
        FROM student_section ss
        JOIN users u ON ss.userID = u.userID
        JOIN sections s ON ss.sectionID = s.sectionID
        WHERE ss.sectionID = :sectionID AND ss.archived = 0
        ORDER BY ss.enrollmentDate DESC";
$stmt = $dbConnection->prepare($sql);
$stmt->bindValue(':sectionID', $sectionID, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$response = [
    'sectionName' => $students ? $students[0]['sectionName'] : '',
    'students' => $students
];

echo json_encode($response);
?>