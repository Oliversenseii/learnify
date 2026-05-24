<?php
require_once '../../config/db_connection.php';

if (isset($_POST['sectionID']) && isset($_POST['academicSession'])) {
    $sectionID = $_POST['sectionID'];
    $academicSession = $_POST['academicSession'];

    try {
        $countSql = "SELECT u.sex, COUNT(*) as count 
                     FROM student_section ss 
                     JOIN users u ON ss.userID = u.userID 
                     WHERE ss.sectionID = ? AND ss.academicSession = ? AND ss.status = 'Enrolled' AND ss.archived = 0 
                     GROUP BY u.sex";
        $countStmt = $dbConnection->prepare($countSql);
        $countStmt->bindParam(1, $sectionID, PDO::PARAM_INT);
        $countStmt->bindParam(2, $academicSession, PDO::PARAM_STR);
        $countStmt->execute();
        $genderCounts = $countStmt->fetchAll(PDO::FETCH_ASSOC);

        $maleCount = 0;
        $femaleCount = 0;
        foreach ($genderCounts as $row) {
            if ($row['sex'] === 'Male') $maleCount = $row['count'];
            if ($row['sex'] === 'Female') $femaleCount = $row['count'];
        }

        echo "Current Enrolled: Males: $maleCount | Females: $femaleCount";
    } catch (PDOException $e) {
        echo "Error fetching stats.";
    }
}
?>
