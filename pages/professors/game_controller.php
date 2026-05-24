<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';

// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');
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
        $_SESSION['firstName'] = htmlspecialchars(strtoupper($user['firstName']));
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
    } else {
        header("Location: ../../index.php");
        exit;
    }
    // Handle search
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'games';
    // Fetch games
    $gameStmt = $dbConnection->prepare("
        SELECT gameID, title, description, gameCode, isActive
        FROM learn_quiz_games
        WHERE professorID = :professorID AND title LIKE :search
    ");
    $gameStmt->bindParam(':professorID', $userID, PDO::PARAM_INT);
    $gameStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $gameStmt->execute();
    $games = $gameStmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch questions with game title and question number
    $questionStmt = $dbConnection->prepare("
        SELECT q.questionID, q.gameID, g.title AS gameTitle, q.questionText,
               q.optionA, q.optionB, q.optionC, q.optionD, q.correctAnswer, q.timeLimit, q.points,
               (SELECT COUNT(*) FROM learn_quiz_questions q2 WHERE q2.gameID = q.gameID AND q2.questionID <= q.questionID) AS questionNumber
        FROM learn_quiz_questions q
        JOIN learn_quiz_games g ON q.gameID = g.gameID
        WHERE g.professorID = :professorID AND (q.questionText LIKE :search OR g.title LIKE :search OR q.optionA LIKE :search OR q.optionB LIKE :search OR q.optionC LIKE :search OR q.optionD LIKE :search)
    ");
    $questionStmt->bindParam(':professorID', $userID, PDO::PARAM_INT);
    $questionStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $questionStmt->execute();
    $questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch progress for a specific game
    $progress = [];
    $studentAnswers = [];
    if (isset($_GET['gameID'])) {
        $gameID = filter_var($_GET['gameID'], FILTER_VALIDATE_INT);
       
        // Fetch student progress with firstName, lastName, image, and correct answers count
        $progressStmt = $dbConnection->prepare("
            SELECT u.userID, u.firstName, u.lastName, u.image, qs.sessionID,
                   SUM(CASE WHEN qa.isCorrect = 1 THEN 1 ELSE 0 END) as correctAnswers,
                   (SELECT COUNT(*) FROM learn_quiz_questions WHERE gameID = :gameID) as totalQuestions,
                   COUNT(qa.answerID) as questionsAnswered
            FROM learn_quiz_sessions qs
            JOIN users u ON qs.userID = u.userID
            LEFT JOIN learn_quiz_answers qa ON qs.sessionID = qa.sessionID
            WHERE qs.gameID = :gameID
            GROUP BY qs.sessionID
            ORDER BY u.lastName ASC, u.firstName ASC
        ");
        $progressStmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
        $progressStmt->execute();
        $progress = $progressStmt->fetchAll(PDO::FETCH_ASSOC);
        // Fetch student answers for the modal, including points
        $answersStmt = $dbConnection->prepare("
            SELECT u.userID, u.firstName, u.lastName, qs.sessionID, q.questionText, qa.userAnswer,
                   qa.isCorrect, q.correctAnswer, q.optionA, q.optionB, q.optionC, q.optionD, q.points,
                   (SELECT COUNT(*) FROM learn_quiz_questions q2 WHERE q2.gameID = q.gameID AND q2.questionID <= q.questionID) AS questionNumber
            FROM learn_quiz_sessions qs
            JOIN users u ON qs.userID = u.userID
            JOIN learn_quiz_answers qa ON qs.sessionID = qa.sessionID
            JOIN learn_quiz_questions q ON qa.questionID = q.questionID
            WHERE qs.gameID = :gameID
            ORDER BY u.lastName ASC, u.firstName ASC, q.questionID
        ");
        $answersStmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
        $answersStmt->execute();
        $studentAnswers = $answersStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Fetch games for dropdowns
    $allGamesStmt = $dbConnection->prepare("SELECT gameID, title FROM learn_quiz_games WHERE professorID = :professorID");
    $allGamesStmt->bindParam(':professorID', $userID, PDO::PARAM_INT);
    $allGamesStmt->execute();
    $allGames = $allGamesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
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
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/game_table.css">
    <link rel="stylesheet" href="./utils/notification.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Quizizz Controller</title>
    <style>
        :root {
         --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
         --hover: #d5d5d5;
         --secondary-hover: #e6f0ff;
         --secondary-grey: #f8faff;
      }
      body.dark {
         --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }
        .tabs {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .tab {
            padding: 0.75rem 1.5rem;
            background: var(--light);
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.2rem);
            margin-top: 10px;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .tab.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        .tab:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            color: #ffffff;
        }
        .btn-toggle, .btn-edit, .btn-add, .btn-add-question, .btn-view-answers, .btn-copy, .btn-view-progress {
            padding: 7px 12px;
            margin-left: 5px;
            border: none;
            border-radius: 5px;
            font-size: clamp(1rem, 3vw, 1.1rem);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .btn-toggle {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #ffffff;
        }
        .btn-toggle.activate {
            background: linear-gradient(135deg, #28a745, #218838);
        }
        .btn-edit, .btn-view-answers, .btn-copy, .btn-view-progress {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: black;
        }
        .btn-add, .btn-add-question {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }
        .btn-toggle:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        .btn-toggle.activate:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }
        .btn-edit:hover, .btn-view-answers:hover, .btn-copy:hover, .btn-view-progress:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
        }
        .btn-add:hover, .btn-add-question:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }
        .success-notification, .error-notification, .copy-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 15px;
            margin: 1.5rem;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            position: fixed;
            bottom: 10px;
            right: 20px;
            z-index: 1000;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }
        .error-notification {
            background-color: var(--red);
        }
        .modal {
            z-index: 1000000;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .table-filter select, .table-filter input {
            background-color: var(--grey);
            color: var(--dark);
            padding: 0.5rem;
            border: 1px solid var(--grey);
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            max-width: 200px;
        }
        .modal-content {
            max-width: 600px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-sizing: border-box;
            transition: max-width 0.3s ease-in-out, max-height 0.3s ease-in-out, width 0.3s ease-in-out, height 0.3s ease-in-out, padding 0.3s ease-in-out;
            animation: slideIn 0.3s ease-in-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-fullscreen .modal-content {
            max-width: 100vw;
            max-height: 100vh;
            width: 100vw;
            height: 100vh;
            padding: 2rem;
            border-radius: 0;
            box-shadow: none;
        }
        .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            padding: 1.5rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 600;
        }
        .modal-header .close-btn, .modal-header .fullscreen-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: clamp(1.2rem, 3vw, 2rem);
            cursor: pointer;
            transition: color 0.2s ease;
            margin-left: 0.5rem;
        }
        .modal-header .close-btn:hover, .modal-header .fullscreen-btn:hover {
            color: var(--red);
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-body label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 900;
            text-align: left;
        }
        .modal-body input, .modal-body select, .modal-body textarea {
            width: 100%;
            padding: 0.7rem;
            margin-bottom: 1rem;
            background-color: var(--grey);
            color: var(--dark);
            border: 1px solid var(--grey);
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }
        .modal-body textarea {
            height: 100px;
            resize: vertical;
        }
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--grey);
            display: flex;
            justify-content: flex-end;
            background: var(--light);
        }
        .modal-footer button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.1rem);
            font-weight: 500;
            cursor: pointer;
        }
        .modal-footer .save-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }
        .modal-footer .save-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }
        .modal-footer .close-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            margin-left: 0.5rem;
        }
        .modal-footer .close-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        .table-filter {
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .question-fieldset {
            border: 1px solid var(--dark-grey);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.375rem;
            position: relative;
        }
        .question-fieldset legend {
            font-weight: 500;
            padding: 0 0.5rem;
        }
        .remove-question-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            border-radius: 0.375rem;
            padding: 0.2rem 0.5rem;
            cursor: pointer;
        }
        .remove-question-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        #games-table, #questions-table, #progress-table {
            background-color: var(--light);
        }
        #games-table tr:nth-child(even),
        #questions-table tr:nth-child(even),
        #progress-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        #games-table tr:hover,
        #questions-table tr:hover ,
        #progress-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }
        #games-table td,
        #questions-table td,
        #progress-table td {
            border: none;
        }
        #games-table ,
        #questions-table ,
        #progress-table {
            font-size: clamp(1rem, 3vw, 1.1rem);
        }
        .answers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .answers-table th, .answers-table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--grey);
            color: var(--dark);
        }
        .answers-table th {
            background-color: var(--grey);
            font-weight: 600;
            color: var(--dark);
        }
        .answers-table tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
        .correct {
            color: #4CAF50 !important;
            font-weight: bold;
        }
        .incorrect {
            color: var(--red) !important;
            font-weight: bold;
        }
        .qr-code-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            vertical-align: middle;
        }
        @media screen and (max-width: 768px) {
            .tabs {
                flex-direction: column;
                align-items: center;
            }
            .tab {
                width: 100%;
                text-align: center;
                padding: 0.5rem;
            }
            .btn-toggle, .btn-edit, .btn-add, .btn-add-question, .btn-view-answers, .btn-copy, .btn-view-progress {
                padding: 5px 8px;
            }
            .table-filter {
                flex-direction: column;
                align-items: stretch;
            }
            .table-filter select, .table-filter input {
                padding: 0.3rem;
            }
            .modal-content {
                max-width: 95%;
                padding: 0.8rem;
            }
            .modal-fullscreen .modal-content {
                max-width: 100vw;
                max-height: 100vh;
                width: 100vw;
                height: 100vh;
                padding: 0.8rem;
            }
            .modal-body {
                padding: 0.8rem;
            }
            .modal-body input, .modal-body select, .modal-body textarea {
                padding: 0.3rem;
            }
            .modal-footer button {
                padding: 0.5rem 1rem;
            }
            .question-fieldset {
                padding: 0.6rem;
            }
            .remove-question-btn {
                padding: 0.15rem 0.3rem;
            }
            .admin-table {
                overflow-x: auto;
                padding: 0;
                margin: 1rem 0rem;
                padding: 1rem;
            }
            table {
                min-width: 600px;
            }
            .qr-code-img {
                width: 40px;
                height: 40px;
            }
        }
        @media screen and (max-width: 480px) {
            .tabs {
                gap: 0.3rem;
            }
            .tab {
                padding: 0.4rem;
            }
           
            .btn-toggle, .btn-edit, .btn-add, .btn-add-question, .btn-view-answers, .btn-copy, .btn-view-progress {
                padding: 4px 6px;
            }
            .success-notification, .error-notification, .copy-notification {
                padding: 8px;
                margin: 0.8rem;
            }
            
            .modal-body {
                padding: 0.6rem;
            }
            .modal-body input, .modal-body select, .modal-body textarea {
                padding: 0.25rem;
            }
            .modal-footer button {
                padding: 0.4rem 0.8rem;
            }
            .question-fieldset {
                padding: 0.5rem;
            }
            .remove-question-btn {
                padding: 0.1rem 0.25rem;
            }
            .qr-code-img {
                width: 30px;
                height: 30px;
            }
        }
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li><a href="./professor_main_dash.php"><i class='bx bxs-dashboard'></i><span class="text">Dashboard</span></a></li>
            <li><a href="./modules.php"><i class='bx bxs-bookmark'></i><span class="text">Modules</span></a></li>
            <li><a href="./calendar.php"><i class='bx bxs-calendar'></i><span class="text">Calendar</span></a></li>
            <li><a href="./message_admin.php"><i class='bx bxs-message'></i><span class="text">Message Admin</span></a></li>
            <li class="active"><a href="./game_controller.php"><i class='bx bxs-game'></i><span class="text">Game</span></a></li>
        </ul>
        <ul class="side-menu">
            <li><a href="./settings.php"><i class='bx bxs-cog'></i><span class="text">Settings</span></a></li>
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
                    <h1>Brainzap Controller</h1>
                    <ul class="breadcrumb">
                        <li><a href="./professor_main_dash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./game_controller.php">Brainzap Controller</a></li>
                    </ul>
                </div>
            </div>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification" id="success-notification">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification" id="error-notification">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            <div class="tabs">
                <a href="?tab=games<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'games' ? 'active' : ''; ?>"><i class='bx bxs-joystick'></i> Games</a>
                <a href="?tab=questions<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'questions' ? 'active' : ''; ?>"><i class='bx bxs-help-circle'></i> Questions</a>
                <a href="?tab=progress<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'progress' ? 'active' : ''; ?>"><i class='bx bxs-bar-chart-alt-2'></i> Progress</a>
            </div>
            <!-- Games Section -->
            <?php if ($activeTab == 'games'): ?>
                <div class="admin-table">
                    <div class="table-filter">
                        <select onchange="filterTable('games-table', this, 0)">
                            <option value="">All Titles</option>
                            <?php
                            $uniqueTitles = array_unique(array_column($games, 'title'));
                            sort($uniqueTitles);
                            foreach ($uniqueTitles as $title): ?>
                                <option value="<?php echo htmlspecialchars($title); ?>"><?php echo htmlspecialchars($title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="filterTable('games-table', this, 4)">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <button class="btn-add" onclick="openModal('add-game-modal')"><i class='bx bxs-plus-circle'></i> Add Game</button>
                    <table id="games-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Game Code</th>
                                <th>QR Code</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($games)): ?>
                                <?php foreach ($games as $game): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($game['title']); ?></td>
                                        <td><?php
                                            $description = htmlspecialchars($game['description']);
                                            if (strlen($description) > 40) {
                                                $description = substr($description, 0, 20) . "...";
                                            }
                                            echo $description;
                                        ?></td>
                                        <td>
                                            <span><?php echo htmlspecialchars($game['gameCode']); ?></span>
                                            <button class="btn-copy" onclick="copyToClipboard('<?php echo htmlspecialchars($game['gameCode']); ?>')">
                                                <i class='bx bxs-copy'></i> Copy
                                            </button>
                                        </td>
                                        <td>
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($game['gameCode']); ?>" alt="QR Code" class="qr-code-img">
                                        </td>
                                        <td><?php echo $game['isActive'] ? 'Active' : 'Inactive'; ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditGameModal(<?php echo $game['gameID']; ?>, '<?php echo htmlspecialchars($game['title']); ?>', '<?php echo htmlspecialchars($game['description']); ?>', '<?php echo htmlspecialchars($game['gameCode']); ?>', <?php echo $game['isActive']; ?>)">
                                                <i class='bx bxs-edit'></i> Edit
                                            </button>
                                            <form action="manage_quiz.php" method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_game">
                                                <input type="hidden" name="gameID" value="<?php echo $game['gameID']; ?>">
                                                <input type="hidden" name="isActive" value="<?php echo $game['isActive']; ?>">
                                                <button type="submit" class="btn-toggle <?php echo $game['isActive'] ? '' : 'activate'; ?>">
                                                    <i class='bx bx-power-off'></i> <?php echo $game['isActive'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                            <a href="?tab=progress&gameID=<?php echo $game['gameID']; ?>" class="btn-view-progress">
                                                <i class='bx bxs-show'></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No games found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Add Game Modal -->
                <div id="add-game-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Add Game</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('add-game-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('add-game-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_quiz.php" method="post">
                                <input type="hidden" name="action" value="add_game">
                                <label>Game Title</label>
                                <input type="text" name="title" required>
                                <label>Description</label>
                                <textarea name="description"></textarea>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('add-game-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Edit Game Modal -->
                <div id="edit-game-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Game</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('edit-game-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('edit-game-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_quiz.php" method="post">
                                <input type="hidden" name="action" value="edit_game">
                                <input type="hidden" name="gameID" id="edit-game-id">
                                <label>Game Title</label>
                                <input type="text" name="title" id="edit-game-title" required>
                                <label>Description</label>
                                <textarea name="description" id="edit-game-description"></textarea>
                                <label>Game Code</label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <input type="text" name="gameCode" id="edit-game-code" readonly>
                                    <button type="button" class="btn-copy" onclick="copyToClipboard(document.getElementById('edit-game-code').value)">
                                        <i class='bx bxs-copy'></i> Copy
                                    </button>
                                </div>
                                <label>Status</label>
                                <select name="isActive" id="edit-game-status" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('edit-game-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Questions Section -->
            <?php if ($activeTab == 'questions'): ?>
                <div class="admin-table">
                    <div class="table-filter">
                        <input type="text" id="question-search" placeholder="Search questions..." oninput="searchQuestions('questions-table', this)">
                        <select onchange="filterTable('questions-table', this, 1)">
                            <option value="">All Games</option>
                            <?php
                            $uniqueGames = array_unique(array_column($questions, 'gameTitle'));
                            sort($uniqueGames);
                            foreach ($uniqueGames as $gameTitle): ?>
                                <option value="<?php echo htmlspecialchars($gameTitle); ?>"><?php echo htmlspecialchars($gameTitle); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="filterTable('questions-table', this, 7)">
                            <option value="">All Correct Answers</option>
                            <?php
                            $uniqueCorrectAnswers = array_unique(array_column($questions, 'correctAnswer'));
                            sort($uniqueCorrectAnswers);
                            foreach ($uniqueCorrectAnswers as $correctAnswer): ?>
                                <option value="<?php echo htmlspecialchars($correctAnswer); ?>"><?php echo htmlspecialchars($correctAnswer); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn-add" onclick="openModal('add-question-modal')">Add Question(s)</button>
                    <table id="questions-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Game</th>
                                <th>Question Text</th>
                                <th>Option A</th>
                                <th>Option B</th>
                                <th>Option C</th>
                                <th>Option D</th>
                                <th>Correct Answer</th>
                                <th>Time Limit</th>
                                <th>Points</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($questions)): ?>
                                <?php foreach ($questions as $question): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($question['questionNumber']); ?></td>
                                        <td><?php echo htmlspecialchars($question['gameTitle']); ?></td>
                                        <td><?php
                                            $text = htmlspecialchars($question['questionText']);
                                            if (strlen($text) > 40) {
                                                $text = substr($text, 0, 40) . "...";
                                            }
                                            echo $text;
                                        ?></td>
                                        <td><?php
                                            $optionA = htmlspecialchars($question['optionA']);
                                            if (strlen($optionA) > 40) {
                                                $optionA = substr($optionA, 0, 40) . "...";
                                            }
                                            echo $optionA;
                                        ?></td>
                                        <td><?php
                                            $optionB = htmlspecialchars($question['optionB']);
                                            if (strlen($optionB) > 40) {
                                                $optionB = substr($optionB, 0, 40) . "...";
                                            }
                                            echo $optionB;
                                        ?></td>
                                        <td><?php
                                            $optionC = htmlspecialchars($question['optionC']);
                                            if (strlen($optionC) > 40) {
                                                $optionC = substr($optionC, 0, 40) . "...";
                                            }
                                            echo $optionC;
                                        ?></td>
                                        <td><?php
                                            $optionD = htmlspecialchars($question['optionD']);
                                            if (strlen($optionD) > 40) {
                                                $optionD = substr($optionD, 0, 40) . "...";
                                            }
                                            echo $optionD;
                                        ?></td>
                                        <td><?php echo htmlspecialchars($question['correctAnswer']); ?></td>
                                        <td><?php echo htmlspecialchars($question['timeLimit']); ?></td>
                                        <td><?php echo htmlspecialchars($question['points']); ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditQuestionModal(<?php echo $question['questionID']; ?>, <?php echo $question['gameID']; ?>, '<?php echo htmlspecialchars($question['questionText']); ?>', '<?php echo htmlspecialchars($question['optionA']); ?>', '<?php echo htmlspecialchars($question['optionB']); ?>', '<?php echo htmlspecialchars($question['optionC']); ?>', '<?php echo htmlspecialchars($question['optionD']); ?>', '<?php echo htmlspecialchars($question['correctAnswer']); ?>', <?php echo $question['timeLimit']; ?>, <?php echo $question['points']; ?>)">
                                                <i class='bx bxs-edit'></i>
                                            </button>
                                            <a href="manage_quiz.php?action=delete_question&questionID=<?php echo $question['questionID']; ?>" class="btn-toggle" onclick="return confirm('Are you sure you want to delete this question?');">
                                                <i class='bx bxs-trash'></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11">No questions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Add Question Modal -->
                <div id="add-question-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Add Question(s)</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('add-question-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('add-question-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_quiz.php" method="post">
                                <input type="hidden" name="action" value="add_question">
                                <label>Game</label>
                                <select name="gameID" id="add-question-game" required>
                                    <?php foreach ($allGames as $game): ?>
                                        <option value="<?php echo $game['gameID']; ?>"><?php echo htmlspecialchars($game['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="questions-container">
                                    <fieldset class="question-fieldset">
                                        <legend>Question 1</legend>
                                        <button type="button" class="remove-question-btn" onclick="removeQuestionField(this)" style="display: none;"><i class='bx bx-x'></i></button>
                                        <label>Question Text</label>
                                        <textarea name="questions[0][questionText]" required></textarea>
                                        <label>Option A</label>
                                        <input type="text" name="questions[0][optionA]" required>
                                        <label>Option B</label>
                                        <input type="text" name="questions[0][optionB]" required>
                                        <label>Option C</label>
                                        <input type="text" name="questions[0][optionC]" required>
                                        <label>Option D</label>
                                        <input type="text" name="questions[0][optionD]" required>
                                        <label>Correct Answer</label>
                                        <select name="questions[0][correctAnswer]" required>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="D">D</option>
                                        </select>
                                        <label>Time Limit (seconds)</label>
                                        <input type="number" name="questions[0][timeLimit]" id="add-question-timeLimit" value="30" min="5" required>
                                        <label>Points</label>
                                        <input type="number" name="questions[0][points]" id="add-question-points" value="100" min="10" required>
                                    </fieldset>
                                </div>
                                <button type="button" class="btn-add-question" onclick="addQuestionField()">Add Another Question</button>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('add-question-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Edit Question Modal -->
                <div id="edit-question-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Question</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('edit-question-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('edit-question-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_quiz.php" method="post">
                                <input type="hidden" name="action" value="edit_question">
                                <input type="hidden" name="questionID" id="edit-question-id">
                                <label>Game</label>
                                <select name="gameID" id="edit-question-game" required>
                                    <?php foreach ($allGames as $game): ?>
                                        <option value="<?php echo $game['gameID']; ?>"><?php echo htmlspecialchars($game['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Question Text</label>
                                <textarea name="questionText" id="edit-question-text" required></textarea>
                                <label>Option A</label>
                                <input type="text" name="optionA" id="edit-question-optionA" required>
                                <label>Option B</label>
                                <input type="text" name="optionB" id="edit-question-optionB" required>
                                <label>Option C</label>
                                <input type="text" name="optionC" id="edit-question-optionC" required>
                                <label>Option D</label>
                                <input type="text" name="optionD" id="edit-question-optionD" required>
                                <label>Correct Answer</label>
                                <select name="correctAnswer" id="edit-question-correct" required>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                                <label>Time Limit (seconds)</label>
                                <input type="number" name="timeLimit" id="edit-question-timeLimit" min="5" required>
                                <label>Points</label>
                                <input type="number" name="points" id="edit-question-points" min="10" required>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('edit-question-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Progress Section -->
            <?php if ($activeTab == 'progress'): ?>
                <div class="admin-table">
                    <div class="table-filter">
                        <select name="gameID" onchange="this.form.submit()" form="progress-form">
                            <option value="">Select a game</option>
                            <?php foreach ($allGames as $game): ?>
                                <option value="<?php echo $game['gameID']; ?>" <?php echo isset($_GET['gameID']) && $_GET['gameID'] == $game['gameID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <form id="progress-form" action="game_controller.php" method="get" style="display: none;">
                            <input type="hidden" name="tab" value="progress">
                        </form>
                    </div>
                    <table id="progress-table">
                        <thead>
                            <tr>
                                <th>Profile</th>
                                <th>Student</th>
                                <th>Score</th>
                                <th>Questions Answered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($progress)): ?>
                                <?php foreach ($progress as $row): ?>
                                    <tr>
                                        <td><img src="<?php echo htmlspecialchars($row['image']); ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;"></td>
                                        <td><?php echo htmlspecialchars(strtoupper($row['lastName']) . ', ' . strtoupper($row['firstName'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['correctAnswers'] . ' / ' . $row['totalQuestions']); ?></td>
                                        <td><?php echo htmlspecialchars($row['questionsAnswered'] . ' / ' . $row['totalQuestions']); ?></td>
                                        <td>
                                            <button class="btn-view-answers" onclick="openStudentAnswersModal(<?php echo $row['sessionID']; ?>, '<?php echo htmlspecialchars(strtoupper($row['lastName']) . ', ' . strtoupper($row['firstName'])); ?>')">
                                                <i class='bx bxs-show'></i> View Answers
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No progress data available for the selected game.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Student Answers Modal -->
                <div id="student-answers-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="student-answers-title">Student Answers</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('student-answers-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('student-answers-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <table class="answers-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Question</th>
                                        <th>Student's Answer</th>
                                        <th>Correct Answer</th>
                                        <th>Points</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="student-answers-body">
                                    <!-- Answers will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="close-btn" onclick="closeModal('student-answers-modal')">Close</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </section>
    <script>
        // Auto-hide notifications after 10 seconds
        document.addEventListener('DOMContentLoaded', () => {
            const successNotification = document.getElementById('success-notification');
            const errorNotification = document.getElementById('error-notification');
            if (successNotification) {
                setTimeout(() => {
                    successNotification.style.transition = 'opacity 0.5s';
                    successNotification.style.opacity = '0';
                    setTimeout(() => successNotification.remove(), 500);
                }, 10000);
            }
            if (errorNotification) {
                setTimeout(() => {
                    errorNotification.style.transition = 'opacity 0.5s';
                    errorNotification.style.opacity = '0';
                    setTimeout(() => errorNotification.remove(), 500);
                }, 10000);
            }
        });
        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const notification = document.createElement('div');
                notification.className = 'copy-notification';
                notification.textContent = 'Game code copied to clipboard!';
                document.body.appendChild(notification);
                setTimeout(() => {
                    notification.style.transition = 'opacity 0.5s';
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 500);
                }, 3000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
        // Student answers data
        const studentAnswers = <?php echo json_encode($studentAnswers); ?>;
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('modal-fullscreen');
            modal.querySelector('.fullscreen-btn i').className = 'bx bx-fullscreen';
            modal.style.display = 'flex';
            if (modalId === 'add-question-modal') {
                resetQuestionFields();
            }
        }
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('modal-fullscreen');
            modal.querySelector('.fullscreen-btn i').className = 'bx bx-fullscreen';
            modal.style.display = 'none';
        }
        function toggleFullScreen(modalId) {
            const modal = document.getElementById(modalId);
            const fullscreenBtn = modal.querySelector('.fullscreen-btn i');
            modal.classList.toggle('modal-fullscreen');
            if (modal.classList.contains('modal-fullscreen')) {
                fullscreenBtn.className = 'bx bx-exit-fullscreen';
            } else {
                fullscreenBtn.className = 'bx bx-fullscreen';
            }
        }
        function openEditGameModal(id, title, description, gameCode, isActive) {
            document.getElementById('edit-game-id').value = id;
            document.getElementById('edit-game-title').value = title;
            document.getElementById('edit-game-description').value = description;
            document.getElementById('edit-game-code').value = gameCode;
            document.getElementById('edit-game-status').value = isActive;
            openModal('edit-game-modal');
        }
        function openEditQuestionModal(id, gameID, questionText, optionA, optionB, optionC, optionD, correctAnswer, timeLimit, points) {
            document.getElementById('edit-question-id').value = id;
            document.getElementById('edit-question-game').value = gameID;
            document.getElementById('edit-question-text').value = questionText;
            document.getElementById('edit-question-optionA').value = optionA;
            document.getElementById('edit-question-optionB').value = optionB;
            document.getElementById('edit-question-optionC').value = optionC;
            document.getElementById('edit-question-optionD').value = optionD;
            document.getElementById('edit-question-correct').value = correctAnswer;
            document.getElementById('edit-question-timeLimit').value = timeLimit;
            document.getElementById('edit-question-points').value = points;
            openModal('edit-question-modal');
        }
        function addQuestionField() {
            const container = document.getElementById('questions-container');
            const count = container.querySelectorAll('.question-fieldset').length;
            const newFieldset = document.createElement('fieldset');
            newFieldset.className = 'question-fieldset';
            newFieldset.innerHTML = `
                <legend>Question ${count + 1}</legend>
                <button type="button" class="remove-question-btn" onclick="removeQuestionField(this)"><i class='bx bx-x'></i></button>
                <label>Question Text</label>
                <textarea name="questions[${count}][questionText]" required></textarea>
                <label>Option A</label>
                <input type="text" name="questions[${count}][optionA]" required>
                <label>Option B</label>
                <input type="text" name="questions[${count}][optionB]" required>
                <label>Option C</label>
                <input type="text" name="questions[${count}][optionC]" required>
                <label>Option D</label>
                <input type="text" name="questions[${count}][optionD]" required>
                <label>Correct Answer</label>
                <select name="questions[${count}][correctAnswer]" required>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
                <label>Time Limit (seconds)</label>
                <input type="number" name="questions[${count}][timeLimit]" value="30" min="5" required>
                <label>Points</label>
                <input type="number" name="questions[${count}][points]" value="100" min="10" required>
            `;
            container.appendChild(newFieldset);
            updateRemoveButtons();
        }
        function removeQuestionField(button) {
            button.parentElement.remove();
            updateQuestionNumbers();
            updateRemoveButtons();
        }
        function updateQuestionNumbers() {
            const fieldsets = document.querySelectorAll('#questions-container .question-fieldset');
            fieldsets.forEach((fieldset, index) => {
                fieldset.querySelector('legend').textContent = `Question ${index + 1}`;
                const inputs = fieldset.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    const name = input.name.replace(/questions\[\d+\]/, `questions[${index}]`);
                    input.name = name;
                });
            });
        }
        function updateRemoveButtons() {
            const fieldsets = document.querySelectorAll('#questions-container .question-fieldset');
            fieldsets.forEach((fieldset, index) => {
                const removeBtn = fieldset.querySelector('.remove-question-btn');
                removeBtn.style.display = index === 0 ? 'none' : 'block';
            });
        }
        function resetQuestionFields() {
            const container = document.getElementById('questions-container');
            container.innerHTML = `
                <fieldset class="question-fieldset">
                    <legend>Question 1</legend>
                    <button type="button" class="remove-question-btn" onclick="removeQuestionField(this)" style="display: none;"><i class='bx bx-x'></i></button>
                    <label>Question Text</label>
                    <textarea name="questions[0][questionText]" required></textarea>
                    <label>Option A</label>
                    <input type="text" name="questions[0][optionA]" required>
                    <label>Option B</label>
                    <input type="text" name="questions[0][optionB]" required>
                    <label>Option C</label>
                    <input type="text" name="questions[0][optionC]" required>
                    <label>Option D</label>
                    <input type="text" name="questions[0][optionD]" required>
                    <label>Correct Answer</label>
                    <select name="questions[0][correctAnswer]" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                    <label>Time Limit (seconds)</label>
                    <input type="number" name="questions[0][timeLimit]" value="30" min="5" required>
                    <label>Points</label>
                    <input type="number" name="questions[0][points]" value="100" min="10" required>
                </fieldset>
            `;
        }
        function searchQuestions(tableId, input) {
            const filter = input.value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                for (let j = 0; j < cells.length - 1; j++) {
                    if (cells[j].textContent.toLowerCase().includes(filter)) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }
        function filterTable(tableId, select, column) {
            const filter = select.value;
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                const cell = rows[i].getElementsByTagName('td')[column];
                rows[i].style.display = filter === '' || cell.textContent === filter ? '' : 'none';
            }
        }
        function openStudentAnswersModal(sessionID, studentName) {
            const modal = document.getElementById('student-answers-modal');
            const title = document.getElementById('student-answers-title');
            const body = document.getElementById('student-answers-body');
            title.textContent = `Answers for ${studentName}`;
            body.innerHTML = '';
            const answers = studentAnswers.filter(answer => answer.sessionID == sessionID);
            if (answers.length === 0) {
                body.innerHTML = '<tr><td colspan="6">No answers available for this student.</td></tr>';
            } else {
                answers.forEach(answer => {
                    const row = document.createElement('tr');
                    const status = answer.isCorrect ? 'Correct' : 'Incorrect';
                    const statusClass = answer.isCorrect ? 'correct' : 'incorrect';
                    const userAnswerText = answer[`option${answer.userAnswer}`] || answer.userAnswer;
                    const correctAnswerText = answer[`option${answer.correctAnswer}`] || answer.correctAnswer;
                    row.innerHTML = `
                        <td>${answer.questionNumber}</td>
                        <td>${answer.questionText}</td>
                        <td>${userAnswerText}</td>
                        <td>${correctAnswerText}</td>
                        <td>${answer.points}</td>
                        <td class="${statusClass}">${status}</td>
                    `;
                    body.appendChild(row);
                });
            }
            openModal('student-answers-modal');
        }
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>