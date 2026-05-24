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
$quizStmt = $dbConnection->prepare("SELECT quizID, title, description, quizType, createdDate 
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

// Fetch questions
$questionStmt = $dbConnection->prepare("SELECT questionID, questionText, option1, option2, option3, option4, correctOption, points 
                                       FROM questions 
                                       WHERE quizID = :quizID AND archived = 0 
                                       ORDER BY questionID");
$questionStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
$questionStmt->execute();
$questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Learnify - View Quiz</title>
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

        .quiz-container {
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 0.5rem;
            overflow-x: auto;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .quiz-header {
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--blue);
            padding-bottom: 0.5rem;
        }

        .quiz-header h3 {
            margin: 0;
            color: var(--dark);
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 700;
        }

        .quiz-details {
            margin-bottom: 2rem;
            text-align: left;
            max-width: 600px;
            width: 100%;
            background-color: var(--grey);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); 
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid var(--grey);
        }

        .quiz-details p {
            margin: 0.5rem 0;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--dark);
        }

        .quiz-details p strong {
            color: var(--blue);
            font-weight: 600;
        }

        .question-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: clamp(1rem, 3vw, 1.2rem);
            background: #fff;
            border-radius: 0.375rem;
            overflow: hidden;
            min-width: 900px;
            background-color: var(--light);
        }

        .question-table th,
        .question-table td {
            padding: 1rem;
            text-align: left;
        }

        .question-table th {
            background: #0056b3;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .question-table td {
            color: var(--dark);
            vertical-align: top;
        }

        .question-table tr:nth-child(even) {
             background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .question-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .options-list {
            margin: 0;
            padding-left: 1.5rem;
            list-style-type: decimal;
        }

        .options-list li {
            margin: 0.3rem 0;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .options-list li.correct {
            color: var(--green);
            font-weight: 600;
        }

        .back-btn {
            background-color: var(--purple);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            float: right;
            align-items: center;
            gap: 0.5rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin: 2.5rem;
        }

        .back-btn:hover {
            background-color: #7c3aed;
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
            background-color: var(--red);
        }

        .modal {
            z-index: 1000000;
        }

        @media screen and (max-width: 768px) {
            .quiz-container {
                margin: 1rem 0rem;
                padding: 1rem;
            }
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
                    <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
                    <ul class="breadcrumb">
                        <li><a href="./professorDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>">Quizzes</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">View</a></li>
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

            <!-- <a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-btn">
                <i class='bx bx-arrow-back'></i> Back to Quiz Management
            </a> -->

            <div class="quiz-container">
                <div class="quiz-header">
                    <h3><?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h3>
                </div>
                <div class="quiz-details">
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($quiz['quizType']); ?></p>
                    <p><strong>Description:</strong> <?php echo $quiz['description'] ? htmlspecialchars($quiz['description']) : 'No description'; ?></p>
                    <p><strong>Created:</strong> <?php echo date('F j, Y', strtotime($quiz['createdDate'])); ?></p>
                </div>
                <h3 style="margin: 1rem 0; color: var(--dark); font-weight: 600;">Questions</h3>
                <?php if (!empty($questions)): ?>
                    <table class="question-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Question</th>
                                <th>Options</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questions as $qIndex => $question): ?>
                                <tr>
                                    <td><?php echo $qIndex + 1; ?></td>
                                    <td><?php echo htmlspecialchars($question['questionText']); ?></td>
                                    <td>
                                        <?php if ($quiz['quizType'] !== 'Essay'): ?>
                                            <ul class="options-list">
                                                <li class="<?php echo $question['correctOption'] == 1 ? 'correct' : ''; ?>">
                                                    <?php echo htmlspecialchars($question['option1']); ?>
                                                    <?php echo $question['correctOption'] == 1 ? '(Correct)' : ''; ?>
                                                </li>
                                                <li class="<?php echo $question['correctOption'] == 2 ? 'correct' : ''; ?>">
                                                    <?php echo htmlspecialchars($question['option2']); ?>
                                                    <?php echo $question['correctOption'] == 2 ? '(Correct)' : ''; ?>
                                                </li>
                                                <?php if ($quiz['quizType'] === 'Multiple Choice'): ?>
                                                    <li class="<?php echo $question['correctOption'] == 3 ? 'correct' : ''; ?>">
                                                        <?php echo htmlspecialchars($question['option3']); ?>
                                                        <?php echo $question['correctOption'] == 3 ? '(Correct)' : ''; ?>
                                                    </li>
                                                    <li class="<?php echo $question['correctOption'] == 4 ? 'correct' : ''; ?>">
                                                        <?php echo htmlspecialchars($question['option4']); ?>
                                                        <?php echo $question['correctOption'] == 4 ? '(Correct)' : ''; ?>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p>N/A (Essay)</p>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $question['points']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: var(--dark-grey); font-size: clamp(1rem, 3vw, 1.2rem);">No questions found for this quiz.</p>
                <?php endif; ?>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
</body>
</html>