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

// Validate teacherSectionID and assignmentID
if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT) ||
    !isset($_GET['assignmentID']) || !filter_var($_GET['assignmentID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section or assignment ID.";
    header("Location: assignmentDash.php?teacherSectionID=" . (isset($_GET['teacherSectionID']) ? $_GET['teacherSectionID'] : ''));
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];
$assignmentID = (int)$_GET['assignmentID'];

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

// Fetch assignment details
$assignmentStmt = $dbConnection->prepare("SELECT assignmentID, title, maxScore 
                                         FROM assignments 
                                         WHERE assignmentID = :assignmentID AND teacherSectionID = :teacherSectionID AND archived = 0");
$assignmentStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
$assignmentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$assignmentStmt->execute();
$assignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC);
if (!$assignment) {
    $_SESSION['error_message'] = "Invalid or archived assignment.";
    header("Location: assignmentDash.php?teacherSectionID=$teacherSectionID");
    exit;
}

// Fetch students and their scores/submissions
$scoresStmt = $dbConnection->prepare("SELECT u.userID AS studentID, u.firstName, u.lastName, 
                                            a_s.scoreID, a_s.totalScore, a_s.recordedDate, 
                                            a_sub.fileName, a_sub.filePath, a_sub.submissionDate 
                                     FROM users u 
                                     JOIN student_section ss ON u.userID = ss.userID 
                                     LEFT JOIN assignment_scores a_s ON u.userID = a_s.studentID AND a_s.assignmentID = :assignmentID AND a_s.archived = 0 
                                     LEFT JOIN assignment_submissions a_sub ON u.userID = a_sub.studentID AND a_sub.assignmentID = :assignmentID AND a_sub.archived = 0 
                                     WHERE ss.sectionID = :sectionID AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0 
                                     ORDER BY u.lastName, u.firstName");
$scoresStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
$scoresStmt->bindParam(':sectionID', $section['sectionID'], PDO::PARAM_INT);
$scoresStmt->execute();
$scores = $scoresStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle score updates
if (isset($_POST['updateScores']) && isset($_POST['scores'])) {
    $success = true;
    foreach ($_POST['scores'] as $studentID => $scoreData) {
        $studentID = filter_var($studentID, FILTER_VALIDATE_INT);
        $scoreInput = trim($scoreData['score']);
        // Skip if score is empty
        if ($scoreInput === '') {
            continue;
        }
        $score = filter_var($scoreInput, FILTER_VALIDATE_INT);
        if ($studentID && $score !== false && $score >= 0 && $score <= $assignment['maxScore']) {
            $checkScoreStmt = $dbConnection->prepare("SELECT scoreID FROM assignment_scores 
                                                     WHERE assignmentID = :assignmentID AND studentID = :studentID AND archived = 0");
            $checkScoreStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
            $checkScoreStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
            $checkScoreStmt->execute();

            if ($checkScoreStmt->rowCount() > 0) {
                $updateStmt = $dbConnection->prepare("UPDATE assignment_scores 
                                                     SET totalScore = :score, maxScore = :maxScore, recordedDate = NOW() 
                                                     WHERE assignmentID = :assignmentID AND studentID = :studentID AND archived = 0");
                $updateStmt->bindParam(':score', $score, PDO::PARAM_INT);
                $updateStmt->bindParam(':maxScore', $assignment['maxScore'], PDO::PARAM_INT);
                $updateStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
                $updateStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                if (!$updateStmt->execute()) {
                    $success = false;
                }
            } else {
                $insertStmt = $dbConnection->prepare("INSERT INTO assignment_scores (assignmentID, studentID, totalScore, maxScore, recordedDate) 
                                                     VALUES (: bulbs, :studentID, :score, :maxScore, NOW())");
                $insertStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
                $insertStmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
                $insertStmt->bindParam(':score', $score, PDO::PARAM_INT);
                $insertStmt->bindParam(':maxScore', $assignment['maxScore'], PDO::PARAM_INT);
                if (!$insertStmt->execute()) {
                    $success = false;
                }
            }
        } else {
            $success = false;
        }
    }
    if ($success) {
        $_SESSION['success_message'] = "Scores updated successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to update some scores. Ensure scores are between 0 and {$assignment['maxScore']}.";
    }
    header("Location: score_assignment.php?teacherSectionID=$teacherSectionID&assignmentID=$assignmentID");
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
    <title>Learnify - Assignment Scores</title>
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
        }

        .score-table th,
        .score-table td {
            padding: 1rem;
            text-align: left;
            color: var(--dark-grey);
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

        .score-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .score-table input[type="number"] {
            width: 100px;
            padding: 0.5rem;
            border: 1px solid var(--dark-grey);
            background-color: var(--light);
            color: var(--dark);
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .score-table input[type="number"]:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(60, 145, 230, 0.1);
            outline: none;
        }

        .score-table button, .back-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .score-table button {
            margin-top: 20px;
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

        .download-btn, .view-btn {
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: white;
            border-radius: 0.375rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .download-btn {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }

        .download-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
        }

        .download-btn i, .view-btn i {
            margin-right: 5px;
        }

        .view-btn {
            background: linear-gradient(135deg, #6f42c1, #5a32a8);
        }

        .view-btn:hover {
            background: linear-gradient(135deg, #5a32a8, #4a288f);
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

        .modal {
            z-index: 1000000;
        }

        @media screen and (max-width: 768px) {
            .score-table {
                margin: 1rem 0rem;
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
            .download-btn {
                padding: 0.6rem 1.2rem;
            }
        }

        @media screen and (max-width: 480px) {
            .score-table table {
                font-size: 0.8rem;
                min-width: 600px;
            }

            .score-table th,
            .score-table td {
                padding: 0.5rem;
            }

            .score-table input[type="number"],
            .score-table button,
            .back-btn,
            .download-btn {
                padding: 0.5rem 1rem;
            }

            .bxs-file-doc, .bx-download {
                display: none;
            }
        }

         #pointz {
            color: green;
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
                    <h1>Assignment Scores</h1>
                    <ul class="breadcrumb">
                        <li><a href="./professorDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Assignment</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Score</a></li>
                    </ul>
                </div>
                <a href="assignmentDash.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Assignment Management
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

            <!-- <a href="assignmentDash.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-btn">
                <i class='bx bx-arrow-back'></i> Back to Assignment Management
            </a> -->

            <div class="score-table">
                <h3>Scores for Assignment: <?php echo htmlspecialchars($assignment['title']); ?> <span id="pointz">(Total: <?php echo $assignment['maxScore']; ?> points)</span> </h3>
                <form method="POST">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Score (0-<?php echo $assignment['maxScore']; ?>)</th>
                                <th>Submission Date</th>
                                <th colspan="2">Actions</th>
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
                                                   min="0" max="<?php echo $assignment['maxScore']; ?>" step="1" 
                                                   placeholder="Enter score">
                                        </td>
                                        <td><?php echo $score['submissionDate'] ? date('F j, Y, g:i A', strtotime($score['submissionDate'])) : 'Not submitted'; ?></td>
                                        <td>
                                            <?php if ($score['fileName'] && $score['filePath'] && file_exists($score['filePath'])): ?>
                                                <a href="<?php echo htmlspecialchars($score['filePath']); ?>" class="download-btn" download>
                                                    <i class='bx bx-download'></i> Download
                                                    <!-- <?php echo htmlspecialchars($score['fileName']); ?> -->
                                                </a>
                                            <?php else: ?>
                                                No file submitted
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($score['fileName'] && $score['filePath'] && file_exists($score['filePath'])): ?>
                                                <a href="view_submitted_assignments.php?file=<?php echo htmlspecialchars($score['filePath']); ?>" class="view-btn">
                                                    <i class='bx bx-download'></i> <?php echo htmlspecialchars($score['fileName']); ?>
                                                    <i class='bx bxs-file-doc'></i> View File
                                                </a>
                                            <?php else: ?>
                                                <span>-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No students enrolled in this section.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if (!empty($scores)): ?>
                        <button type="submit" name="updateScores">Update Scores</button>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
</body>
</html>