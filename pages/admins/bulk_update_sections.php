<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';

// Define academic sessions (matching ENUM values in database)
$academicSessions = ['2025 - 2026', '2026 - 2027', '2027 - 2028', '2028 - 2029', '2029 - 2030'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_sections'])) {
    $selectedSections = $_POST['selected_sections'];
    $newSectionID = isset($_POST['new_section_id']) ? (int)$_POST['new_section_id'] : null;
    $newAcademicSession = isset($_POST['new_academic_session']) ? $_POST['new_academic_session'] : null;
    $newStatus = isset($_POST['new_status']) ? $_POST['new_status'] : null;
    $currentAcademicSession = $_POST['academic_session'] ?? '';

    if (empty($selectedSections)) {
        $_SESSION['error_message'] = 'No sections selected.';
        header('Location: data_student_section.php?academic_session=' . urlencode($currentAcademicSession));
        exit;
    }

    if (!$newSectionID && !$newAcademicSession && !$newStatus) {
        $_SESSION['error_message'] = 'No changes selected.';
        header('Location: data_student_section.php?academic_session=' . urlencode($currentAcademicSession));
        exit;
    }

    if ($newAcademicSession && !in_array($newAcademicSession, $academicSessions)) {
        $_SESSION['error_message'] = 'Invalid academic session selected.';
        header('Location: data_student_section.php?academic_session=' . urlencode($currentAcademicSession));
        exit;
    }

    if ($newStatus && !in_array($newStatus, ['Enrolled', 'Pending', 'Dropped', 'Completed'])) {
        $_SESSION['error_message'] = 'Invalid status selected.';
        header('Location: data_student_section.php?academic_session=' . urlencode($currentAcademicSession));
        exit;
    }

    try {
        $dbConnection->beginTransaction();

        if ($newSectionID) {
            // Check current gender counts for the new section
            $countSql = "SELECT u.sex, COUNT(*) as count 
                        FROM student_section ss 
                        JOIN users u ON ss.userID = u.userID 
                        WHERE ss.sectionID = ? AND ss.status = 'Enrolled' AND ss.archived = 0 
                        GROUP BY u.sex";
            $countStmt = $dbConnection->prepare($countSql);
            $countStmt->bindParam(1, $newSectionID, PDO::PARAM_INT);
            $countStmt->execute();
            $genderCounts = $countStmt->fetchAll(PDO::FETCH_ASSOC);

            $maleCount = 0;
            $femaleCount = 0;
            foreach ($genderCounts as $row) {
                if ($row['sex'] === 'Male') $maleCount = $row['count'];
                if ($row['sex'] === 'Female') $femaleCount = $row['count'];
            }

            // Count males and females in selected sections
            $newMales = 0;
            $newFemales = 0;
            $checkUserSql = "SELECT u.sex 
                           FROM student_section ss 
                           JOIN users u ON ss.userID = u.userID 
                           WHERE ss.studentSectionID = ?";
            $checkUserStmt = $dbConnection->prepare($checkUserSql);

            foreach ($selectedSections as $studentSectionID) {
                $checkUserStmt->bindParam(1, $studentSectionID, PDO::PARAM_INT);
                $checkUserStmt->execute();
                $user = $checkUserStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    if ($user['sex'] === 'Male') $newMales++;
                    if ($user['sex'] === 'Female') $newFemales++;
                }
            }

            // Validate gender limits
            if ($maleCount + $newMales > 25 || $femaleCount + $newFemales > 25) {
                $dbConnection->rollBack();
                $_SESSION['error_message'] = "Cannot update: Would exceed limit of 25 males or 25 females in the new section.";
                header('Location: data_student_section.php?academic_session=' . urlencode($currentAcademicSession));
                exit;
            }
        }

        // Update sections
        $sql = "UPDATE student_section SET ";
        $params = [];
        $bindings = [];

        if ($newSectionID) {
            $sql .= "sectionID = :sectionID";
            $params[':sectionID'] = $newSectionID;
        }

        if ($newAcademicSession) {
            if ($newSectionID) {
                $sql .= ", ";
            }
            $sql .= "academicSession = :academicSession";
            $params[':academicSession'] = $newAcademicSession;
        }

        if ($newStatus) {
            if ($newSectionID || $newAcademicSession) {
                $sql .= ", ";
            }
            $sql .= "status = :status";
            $params[':status'] = $newStatus;
        }

        $sql .= " WHERE studentSectionID = :studentSectionID";
        $stmt = $dbConnection->prepare($sql);

        foreach ($selectedSections as $studentSectionID) {
            $bindings = $params;
            $bindings[':studentSectionID'] = (int)$studentSectionID;
            foreach ($bindings as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
        }

        $dbConnection->commit();
        $_SESSION['success_message'] = 'Selected student sections updated successfully.';
    } catch (Exception $e) {
        $dbConnection->rollBack();
        $_SESSION['error_message'] = 'Error updating sections: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'Invalid request.';
}

header('Location: data_student_section.php?academic_session=' . urlencode($currentAcademicSession));
exit;
?>