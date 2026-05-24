<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';

// Validate session
if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php");
    exit;
}
$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

// Get user details
try {
    $stmt = $dbConnection->prepare("SELECT firstName, userType, image FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
    } else {
        header("Location: ../../index.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred.";
    header("Location: professorDash.php");
    exit;
}

// Get parameters
$teacherSectionID = isset($_GET['teacherSectionID']) ? filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT) : 0;
$sectionID = isset($_GET['sectionID']) ? filter_var($_GET['sectionID'], FILTER_VALIDATE_INT) : 0;
$subjectID = isset($_GET['subjectID']) ? filter_var($_GET['subjectID'], FILTER_VALIDATE_INT) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'record';
$attendanceDate = isset($_GET['attendanceDate']) ? filter_var($_GET['attendanceDate'], FILTER_SANITIZE_STRING) : date('Y-m-d');
$selectedYear = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : 2025;

// Validate teacherSectionID
if (!$teacherSectionID) {
    $_SESSION['error_message'] = "Invalid teacher section ID.";
    header("Location: professorDash.php");
    exit;
}

// Verify authorization
$checkStmt = $dbConnection->prepare("SELECT sectionID, subjectID FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND teacherID = :userID AND archived = 0");
$checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
$checkStmt->execute();
$sectionData = $checkStmt->fetch(PDO::FETCH_ASSOC);
error_log("Authorization Check - teacherSectionID: $teacherSectionID, userID: $userID, rowCount: " . $checkStmt->rowCount());
if (!$sectionData) {
    error_log("Authorization failed for teacherSectionID: $teacherSectionID, userID: $userID");
    $_SESSION['error_message'] = "Invalid assignment or not authorized.";
    header("Location: professorDash.php");
    exit;
}
$sectionID = $sectionData['sectionID'];
$subjectID = $sectionData['subjectID'];

// Fetch section and subject names
$sectionStmt = $dbConnection->prepare("SELECT sectionName FROM sections WHERE sectionID = :sectionID AND archived = 0");
$sectionStmt->bindParam(':sectionID', $sectionID, PDO::PARAM_INT);
$sectionStmt->execute();
$sectionData = $sectionStmt->fetch(PDO::FETCH_ASSOC);
$sectionName = $sectionData ? $sectionData['sectionName'] : 'Unknown Section';

$subjectStmt = $dbConnection->prepare("SELECT subjectName FROM subjects WHERE subjectID = :subjectID AND archived = 0");
$subjectStmt->bindParam(':subjectID', $subjectID, PDO::PARAM_INT);
$subjectStmt->execute();
$subjectData = $subjectStmt->fetch(PDO::FETCH_ASSOC);
$subjectName = $subjectData ? $subjectData['subjectName'] : 'Unknown Subject';

// Handle attendance submission
if (isset($_POST['submitAttendance'])) {
    $submittedDate = filter_var($_POST['attendanceDate'], FILTER_SANITIZE_STRING);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $submittedDate)) {
        $_SESSION['error_message'] = "Invalid attendance date format.";
        $redirectUrl = "attendance.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&subjectID=$subjectID&action=record&year=$selectedYear";
        header("Location: $redirectUrl");
        exit;
    }
    $attendanceStatuses = isset($_POST['attendance']) ? $_POST['attendance'] : [];
    
    $checkDateStmt = $dbConnection->prepare("SELECT a.studentID, a.status, CONCAT(u.firstName, ' ', u.lastName) AS studentName, u.image
                                            FROM attendance a
                                            JOIN users u ON a.studentID = u.userID
                                            WHERE a.teacherSectionID = :teacherSectionID AND a.attendanceDate = :attendanceDate AND a.archived = 0");
    $checkDateStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkDateStmt->bindParam(':attendanceDate', $submittedDate);
    $checkDateStmt->execute();
    $existingRecords = $checkDateStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($existingRecords)) {
        $success = true;
        foreach ($attendanceStatuses as $studentID => $status) {
            if (!in_array($status, ['Present', 'Absent', 'Late'])) {
                continue;
            }
            $studentID = filter_var($studentID, FILTER_VALIDATE_INT);
            if ($studentID) {
                $insertStmt = $dbConnection->prepare("INSERT INTO attendance (studentID, teacherSectionID, attendanceDate, status) VALUES (:studentID, :teacherSectionID, :attendanceDate, :status)");
                $insertStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                $insertStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                $insertStmt->bindParam(':attendanceDate', $submittedDate);
                $insertStmt->bindParam(':status', $status);
                if (!$insertStmt->execute()) {
                    $success = false;
                }
            }
        }
        if ($success) {
            $readableDate = date("F j, Y", strtotime($submittedDate));
            $_SESSION['success_message'] = "Attendance recorded successfully for " . htmlspecialchars($readableDate) . ".";
        } else {
            $_SESSION['error_message'] = "Failed to record some attendance entries.";
        }
        $redirectUrl = "attendance.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&subjectID=$subjectID&action=record&year=$selectedYear";
        header("Location: $redirectUrl");
        exit;
    } else {
        $_SESSION['pendingAttendance'] = [
            'date' => $submittedDate,
            'statuses' => $attendanceStatuses,
            'teacherSectionID' => $teacherSectionID
        ];
        $redirectUrl = "attendance.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&subjectID=$subjectID&action=record&attendanceDate=$submittedDate&showComparison=true";
        header("Location: $redirectUrl");
        exit;
    }
}

// Handle attendance replacement
if (isset($_POST['replaceAttendance'])) {
    $submittedDate = $_POST['attendanceDate'];
    $attendanceStatuses = isset($_POST['attendance']) ? $_POST['attendance'] : [];
    
    $checkStmt = $dbConnection->prepare("SELECT * FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND teacherID = :userID AND archived = 0");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $deleteStmt = $dbConnection->prepare("DELETE FROM attendance WHERE teacherSectionID = :teacherSectionID AND attendanceDate = :attendanceDate AND archived = 0");
        $deleteStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $deleteStmt->bindParam(':attendanceDate', $submittedDate);
        $deleteStmt->execute();
        
        $success = true;
        foreach ($attendanceStatuses as $studentID => $status) {
            if (!in_array($status, ['Present', 'Absent', 'Late'])) {
                continue;
            }
            $studentID = filter_var($studentID, FILTER_VALIDATE_INT);
            if ($studentID) {
                $insertStmt = $dbConnection->prepare("INSERT INTO attendance (studentID, teacherSectionID, attendanceDate, status) VALUES (:studentID, :teacherSectionID, :attendanceDate, :status)");
                $insertStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                $insertStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                $insertStmt->bindParam(':attendanceDate', $submittedDate);
                $insertStmt->bindParam(':status', $status);
                if (!$insertStmt->execute()) {
                    $success = false;
                }
            }
        }
        if ($success) {
            $readableDate = date("F j, Y", strtotime($submittedDate));
            $_SESSION['success_message'] = "Attendance replaced successfully for " . htmlspecialchars($readableDate) . ".";

        } else {
            $_SESSION['error_message'] = "Failed to replace some attendance entries.";
        }
        unset($_SESSION['pendingAttendance']);
    } else {
        $_SESSION['error_message'] = "Invalid assignment.";
    }
    $year = date("Y");
    $redirectUrl = "attendance.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&subjectID=$subjectID&action=record&year=$year";
    header("Location: $redirectUrl");
    exit;
}

// Handle attendance update (for edit mode)
if (isset($_POST['updateAttendance'])) {
    $attendanceStatuses = isset($_POST['attendance']) ? $_POST['attendance'] : [];
    
    $checkStmt = $dbConnection->prepare("SELECT * FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND teacherID = :userID AND archived = 0");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $success = true;
        foreach ($attendanceStatuses as $studentID => $statuses) {
            $studentID = filter_var($studentID, FILTER_VALIDATE_INT);
            foreach ($statuses as $date => $status) {
                if (!in_array($status, ['Present', 'Absent', 'Late', '-'])) {
                    continue;
                }
                if ($studentID && $status !== '-') {
                    $checkAttendanceStmt = $dbConnection->prepare("SELECT attendanceID FROM attendance WHERE studentID = :studentID AND teacherSectionID = :teacherSectionID AND attendanceDate = :attendanceDate AND archived = 0");
                    $checkAttendanceStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                    $checkAttendanceStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                    $checkAttendanceStmt->bindParam(':attendanceDate', $date);
                    $checkAttendanceStmt->execute();
                    
                    if ($checkAttendanceStmt->rowCount() > 0) {
                        $updateStmt = $dbConnection->prepare("UPDATE attendance SET status = :status WHERE studentID = :studentID AND teacherSectionID = :teacherSectionID AND attendanceDate = :attendanceDate AND archived = 0");
                        $updateStmt->bindParam(':status', $status);
                        $updateStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                        $updateStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                        $updateStmt->bindParam(':attendanceDate', $date);
                        if (!$updateStmt->execute()) {
                            $success = false;
                        }
                    } else {
                        $insertStmt = $dbConnection->prepare("INSERT INTO attendance (studentID, teacherSectionID, attendanceDate, status) VALUES (:studentID, :teacherSectionID, :attendanceDate, :status)");
                        $insertStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                        $insertStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                        $insertStmt->bindParam(':attendanceDate', $date);
                        $insertStmt->bindParam(':status', $status);
                        if (!$insertStmt->execute()) {
                            $success = false;
                        }
                    }
                } elseif ($studentID && $status === '-') {
                    $deleteStmt = $dbConnection->prepare("DELETE FROM attendance WHERE studentID = :studentID AND teacherSectionID = :teacherSectionID AND attendanceDate = :attendanceDate AND archived = 0");
                    $deleteStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                    $deleteStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                    $deleteStmt->bindParam(':attendanceDate', $date);
                    $deleteStmt->execute();
                }
            }
        }
        if ($success) {
            $_SESSION['success_message'] = "Attendance updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update some attendance entries.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid assignment.";
    }
    $year = date("Y");
    $redirectUrl = "attendance.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&subjectID=$subjectID&action=edit&year=$year";
    header("Location: $redirectUrl");
    exit;
}

// Handle archive attendance
if (isset($_POST['archiveAttendance'])) {
    $archiveRecords = isset($_POST['archive']) ? $_POST['archive'] : [];
    
    $checkStmt = $dbConnection->prepare("SELECT * FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND teacherID = :userID AND archived = 0");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $success = true;
        foreach ($archiveRecords as $studentID => $dates) {
            $studentID = filter_var($studentID, FILTER_VALIDATE_INT);
            foreach ($dates as $date => $value) {
                if ($value === '1' && $studentID) {
                    $updateStmt = $dbConnection->prepare("UPDATE attendance SET archived = 1 WHERE studentID = :studentID AND teacherSectionID = :teacherSectionID AND attendanceDate = :attendanceDate AND archived = 0");
                    $updateStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                    $updateStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                    $updateStmt->bindParam(':attendanceDate', $date);
                    if (!$updateStmt->execute()) {
                        $success = false;
                    }
                }
            }
        }
        if ($success) {
            $_SESSION['success_message'] = "Selected attendance records archived successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to archive some attendance records.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid assignment.";
    }
    $redirectUrl = "attendance.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&subjectID=$subjectID&action=archive&year=$selectedYear";
    header("Location: $redirectUrl");
    exit;
}

// Fetch students
$studentStmt = $dbConnection->prepare("SELECT u.userID, CONCAT(u.firstName, ' ', u.lastName) AS studentName, u.image
                                       FROM student_section ss
                                       JOIN users u ON ss.userID = u.userID
                                       WHERE ss.sectionID = :sectionID AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
                                       ORDER BY u.firstName");
$studentStmt->bindParam(':sectionID', $sectionID, PDO::PARAM_INT);
$studentStmt->execute();
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    $_SESSION['error_message'] = "No students enrolled in this section.";
    header("Location: professorDash.php?sectionID=$sectionID&subjectID=$subjectID");
    exit;
}

// Fetch attendance records for View Record, Archive, and Analytics tabs
$attendanceSQL = "SELECT a.studentID, a.attendanceDate, a.status
                 FROM attendance a
                 WHERE a.teacherSectionID = :teacherSectionID AND a.archived = 0
                 AND YEAR(a.attendanceDate) = :selectedYear
                 ORDER BY a.attendanceDate DESC";
$attendanceStmt = $dbConnection->prepare($attendanceSQL);
$attendanceStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$attendanceStmt->bindParam(':selectedYear', $selectedYear, PDO::PARAM_INT);
$attendanceStmt->execute();
$attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals for View Record tab
$attendanceTotals = [];
foreach ($students as $student) {
    $attendanceTotals[$student['userID']] = [
        'present' => 0,
        'late' => 0,
        'absent' => 0
    ];
}
foreach ($attendanceRecords as $record) {
    if (isset($attendanceTotals[$record['studentID']])) {
        if ($record['status'] === 'Present') {
            $attendanceTotals[$record['studentID']]['present']++;
        } elseif ($record['status'] === 'Late') {
            $attendanceTotals[$record['studentID']]['late']++;
        } elseif ($record['status'] === 'Absent') {
            $attendanceTotals[$record['studentID']]['absent']++;
        }
    }
}

// Fetch available years for filter
$yearStmt = $dbConnection->prepare("SELECT DISTINCT YEAR(attendanceDate) AS year FROM attendance WHERE teacherSectionID = :teacherSectionID AND archived = 0 ORDER BY year DESC");
$yearStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$yearStmt->execute();
$availableYears = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($availableYears)) {
    $availableYears = [2025];
}

// Build attendance matrix for View Record, Edit, and Archive
$dateStmt = $dbConnection->prepare("SELECT DISTINCT attendanceDate FROM attendance WHERE teacherSectionID = :teacherSectionID AND YEAR(attendanceDate) = :selectedYear AND archived = 0 ORDER BY attendanceDate DESC");
$dateStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$dateStmt->bindParam(':selectedYear', $selectedYear, PDO::PARAM_INT);
$dateStmt->execute();
$attendanceDates = $dateStmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($attendanceDates) && $action !== 'record' && $action !== 'analytics') {
    $attendanceDates = [date('Y-m-d')];
}
$attendanceMatrix = [];
foreach ($students as $student) {
    $attendanceMatrix[$student['userID']] = [
        'studentName' => ucwords($student['studentName']),
        'image' => $student['image'] ? htmlspecialchars($student['image']) : './img/noprofile.png',
        'records' => array_fill_keys($attendanceDates, '-')
    ];
}
foreach ($attendanceRecords as $record) {
    if (isset($attendanceMatrix[$record['studentID']])) {
        $statusShort = $record['status'] === 'Present' ? 'P' : ($record['status'] === 'Absent' ? 'A' : 'L');
        $attendanceMatrix[$record['studentID']]['records'][$record['attendanceDate']] = $statusShort;
    }
}

// Analytics: Attendance Rate by Student
$attendanceRateStmt = $dbConnection->prepare("
    SELECT 
        a.studentID, 
        CONCAT(u.firstName, ' ', u.lastName) AS studentName,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
        COUNT(a.status) AS total_count
    FROM attendance a
    JOIN users u ON a.studentID = u.userID
    WHERE a.teacherSectionID = :teacherSectionID 
    AND a.archived = 0 
    AND YEAR(a.attendanceDate) = :selectedYear
    GROUP BY a.studentID
    ORDER BY u.firstName
");
$attendanceRateStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$attendanceRateStmt->bindParam(':selectedYear', $selectedYear, PDO::PARAM_INT);
$attendanceRateStmt->execute();
$attendanceRates = $attendanceRateStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js
$studentNames = [];
$attendancePercentages = [];
$latePercentages = [];
$absentPercentages = [];
foreach ($attendanceRates as $rate) {
    $studentNames[] = htmlspecialchars(ucwords($rate['studentName']));
    $total = $rate['total_count'];
    $present = $rate['present_count'];
    $late = $rate['late_count'];
    $absent = $rate['absent_count'];
    // Calculate attendance rate as (Present + Late) / Total
    $attendancePercentage = $total > 0 ? round((($present + $late) / $total) * 100, 2) : 0;
    $latePercentage = $total > 0 ? round(($late / $total) * 100, 2) : 0;
    $absentPercentage = $total > 0 ? round(($absent / $total) * 100, 2) : 0;
    $attendancePercentages[] = $attendancePercentage;
    $latePercentages[] = $latePercentage;
    $absentPercentages[] = $absentPercentage;
}

// Analytics: Status Distribution by Date
$statusDistributionStmt = $dbConnection->prepare("
    SELECT 
        a.attendanceDate,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
    FROM attendance a
    WHERE a.teacherSectionID = :teacherSectionID 
    AND a.archived = 0 
    AND YEAR(a.attendanceDate) = :selectedYear
    GROUP BY a.attendanceDate
    ORDER BY a.attendanceDate ASC
");
$statusDistributionStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$statusDistributionStmt->bindParam(':selectedYear', $selectedYear, PDO::PARAM_INT);
$statusDistributionStmt->execute();
$statusDistribution = $statusDistributionStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js
$studentNames = [];
$attendancePercentages = [];
foreach ($attendanceRates as $rate) {
    $studentNames[] = htmlspecialchars(ucwords($rate['studentName']));
    $total = $rate['total_count'];
    $present = $rate['present_count'];
    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;
    $attendancePercentages[] = $percentage;
}

$dates = [];
$presentCounts = [];
$lateCounts = [];
$absentCounts = [];
foreach ($statusDistribution as $dist) {
    $dates[] = date('M j, Y', strtotime($dist['attendanceDate']));
    $presentCounts[] = (int)$dist['present_count'];
    $lateCounts[] = (int)$dist['late_count'];
    $absentCounts[] = (int)$dist['absent_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Attendance Management</title>
    <style>
        :root {
            --poppins: 'Poppins', sans-serif;
            --lato: 'Lato', sans-serif;
            --light: #F9F9F9;
            --blue: #3C91E6;
            --light-blue: #CFE8FF;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --red: #DB504A;
            --light-red: #F5A5A0;
            --yellow: #FFCE26;
            --light-yellow: #FFF2C6;
            --green: #28A745;
            --light-green: #A9D08D;
            --hover: #d5d5d5;
            --secondary-hover: #e0e0e0;
            --secondary-grey: #f5f5f5;
        }

        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }

        .attendance-container {
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .attendance-title {
            font-size: clamp(1.2rem, 3vw, 2.5rem);
            color: var(--dark);
            font-family: var(--poppins);
        }

        .back-btn {
            color: var(--blue);
            text-decoration: none;
            font-weight: 500;
            font-family: var(--poppins);
            transition: color 0.3s;
        }

        .back-btn:hover {
            color: var(--dark);
        }

        .success-notification, .error-notification {
            padding: 10px;
            margin-bottom: 20px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            border-radius: 4px;
            text-align: center;
            font-family: var(--poppins);
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid var(--grey);
            margin-bottom: 20px;
            justify-content: center;
            gap: 10px;
        }

        .tab-button {
            padding: 10px 20px;
            background: var(--grey);
            border: none;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--dark);
            font-family: var(--poppins);
            transition: background 0.3s, color 0.3s;
            max-width: 300px;
            width: 100%;
        }

        .tab-button.active {
            color: #007bff;
            border-bottom: 3px solid #007bff;
        }

        .tab-button:hover {
            background: #0056b3;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .date-filter, .year-filter {
            display: flex;
            align-items: center;
            font-size: clamp(1rem, 3vw, 1.2rem);
            gap: 10px;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--dark);
            font-family: var(--poppins);
        }

        .form-input-attendance, .form-select {
            padding: 8px;
            border: 1px solid #ccc;
            background-color: var(--grey);
            border-radius: 4px;
            color: var(--dark);
            margin-bottom: 10px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-family: var(--poppins);
            transition: border-color 0.3s;
        }

        .form-input-attendance:focus, .form-select:focus {
            border-color: var(--blue);
            outline: none;
        }

        #attendanceDate::-webkit-calendar-picker-indicator,
        #editAttendanceDate::-webkit-calendar-picker-indicator {
            background-color: #FFCE26;
            padding: 6px;
            border-radius: 50%;
            cursor: pointer;
        }

        #attendanceDate::-webkit-calendar-picker-indicator:hover,
        #editAttendanceDate::-webkit-calendar-picker-indicator:hover {
            background-color: #FFA500;
        }

        .filter-btn, .clear-btn, .submit-btn, .cancel-btn, .confirm-btn, .replace-btn, .archive-btn, .download-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: var(--poppins);
            transition: background 0.3s;
        }

        .submit-btn,
        .filter-btn,
        .clear-btn,
        .confirm-btn,
        .archive-btn,
        .download-btn {
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .filter-btn, .submit-btn, .confirm-btn, .replace-btn, .archive-btn, .download-btn {
            background: #007bff;
            color: white;
        }

        .filter-btn:hover, .submit-btn:hover, .confirm-btn:hover, .replace-btn:hover, .archive-btn:hover, .download-btn:hover {
            background: #0056b3;
        }

        .clear-btn, .cancel-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .clear-btn:hover, .cancel-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            margin-top: 5px;
            font-family: var(--poppins);
        }

        .attendance-table th, .attendance-table td {
            padding: 12px;
            text-align: left;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .attendance-table th {
            background: var(--blue);
            color: white;
            font-weight: 600;
        }

        .attendance-table td {
            color: var(--dark);
        }

        .attendance-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .attendance-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-image {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        .status-checkbox, .archive-checkbox, .check-all {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            accent-color: var(--blue);
            vertical-align: middle;
            cursor: pointer;
            transition: box-shadow 0.2s;
        }

        .status-checkbox:hover, .archive-checkbox:hover, .check-all:hover {
            box-shadow: 0 0 8px rgba(60, 145, 230, 0.5);
        }

        .status-select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            background-color: var(--grey);
            color: var(--dark);
            cursor: pointer;
            font-family: var(--poppins);
            transition: border-color 0.3s;
        }

        .status-select:focus {
            border-color: var(--blue);
            outline: none;
        }

        .status-cell.present {
            background: var(--green);
            color: white;
            text-align: center;
            border: 1px solid var(--green);
        }

        .status-cell.late {
            background: var(--yellow);
            color: white;
            text-align: center;
            border: 1px solid var(--yellow);
        }

        .status-cell.absent {
            background: var(--red);
            color: white;
            text-align: center;
            border: 1px solid var(--red);
        }

        .totals-cell {
            text-align: center;
            font-weight: 500;
            color: var(--dark);
        }

        .numbering-cell {
            text-align: center;
            width: 50px;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            font-family: var(--poppins);
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--dark);
        }

        .legend-color {
            width: 20px;
            height: 20px;
            display: inline-block;
            margin-right: 8px;
            border-radius: 4px;
        }

        .legend-color.present {
            background: var(--green);
        }

        .legend-color.late {
            background: var(--yellow);
        }

        .legend-color.absent {
            background: var(--red);
        }

        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-family: var(--poppins);
        }

        .comparison-table th, .comparison-table td {
            padding: 10px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--dark);
            text-align: center;
        }

        .comparison-table th {
            background: #007bff;
            color: white;
        }

        .comparison-table tr:nth-child(even) {
            background-color: var(--grey);
        }

        .comparison-table tr:hover {
            background: linear-gradient(135deg, var(--light), gray);
        }

        .overflow-x-auto {
            overflow-x: auto;
        }

        .chart-container {
            max-width: 800px;
            width: 100%;
            margin: 20px auto;
            padding: 10px;
            max-height: 80vh;
            height: 60vh;
            border: 1px solid #ccc;
            background: var(--grey);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .analytics-report-container {
            background: var(--light);
            border: 2px solid #ccc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow-x: auto;
        }

        .analytics-report-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .analytics-report-header h1 {
            color: var(--dark);
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            border-bottom: 1px solid var(--dark);
            margin: 0;
            font-family: var(--poppins);
        }

        .analytics-report-content h2 {
            color: var(--dark);
            font-size: 20px;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: clamp(1.2rem, 3vw, 2.3rem);
            font-family: var(--poppins);
        }

        .analytics-report-footer {
            text-align: center;
            font-size: 12px;
            color: var(--dark-grey);
            margin-top: 20px;
            font-size: clamp(1rem, 3vw, 1.5rem);
            padding-top: 10px;
            border-top: 1px solid var(--grey);
            font-family: var(--poppins);
        }

        @media print {
            .download-btn-container {
                display: none;
            }

            .analytics-report-container {
                box-shadow: none;
                margin: 0;
            }

            .chart-container {
                max-width: 100%;
            }
        }

        .modal #confirmModal,
        .modal #comparisonModal {
            max-width: 600px;
            width: 100%;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .modal #comparisonModal {
            max-height: 80vh;
            overflow-y: auto;
        }

        @media screen and (max-width: 460px) {
            .tabs {
                flex-direction: column;
            }
            .date-filter {
                display: flex;
                flex-direction: column;
            }
            .legend {
                display: flex;
                flex-direction: column;
            }
            .attendance-table table {
                min-width: 600px;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 0.5rem;
            }
            .status-checkbox, .archive-checkbox, .check-all {
                width: 15px;
                height: 15px;
            }
        }

        .attendance-form {
            overflow-x: auto;
        }

        .back-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .back-button:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1A4971);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .back-button i {
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
             --transition: all 0.3s ease;
             --white: #FFFFFF;
             --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>
    <body class="google-classroom-style">
        
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li class="active">
                <a href="./professor_main_dash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li>
                <a href="./message_admin.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Admin</span>
                </a>
            </li>
            <li>
                <a href="./game_controller.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Game</span>
                </a>
            </li>
        </ul>
        <ul class="side-menu">
            <li>
                <a href="./settings.php">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0);" class="logout" onclick="showLogoutModal()">
                    <i class='bx bxs-log-out-circle'></i>
                    <span class="text">Logout</span>
                </a>
            </li>
        </ul>
    </section>

    <?php require_once './view/modal.php' ?>

    <section id="content">
        <nav>
            <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users..." required aria-label="Search">
                    <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile" aria-label="Profile">
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small>Teacher</small>
                </div>
            </a>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Attendance Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Attendance</a></li>
                    </ul>
                </div>
                <a href="professorDash.php?sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Teacher Management
                </a>
            </div>

            <div class="attendance-container">
                <div class="attendance-header">
                    <div>
                        <h1 class="attendance-title"><?php echo htmlspecialchars($sectionName . ' - ' . $subjectName); ?></h1>
                    </div>
                    <!-- <a href="professorDash.php?sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>" class="back-btn">
                        <i class='bx bx-arrow-back'></i> Back to Teacher Management
                    </a> -->
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-notification"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="error-notification"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <!-- Tabs Navigation -->
                <div class="tabs">
                    <button class="tab-button <?php echo $action === 'record' ? 'active' : ''; ?>" data-tab="record"><i class='bx bxs-plus-circle'></i> Add</button>
                    <button class="tab-button <?php echo $action === 'edit' ? 'active' : ''; ?>" data-tab="edit"><i class='bx bxs-edit'></i> Edit</button>
                    <button class="tab-button <?php echo $action === 'view' ? 'active' : ''; ?>" data-tab="view"><i class='bx bxs-folder'></i> View Record</button>
                    <button class="tab-button <?php echo $action === 'archive' ? 'active' : ''; ?>" data-tab="archive"><i class='bx bxs-archive'></i> Archive</button>
                    <button class="tab-button <?php echo $action === 'analytics' ? 'active' : ''; ?>" data-tab="analytics"><i class='bx bxs-bar-chart-alt-2'></i> Analytics</button>
                     <a href="attendance_rate.php?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>&year=<?php echo $selectedYear; ?>" class="tab-button"><i class='bx bxs-pie-chart-alt-2'></i> Attendance Rate</a>
                </div>

                <!-- Add Attendance Tab -->
                <div class="tab-content <?php echo $action === 'record' ? 'active' : ''; ?>" id="record">
                    <form method="POST" class="attendance-form" id="recordAttendanceForm">
                        <input type="hidden" name="teacherSectionID" value="<?php echo $teacherSectionID; ?>">
                        <label for="attendanceDate" class="form-label">Date:</label>
                        <input type="date" name="attendanceDate" id="attendanceDate" value="<?php echo htmlspecialchars($attendanceDate); ?>" required aria-label="Attendance Date" class="form-input-attendance">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Present</th>
                                    <th>Late</th>
                                    <th>Absent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rowNumber = 1; ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="numbering-cell"><?php echo $rowNumber++; ?></td>
                                        <td>
                                            <div class="student-info">
                                                <img src="<?php echo $student['image'] ? htmlspecialchars($student['image']) : './img/noprofile.png'; ?>" alt="Student Image" class="student-image">
                                                <?php echo htmlspecialchars(ucwords($student['studentName'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="checkbox" name="attendance[<?php echo $student['userID']; ?>]" value="Present" class="status-checkbox" data-status="Present" checked onchange="handleCheckbox(this, <?php echo $student['userID']; ?>)">
                                        </td>
                                        <td>
                                            <input type="checkbox" name="attendance[<?php echo $student['userID']; ?>]" value="Late" class="status-checkbox" data-status="Late" onchange="handleCheckbox(this, <?php echo $student['userID']; ?>)">
                                        </td>
                                        <td>
                                            <input type="checkbox" name="attendance[<?php echo $student['userID']; ?>]" value="Absent" class="status-checkbox" data-status="Absent" onchange="handleCheckbox(this, <?php echo $student['userID']; ?>)">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="submit-btn" onclick="checkAttendance()">Submit Attendance</button>
                        <input type="hidden" name="submitAttendance" value="true">
                    </form>
                </div>

                <!-- Edit Attendance Tab -->
                <div class="tab-content <?php echo $action === 'edit' ? 'active' : ''; ?>" id="edit">
                    <form class="date-filter" method="GET">
                        <input type="hidden" name="teacherSectionID" value="<?php echo $teacherSectionID; ?>">
                        <input type="hidden" name="sectionID" value="<?php echo $sectionID; ?>">
                        <input type="hidden" name="subjectID" value="<?php echo $subjectID; ?>">
                        <input type="hidden" name="action" value="edit">
                        <label for="editAttendanceDate" class="form-label">Filter by Date:</label>
                        <input type="date" name="attendanceDate" id="editAttendanceDate" value="<?php echo htmlspecialchars($attendanceDate); ?>" aria-label="Filter Attendance Date" class="form-input-attendance">
                        <button type="submit" class="filter-btn">Filter</button>
                        <?php if ($attendanceDate): ?>
                            <a href="attendance.php?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>&action=edit" class="clear-btn">Clear</a>
                        <?php endif; ?>
                    </form>
                    <form method="POST" class="attendance-form" id="editAttendanceForm">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <?php foreach ($attendanceDates as $date): ?>
                                        <th><?php echo date('M j, Y', strtotime($date)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rowNumber = 1; ?>
                                <?php foreach ($attendanceMatrix as $studentID => $data): ?>
                                    <tr>
                                        <td class="numbering-cell"><?php echo $rowNumber++; ?></td>
                                        <td>
                                            <div class="student-info">
                                                <img src="<?php echo $data['image']; ?>" alt="Student Image" class="student-image">
                                                <?php echo htmlspecialchars($data['studentName']); ?>
                                            </div>
                                        </td>
                                        <?php foreach ($data['records'] as $date => $status): ?>
                                            <td>
                                                <select name="attendance[<?php echo $studentID; ?>][<?php echo $date; ?>]" class="status-select">
                                                    <option disabled selected value="- Select Status -" <?php echo $status === '-' ? 'selected' : ''; ?>>-</option>
                                                    <option value="Present" <?php echo $status === 'P' ? 'selected' : ''; ?>>Present</option>
                                                    <option value="Absent" <?php echo $status === 'A' ? 'selected' : ''; ?>>Absent</option>
                                                    <option value="Late" <?php echo $status === 'L' ? 'selected' : ''; ?>>Late</option>
                                                </select>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="submit-btn" onclick="showConfirmModal('updateAttendance')">
                            <i class='bx bx-save'></i> Update Attendance
                        </button>
                        <input type="hidden" name="updateAttendance" value="true">
                    </form>
                </div>

                <!-- View Record Tab -->
                <div class="tab-content <?php echo $action === 'view' ? 'active' : ''; ?>" id="view">
                    <form class="year-filter" method="GET">
                        <input type="hidden" name="teacherSectionID" value="<?php echo $teacherSectionID; ?>">
                        <input type="hidden" name="sectionID" value="<?php echo $sectionID; ?>">
                        <input type="hidden" name="subjectID" value="<?php echo $subjectID; ?>">
                        <input type="hidden" name="action" value="view">
                        <label for="yearFilter" class="form-label">Filter by Year:</label>
                        <select name="year" id="yearFilter" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selectedYear != 2025): ?>
                            <a href="attendance.php?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>&action=view" class="clear-btn">Reset to 2025</a>
                        <?php endif; ?>
                    </form>
                    <div class="legend">
                        <span class="legend-item"><span class="legend-color present"></span> Present</span>
                        <span class="legend-item"><span class="legend-color late"></span> Late</span>
                        <span class="legend-item"><span class="legend-color absent"></span> Absent</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <?php foreach ($attendanceDates as $date): ?>
                                        <th><?php echo date('M j, Y', strtotime($date)); ?></th>
                                    <?php endforeach; ?>
                                    <th>Totals</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rowNumber = 1; ?>
                                <?php foreach ($attendanceMatrix as $studentID => $data): ?>
                                    <tr>
                                        <td class="numbering-cell"><?php echo $rowNumber++; ?></td>
                                        <td>
                                            <div class="student-info">
                                                <img src="<?php echo $data['image']; ?>" alt="Student Image" class="student-image">
                                                <?php echo htmlspecialchars($data['studentName']); ?>
                                            </div>
                                        </td>
                                        <?php foreach ($data['records'] as $date => $status): ?>
                                            <td class="status-cell <?php echo $status === 'P' ? 'present' : ($status === 'L' ? 'late' : ($status === 'A' ? 'absent' : '')); ?>">
                                                <?php echo $status === '-' ? '-' : $status; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="totals-cell">
                                            <?php
                                            $totals = $attendanceTotals[$studentID];
                                            echo "{$totals['present']} Present, {$totals['late']} Late, {$totals['absent']} Absent";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Archive Records Tab -->
                <div class="tab-content <?php echo $action === 'archive' ? 'active' : ''; ?>" id="archive">
                    <form class="year-filter" method="GET">
                        <input type="hidden" name="teacherSectionID" value="<?php echo $teacherSectionID; ?>">
                        <input type="hidden" name="sectionID" value="<?php echo $sectionID; ?>">
                        <input type="hidden" name="subjectID" value="<?php echo $subjectID; ?>">
                        <input type="hidden" name="action" value="archive">
                        <label for="archiveYearFilter" class="form-label">Filter by Year:</label>
                        <select name="year" id="archiveYearFilter" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selectedYear != 2025): ?>
                            <a href="attendance.php?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>&action=archive" class="clear-btn">Reset to 2025</a>
                        <?php endif; ?>
                    </form>
                    <div class="legend">
                        <span class="legend-item"><span class="legend-color present"></span> Present</span>
                        <span class="legend-item"><span class="legend-color late"></span> Late</span>
                        <span class="legend-item"><span class="legend-color absent"></span> Absent</span>
                    </div>
                    <form method="POST" class="attendance-form" id="archiveAttendanceForm">
                        <div class="overflow-x-auto">
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student</th>
                                        <?php foreach ($attendanceDates as $date): ?>
                                            <th>
                                                <input type="checkbox" class="check-all" data-date="<?php echo htmlspecialchars($date); ?>">
                                                <?php echo date('M j, Y', strtotime($date)); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rowNumber = 1; ?>
                                    <?php foreach ($attendanceMatrix as $studentID => $data): ?>
                                        <tr>
                                            <td class="numbering-cell"><?php echo $rowNumber++; ?></td>
                                            <td>
                                                <div class="student-info">
                                                    <img src="<?php echo $data['image']; ?>" alt="Student Image" class="student-image">
                                                    <?php echo htmlspecialchars($data['studentName']); ?>
                                                </div>
                                            </td>
                                            <?php foreach ($data['records'] as $date => $status): ?>
                                                <td class="status-cell <?php echo $status === 'P' ? 'present' : ($status === 'L' ? 'late' : ($status === 'A' ? 'absent' : '')); ?>">
                                                    <?php if ($status !== '-'): ?>
                                                        <input type="checkbox" name="archive[<?php echo $studentID; ?>][<?php echo $date; ?>]" value="1" class="archive-checkbox" data-date="<?php echo htmlspecialchars($date); ?>">
                                                        <?php echo $status; ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="archive-btn" onclick="showConfirmModal('archiveAttendance')">
                            <i class='bx bx-archive'></i> Confirm Archive
                        </button>
                        <input type="hidden" name="archiveAttendance" value="true">
                    </form>
                </div>

                <!-- Analytics Tab -->
                <div class="tab-content <?php echo $action === 'analytics' ? 'active' : ''; ?>" id="analytics">
                    <form class="year-filter" method="GET">
                        <input type="hidden" name="teacherSectionID" value="<?php echo $teacherSectionID; ?>">
                        <input type="hidden" name="sectionID" value="<?php echo $sectionID; ?>">
                        <input type="hidden" name="subjectID" value="<?php echo $subjectID; ?>">
                        <input type="hidden" name="action" value="analytics">
                        <label for="analyticsYearFilter" class="form-label">Filter by Year:</label>
                        <select name="year" id="analyticsYearFilter" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selectedYear != 2025): ?>
                            <a href="attendance.php?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>&action=analytics" class="clear-btn">Reset to 2025</a>
                        <?php endif; ?>
                    </form>
                    <div class="analytics-report-container" id="analytics-report">
                        <div class="analytics-report-header">
                            <h1>Learnify Attendance Analytics Report</h1>
                        </div>
                        <div class="analytics-report-content">
                            <h2>Attendance Rate by Student (<?php echo $selectedYear; ?>)</h2>
                            <div class="legend">
                                <span class="legend-item"><span class="legend-color present"></span> Present</span>
                                <span class="legend-item"><span class="legend-color late"></span> Late</span>
                                <span class="legend-item"><span class="legend-color absent"></span> Absent</span>
                            </div>
                            <div id="studentPieCharts" style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: center;">
                                <?php foreach ($attendanceRates as $index => $rate): ?>
                                    <?php
                                    $total = $rate['total_count'];
                                    if ($total > 0) { // Only include students with non-zero attendance
                                    ?>
                                        <div style="width: 300px; text-align: center;">
                                            <h3><?php echo htmlspecialchars(ucwords($rate['studentName'])); ?></h3>
                                            <div class="chart-container" style="width: 100%; height: 250px;">
                                                <canvas id="pieChart_<?php echo $rate['studentID']; ?>"></canvas>
                                            </div>
                                        </div>
                                    <?php } ?>
                                <?php endforeach; ?>
                            </div>
                            <h2>Status Distribution by Date (<?php echo $selectedYear; ?>)</h2>
                            <div class="chart-container">
                                <canvas id="statusDistributionChart" height="400"></canvas>
                            </div>
                            <h2>Attendance Statistics by Student</h2>
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student</th>
                                        <th>Present (%)</th>
                                        <th>Late (%)</th>
                                        <th>Absent (%)</th>
                                        <th>Attendance Rate (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rowNumber = 1; ?>
                                    <?php foreach ($attendanceRates as $index => $rate): ?>
                                        <tr>
                                            <td class="numbering-cell"><?php echo $rowNumber++; ?></td>
                                            <td>
                                                <div class="student-info">
                                                    <img src="<?php echo $students[array_search($rate['studentID'], array_column($students, 'userID'))]['image'] ?: './img/noprofile.png'; ?>" alt="Student Image" class="student-image">
                                                    <?php echo htmlspecialchars(ucwords($rate['studentName'])); ?>
                                                </div>
                                            </td>
                                            <td class="totals-cell"><?php echo $rate['total_count'] > 0 ? round(($rate['present_count'] / $rate['total_count']) * 100, 2) : 0; ?>%</td>
                                            <td class="totals-cell"><?php echo $rate['total_count'] > 0 ? round(($rate['late_count'] / $rate['total_count']) * 100, 2) : 0; ?>%</td>
                                            <td class="totals-cell"><?php echo $rate['total_count'] > 0 ? round(($rate['absent_count'] / $rate['total_count']) * 100, 2) : 0; ?>%</td>
                                            <td class="totals-cell"><strong style="color: #28a745"><?php echo $attendancePercentages[$index]; ?>%</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="analytics-report-footer">
                            Generated by Learnify System (Teacher: <?php echo htmlspecialchars($_SESSION['firstName']); ?>) | <?php echo date('F j, Y'); ?>
                        </div>
                    </div>
                    <div class="download-btn-container">
                        <button class="download-btn" id="downloadAnalyticsBtn">Download PDF</button>
                    </div>
                </div>

                <!-- Confirmation Modal -->
                <div id="confirmModal" class="modal">
                    <div class="modal-content" id="confirmModal">
                        <h2>Confirm Action</h2>
                        <p>Are you sure you want to <span id="modalAction"></span> the selected attendance records?</p>
                        <div class="modal-buttons">
                            <button onclick="closeConfirmModal()" class="cancel-btn">Cancel</button>
                            <button onclick="submitForm()" class="confirm-btn">Confirm</button>
                        </div>
                    </div>
                </div>

                <!-- Comparison Modal -->
                <?php if (isset($_GET['showComparison']) && $_GET['showComparison'] === 'true' && isset($_SESSION['pendingAttendance'])): ?>
                    <?php
                    $pending = $_SESSION['pendingAttendance'];
                    $submittedDate = $pending['date'];
                    $newStatuses = $pending['statuses'];
                    $checkDateStmt = $dbConnection->prepare("SELECT a.studentID, a.status, CONCAT(u.firstName, ' ', u.lastName) AS studentName, u.image
                                                            FROM attendance a
                                                            JOIN users u ON a.studentID = u.userID
                                                            WHERE a.teacherSectionID = :teacherSectionID AND a.attendanceDate = :attendanceDate AND a.archived = 0");
                    $checkDateStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                    $checkDateStmt->bindParam(':attendanceDate', $submittedDate);
                    $checkDateStmt->execute();
                    $existingRecords = $checkDateStmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div id="comparisonModal" class="modal" style="display: block;">
                        <div class="modal-content" id="comparisonModal">
                            <h2>Replace Attendance Records</h2>
                                <?php 
                                    $readableDate = date("F j, Y", strtotime($submittedDate)); 
                                ?>
                                <p>
                                    You already have an existing attendance record for 
                                    <strong style="color: #c82333;"><?php echo htmlspecialchars($readableDate); ?></strong>. 
                                    Do you want to replace this data?
                                </p>
                                <table class="comparison-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student</th>
                                        <th>Existing Status</th>
                                        <th>New Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rowNumber = 1; ?>
                                    <?php foreach ($students as $student): ?>
                                        <?php
                                        $studentID = $student['userID'];
                                        $existingStatus = '-';
                                        foreach ($existingRecords as $record) {
                                            if ($record['studentID'] == $studentID) {
                                                $existingStatus = $record['status'];
                                                break;
                                            }
                                        }
                                        $newStatus = isset($newStatuses[$studentID]) && in_array($newStatuses[$studentID], ['Present', 'Absent', 'Late']) ? $newStatuses[$studentID] : '-';
                                        ?>
                                        <tr>
                                            <td class="numbering-cell"><?php echo $rowNumber++; ?></td>
                                            <td>
                                                <div class="student-info">
                                                    <img src="<?php echo $student['image'] ? htmlspecialchars($student['image']) : './img/noprofile.png'; ?>" alt="Student Image" class="student-image">
                                                    <?php echo htmlspecialchars(ucwords($student['studentName'])); ?>
                                                </div>
                                            </td>
                                            <td class="status-cell <?php echo $existingStatus === 'Present' ? 'present' : ($existingStatus === 'Late' ? 'late' : ($existingStatus === 'Absent' ? 'absent' : '')); ?>">
                                                <?php echo $existingStatus === '-' ? '-' : substr($existingStatus, 0, 1); ?>
                                            </td>
                                            <td class="status-cell <?php echo $newStatus === 'Present' ? 'present' : ($newStatus === 'Late' ? 'late' : ($newStatus === 'Absent' ? 'absent' : '')); ?>">
                                                <?php echo $newStatus === '-' ? '-' : substr($newStatus, 0, 1); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <form method="POST" id="replaceAttendanceForm">
                                <input type="hidden" name="attendanceDate" value="<?php echo htmlspecialchars($submittedDate); ?>">
                                <?php foreach ($newStatuses as $studentID => $status): ?>
                                    <input type="hidden" name="attendance[<?php echo htmlspecialchars($studentID); ?>]" value="<?php echo htmlspecialchars($status); ?>">
                                <?php endforeach; ?>
                                <input type="hidden" name="replaceAttendance" value="true">
                                <div class="modal-buttons">
                                    <button type="button" onclick="closeComparisonModal()" class="cancel-btn">Cancel</button>
                                    <button type="submit" class="replace-btn">Replace</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        // Tab switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                const tab = button.getAttribute('data-tab');
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.getElementById(tab).classList.add('active');
                button.classList.add('active');
                window.history.pushState({}, '', `?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>&action=${tab}&year=<?php echo $selectedYear; ?>`);
            });
        });

        // Checkbox handling for single selection in Add Attendance
        function handleCheckbox(checkbox, studentId) {
            const checkboxes = document.querySelectorAll(`input[name="attendance[${studentId}]"]`);
            checkboxes.forEach(cb => {
                if (cb !== checkbox) cb.checked = false;
            });
            if (!checkbox.checked) {
                // If unchecked, default to Present
                document.querySelector(`input[name="attendance[${studentId}]"][value="Present"]`).checked = true;
            }
        }

        // Check All handling for Archive tab
        document.querySelectorAll('.check-all').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const date = this.getAttribute('data-date');
                const checkboxes = document.querySelectorAll(`.archive-checkbox[data-date="${date}"]`);
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
            });
        });

        // Confirmation modal handling
        let formToSubmit = null;
        function showConfirmModal(action) {
            formToSubmit = action === 'submitAttendance' ? 'recordAttendanceForm' : (action === 'archiveAttendance' ? 'archiveAttendanceForm' : 'editAttendanceForm');
            document.getElementById('modalAction').textContent = action === 'submitAttendance' ? 'submit' : (action === 'archiveAttendance' ? 'archive' : 'update');
            document.getElementById('confirmModal').style.display = 'block';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        function submitForm() {
            document.getElementById(formToSubmit).submit();
        }

        // Comparison modal handling
        function closeComparisonModal() {
            window.location.href = `?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>&action=record&attendanceDate=<?php echo htmlspecialchars($attendanceDate); ?>`;
        }

        // Check attendance before submission
        function checkAttendance() {
            const form = document.getElementById('recordAttendanceForm');
            const formData = new FormData(form);
            const date = formData.get('attendanceDate');
            
            fetch('check_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `teacherSectionID=<?php echo $teacherSectionID; ?>&attendanceDate=${encodeURIComponent(date)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    form.submit();
                } else {
                    showConfirmModal('submitAttendance');
                }
            })
            .catch(error => {
                console.error('Error checking attendance:', error);
                showConfirmModal('submitAttendance');
            });
        }

        // Initialize charts and PDF download
        document.addEventListener('DOMContentLoaded', () => {
            // Set default Present checkboxes
            const checkboxes = document.querySelectorAll('.status-checkbox');
            checkboxes.forEach(cb => {
                if (cb.value === 'Present') cb.checked = true;
            });

            // Initialize Individual Pie Charts for Each Student
            <?php foreach ($attendanceRates as $index => $rate): ?>
                <?php
                $total = $rate['total_count'];
                if ($total > 0) { // Only include students with non-zero attendance
                    $presentPercent = round(($rate['present_count'] / $total) * 100, 2);
                    $latePercent = round(($rate['late_count'] / $total) * 100, 2);
                    $absentPercent = round(($rate['absent_count'] / $total) * 100, 2);
                    // Filter out zero percentages to avoid empty slices
                    $labels = [];
                    $data = [];
                    $colors = [];
                    if ($presentPercent > 0) {
                        $labels[] = 'Present';
                        $data[] = $presentPercent;
                        $colors[] = '#28A745';
                    }
                    if ($latePercent > 0) {
                        $labels[] = 'Late';
                        $data[] = $latePercent;
                        $colors[] = '#FFCE26';
                    }
                    if ($absentPercent > 0) {
                        $labels[] = 'Absent';
                        $data[] = $absentPercent;
                        $colors[] = '#DB504A';
                    }
                ?>
                    const ctx_<?php echo $rate['studentID']; ?> = document.getElementById('pieChart_<?php echo $rate['studentID']; ?>');
                    if (ctx_<?php echo $rate['studentID']; ?>) {
                        new Chart(ctx_<?php echo $rate['studentID']; ?>, {
                            type: 'pie',
                            data: {
                                labels: <?php echo json_encode($labels); ?>,
                                datasets: [{
                                    data: <?php echo json_encode($data); ?>,
                                    backgroundColor: <?php echo json_encode($colors); ?>,
                                    borderColor: '#FFFFFF',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'bottom',
                                        labels: {
                                            font: {
                                                family: "'Poppins', sans-serif",
                                                size: 14
                                            }
                                        }
                                    },
                                    tooltip: {
                                        bodyFont: {
                                            family: "'Poppins', sans-serif",
                                            size: 14
                                        },
                                        titleFont: {
                                            family: "'Poppins', sans-serif",
                                            size: 14
                                        },
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.label || '';
                                                if (label) {
                                                    label += ': ';
                                                }
                                                label += context.parsed + '%';
                                                return label;
                                            }
                                        }
                                    },
                                    datalabels: {
                                        color: '#FFFFFF',
                                        font: {
                                            family: "'Poppins', sans-serif",
                                            size: 14,
                                            weight: 'bold'
                                        },
                                        formatter: (value) => value + '%',
                                        display: true
                                    }
                                }
                            },
                            plugins: [ChartDataLabels]
                        });
                    }
                <?php } ?>
            <?php endforeach; ?>

            // Initialize Status Distribution Bar Chart (Horizontal)
            const statusDistributionCtx = document.getElementById('statusDistributionChart');
            if (statusDistributionCtx) {
                // Calculate percentages for each date
                const dates = <?php echo json_encode($dates); ?>;
                const presentPercentages = [];
                const latePercentages = [];
                const absentPercentages = [];
                const totalCounts = [];
                <?php
                foreach ($statusDistribution as $dist) {
                    $total = $dist['present_count'] + $dist['late_count'] + $dist['absent_count'];
                    $presentPercent = $total > 0 ? round(($dist['present_count'] / $total) * 100, 2) : 0;
                    $latePercent = $total > 0 ? round(($dist['late_count'] / $total) * 100, 2) : 0;
                    $absentPercent = $total > 0 ? round(($dist['absent_count'] / $total) * 100, 2) : 0;
                    echo "presentPercentages.push($presentPercent);\n";
                    echo "latePercentages.push($latePercent);\n";
                    echo "absentPercentages.push($absentPercent);\n";
                    echo "totalCounts.push($total);\n";
                }
                ?>
                new Chart(statusDistributionCtx, {
                    type: 'bar',
                    data: {
                        labels: dates,
                        datasets: [
                            {
                                label: 'Present (%)',
                                data: presentPercentages,
                                backgroundColor: '#28A745',
                                borderColor: '#218838',
                                borderWidth: 1
                            },
                            {
                                label: 'Late (%)',
                                data: latePercentages,
                                backgroundColor: '#FFCE26',
                                borderColor: '#FFA500',
                                borderWidth: 1
                            },
                            {
                                label: 'Absent (%)',
                                data: absentPercentages,
                                backgroundColor: '#DB504A',
                                borderColor: '#C82333',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        indexAxis: 'y', // Horizontal bar chart (dates on y-axis)
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Percentage (%)',
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: 17,
                                        weight: 'bold'
                                    }
                                },
                                ticks: {
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: 17
                                    }
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Date',
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: 17,
                                        weight: 'bold'
                                    }
                                },
                                ticks: {
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: 17
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: 17
                                    }
                                }
                            },
                            tooltip: {
                                bodyFont: {
                                    family: "'Poppins', sans-serif",
                                    size: 17
                                },
                                titleFont: {
                                    family: "'Poppins', sans-serif",
                                    size: 17
                                },
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed.x + '%';
                                        return label;
                                    },
                                    afterLabel: function(context) {
                                        const index = context.dataIndex;
                                        const total = totalCounts[index];
                                        const present = <?php echo json_encode($presentCounts); ?>[index];
                                        const late = <?php echo json_encode($lateCounts); ?>[index];
                                        const absent = <?php echo json_encode($absentCounts); ?>[index];
                                        return `Total: ${total} students\nPresent: ${present}\nLate: ${late}\nAbsent: ${absent}`;
                                    }
                                }
                            },
                            datalabels: {
                                color: '#FFFFFF', 
                                font: {
                                    family: "'Poppins', sans-serif",
                                    size: 14,
                                    weight: 'bold'
                                },
                                formatter: (value) => value > 0 ? value + '%' : '',
                                anchor: 'center', 
                                align: 'center', 
                                display: true
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            // PDF download for Analytics tab
            const downloadBtn = document.getElementById('downloadAnalyticsBtn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    const element = document.getElementById('analytics-report');
                    if (!element) {
                        alert('Error: Analytics report content not found.');
                        return;
                    }
                    const safeProfessorName = '<?php echo preg_replace('/[^A-Za-z0-9_-]/', '_', $_SESSION['firstName']); ?>';
                    const timestamp = new Date().toISOString().replace(/[-:T.]/g, '').slice(0, 14);
                    html2pdf()
                        .set({
                            margin: [5, 5, 5, 5],
                            filename: `Learnify_Attendance_Analytics_${safeProfessorName}_${timestamp}.pdf`,
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2, useCORS: true },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                        })
                        .from(element)
                        .save()
                        .catch(error => {
                            console.error('Error generating PDF:', error);
                            alert('Failed to generate PDF. Please try again.');
                        });
                });
            }
        });
    </script>
</body>
</html>