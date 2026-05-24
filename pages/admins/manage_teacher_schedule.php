<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle edit action
        if (isset($_POST['editTeacher']) && !empty($_POST['teacherSectionID'])) {
            $teacherSectionID = $_POST['teacherSectionID'];
            $teacherID = $_POST['teacherID'] ?? '';
            $sectionID = $_POST['sectionID'] ?? '';
            $subjectID = $_POST['subjectID'] ?? '';
            $day = $_POST['day'] ?? '';
            $timeSlot = $_POST['timeSlot'] ?? '';
            $advisory = $_POST['advisory'] ?? 0;

            $timeSlots = [
                '6:00 AM - 8:00 AM' => ['start' => '06:00:00', 'end' => '08:00:00'],
                '8:00 AM - 10:00 AM' => ['start' => '08:00:00', 'end' => '10:00:00'],
                '10:00 AM - 12:00 PM' => ['start' => '10:00:00', 'end' => '12:00:00'],
                '1:00 PM - 3:00 PM' => ['start' => '13:00:00', 'end' => '15:00:00'],
                '3:00 PM - 5:00 PM' => ['start' => '15:00:00', 'end' => '17:00:00'],
                '5:00 PM - 7:00 PM' => ['start' => '17:00:00', 'end' => '19:00:00']
            ];

            if (empty($sectionID) || empty($subjectID) || empty($day) || empty($timeSlot)) {
                $_SESSION['error_message'] = "Please fill all required fields.";
            } else {
                $startTime = $timeSlots[$timeSlot]['start'];
                $endTime = $timeSlots[$timeSlot]['end'];

                // Check for schedule conflict (excluding the current schedule)
                $checkConflict = "SELECT * FROM teacher_section 
                    WHERE teacherID = ? AND teacherSectionID != ? AND day = ? 
                    AND (
                        (startTime < ? AND endTime > ?) 
                        OR (startTime < ? AND endTime > ?)
                        OR (startTime >= ? AND endTime <= ?)
                    ) 
                    AND archived = 0";
                $stmt = $dbConnection->prepare($checkConflict);
                $stmt->execute([
                    $teacherID, $teacherSectionID, $day,
                    $endTime, $startTime,
                    $startTime, $endTime,
                    $startTime, $endTime
                ]);

                if ($stmt->rowCount() > 0) {
                    $_SESSION['error_message'] = "Teacher already has a schedule at this time and day.";
                } else {
                    // Check for section schedule conflict (excluding the current schedule)
                    $checkSectionConflict = "SELECT * FROM teacher_section 
                        WHERE sectionID = ? AND teacherSectionID != ? AND day = ? 
                        AND (
                            (startTime < ? AND endTime > ?) 
                            OR (startTime < ? AND endTime > ?)
                            OR (startTime >= ? AND endTime <= ?)
                        ) 
                        AND archived = 0";
                    $stmt = $dbConnection->prepare($checkSectionConflict);
                    $stmt->execute([
                        $sectionID, $teacherSectionID, $day,
                        $endTime, $startTime,
                        $startTime, $endTime,
                        $startTime, $endTime
                    ]);

                    if ($stmt->rowCount() > 0) {
                        $_SESSION['error_message'] = "Section already has a schedule at this time and day.";
                    } else {
                        $updateSQL = "UPDATE teacher_section 
                            SET sectionID = ?, subjectID = ?, day = ?, startTime = ?, endTime = ?, advisory = ? 
                            WHERE teacherSectionID = ? AND archived = 0";
                        $stmt = $dbConnection->prepare($updateSQL);
                        $success = $stmt->execute([$sectionID, $subjectID, $day, $startTime, $endTime, $advisory, $teacherSectionID]);

                        if ($success) {
                            $_SESSION['success_message'] = "Schedule updated successfully.";
                        } else {
                            $_SESSION['error_message'] = "Error updating schedule.";
                        }
                    }
                }
            }
        }

        // Handle archive action
        if (isset($_POST['archiveTeacher']) && !empty($_POST['teacherSectionID'])) {
            $teacherSectionID = $_POST['teacherSectionID'];
            $archiveSQL = "UPDATE teacher_section SET archived = 1 WHERE teacherSectionID = ? AND archived = 0";
            $stmt = $dbConnection->prepare($archiveSQL);
            $success = $stmt->execute([$teacherSectionID]);

            if ($success) {
                $_SESSION['success_message'] = "Schedule archived successfully.";
            } else {
                $_SESSION['error_message'] = "Error archiving schedule.";
            }
        }

        header("Location: enroll_teacher_section.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header("Location: enroll_teacher_section.php");
        exit;
    }
} else {
    header("Location: enroll_teacher_section.php");
    exit;
}
?>