<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

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
$selectedYear = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : 2025;
$searchQuery = isset($_GET['search']) ? trim(filter_var($_GET['search'], FILTER_SANITIZE_STRING)) : '';
$page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : 1;
$perPage = 10; // Number of students per page

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

// Fetch grading weights
$weightsStmt = $dbConnection->prepare("SELECT quiz_weight FROM grading_weights WHERE teacherSectionID = :teacherSectionID");
$weightsStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$weightsStmt->execute();
$weights = $weightsStmt->fetch(PDO::FETCH_ASSOC);
$quizWeight = $weights ? $weights['quiz_weight'] : 35;

// Fetch students with search and pagination
$searchCondition = $searchQuery ? "AND CONCAT(u.firstName, ' ', u.lastName) LIKE :searchQuery" : '';
$offset = ($page - 1) * $perPage;

$studentStmt = $dbConnection->prepare("SELECT u.userID, CONCAT(u.firstName, ' ', u.lastName) AS studentName, u.image
                                       FROM student_section ss
                                       JOIN users u ON ss.userID = u.userID
                                       WHERE ss.sectionID = :sectionID AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
                                       $searchCondition
                                       ORDER BY u.firstName
                                       LIMIT :perPage OFFSET :offset");
$studentStmt->bindParam(':sectionID', $sectionID, PDO::PARAM_INT);
if ($searchQuery) {
    $searchParam = "%$searchQuery%";
    $studentStmt->bindParam(':searchQuery', $searchParam, PDO::PARAM_STR);
}
$studentStmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
$studentStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$studentStmt->execute();
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

// Count total students for pagination
$countStmt = $dbConnection->prepare("SELECT COUNT(*) as total
                                     FROM student_section ss
                                     JOIN users u ON ss.userID = u.userID
                                     WHERE ss.sectionID = :sectionID AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
                                     $searchCondition");
$countStmt->bindParam(':sectionID', $sectionID, PDO::PARAM_INT);
if ($searchQuery) {
    $countStmt->bindParam(':searchQuery', $searchParam, PDO::PARAM_STR);
}
$countStmt->execute();
$totalStudents = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalStudents / $perPage);

if (empty($students) && !$searchQuery) {
    $_SESSION['error_message'] = "No students enrolled in this section.";
    header("Location: professorDash.php?sectionID=$sectionID&subjectID=$subjectID");
    exit;
}

// Fetch quizzes for the given teacherSectionID and year
try {
    $quizListStmt = $dbConnection->prepare("SELECT quizID, title FROM quizzes 
                                            WHERE teacherSectionID = :teacherSectionID AND archived = 0 
                                            AND YEAR(createdDate) = :selectedYear 
                                            ORDER BY createdDate");
    $quizListStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $quizListStmt->bindParam(':selectedYear', $selectedYear, PDO::PARAM_INT);
    $quizListStmt->execute();
    $quizzes = $quizListStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to fetch quizzes: " . htmlspecialchars($e->getMessage());
    header("Location: professorDash.php");
    exit;
}

// Fetch quiz scores
try {
    $quizStmt = $dbConnection->prepare("SELECT qs.studentID, qs.quizID, qs.totalScore, qs.maxScore
                                       FROM quiz_scores qs
                                       JOIN quizzes q ON qs.quizID = q.quizID
                                       WHERE q.teacherSectionID = :teacherSectionID AND qs.approved = 1 AND qs.archived = 0
                                       AND YEAR(qs.recordedDate) = :selectedYear");
    $quizStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $quizStmt->bindParam(':selectedYear', $selectedYear, PDO::PARAM_INT);
    $quizStmt->execute();
    $quizScores = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to fetch quiz scores: " . htmlspecialchars($e->getMessage());
    header("Location: professorDash.php");
    exit;
}

// Compute quiz data
$quizData = [];
foreach ($students as $student) {
    $studentID = $student['userID'];
    $totalScore = 0;
    $totalMaxScore = 0;
    $quizCount = 0;
    $studentQuizScores = [];

    // Initialize scores for each quiz
    foreach ($quizzes as $quiz) {
        $studentQuizScores[$quiz['quizID']] = ['score' => 'N/A'];
    }

    // Populate scores
    foreach ($quizScores as $score) {
        if ($score['studentID'] == $studentID) {
            $quizCount++;
            $totalScore += $score['totalScore'];
            $totalMaxScore += $score['maxScore'];
            $percentage = $score['maxScore'] > 0 ? ($score['totalScore'] / $score['maxScore']) * 100 : 0;
            $studentQuizScores[$score['quizID']] = ['score' => round($percentage, 2)];
        }
    }

    // Calculate average quiz score as percentage
    $averageQuizScore = $totalMaxScore > 0 ? ($totalScore / $totalMaxScore) * 100 : 0;

    $quizData[$studentID] = [
        'studentName' => ucwords($student['studentName']),
        'image' => $student['image'] ? htmlspecialchars($student['image']) : './img/noprofile.png',
        'quizCount' => $quizCount,
        'averageQuizScore' => round($averageQuizScore, 2),
        'quizScores' => $studentQuizScores
    ];
}

// Fetch available years for filter
$yearStmt = $dbConnection->prepare("SELECT DISTINCT YEAR(qs.recordedDate) AS year 
                                   FROM quiz_scores qs
                                   JOIN quizzes q ON qs.quizID = q.quizID
                                   WHERE q.teacherSectionID = :teacherSectionID AND qs.archived = 0 
                                   ORDER BY year DESC");
$yearStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$yearStmt->execute();
$availableYears = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($availableYears)) {
    $availableYears = [2025];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Quiz Rate</title>
    <style>
        :root {
            --poppins: 'Poppins', sans-serif;
            --light: #F9F9F9;
            --blue: #3C91E6;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --red: #DB504A;
            --yellow: #FFCE26;
            --green: #28A745;
            --hover: #d5d5d5;
            --secondary-hover: #e0e0e0;
            --secondary-grey: #f5f5f5;
        }

        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }

        .quiz-rate-container {
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .quiz-rate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .quiz-rate-header p {
            font-size: clamp(1rem, 3vw, 1.5rem);
            color: var(--dark);
        }

        .quiz-rate-header p span {
            border-bottom: 3px solid #007bff;
        }

        .quiz-rate-title {
            font-size: clamp(1.2rem, 3vw, 2rem);
            color: var(--dark);
            font-family: var(--poppins);
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

        .filter-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--dark);
            font-family: var(--poppins);
        }

        .form-select, .form-input-container {
            padding: 8px;
            border: 1px solid #ccc;
            background-color: var(--grey);
            border-radius: 4px;
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-family: var(--poppins);
            transition: border-color 0.3s;
        }

        .form-input-container {
            max-width: 300px;
            width: 100%;
        }

        .form-select:focus, .form-input-container:focus {
            border-color: var(--blue);
            outline: none;
        }

        .clear-btn, .download-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: var(--poppins);
            transition: background 0.3s;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .download-btn {
            background: #007bff;
            color: white;
        }

        .download-btn:hover {
            background: #0056b3;
        }

        .clear-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .clear-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .quiz-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-family: var(--poppins);
            table-layout: auto;
        }

        .quiz-table th, .quiz-table td {
            padding: 12px;
            text-align: left;
            font-size: clamp(0.9rem, 2.5vw, 1.1rem);
            border: 1px solid #ddd;
        }

        .quiz-table th {
            background: var(--blue);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .quiz-table td {
            color: var(--dark);
        }

        .quiz-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
        }

        .quiz-table tr:hover {
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

        .numbering-cell {
            text-align: center;
            width: 50px;
        }

        .quiz-title {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .report-container {
            background: var(--light);
            border: 2px solid #ccc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .report-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .report-header h1 {
            color: var(--dark);
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            border-bottom: 1px solid var(--dark);
            margin: 0;
            font-family: var(--poppins);
        }

        .report-footer {
            text-align: center;
            font-size: 12px;
            color: var(--dark-grey);
            margin-top: 20px;
            font-size: clamp(1rem, 3vw, 1.5rem);
            padding-top: 10px;
            border-top: 1px solid var(--grey);
            font-family: var(--poppins);
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: var(--poppins);
            font-size: clamp(1rem, 3vw, 1.2rem);
            background: var(--grey);
            color: var(--dark);
            transition: background 0.3s, color 0.3s;
        }

        .tab-button.active, .tab-button:hover {
            background: var(--blue);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .help-content {
            font-family: var(--poppins);
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--dark);
            line-height: 1.6;
        }

        .help-content h3 {
            color: var(--blue);
            margin-top: 20px;
        }

        .help-content ul {
            list-style-type: disc;
            margin-left: 20px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            font-family: var(--poppins);
        }

        .pagination a {
            padding: 8px 12px;
            text-decoration: none;
            color: var(--dark);
            border: 1px solid var(--grey);
            border-radius: 4px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            transition: background 0.3s;
        }

        .pagination a:hover {
            background: var(--hover);
        }

        .pagination a.active {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
        }

        .pagination a.disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        @media print {
            .download-btn-container, .tab-buttons, .filter-container, .pagination {
                display: none;
            }

            .report-container {
                box-shadow: none;
                margin: 0;
            }

            .tab-content:not(#quiz-report-tab) {
                display: none !important;
            }

            #quiz-report-tab {
                display: block !important;
            }
        }

        .back-button {
            background: linear-gradient(135deg, #2B6CB0, #1E4A7A);
            color: #FFFFFF;
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .back-button:hover {
            background: linear-gradient(135deg, #1E4A7A, #1A4971);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .back-button i {
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        @media screen and (max-width: 460px) {
            .filter-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .quiz-table th, .quiz-table td {
                font-size: clamp(0.8rem, 2vw, 0.9rem);
                padding: 8px;
            }

            .quiz-title {
                max-width: 100px;
            }

            .filter-container,
            .quiz-rate-header,
            .tab-buttons {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .tab-button, .form-select {
                width: 100%;
            }
            .pagination {
                overflow-x: auto;
                margin-bottom: 10px;
            }
        }

        @media screen and (max-width: 768px) {
            .quiz-table {
                overflow-x: auto;
                display: block;
            }
        }
    </style>
</head>
<body class="google-classroom-style">
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
                    <h1>Quiz Rate</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Quiz Rate</a></li>
                    </ul>
                </div>
                <a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Quiz Dashboard
                </a>
            </div>

            <div class="quiz-rate-container">
                <div class="quiz-rate-header">
                    <h1 class="quiz-rate-title"><?php echo htmlspecialchars($sectionName . ' - ' . $subjectName); ?></h1>
                    <p>Quiz Weight: <span><?php echo $quizWeight; ?>%</span></p>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-notification"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="error-notification"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <div class="tab-buttons">
                    <button class="tab-button active" data-tab="quiz-report-tab">Quiz Report</button>
                    <button class="tab-button" data-tab="help-tab">Help/Guide</button>
                </div>

                <div id="quiz-report-tab" class="tab-content active">
                    <form class="filter-container" method="GET">
                        <input type="hidden" name="teacherSectionID" value="<?php echo $teacherSectionID; ?>">
                        <input type="hidden" name="sectionID" value="<?php echo $sectionID; ?>">
                        <input type="hidden" name="subjectID" value="<?php echo $subjectID; ?>">
                        <label for="yearFilter" class="form-label">Filter by Year:</label>
                        <select name="year" id="yearFilter" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="searchFilter" class="form-label">Search Student:</label>
                        <input type="text" name="search" id="searchFilter" class="form-input-container" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Enter student name">
                        <button type="submit" class="download-btn">Search</button>
                        <?php if ($selectedYear != 2025 || $searchQuery): ?>
                            <a href="quiz_rate.php?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>" class="clear-btn">Clear Filters</a>
                        <?php endif; ?>
                    </form>

                    <?php if (empty($students) && $searchQuery): ?>
                        <div class="error-notification">No students found matching "<?php echo htmlspecialchars($searchQuery); ?>".</div>
                    <?php endif; ?>

                    <div class="report-container" id="quiz-report">
                        <div class="report-header">
                            <h1>Learnify Quiz Rate Report (<?php echo $selectedYear; ?><?php echo $searchQuery ? ' - Filtered: ' . htmlspecialchars($searchQuery) : ''; ?>)</h1>
                        </div>
                        <table class="quiz-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <th class="quiz-title" title="<?php echo htmlspecialchars($quiz['title']); ?>">
                                            <?php echo htmlspecialchars(substr($quiz['title'], 0, 15)) . (strlen($quiz['title']) > 15 ? '...' : ''); ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th>Total Quizzes</th>
                                    <th>Average Quiz Score (%)</th>
                                    <th>Quiz Weight (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rowNumber = ($page - 1) * $perPage + 1; ?>
                                <?php foreach ($quizData as $studentID => $data): ?>
                                    <tr>
                                        <td class="numbering-cell"><?php echo $rowNumber++; ?></td>
                                        <td>
                                            <div class="student-info">
                                                <img src="<?php echo $data['image']; ?>" alt="Student Image" class="student-image">
                                                <?php echo htmlspecialchars($data['studentName']); ?>
                                            </div>
                                        </td>
                                        <?php foreach ($quizzes as $quiz): ?>
                                            <td>
                                                <?php echo $data['quizScores'][$quiz['quizID']]['score'] == 'N/A' ? 'N/A' : $data['quizScores'][$quiz['quizID']]['score'] . '%'; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td><?php echo $data['quizCount']; ?></td>
                                        <td><strong style="color: #28a745"><?php echo $data['averageQuizScore']; ?>%</strong></td>
                                        <td><?php echo $quizWeight; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="report-footer">
                            Generated by Learnify System (Teacher: <?php echo htmlspecialchars($_SESSION['firstName']); ?>) | <?php echo date('F j, Y'); ?>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            $baseUrl = "quiz_rate.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&subjectID=$subjectID&year=$selectedYear" . ($searchQuery ? "&search=" . urlencode($searchQuery) : '');
                            $prevPage = $page - 1;
                            $nextPage = $page + 1;
                            ?>
                            <a href="<?php echo $baseUrl . '&page=' . $prevPage; ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">Previous</a>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="<?php echo $baseUrl . '&page=' . $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <a href="<?php echo $baseUrl . '&page=' . $nextPage; ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">Next</a>
                        </div>
                    <?php endif; ?>

                    <div class="download-btn-container">
                        <button class="download-btn" id="downloadReportBtn">Download PDF</button>
                    </div>
                </div>

                <div id="help-tab" class="tab-content">
                    <div class="help-content">
                        <h3>Quiz Rate Computation Guide</h3>
                        <p>The quiz rate is calculated based on the following formulas:</p>
                        <ul>
                            <li><strong>Per Quiz Score (%)</strong>: (totalScore ÷ maxScore) × 100 for each approved quiz.</li>
                            <li><strong>Average Quiz Score (%)</strong>: (Sum of all Approved Quiz Scores ÷ Sum of Maximum Possible Scores) × 100.</li>
                            <li>Only quizzes marked as <strong>approved</strong> and non-archived are included in the calculation.</li>
                            <li>If a student has no approved quizzes, their average is <strong>0%</strong>.</li>
                            <li>If a student has not submitted a quiz, the score is displayed as <strong>N/A</strong>.</li>
                        </ul>
                        <h3>Computation Steps</h3>
                        <p><strong>Step 1: Per Quiz Score</strong></p>
                        <p>For each quiz, calculate: (totalScore ÷ maxScore) × 100.</p>
                        <p><strong>Step 2: Sum Quiz Scores</strong></p>
                        <p>Add up the <strong>totalScore</strong> for all approved quizzes for a student.</p>
                        <p><strong>Step 3: Sum Maximum Scores</strong></p>
                        <p>Add up the <strong>maxScore</strong> for all approved quizzes for a student.</p>
                        <p><strong>Step 4: Calculate Average</strong></p>
                        <p>Average Quiz Score (%) = (Total Quiz Scores ÷ Total Maximum Scores) × 100</p>
                        <h3>Quiz Weight</h3>
                        <p>The admin-assigned weight for quizzes in the final grade calculation is <strong><?php echo $quizWeight; ?>%</strong>.</p>
                        <p><em>Note: The quiz average percentage shown in the report is used in the final grade computation alongside attendance and assignment averages, weighted as defined by the admin.</em></p>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Tab switching logic
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    button.classList.add('active');
                    document.getElementById(button.dataset.tab).classList.add('active');
                });
            });

            // PDF download logic
            const downloadBtn = document.getElementById('downloadReportBtn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    const element = document.getElementById('quiz-report');
                    if (!element) {
                        alert('Error: Quiz report content not found.');
                        return;
                    }
                    const safeProfessorName = '<?php echo preg_replace('/[^A-Za-z0-9_-]/', '_', $_SESSION['firstName']); ?>';
                    const timestamp = new Date().toISOString().replace(/[-:T.]/g, '').slice(0, 14);
                    html2pdf()
                        .set({
                            margin: [5, 5, 5, 5],
                            filename: `Learnify_Quiz_Rate_${safeProfessorName}_${timestamp}.pdf`,
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