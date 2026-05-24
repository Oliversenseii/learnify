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

// Validate teacherSectionID and quizID
if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT) ||
    !isset($_GET['quizID']) || !filter_var($_GET['quizID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section or quiz ID.";
    header("Location: dashQuiz.php?teacherSectionID=" . (isset($_GET['teacherSectionID']) ? $_GET['teacherSectionID'] : ''));
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];
$quizID = (int)$_GET['quizID'];

// Verify teacherSectionID belongs to the professor
$checkStmt = $dbConnection->prepare("SELECT ts.sectionID, s.sectionName, s.sectionCode, sub.subjectName, sub.subjectCode 
                                     FROM teacher_section ts 
                                     JOIN sections s ON ts.sectionID = s.sectionID 
                                     JOIN subjects sub ON ts.subjectID = sub.subjectID 
                                     WHERE ts.teacherSectionID = :teacherSectionID AND ts.teacherID = :userID AND ts.archived = 0");
$checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
$checkStmt->execute();
$section = $checkStmt->fetch(PDO::FETCH_ASSOC);
if (!$section) {
    $_SESSION['error_message'] = "Invalid section assignment.";
    header("Location: professorDash.php");
    exit;
}

// Fetch quiz details
$quizStmt = $dbConnection->prepare("SELECT quizID, title, quizType 
                                   FROM quizzes 
                                   WHERE quizID = :quizID AND teacherSectionID = :teacherSectionID AND archived = 0");
$quizStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
$quizStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$quizStmt->execute();
$quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);
if (!$quiz) {
    $_SESSION['error_message'] = "Invalid or archived quiz.";
    header("Location: dashQuiz.php?teacherSectionID=$teacherSectionID");
    exit;
}

// Fetch total points for the quiz
$totalPointsStmt = $dbConnection->prepare("SELECT SUM(points) as totalPoints 
                                          FROM questions 
                                          WHERE quizID = :quizID AND archived = 0");
$totalPointsStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
$totalPointsStmt->execute();
$totalPoints = $totalPointsStmt->fetch(PDO::FETCH_ASSOC)['totalPoints'] ?: 0;

// Fetch students and their scores
$scoresStmt = $dbConnection->prepare("SELECT u.userID AS studentID, u.firstName, u.lastName, qs.totalScore, qs.recordedDate, qs.approved 
                                     FROM users u 
                                     JOIN student_section ss ON u.userID = ss.userID 
                                     LEFT JOIN quiz_scores qs ON u.userID = qs.studentID AND qs.quizID = :quizID AND qs.archived = 0 
                                     WHERE ss.sectionID = :sectionID AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0 
                                     ORDER BY u.lastName, u.firstName");
$scoresStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
$scoresStmt->bindParam(':sectionID', $section['sectionID'], PDO::PARAM_INT);
$scoresStmt->execute();
$scores = $scoresStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle score updates and approvals
if (isset($_POST['updateScores']) && (isset($_POST['scores']) || isset($_POST['approvals']))) {
    $success = true;
    // Handle score updates
    if (isset($_POST['scores'])) {
        foreach ($_POST['scores'] as $studentID => $scoreData) {
            $studentID = filter_var($studentID, FILTER_VALIDATE_INT);
            $scoreInput = trim($scoreData['score']);
            // Skip if score is empty
            if ($scoreInput === '') {
                continue;
            }
            $score = filter_var($scoreInput, FILTER_VALIDATE_INT);
            if ($studentID && $score !== false && $score >= 0 && $score <= $totalPoints) {
                $checkScoreStmt = $dbConnection->prepare("SELECT scoreID FROM quiz_scores 
                                                         WHERE quizID = :quizID AND studentID = :studentID AND archived = 0");
                $checkScoreStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                $checkScoreStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                $checkScoreStmt->execute();

                if ($checkScoreStmt->rowCount() > 0) {
                    $updateStmt = $dbConnection->prepare("UPDATE quiz_scores 
                                                         SET totalScore = :score, maxScore = :maxScore, recordedDate = NOW() 
                                                         WHERE quizID = :quizID AND studentID = :studentID AND archived = 0");
                    $updateStmt->bindParam(':score', $score, PDO::PARAM_INT);
                    $updateStmt->bindParam(':maxScore', $totalPoints, PDO::PARAM_INT);
                    $updateStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                    $updateStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                    if (!$updateStmt->execute()) {
                        $success = false;
                    }
                } else {
                    $insertStmt = $dbConnection->prepare("INSERT INTO quiz_scores (quizID, studentID, totalScore, maxScore, recordedDate, approved) 
                                                         VALUES (:quizID, :studentID, :score, :maxScore, NOW(), 0)");
                    $insertStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                    $insertStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                    $insertStmt->bindParam(':score', $score, PDO::PARAM_INT);
                    $insertStmt->bindParam(':maxScore', $totalPoints, PDO::PARAM_INT);
                    if (!$insertStmt->execute()) {
                        $success = false;
                    }
                }
            } else {
                $success = false;
            }
        }
    }
    // Handle approval updates for all students in the section
    foreach ($scores as $score) {
        $studentID = $score['studentID'];
        $approved = isset($_POST['approvals'][$studentID]) && filter_var($_POST['approvals'][$studentID], FILTER_VALIDATE_INT) === 1 ? 1 : 0;
        if ($score['totalScore'] !== null) { // Only update approval if a score exists
            $updateStmt = $dbConnection->prepare("UPDATE quiz_scores 
                                                 SET approved = :approved 
                                                 WHERE quizID = :quizID AND studentID = :studentID AND archived = 0");
            $updateStmt->bindParam(':approved', $approved, PDO::PARAM_INT);
            $updateStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
            $updateStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
            if (!$updateStmt->execute()) {
                $success = false;
            }
        }
    }
    if ($success) {
        $_SESSION['success_message'] = "Scores and approvals updated successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to update some scores or approvals. Ensure scores are between 0 and $totalPoints.";
    }
    header("Location: score_quiz.php?teacherSectionID=$teacherSectionID&quizID=$quizID");
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
    <link rel="stylesheet" href="./utils/subjects_sidebar.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Quiz Scores</title>
    <style>
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --grey: #f3f4f6;
            --dark-grey: #9ca3af;
            --dark: #1f2937;
            --green: #10b981;
            --red: #ef4444;
            --purple: #8b5cf6;
            --light-blue: #e0f2fe;
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
        }

        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }

        .score-table {
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
            overflow-x: auto;
        }

        .score-table h3 {
            margin: 0 0 1.5rem;
            color: var(--dark);
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 700;
            border-bottom: 2px solid var(--blue);
            padding-bottom: 0.5rem;
        }

        .score-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: clamp(1rem, 3vw, 1.2rem);
            min-width: 900px;
            border-radius: 0.375rem;
            overflow: hidden;
            background-color: var(--light);
        }

        .score-table table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .score-table th,
        .score-table td {
            padding: 1rem;
            text-align: left;
        }

        .score-table th {
            background: #0056b3;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .score-table td {
            color: var(--dark);
            vertical-align: middle;
        }

        .score-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .score-table tr:hover, .score-table tr:nth-child(even):hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .score-table input[type="number"] {
            width: 100px;
            padding: 0.5rem;
            border: 1px solid var(--dark-grey);
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            background-color: var(--light);
            color: var(--dark);
        }

        .score-table input[type="number"]:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(60, 145, 230, 0.1);
            outline: none;
        }

        .score-table input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .score-table button, .back-btn, .answers-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            margin-top: 20px;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.1rem);
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .score-table button:hover, .back-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
        }

        .back-btn {
            background-color: var(--purple);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 1.5rem;
        }

        .back-btn:hover {
            background-color: #7c3aed;
        }

        .answers-btn {
            background: linear-gradient(135deg, #6f42c1, #5a32a8) !important;
            padding: 0.5rem 1rem;
        }

        .answers-btn:hover {
            background: linear-gradient(135deg, #5a32a8, #4a288f) !important;
            transform: translateY(-2px);
        }

        .success-notification, .error-notification {
            background: #10b981;
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 0.375rem;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        /* Modal Styles */
        #modal-answers {
            background: var(--light);
            border-radius: 0.5rem;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .modal-content h3 {
            margin: 0 0 1.5rem;
            color: var(--dark);
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 700;
            border-bottom: 2px solid var(--blue);
            padding-bottom: 0.5rem;
        }

        .modal-content table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: clamp(1rem, 3vw, 1.2rem);
            border-radius: 0.375rem;
            overflow: hidden;
        }

        .modal-content tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .modal-content tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .modal-content th,
        .modal-content td {
            padding: 0.75rem;
            text-align: left;
            color: var(--dark-grey);
        }

        .modal-content th {
            background: #0056b3;
            color: white;
            font-weight: 600;
        }

        .modal-content td {
            color: var(--dark);
        }

        .modal-content .correct {
            color: var(--green);
            font-weight: 600;
        }

        .modal-content .incorrect {
            color: var(--red);
            font-weight: 600;
        }

        @media screen and (max-width: 768px) {
            .score-table {
                margin: 1rem;
                padding: 1rem;
            }

            .score-table table {
                min-width: 700px;
            }

            .score-table th,
            .score-table td {
                padding: 0.75rem;
            }

            .score-table input[type="number"],
            .score-table button,
            .back-btn,
            .answers-btn {
                padding: 0.6rem 1.2rem;
            }

            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }

            .modal-content table {
                min-width: 600px;
            }
        }

        @media screen and (max-width: 480px) {

            .score-table table {
                min-width: 600px;
            }

            .score-table th,
            .score-table td {
                padding: 0.5rem;
            }

            .score-table input[type="number"],
            .score-table button,
            .back-btn,
            .answers-btn {
                padding: 0.5rem 1rem;
            }

            .modal-content {
                padding: 1rem;
            }

            .modal-content table {
                min-width: 500px;
            }

            .modal-content .close-btn {
                display: none;
            }
        }

        #pointz {
            color: green;
        }

        .modal-content .btn-container {
            float: right;
        }

        .modal-content .close-btn {
            color: var(--dark);
            padding: 0.75rem 1.5rem;
            background-color: transparent;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .modal-content .close-btn:hover {
            color: red;
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
            <i class='bx bx-menu'></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users..." required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
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
                    <h1>Quiz Scores</h1>
                    <ul class="breadcrumb">
                        <li><a href="./professorDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>">Quizzes</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Score</a></li>
                    </ul>
                </div>
                <a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Quiz Dashboard
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

            <div class="score-table">
                <h3>Scores for Quiz: <?php echo htmlspecialchars($quiz['title']); ?> <span id="pointz">(Total: <?php echo $totalPoints; ?> points)</span></h3>
                <form method="POST">
                    <div style="margin-bottom: 1rem;">
                        <label>
                            <input type="checkbox" id="checkAll" onchange="toggleAllCheckboxes()">
                            Approve All
                        </label>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Score (0-<?php echo $totalPoints; ?>)</th>
                                <th>Submitted Date</th>
                                <th>Approve</th>
                                <th>Answers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($scores)): ?>
                                <?php foreach ($scores as $index => $score): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($score['lastName'] . ', ' . $score['firstName']); ?></td>
                                        <td>
                                            <input type="number" name="scores[<?php echo $score['studentID']; ?>][score]" 
                                                   value="<?php echo htmlspecialchars($score['totalScore'] !== null ? $score['totalScore'] : ''); ?>" 
                                                   min="0" max="<?php echo $totalPoints; ?>" step="1" 
                                                   placeholder="Enter score">
                                        </td>
                                        <td><?php echo $score['recordedDate'] ? date('F j, Y, g:i A', strtotime($score['recordedDate'])) : 'Not submitted'; ?></td>
                                        <td>
                                            <input type="checkbox" name="approvals[<?php echo $score['studentID']; ?>]" 
                                                   value="1" <?php echo $score['approved'] ? 'checked' : ''; ?>
                                                   class="approval-checkbox" <?php echo $score['totalScore'] === null ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <button type="button" class="answers-btn" 
                                                    onclick="showAnswersModal(<?php echo $score['studentID']; ?>, '<?php echo htmlspecialchars($score['firstName'] . ' ' . $score['lastName']); ?>')">
                                                <i class='bx bx-detail'></i> View Answers
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No students enrolled in this section.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if (!empty($scores)): ?>
                        <button type="submit" name="updateScores">Update Scores & Approvals</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Answers Modal -->
            <div id="answersModal" class="modal">
                <div class="modal-content" id="modal-answers">
                    <div class="btn-container">
                        <button class="close-btn" onclick="closeAnswersModal()">X</button>
                    </div>
                    <h3 id="modalStudentName"></h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Answer (Letter)</th>
                                <th>Answer Text</th>
                                <th>Correct?</th>
                            </tr>
                        </thead>
                        <tbody id="modalAnswersBody"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </section>

    <script>
        function showAnswersModal(studentID, studentName) {
            const modal = document.getElementById('answersModal');
            const modalStudentName = document.getElementById('modalStudentName');
            const modalAnswersBody = document.getElementById('modalAnswersBody');

            modalStudentName.textContent = `Answers for ${studentName}`;
            modalAnswersBody.innerHTML = '<tr><td colspan="4">Loading answers...</td></tr>';

            // Fetch answers via AJAX
            fetch('get_answers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `quizID=<?php echo $quizID; ?>&studentID=${studentID}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                modalAnswersBody.innerHTML = '';
                if (data.error) {
                    modalAnswersBody.innerHTML = `<tr><td colspan="4">${data.error}</td></tr>`;
                } else if (data.length === 0) {
                    modalAnswersBody.innerHTML = `<tr><td colspan="4">No answers submitted</td></tr>`;
                } else {
                    data.forEach((answer, index) => {
                        const answerLetter = answer.answerLetter || 'N/A';
                        const answerText = answer.answerText || 'No response';
                        const isCorrect = answer.isCorrect == 1 ? 'Correct' : (answer.isCorrect == 0 ? 'Incorrect' : 'Not graded');
                        const correctClass = answer.isCorrect == 1 ? 'correct' : (answer.isCorrect == 0 ? 'incorrect' : '');
                        modalAnswersBody.innerHTML += `
                            <tr>
                                <td>${index + 1}. ${answer.questionText}</td>
                                <td>${answerLetter}</td>
                                <td>${answerText}</td>
                                <td class="${correctClass}">${isCorrect}</td>
                            </tr>
                        `;
                    });
                }
                modal.style.display = 'flex';
            })
            .catch(error => {
                console.error('Error:', error);
                modalAnswersBody.innerHTML = `<tr><td colspan="4">Error loading answers: ${error.message}</td></tr>`;
                modal.style.display = 'flex';
            });
        }

        function closeAnswersModal() {
            document.getElementById('answersModal').style.display = 'none';
        }

        function toggleAllCheckboxes() {
            const checkAll = document.getElementById('checkAll');
            const checkboxes = document.querySelectorAll('.approval-checkbox:not(:disabled)');
            checkboxes.forEach(checkbox => {
                checkbox.checked = checkAll.checked;
            });
        }

        // Close modal when clicking outside
        document.getElementById('answersModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAnswersModal();
            }
        });
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>