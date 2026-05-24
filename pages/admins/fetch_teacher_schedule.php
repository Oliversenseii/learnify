<?php
require_once '../../config/db_connection.php';
require_once './sessions/session_admin.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle edit case with teacherSectionID
        if (isset($_POST['teacherSectionID']) && !empty($_POST['teacherSectionID'])) {
            $teacherSectionID = $_POST['teacherSectionID'];
            $scheduleDetailSQL = "
                SELECT 
                    ts.teacherSectionID,
                    ts.teacherID,
                    u.firstName,
                    u.lastName,
                    ts.sectionID,
                    s.sectionName,
                    s.sectionCode,
                    ts.subjectID,
                    sub.subjectName,
                    sub.subjectCode,
                    ts.startTime,
                    ts.endTime,
                    ts.day,
                    ts.advisory
                FROM teacher_section ts
                JOIN users u ON ts.teacherID = u.userID
                JOIN sections s ON ts.sectionID = s.sectionID
                JOIN subjects sub ON ts.subjectID = sub.subjectID
                WHERE ts.teacherSectionID = ? AND ts.archived = 0";
            $scheduleDetailStmt = $dbConnection->prepare($scheduleDetailSQL);
            $scheduleDetailStmt->execute([$teacherSectionID]);
            $schedule = $scheduleDetailStmt->fetch(PDO::FETCH_ASSOC);

            if ($schedule) {
                echo json_encode(['schedule' => $schedule]);
            } else {
                echo json_encode(['error' => 'Schedule not found.']);
            }
            exit;
        }

        // Handle initial load or enrollment availability with teacherID
        if (isset($_POST['teacherID']) && !empty($_POST['teacherID'])) {
            $teacherID = $_POST['teacherID'];

            // Fetch profile
            $profileSQL = "SELECT firstName, lastName, image FROM users WHERE userID = ? AND userType = 'Professor' AND archived = 0";
            $profileStmt = $dbConnection->prepare($profileSQL);
            $profileStmt->execute([$teacherID]);
            $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                echo json_encode(['error' => 'Teacher not found or not a valid professor.']);
                exit;
            }

            // Fetch schedules
            $scheduleSQL = "
                SELECT 
                    ts.teacherSectionID,
                    ts.teacherID,
                    ts.sectionID,
                    s.sectionName,
                    s.sectionCode,
                    ts.subjectID,
                    sub.subjectName,
                    sub.subjectCode,
                    ts.startTime,
                    ts.endTime,
                    ts.day,
                    ts.advisory
                FROM teacher_section ts
                JOIN sections s ON ts.sectionID = s.sectionID
                JOIN subjects sub ON ts.subjectID = sub.subjectID
                WHERE ts.teacherID = ? AND ts.archived = 0 AND s.archived = 0 AND sub.archived = 0
                ORDER BY ts.day, ts.startTime";
            $scheduleStmt = $dbConnection->prepare($scheduleSQL);
            $scheduleStmt->execute([$teacherID]);
            $schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

            // If specific day and timeSlot are provided, fetch section and subject availability
            $sectionTimeSlots = [];
            $sectionSchedules = [];
            if (isset($_POST['day']) && isset($_POST['timeSlot'])) {
                $day = $_POST['day'];
                $timeSlot = $_POST['timeSlot'];

                $timeSlots = [
                    '6:00 AM - 8:00 AM' => ['start' => '06:00:00', 'end' => '08:00:00'],
                    '8:00 AM - 10:00 AM' => ['start' => '08:00:00', 'end' => '10:00:00'],
                    '10:00 AM - 12:00 PM' => ['start' => '10:00:00', 'end' => '12:00:00'],
                    '1:00 PM - 3:00 PM' => ['start' => '13:00:00', 'end' => '15:00:00'],
                    '3:00 PM - 5:00 PM' => ['start' => '15:00:00', 'end' => '17:00:00'],
                    '5:00 PM - 7:00 PM' => ['start' => '17:00:00', 'end' => '19:00:00']
                ];

                $startTime = $timeSlots[$timeSlot]['start'];
                $endTime = $timeSlots[$timeSlot]['end'];

                // Fetch sections already scheduled at this time and day
                $sectionTimeSQL = "
                    SELECT sectionID 
                    FROM teacher_section 
                    WHERE day = ? 
                    AND (
                        (startTime < ? AND endTime > ?) 
                        OR (startTime < ? AND endTime > ?)
                        OR (startTime >= ? AND endTime <= ?)
                    ) 
                    AND archived = 0";
                $sectionTimeStmt = $dbConnection->prepare($sectionTimeSQL);
                $sectionTimeStmt->execute([$day, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime]);
                $sectionTimeSlots = $sectionTimeStmt->fetchAll(PDO::FETCH_ASSOC);

                // Fetch subjects already assigned to sections at this time and day
                $sectionScheduleSQL = "
                    SELECT subjectID 
                    FROM teacher_section 
                    WHERE day = ? 
                    AND (
                        (startTime < ? AND endTime > ?) 
                        OR (startTime < ? AND endTime > ?)
                        OR (startTime >= ? AND endTime <= ?)
                    ) 
                    AND archived = 0";
                $sectionScheduleStmt = $dbConnection->prepare($sectionScheduleSQL);
                $sectionScheduleStmt->execute([$day, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime]);
                $sectionSchedules = $sectionScheduleStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'profile' => $profile,
                'schedules' => $schedules,
                'sectionTimeSlots' => $sectionTimeSlots,
                'sectionSchedules' => $sectionSchedules
            ]);
        } else {
            echo json_encode(['error' => 'Teacher ID or teacherSectionID is required.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method.']);
}
?>