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

// Validate teacherSectionID
if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section ID.";
    header("Location: professorDash.php");
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];
$selectedYear = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : 2025;

// Verify teacherSectionID belongs to the professor
$checkStmt = $dbConnection->prepare("SELECT ts.sectionID, ts.subjectID, s.sectionName, s.sectionCode, sub.subjectName, sub.subjectCode 
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

try {
    // Handle quiz archiving
    if (isset($_GET['archiveQuiz']) && isset($_GET['quizID'])) {
        $quizID = filter_var($_GET['quizID'], FILTER_VALIDATE_INT);
        if ($quizID) {
            $checkQuizStmt = $dbConnection->prepare("SELECT quizID FROM quizzes WHERE quizID = :quizID AND teacherSectionID = :teacherSectionID AND archived = 0");
            $checkQuizStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
            $checkQuizStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
            $checkQuizStmt->execute();
            if ($checkQuizStmt->rowCount() > 0) {
                $archiveStmt = $dbConnection->prepare("UPDATE quizzes SET archived = 1 WHERE quizID = :quizID");
                $archiveStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                if ($archiveStmt->execute()) {
                    $_SESSION['success_message'] = "Quiz archived successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to archive quiz.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid quiz.";
            }
        }
        header("Location: dashQuiz.php?teacherSectionID=$teacherSectionID&subjectID=" . htmlspecialchars($section['subjectID']) . "&year=$selectedYear");
        exit;
    }

    // Fetch quizzes
    $quizStmt = $dbConnection->prepare("SELECT quizID, title, description, quizType, createdDate, dueDate 
                                   FROM quizzes 
                                   WHERE teacherSectionID = :teacherSectionID AND archived = 0 
                                   ORDER BY createdDate DESC");
    $quizStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $quizStmt->execute();
    $quizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($quizzes)) {
        error_log("No quizzes found for teacherSectionID: $teacherSectionID");
    } else {
        error_log("Found " . count($quizzes) . " quizzes for teacherSectionID: $teacherSectionID");
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
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
    <title>Learnify - Quiz Management</title>
    <style>
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        .btn-download {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.2rem);
            transition: background-color 0.3s;
        }
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

        .admin-table {
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
            overflow-x: auto;
        }

        .admin-table h2 {
            margin: 0 0 1.5rem;
            color: var(--dark);
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 700;
            border-bottom: 2px solid var(--dark-grey);
            padding-bottom: 0.5rem;
        }

        .admin-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: clamp(1rem, 3vw, 1.2rem);
            min-width: 900px;
            border-radius: 0.375rem;
            overflow: hidden;
        }

        .admin-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .admin-table th,
        .admin-table td {
            padding: 1rem;
            text-align: left;
        }

        .admin-table th {
            background: #0056b3;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .admin-table td {
            color: var(--dark);
            vertical-align: middle;
        }

        .admin-table tr:hover {
            background-color: var(--grey);
            transition: background-color 0.2s ease;
        }

        .create-btn, .rate-btn {
            float: right;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.375rem;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: clamp(1rem, 3vw, 1.1rem);
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin: -0.1rem;
        }

        .edit-btn, .archive-btn, .score-btn, .view-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.375rem;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: clamp(1rem, 3vw, 1.1rem);
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin: 0.3rem;
        }

        .edit-btn {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: black;
        }

        .archive-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .create-btn:hover, .score-btn:hover, .view-btn:hover, .rate-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
        }

        .edit-btn:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
            transform: translateY(-2px);
        }

        .archive-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
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

        .modal {
            z-index: 1000000;
        }

        @media screen and (max-width: 768px) {
            .admin-table {
                margin: 1rem;
                padding: 1rem;
            }

            .admin-table table {
                min-width: 700px;
            }

            .admin-table th,
            .admin-table td {
                padding: 0.75rem;
            }

            .create-btn, .edit-btn, .archive-btn, .score-btn, .view-btn, .rate-btn {
                padding: 0.5rem 1rem;
            }
        }

        @media screen and (max-width: 480px) {
            .admin-table table {
                min-width: 600px;
            }

            .admin-table th,
            .admin-table td {
                padding: 0.5rem;
            }

            .create-btn, .edit-btn, .archive-btn, .score-btn, .view-btn, .rate-btn {
                padding: 0.4rem 0.8rem;
            }

            .dash-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-bottom: 10px;
            }

            .dash-container a {
                width: 100%;
            }
        }

        .dash-container a {
            margin-bottom: 10px;
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
                    <h1>Quiz Dashboard</h1>
                    <ul class="breadcrumb">
                        <li><a href="./professorDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Quizzes</a></li>
                    </ul>
                </div>
                <a href="./professorDash.php?subjectID=<?php echo htmlspecialchars($section['subjectID']); ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Teacher Management
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

            <div class="admin-table">
                <h2><?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h2>
                <div class="dash-container">
                    <a href="quiz_rate.php?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $section['sectionID']; ?>&subjectID=<?php echo $section['subjectID']; ?>&year=<?php echo $selectedYear; ?>" class="rate-btn">
                        <i class='bx bx-stats'></i> Quiz Rate
                    </a>
                    <a href="create_quiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="create-btn" 
                    style="margin-right: 10px;">
                        <i class='bx bx-plus'></i> Create New Quiz
                    </a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Created</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($quizzes)): ?>
                            <?php foreach ($quizzes as $index => $quiz): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                    <td><?php echo htmlspecialchars($quiz['quizType']); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($quiz['createdDate'])); ?></td>
                                    <td><?php echo $quiz['dueDate'] ? date('F j, Y, g:i A', strtotime($quiz['dueDate'])) : 'Not set'; ?></td>
                                    <td>
                                        <a href="view_quiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>&quizID=<?php echo $quiz['quizID']; ?>" class="view-btn">
                                            <i class='bx bx-show'></i> View
                                        </a>
                                        <a href="edit_quiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>&quizID=<?php echo $quiz['quizID']; ?>" class="edit-btn">
                                            <i class='bx bx-edit'></i> Edit
                                        </a>
                                        <a href="score_quiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>&quizID=<?php echo $quiz['quizID']; ?>" class="score-btn">
                                            <i class='bx bx-list-ul'></i> Points
                                        </a>
                                        <a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>&archiveQuiz=1&quizID=<?php echo $quiz['quizID']; ?>&year=<?php echo $selectedYear; ?>" 
                                           class="archive-btn" 
                                           onclick="return confirm('Are you sure you want to archive this quiz?');">
                                            <i class='bx bx-archive'></i> Archive
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No quizzes found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
</body>
</html>