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

try {
    // Get user details
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

    // Fetch sections and subjects for filter dropdowns
    $sectionStmt = $dbConnection->prepare("
        SELECT DISTINCT s.sectionID, s.sectionName 
        FROM sections s 
        JOIN teacher_section ts ON s.sectionID = ts.sectionID 
        WHERE ts.teacherID = :userID AND s.archived = 0 AND ts.archived = 0 
        ORDER BY s.sectionName
    ");
    $sectionStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $sectionStmt->execute();
    $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);

    $subjectStmt = $dbConnection->prepare("
        SELECT DISTINCT sub.subjectID, sub.subjectName 
        FROM subjects sub 
        JOIN teacher_section ts ON sub.subjectID = ts.subjectID 
        WHERE ts.teacherID = :userID AND sub.archived = 0 AND ts.archived = 0 
        ORDER BY sub.subjectName
    ");
    $subjectStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $subjectStmt->execute();
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

    // Set filter values
    $sectionFilter = filter_var($_GET['sectionID'] ?? (!empty($sections) ? $sections[0]['sectionID'] : ''), FILTER_VALIDATE_INT) ?: '';
    $subjectFilter = filter_var($_GET['subjectID'] ?? (!empty($subjects) ? $subjects[0]['subjectID'] : ''), FILTER_VALIDATE_INT) ?: '';
    $gradeFilterType = $_GET['gradeFilterType'] ?? 'all';
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Check if any filter is applied
    $isFiltered = $sectionFilter || $subjectFilter || $gradeFilterType !== 'all' || $searchQuery;

    // Fetch assignments based on filters
    $sql = "
        SELECT 
            ts.teacherSectionID,
            ts.sectionID,
            s.sectionName,
            ts.subjectID,
            sub.subjectName
        FROM teacher_section ts
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        WHERE ts.teacherID = :userID AND ts.archived = 0 AND s.archived = 0 AND sub.archived = 0
    ";
    $params = [':userID' => $userID];
    if ($sectionFilter) {
        $sql .= " AND ts.sectionID = :sectionID";
        $params[':sectionID'] = $sectionFilter;
    }
    if ($subjectFilter) {
        $sql .= " AND ts.subjectID = :subjectID";
        $params[':subjectID'] = $subjectFilter;
    }
    $sql .= " ORDER BY s.sectionName, sub.subjectName";
    $stmt = $dbConnection->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for display
    $gradesData = [];
    foreach ($assignments as $assignment) {
        // Fetch students with profile images
        $studentSql = "
            SELECT u.userID, u.lastName, u.firstName, u.image 
            FROM student_section ss 
            JOIN users u ON ss.userID = u.userID 
            WHERE ss.sectionID = :sectionID AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0 
        ";
        $studentParams = [':sectionID' => $assignment['sectionID']];
        if ($searchQuery) {
            $studentSql .= " AND (u.lastName LIKE :search OR u.firstName LIKE :search)";
            $studentParams[':search'] = "%$searchQuery%";
        }
        $studentSql .= " ORDER BY u.lastName, u.firstName";
        $studentStmt = $dbConnection->prepare($studentSql);
        foreach ($studentParams as $key => $value) {
            $studentStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $studentStmt->execute();
        $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch quizzes, assignments, and attendance with student counts
        $quizStmt = $dbConnection->prepare("
            SELECT quizID, title AS quizName, 
                   (SELECT COUNT(DISTINCT studentID) FROM quiz_scores WHERE quizID = quizzes.quizID AND archived = 0) AS student_count 
            FROM quizzes 
            WHERE teacherSectionID = :teacherSectionID AND archived = 0 
            ORDER BY title
        ");
        $quizStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
        $quizStmt->execute();
        $quizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);

        $assignmentStmt = $dbConnection->prepare("
            SELECT assignmentID, title AS assignmentName, 
                   (SELECT COUNT(DISTINCT studentID) FROM assignment_scores WHERE assignmentID = assignments.assignmentID AND archived = 0) AS student_count 
            FROM assignments 
            WHERE teacherSectionID = :teacherSectionID AND archived = 0 
            ORDER BY title
        ");
        $assignmentStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
        $assignmentStmt->execute();
        $classAssignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);

        $dateStmt = $dbConnection->prepare("
            SELECT DISTINCT attendanceDate, 
                   (SELECT COUNT(DISTINCT studentID) FROM attendance WHERE attendanceDate = a.attendanceDate AND teacherSectionID = a.teacherSectionID AND archived = 0) AS student_count 
            FROM attendance a 
            WHERE teacherSectionID = :teacherSectionID AND archived = 0 
            ORDER BY attendanceDate DESC
        ");
        $dateStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
        $dateStmt->execute();
        $attendanceDates = $dateStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch scores and attendance
        $quizScoresStmt = $dbConnection->prepare("
            SELECT qs.studentID, qs.quizID, qs.totalScore, qs.maxScore
            FROM quiz_scores qs
            JOIN quizzes q ON qs.quizID = q.quizID
            WHERE q.teacherSectionID = :teacherSectionID AND qs.archived = 0
        ");
        $quizScoresStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
        $quizScoresStmt->execute();
        $quizScores = $quizScoresStmt->fetchAll(PDO::FETCH_ASSOC);

        $assignmentScoresStmt = $dbConnection->prepare("
            SELECT ass.studentID, ass.assignmentID, ass.totalScore, ass.maxScore
            FROM assignment_scores ass
            JOIN assignments a ON ass.assignmentID = a.assignmentID
            WHERE a.teacherSectionID = :teacherSectionID AND ass.archived = 0
        ");
        $assignmentScoresStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
        $assignmentScoresStmt->execute();
        $assignmentScores = $assignmentScoresStmt->fetchAll(PDO::FETCH_ASSOC);

        $attendanceStmt = $dbConnection->prepare("
            SELECT a.studentID, a.attendanceDate, a.status 
            FROM attendance a 
            WHERE a.teacherSectionID = :teacherSectionID AND a.archived = 0
        ");
        $attendanceStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
        $attendanceStmt->execute();
        $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

        // Predictive analytics for at-risk students
        $atRiskStudents = [];
        $gradesMatrix = [];
        foreach ($students as $student) {
            $studentID = $student['userID'];
            $totalQuizScore = 0;
            $totalQuizMax = 0;
            $quizCount = 0;
            $totalAssignmentScore = 0;
            $totalAssignmentMax = 0;
            $assignmentCount = 0;
            $absenceCount = 0;
            $lateCount = 0;
            $totalAttendance = count($attendanceDates);

            // Calculate quiz scores
            foreach ($quizScores as $score) {
                if ($score['studentID'] == $studentID) {
                    $totalQuizScore += $score['totalScore'];
                    $totalQuizMax += $score['maxScore'];
                    $quizCount++;
                    // Check if individual quiz score is below 50%
                    if (($score['totalScore'] / $score['maxScore']) * 100 < 50) {
                        $isAtRisk = true;
                        $riskFactors[] = "Quiz score below 50% ({$score['totalScore']}/{$score['maxScore']})";
                    }
                }
            }

            // Calculate assignment scores
            foreach ($assignmentScores as $score) {
                if ($score['studentID'] == $studentID) {
                    $totalAssignmentScore += $score['totalScore'];
                    $totalAssignmentMax += $score['maxScore'];
                    $assignmentCount++;
                    // Check if individual assignment score is below 50%
                    if (($score['totalScore'] / $score['maxScore']) * 100 < 50) {
                        $isAtRisk = true;
                        $riskFactors[] = "Assignment score below 50% ({$score['totalScore']}/{$score['maxScore']})";
                    }
                }
            }

            // Calculate attendance (absences and lates)
            foreach ($attendanceRecords as $record) {
                if ($record['studentID'] == $studentID) {
                    if ($record['status'] === 'Absent') {
                        $absenceCount++;
                    } elseif ($record['status'] === 'Late') {
                        $lateCount++;
                    }
                }
            }

            // Calculate percentages
            $quizAverage = $quizCount > 0 ? ($totalQuizScore / $totalQuizMax) * 100 : 0;
            $assignmentAverage = $assignmentCount > 0 ? ($totalAssignmentScore / $totalAssignmentMax) * 100 : 0;
            $attendanceRate = $totalAttendance > 0 ? (($totalAttendance - $absenceCount) / $totalAttendance) * 100 : 0;

            // Weighted average calculation (40% quizzes, 40% assignments, 20% attendance)
            $weightedAverage = ($quizAverage * 0.4) + ($assignmentAverage * 0.4) + ($attendanceRate * 0.2);

            // At-risk criteria
            $isAtRisk = false;
            $riskFactors = [];

            // Check for low quiz scores
            foreach ($quizScores as $score) {
                if ($score['studentID'] == $studentID && ($score['totalScore'] / $score['maxScore']) * 100 < 50) {
                    $isAtRisk = true;
                    $riskFactors[] = "Quiz score below 50% ({$score['totalScore']}/{$score['maxScore']})";
                }
            }

            // Check for low assignment scores
            foreach ($assignmentScores as $score) {
                if ($score['studentID'] == $studentID && ($score['totalScore'] / $score['maxScore']) * 100 < 50) {
                    $isAtRisk = true;
                    $riskFactors[] = "Assignment score below 50% ({$score['totalScore']}/{$score['maxScore']})";
                }
            }

            // Check for absences (3 or more)
            if ($absenceCount >= 3) {
                $isAtRisk = true;
                $riskFactors[] = "Excessive absences ($absenceCount)";
            }

            // Check for lates (5 or more)
            if ($lateCount >= 5) {
                $isAtRisk = true;
                $riskFactors[] = "Excessive lates ($lateCount)";
            }

            if ($isAtRisk) {
                $atRiskStudents[$studentID] = $riskFactors;
            }

            // Build grades matrix
            $gradesMatrix[$student['userID']] = [
                'studentName' => strtoupper($student['lastName'] . ', ' . $student['firstName']),
                'image' => $student['image'] ? htmlspecialchars($student['image']) : './img/noprofile.png',
                'quizzes' => array_fill_keys(array_column($quizzes, 'quizID'), empty($quizzes) ? 'No quiz' : '-'),
                'assignments' => array_fill_keys(array_column($classAssignments, 'assignmentID'), empty($classAssignments) ? 'No assignment' : '-'),
                'attendance' => array_fill_keys(array_column($attendanceDates, 'attendanceDate'), empty($attendanceDates) ? 'No attendance yet' : '-'),
                'isAtRisk' => $isAtRisk,
                'riskFactors' => $riskFactors,
                'weightedAverage' => number_format($weightedAverage, 2)
            ];
        }

        foreach ($quizScores as $score) {
            if (isset($gradesMatrix[$score['studentID']])) {
                $gradesMatrix[$score['studentID']]['quizzes'][$score['quizID']] = $score['totalScore'] . '/' . $score['maxScore'];
            }
        }

        foreach ($assignmentScores as $score) {
            if (isset($gradesMatrix[$score['studentID']])) {
                $gradesMatrix[$score['studentID']]['assignments'][$score['assignmentID']] = $score['totalScore'] . '/' . $score['maxScore'];
            }
        }

        foreach ($attendanceRecords as $record) {
            if (isset($gradesMatrix[$record['studentID']])) {
                $statusShort = $record['status'] === 'Present' ? 'P' : ($record['status'] === 'Absent' ? 'A' : 'L');
                $gradesMatrix[$record['studentID']]['attendance'][$record['attendanceDate']] = $statusShort;
            }
        }

        $gradesData[] = [
            'sectionName' => $assignment['sectionName'],
            'subjectName' => $assignment['subjectName'],
            'quizzes' => $quizzes,
            'classAssignments' => $classAssignments,
            'attendanceDates' => $attendanceDates,
            'gradesMatrix' => $gradesMatrix,
            'totalStudents' => count($students)
        ];
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
    header("Location: ./professorDash.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/semi-dash.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Grades</title>
    <style>
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --grey: #f3f4f6;
            --dark-grey: #9ca3af;
            --dark: #1f2937;
            --green: #10b981;
            --red: #ef4444;
            --yellow: #eab308;
            --purple: #8b5cf6;
            --light-blue: #e0f2fe;
            --excel-green: #107C41;
            --excel-cell-grey: #D3D3D3;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --at-risk-bg: #fee2e2;
            --at-risk-border: #dc2626;
        }

        .admin-table h2 {
            color: var(--dark);
        }

        .grades-container {
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 0.7rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--grey);
            transition: var(--transition);
        }

        .grades-form {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            background: var(--light);
            padding: 1rem;
            border-radius: 0.5rem;
        }

        .grades-form select,
        .grades-form button,
        .grades-form .clear-btn,
        .grades-form input[type="search"] {
            padding: 0.75rem;
            font-size: 0.9rem;
            border: 1px solid var(--dark-grey);
            border-radius: 0.375rem;
            background-color: var(--grey);
            color: var(--dark);
            outline: none;
            flex: 1;
            min-width: 150px;
            transition: var(--transition);
        }

        .grades-form input[type="search"] {
            max-width: 300px;
        }

        .grades-form select:focus,
        .grades-form input[type="search"]:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .grades-form button {
            background-color: var(--blue);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        .grades-form button:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }

        .grades-form .clear-btn {
            background-color: var(--red);
            color: white;
            text-decoration: none;
            text-align: center;
            border: none !important;
        }

        .grades-form .clear-btn:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
        }

        .grades-table-container {
            overflow-x: auto;
            max-width: 100%;
            border-radius: 0.375rem;
            box-shadow: var(--shadow-sm);
            margin-top: 1rem;
        }

        .grades-table {
            border-collapse: collapse;
            font-family: 'Calibri', sans-serif;
            font-size: 0.9rem;
            width: 100%;
            margin-bottom: 10px;
        }

        .grades-table th,
        .grades-table td {
            padding: 0.75rem;
            vertical-align: middle;
        }

        .grades-table th {
            border: none !important;
        }

        .grades-table th {
            background-color: var(--excel-green);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            position: sticky;
            top: 0;
            z-index: 10;
            text-align: center;
        }

        .grades-table th.student-name {
            position: sticky;
            left: 0;
            z-index: 15;
            min-width: 250px;
            text-align: left;
        }

        .grades-table td {
            color: var(--dark);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .grades-table td.student-name {
            background-color: var(--light);
            position: sticky;
            left: 0;
            z-index: 10;
            text-transform: uppercase;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .grades-table td.student-name img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 6px;
            object-fit: cover;
            border: 1px solid var(--dark-grey);
        }

        .grades-table td.score-cell {
            min-width: 120px;
        }

        .grades-table td.absent {
            background-color: #a90c0c;
			color: #ffffff;
        }

        .grades-table td.late {
            background-color: #FFCE26;
            color: #342E37;
        }

        .grades-table tr.at-risk td.student-name {
            background-color: var(--light);
            border-left: 3px solid var(--at-risk-border);
        }

        .grades-table tr.at-risk td.student-name .risk-indicator {
            color: var(--red);
            font-weight: bold;
            margin-left: 0.5rem;
            cursor: pointer;
        }

        .grades-table tr:nth-child(even) td.student-name {
            background: rgba(255, 255, 255, 0.1); 
            /* border: 1px solid rgba(255, 255, 255, 0.2);  */
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); 
        }

        .grades-table tr:nth-child(even).at-risk td.student-name {
            background-color: var(--at-risk-bg);
        }

        .grades-table tr:hover td {
             background: rgba(255, 255, 255, 0.1); 
            /* border: 1px solid rgba(255, 255, 255, 0.2);  */
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); 
            transition: background-color 0.2s ease;
        }

        .grades-table tr:hover td.absent {
            background-color: #820a0a;
        }

        .grades-table tr:hover td.late {
            background-color: #e6b800;
        }

        .grades-table tr:hover td.student-name {
            background-color: var(--grey);
        }

        .grades-table tr.at-risk:hover td.student-name {
            background-color: var(--light);
        }

        .success-notification, .error-notification {
            background-color: #10b981;
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 0.375rem;
            text-align: center;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }

        .error-notification {
            background-color: var(--red);
        }

        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: var(--dark);
            color: var(--light);
            text-align: left;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            border: 1px solid #ccc;
            z-index: 20;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltiptext ul li{
            color: var(--light);
            font-weight: 900;
            font-size: 1rem;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        @media (max-width: 768px) {
            .grades-container {
                margin: 1rem;
                padding: 1rem;
            }

            .grades-form {
                flex-direction: column;
                padding: 1rem;
            }

            .grades-form select,
            .grades-form button,
            .grades-form .clear-btn,
            .grades-form input[type="search"] {
                width: 100%;
                margin-bottom: 0.5rem;
                font-size: 0.85rem;
            }

            .grades-table {
                font-size: 0.85rem;
                min-width: 500px;
            }

            .grades-table th,
            .grades-table td {
                padding: 0.5rem;
            }

            .grades-table th.student-name,
            .grades-table td.student-name {
                min-width: 200px;
            }

            .grades-table td.score-cell {
                min-width: 100px;
            }

            .grades-table td.student-name img {
                width: 28px;
                height: 28px;
            }

            .tooltip .tooltiptext {
                width: 150px;
                margin-left: -75px;
            }
        }

        @media (max-width: 480px) {
            .grades-container {
                margin: 0.5rem;
                padding: 0.75rem;
            }

            .grades-form {
                padding: 0.75rem;
            }

            .grades-form select,
            .grades-form button,
            .grades-form .clear-btn,
            .grades-form input[type="search"] {
                font-size: 0.8rem;
            }

            .grades-table {
                font-size: 0.8rem;
                min-width: 400px;
            }

            .grades-table th,
            .grades-table td {
                padding: 0.4rem;
            }

            .grades-table th.student-name,
            .grades-table td.student-name {
                min-width: 150px;
            }

            .grades-table td.score-cell {
                min-width: 80px;
            }

            .grades-table td.student-name img {
                width: 24px;
                height: 24px;
            }

            .tooltip .tooltiptext {
                width: 120px;
                margin-left: -60px;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
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
                    <span class="text">Quizziz Controller</span>
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
                <a href="./version.php">
                    <i class='bx bxs-info-circle'></i>
                    <span class="text">Version</span>
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
                    <h1>Records</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Handled Class</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Manage</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./all.php">Records</a></li>
                    </ul>
                </div>
                <a href="javascript:void(0);" class="btn-download" onclick="history.back()">
                    <i class='bx bxs-left-arrow'></i>
                    <span class="text">Back</span>
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="grades-container">
                <!-- <form method="GET" class="grades-form">
                    <input type="search" name="search" id="searchInput" placeholder="Search students..." value="<?php echo htmlspecialchars($searchQuery); ?>" aria-label="Search students">
                    <select name="sectionID" id="sectionFilter" aria-label="Filter by section">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo htmlspecialchars($section['sectionID']); ?>" <?php echo $sectionFilter == $section['sectionID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['sectionName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="subjectID" id="subjectFilter" aria-label="Filter by subject">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['subjectID']); ?>" <?php echo $subjectFilter == $subject['subjectID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subjectName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="gradeFilterType" id="gradeFilterType" aria-label="Filter by grade type">
                        <option value="all" <?php echo $gradeFilterType === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="quizzes" <?php echo $gradeFilterType === 'quizzes' ? 'selected' : ''; ?>>Quizzes</option>
                        <option value="assignments" <?php echo $gradeFilterType === 'assignments' ? 'selected' : ''; ?>>Assignments</option>
                        <option value="attendance" <?php echo $gradeFilterType === 'attendance' ? 'selected' : ''; ?>>Attendance</option>
                    </select>
                    <button type="submit">Filter</button>
                    <?php if ($sectionFilter || $subjectFilter || $gradeFilterType !== 'all' || $searchQuery): ?>
                        <a href="./all.php" class="clear-btn">Clear</a>
                    <?php endif; ?>
                </form> -->

                <div class="admin-table">
                    <?php foreach ($gradesData as $data): ?>
                        <h2><?php echo htmlspecialchars($data['sectionName'] . ' - ' . $data['subjectName']); ?></h2>
                        <div class="grades-table-container">
                            <table class="grades-table">
                                <thead>
                                    <tr>
                                        <th class="student-name">Student Name</th>
                                        <?php if ($gradeFilterType === 'all' || $gradeFilterType === 'quizzes'): ?>
                                            <?php if (empty($data['quizzes'])): ?>
                                                <th>No quiz</th>
                                            <?php else: ?>
                                                <?php foreach ($data['quizzes'] as $quiz): ?>
                                                    <th><?php echo htmlspecialchars($quiz['quizName']); ?> <br> (<?php echo $quiz['student_count']; ?>/<?php echo $data['totalStudents']; ?>)</th>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($gradeFilterType === 'all' || $gradeFilterType === 'assignments'): ?>
                                            <?php if (empty($data['classAssignments'])): ?>
                                                <th>No assignment</th>
                                            <?php else: ?>
                                                <?php foreach ($data['classAssignments'] as $classAssignment): ?>
                                                    <th><?php echo htmlspecialchars($classAssignment['assignmentName']); ?> <br> (<?php echo $classAssignment['student_count']; ?>/<?php echo $data['totalStudents']; ?>)</th>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($gradeFilterType === 'all' || $gradeFilterType === 'attendance'): ?>
                                            <?php if (empty($data['attendanceDates'])): ?>
                                                <th>No attendance yet</th>
                                            <?php else: ?>
                                                <?php foreach ($data['attendanceDates'] as $date): ?>
                                                    <th><?php echo htmlspecialchars(date('F j, Y', strtotime($date['attendanceDate']))); ?> <br> (<?php echo $date['student_count']; ?>/<?php echo $data['totalStudents']; ?>)</th>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <th>Weighted Average (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data['gradesMatrix'])): ?>
                                        <?php foreach ($data['gradesMatrix'] as $studentID => $studentData): ?>
                                            <tr class="<?php echo $studentData['isAtRisk'] ? 'at-risk' : ''; ?>">
                                                <td class="student-name">
                                                    <img src="<?php echo htmlspecialchars($studentData['image']); ?>" alt="Student Profile">
                                                    <?php echo htmlspecialchars($studentData['studentName']); ?>
                                                    <?php if ($studentData['isAtRisk']): ?>
                                                        <span class="tooltip">
                                                            <span class="risk-indicator">🚩</span>
                                                            <span class="tooltiptext">
                                                                At-risk due to:
                                                                <ul>
                                                                    <?php foreach ($studentData['riskFactors'] as $factor): ?>
                                                                        <li><?php echo htmlspecialchars($factor); ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </span>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($gradeFilterType === 'all' || $gradeFilterType === 'quizzes'): ?>
                                                    <?php if (empty($data['quizzes'])): ?>
                                                        <td class="score-cell">No quiz</td>
                                                    <?php else: ?>
                                                        <?php foreach ($studentData['quizzes'] as $score): ?>
                                                            <td class="score-cell"><?php echo htmlspecialchars($score); ?></td>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($gradeFilterType === 'all' || $gradeFilterType === 'assignments'): ?>
                                                    <?php if (empty($data['classAssignments'])): ?>
                                                        <td class="score-cell">No assignment</td>
                                                    <?php else: ?>
                                                        <?php foreach ($studentData['assignments'] as $score): ?>
                                                            <td class="score-cell"><?php echo htmlspecialchars($score); ?></td>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($gradeFilterType === 'all' || $gradeFilterType === 'attendance'): ?>
                                                    <?php if (empty($data['attendanceDates'])): ?>
                                                        <td class="score-cell">No attendance yet</td>
                                                    <?php else: ?>
                                                        <?php foreach ($studentData['attendance'] as $status): ?>
                                                            <td class="score-cell <?php echo $status === 'A' ? 'absent' : ($status === 'L' ? 'late' : ''); ?>">
                                                                <?php echo htmlspecialchars($status); ?>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <td class="score-cell"><?php echo htmlspecialchars($studentData['weightedAverage']); ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$isFiltered): ?>
                                            <!-- Totals row -->
                                            <tr>
                                                <td class="student-name">Total Students</td>
                                                <?php if ($gradeFilterType === 'all' || $gradeFilterType === 'quizzes'): ?>
                                                    <?php if (empty($data['quizzes'])): ?>
                                                        <td class="score-cell">-</td>
                                                    <?php else: ?>
                                                        <?php foreach ($data['quizzes'] as $quiz): ?>
                                                            <td class="score-cell"><?php echo htmlspecialchars($quiz['student_count'] . '/' . $data['totalStudents']); ?></td>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($gradeFilterType === 'all' || $gradeFilterType === 'assignments'): ?>
                                                    <?php if (empty($data['classAssignments'])): ?>
                                                        <td class="score-cell">-</td>
                                                    <?php else: ?>
                                                        <?php foreach ($data['classAssignments'] as $classAssignment): ?>
                                                            <td class="score-cell"><?php echo htmlspecialchars($classAssignment['student_count'] . '/' . $data['totalStudents']); ?></td>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($gradeFilterType === 'all' || $gradeFilterType === 'attendance'): ?>
                                                    <?php if (empty($data['attendanceDates'])): ?>
                                                        <td class="score-cell">-</td>
                                                    <?php else: ?>
                                                        <?php foreach ($data['attendanceDates'] as $date): ?>
                                                            <td class="score-cell"><?php echo htmlspecialchars($date['student_count'] . '/' . $data['totalStudents']); ?></td>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <td class="score-cell">-</td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo (empty($data['quizzes']) ? 1 : count($data['quizzes'])) + (empty($data['classAssignments']) ? 1 : count($data['classAssignments'])) + (empty($data['attendanceDates']) ? 1 : count($data['attendanceDates'])) + 1; ?>">
                                                No records found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
           
            // Auto-search functionality
            const searchInput = document.getElementById('searchInput');
            const sectionFilter = document.getElementById('sectionFilter');
            const subjectFilter = document.getElementById('subjectFilter');
            const gradeFilterType = document.getElementById('gradeFilterType');

            function updateFilters() {
                const search = searchInput.value;
                const sectionID = sectionFilter.value;
                const subjectID = subjectFilter.value;
                const gradeType = gradeFilterType.value;
                const url = new URL(window.location.href);
                url.searchParams.set('search', search);
                url.searchParams.set('sectionID', sectionID);
                url.searchParams.set('subjectID', subjectID);
                url.searchParams.set('gradeFilterType', gradeType);
                window.location.href = url.toString();
            }

            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(updateFilters, 300); // Debounce for 300ms
            });

            sectionFilter.addEventListener('change', updateFilters);
            subjectFilter.addEventListener('change', updateFilters);
            gradeFilterType.addEventListener('change', updateFilters);
        });
    </script>
</body>
</html>