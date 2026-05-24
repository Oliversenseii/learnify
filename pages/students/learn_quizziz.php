<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './check_enrollment_status.php';

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
    $stmt = $dbConnection->prepare("SELECT firstName, lastName, userType, image FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['lastName'] = htmlspecialchars($user['lastName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
    } else {
        header("Location: ../../index.php");
        exit;
    }

    // Fetch achievements
    $achievements = [
        'first' => 0,
        'second' => 0,
        'third' => 0
    ];
    $achStmt = $dbConnection->prepare("
        SELECT 
            COUNT(CASE WHEN rank = 1 THEN 1 END) as first_place,
            COUNT(CASE WHEN rank = 2 THEN 1 END) as second_place,
            COUNT(CASE WHEN rank = 3 THEN 1 END) as third_place
        FROM (
            SELECT 
                qs.userID,
                ROW_NUMBER() OVER (PARTITION BY qs.gameID ORDER BY qs.totalScore DESC, u.lastName ASC, u.firstName ASC) as rank
            FROM learn_quiz_sessions qs
            JOIN users u ON qs.userID = u.userID
            WHERE qs.userID = :userID AND qs.gameID IN (SELECT gameID FROM learn_quiz_games WHERE isActive = 1)
        ) ranked
        WHERE rank <= 3
    ");
    $achStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $achStmt->execute();
    $achResult = $achStmt->fetch(PDO::FETCH_ASSOC);
    $achievements['first'] = $achResult['first_place'] ?? 0;
    $achievements['second'] = $achResult['second_place'] ?? 0;
    $achievements['third'] = $achResult['third_place'] ?? 0;

    // Initialize variables
    $game = null;
    $session = null;
    $totalQuestions = 0;
    $currentQuestion = null;
    $questionNumber = 0;
    $answeredQuestions = [];
    $leaderboard = [];
    $gameCode = isset($_SESSION['current_game_code']) ? $_SESSION['current_game_code'] : '';
    $showResults = isset($_GET['show_results']) && $_GET['show_results'] === 'true';

    // Fetch previous game codes for the user
    $previousGamesStmt = $dbConnection->prepare("
        SELECT DISTINCT g.gameCode
        FROM learn_quiz_sessions qs
        JOIN learn_quiz_games g ON qs.gameID = g.gameID
        WHERE qs.userID = :userID AND g.isActive = 1
        ORDER BY g.createdAt DESC
    ");
    $previousGamesStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $previousGamesStmt->execute();
    $previousGameCodes = $previousGamesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Handle AJAX request to update timer
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_timer') {
        $questionID = filter_var($_POST['questionID'], FILTER_VALIDATE_INT);
        $remainingTime = filter_var($_POST['remainingTime'], FILTER_VALIDATE_INT);
        if ($questionID && $remainingTime >= 0) {
            $_SESSION['timer_state'][$questionID] = $remainingTime;
        }
        exit;
    }

    // Handle game join or view results
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['gameCode']) || isset($_POST['qr-code']) || isset($_POST['view_gameCode']))) {
        $gameCode = null;
        if (isset($_POST['qr-code'])) {
            $gameCode = trim(filter_var($_POST['qr-code'], FILTER_SANITIZE_STRING));
        } elseif (isset($_POST['gameCode'])) {
            $gameCode = trim(filter_var($_POST['gameCode'], FILTER_SANITIZE_STRING));
        } elseif (isset($_POST['view_gameCode'])) {
            $gameCode = trim(filter_var($_POST['view_gameCode'], FILTER_SANITIZE_STRING));
        }

        $stmt = $dbConnection->prepare("SELECT gameID, title, description, gameCode FROM learn_quiz_games WHERE gameCode = :gameCode AND isActive = 1");
        $stmt->bindParam(':gameCode', $gameCode, PDO::PARAM_STR);
        $stmt->execute();
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($game) {
            // Check if user has a session
            $sessionStmt = $dbConnection->prepare("SELECT sessionID FROM learn_quiz_sessions WHERE gameID = :gameID AND userID = :userID");
            $sessionStmt->bindParam(':gameID', $game['gameID'], PDO::PARAM_INT);
            $sessionStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $sessionStmt->execute();
            $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

            if (!$session && !isset($_POST['view_gameCode'])) {
                // Create new session only if not viewing results
                $insertStmt = $dbConnection->prepare("INSERT INTO learn_quiz_sessions (gameID, userID) VALUES (:gameID, :userID)");
                $insertStmt->bindParam(':gameID', $game['gameID'], PDO::PARAM_INT);
                $insertStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $insertStmt->execute();
                $_SESSION['success_message'] = "Successfully joined the game!";
            }

            $_SESSION['current_game_id'] = $game['gameID'];
            $_SESSION['current_game_code'] = $game['gameCode'];

            if (isset($_POST['view_gameCode']) && $session) {
                $_SESSION['game_started'] = true;
                header("Location: learn_quizziz.php?gameID=" . $game['gameID'] . "&show_results=true");
                exit;
            }

            // Check if all questions are answered
            $totalStmt = $dbConnection->prepare("SELECT COUNT(*) as total FROM learn_quiz_questions WHERE gameID = :gameID");
            $totalStmt->bindParam(':gameID', $game['gameID'], PDO::PARAM_INT);
            $totalStmt->execute();
            $totalQuestions = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

            if ($session) {
                $answeredStmt = $dbConnection->prepare("SELECT COUNT(*) as answered FROM learn_quiz_answers WHERE sessionID = :sessionID");
                $answeredStmt->bindParam(':sessionID', $session['sessionID'], PDO::PARAM_INT);
                $answeredStmt->execute();
                $answeredCount = $answeredStmt->fetch(PDO::FETCH_ASSOC)['answered'];
                if ($answeredCount >= $totalQuestions) {
                    $_SESSION['game_started'] = true;
                    $showResults = true;
                }
            }

            $_SESSION['game_started'] = false;
            header("Location: learn_quizziz.php?gameID=" . $game['gameID']);
            exit;
        } else {
            $_SESSION['error_message'] = "Invalid or inactive game code.";
        }
    }

    // Handle answer submission or skip
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['questionID'])) {
        $gameID = filter_var($_POST['gameID'], FILTER_VALIDATE_INT);
        $questionID = filter_var($_POST['questionID'], FILTER_VALIDATE_INT);
        $sessionStmt = $dbConnection->prepare("SELECT sessionID, totalScore FROM learn_quiz_sessions WHERE gameID = :gameID AND userID = :userID");
        $sessionStmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
        $sessionStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $sessionStmt->execute();
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            $questionStmt = $dbConnection->prepare("SELECT correctAnswer, points FROM learn_quiz_questions WHERE questionID = :questionID");
            $questionStmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
            $questionStmt->execute();
            $question = $questionStmt->fetch(PDO::FETCH_ASSOC);
            $userAnswer = isset($_POST['userAnswer']) ? filter_var($_POST['userAnswer'], FILTER_SANITIZE_STRING) : null;
            $isCorrect = $userAnswer ? ($userAnswer === $question['correctAnswer']) : false;
            $pointsEarned = $isCorrect ? $question['points'] : 0;

            $answerStmt = $dbConnection->prepare("
                INSERT INTO learn_quiz_answers (sessionID, questionID, userAnswer, isCorrect, pointsEarned)
                VALUES (:sessionID, :questionID, :userAnswer, :isCorrect, :pointsEarned)
            ");
            $answerStmt->bindParam(':sessionID', $session['sessionID'], PDO::PARAM_INT);
            $answerStmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
            $answerStmt->bindParam(':userAnswer', $userAnswer, PDO::PARAM_STR, 1);
            $answerStmt->bindParam(':isCorrect', $isCorrect, PDO::PARAM_BOOL);
            $answerStmt->bindParam(':pointsEarned', $pointsEarned, PDO::PARAM_INT);
            $answerStmt->execute();

            $newTotalScore = $session['totalScore'] + $pointsEarned;
            $updateStmt = $dbConnection->prepare("UPDATE learn_quiz_sessions SET totalScore = :totalScore WHERE sessionID = :sessionID");
            $updateStmt->bindParam(':totalScore', $newTotalScore, PDO::PARAM_INT);
            $updateStmt->bindParam(':sessionID', $session['sessionID'], PDO::PARAM_INT);
            $updateStmt->execute();

            // Clear timer state for this question after submission
            if (isset($_SESSION['timer_state'][$questionID])) {
                unset($_SESSION['timer_state'][$questionID]);
            }

            $_SESSION['success_message'] = $userAnswer ? "Answer submitted! Points: $pointsEarned" : "Question skipped due to time out.";
            $_SESSION['is_correct_answer'] = $isCorrect;
            $_SESSION['game_started'] = true;

            // Check if all questions are answered
            $totalStmt = $dbConnection->prepare("SELECT COUNT(*) as total FROM learn_quiz_questions WHERE gameID = :gameID");
            $totalStmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
            $totalStmt->execute();
            $totalQuestions = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
            $answeredStmt = $dbConnection->prepare("SELECT COUNT(*) as answered FROM learn_quiz_answers WHERE sessionID = :sessionID");
            $answeredStmt->bindParam(':sessionID', $session['sessionID'], PDO::PARAM_INT);
            $answeredStmt->execute();
            $answeredCount = $answeredStmt->fetch(PDO::FETCH_ASSOC)['answered'];

            if ($answeredCount >= $totalQuestions) {
                header("Location: learn_quizziz.php?gameID=" . $gameID . "&show_results=true");
            } else {
                header("Location: learn_quizziz.php?gameID=" . $gameID);
            }
            exit;
        }
    }

    // Fetch active game and game details
    if (isset($_GET['gameID'])) {
        $gameID = filter_var($_GET['gameID'], FILTER_VALIDATE_INT);
        $gameStmt = $dbConnection->prepare("SELECT gameID, title, description, gameCode FROM learn_quiz_games WHERE gameID = :gameID AND isActive = 1");
        $gameStmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
        $gameStmt->execute();
        $game = $gameStmt->fetch(PDO::FETCH_ASSOC);

        if ($game) {
            $_SESSION['current_game_id'] = $game['gameID'];
            $_SESSION['current_game_code'] = $game['gameCode'];
            $totalStmt = $dbConnection->prepare("SELECT COUNT(*) as total FROM learn_quiz_questions WHERE gameID = :gameID");
            $totalStmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
            $totalStmt->execute();
            $totalQuestions = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $sessionStmt = $dbConnection->prepare("SELECT sessionID FROM learn_quiz_sessions WHERE gameID = :gameID AND userID = :userID");
            $sessionStmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
            $sessionStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $sessionStmt->execute();
            $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

            if ($session && !$showResults) {
                $answeredStmt = $dbConnection->prepare("SELECT questionID FROM learn_quiz_answers WHERE sessionID = :sessionID");
                $answeredStmt->bindParam(':sessionID', $session['sessionID'], PDO::PARAM_INT);
                $answeredStmt->execute();
                $answeredQuestions = array_column($answeredStmt->fetchAll(PDO::FETCH_ASSOC), 'questionID');

                $questionStmt = $dbConnection->prepare("
                    SELECT questionID, questionText, optionA, optionB, optionC, optionD, timeLimit
                    FROM learn_quiz_questions
                    WHERE gameID = :gameID AND questionID NOT IN (
                        SELECT questionID FROM learn_quiz_answers WHERE sessionID = :sessionID
                    )
                    ORDER BY questionID ASC
                    LIMIT 1
                ");
                $questionStmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
                $questionStmt->bindParam(':sessionID', $session['sessionID'], PDO::PARAM_INT);
                $questionStmt->execute();
                $currentQuestion = $questionStmt->fetch(PDO::FETCH_ASSOC);

                $questionNumberStmt = $dbConnection->prepare("
                    SELECT COUNT(*) as answered
                    FROM learn_quiz_answers
                    WHERE sessionID = :sessionID
                ");
                $questionNumberStmt->bindParam(':sessionID', $session['sessionID'], PDO::PARAM_INT);
                $questionNumberStmt->execute();
                $questionNumber = $questionNumberStmt->fetch(PDO::FETCH_ASSOC)['answered'] + 1;

                if (!$currentQuestion && $session) {
                    $showResults = true;
                }
            }
        } else {
            unset($_SESSION['current_game_id']);
            unset($_SESSION['current_game_code']);
            unset($_SESSION['game_started']);
            header("Location: learn_quizziz.php");
            exit;
        }
    }

    // Fetch leaderboard with full names and images
    $leaderboardGameID = isset($_SESSION['current_game_id']) ? $_SESSION['current_game_id'] : null;
    if ($leaderboardGameID && $session) {
        $leaderboardStmt = $dbConnection->prepare("
            SELECT u.firstName, u.lastName, u.image, qs.totalScore,
                   COUNT(CASE WHEN qa.isCorrect = 1 THEN 1 END) as correctAnswers
            FROM learn_quiz_sessions qs
            JOIN users u ON qs.userID = u.userID
            LEFT JOIN learn_quiz_answers qa ON qs.sessionID = qa.sessionID
            WHERE qs.gameID = :gameID
            GROUP BY qs.sessionID, u.userID
            ORDER BY qs.totalScore DESC, u.lastName ASC, u.firstName ASC
            LIMIT 10
        ");
        $leaderboardStmt->bindParam(':gameID', $leaderboardGameID, PDO::PARAM_INT);
        $leaderboardStmt->execute();
        $leaderboard = $leaderboardStmt->fetchAll(PDO::FETCH_ASSOC);
        usort($leaderboard, function($a, $b) {
            if ($a['totalScore'] == $b['totalScore']) {
                if ($a['lastName'] == $b['lastName']) {
                    return strcmp($a['firstName'], $b['firstName']);
                }
                return strcmp($a['lastName'], $b['lastName']);
            }
            return $b['totalScore'] - $a['totalScore'];
        });
    }

    // Fetch results for current user
    $userResults = [];
    if ($leaderboardGameID && $session) {
        $resultsStmt = $dbConnection->prepare("
            SELECT COUNT(*) as totalAnswered, COUNT(CASE WHEN isCorrect = 1 THEN 1 END) as correctAnswers,
                   SUM(pointsEarned) as totalPoints
            FROM learn_quiz_answers
            WHERE sessionID = :sessionID
        ");
        $resultsStmt->bindParam(':sessionID', $session['sessionID'], PDO::PARAM_INT);
        $resultsStmt->execute();
        $userResults = $resultsStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brainzap - Quiz Game</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
    <script src="./logout.js"></script>
    <style>
        @keyframes slideIn {
            0% { transform: translateX(-100%); opacity: 1; }
            10% { transform: translateX(0); opacity: 1; }
            90% { transform: translateX(0); opacity: 1; }
            100% { transform: translateX(100%); opacity: 0; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .option-a { background: linear-gradient(45deg, #facc15, #ffe066); }
        .option-b { background: linear-gradient(45deg, #ec4899, #ff99cc); }
        .option-c { background: linear-gradient(45deg, #10b981, #33cc99); }
        .option-d { background: linear-gradient(45deg, #8b5cf6, #b266ff); }
        .podium-box.first { background: linear-gradient(45deg, #FFD700, #ffeb3b); }
        .podium-box.second { background: linear-gradient(45deg, #C0C0C0, #e0e0e0); }
        .podium-box.third { background: linear-gradient(45deg, #CD7F32, #e6b800); }
        /* Active Tab Highlight */
        .tab.active {
            background-color: #ffffff;
            color: #1e40af; /* Darker blue for contrast */
            border-bottom: 3px solid #ffffff;
        }
        /* Mobile Menu Animation */
        #mobile-menu {
            transition: all 0.3s ease-in-out;
        }
        #mobile-menu.hidden {
            transform: translateY(-100%);
            opacity: 0;
        }
        #mobile-menu:not(.hidden) {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <audio id="correctSound" src="https://www.soundjay.com/buttons/sounds/button-3.mp3"></audio>
    <audio id="incorrectSound" src="https://www.soundjay.com/buttons/sounds/button-4.mp3"></audio>

    <!-- Navbar -->
    <nav class="bg-gradient-to-r from-blue-600 to-blue-800 p-4 shadow-lg sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="./game.php" class="text-white text-2xl hover:text-red-400 transition"><i class='bx bx-arrow-back'></i></a>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex space-x-4">
                <button class="text-lg tab px-4 py-2 text-white rounded-lg hover:bg-blue-700 transition font-semibold uppercase <?php echo $showResults ? '' : 'active'; ?>" data-tab="play-game">Play Game</button>
                <button class="text-lg tab px-4 py-2 text-white rounded-lg hover:bg-blue-700 transition font-semibold uppercase" data-tab="how-to-play">How to Play</button>
                <button class="text-lg tab px-4 py-2 text-white rounded-lg hover:bg-blue-700 transition font-semibold uppercase" data-tab="mechanics">Mechanics</button>
                <button class="text-lg tab px-4 py-2 text-white rounded-lg hover:bg-blue-700 transition font-semibold uppercase <?php echo $showResults ? 'active' : ''; ?>" data-tab="leaderboard">Leaderboard</button>
                <button class="text-lg tab px-4 py-2 text-white rounded-lg hover:bg-blue-700 transition font-semibold uppercase" data-tab="qr-generator">QR Code Generator</button>
            </div>
            
            <!-- Profile Section -->
            <div class="profile flex items-center space-x-2 text-white cursor-pointer" onclick="openAchievementsModal()">
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image" class="w-10 h-10 rounded-full object-cover">
                <div class="text-right">
                    <p class="font-semibold text-lg"><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <p class="text-sm text-gray-200"><?php echo htmlspecialchars($_SESSION['userType']); ?></p>
                </div>
            </div>
            
            <!-- Hamburger Icon for Mobile -->
            <button class="md:hidden text-white text-2xl focus:outline-none" id="hamburger-btn">
                <i class='bx bx-menu'></i>
            </button>
        </div>
        
        <!-- Mobile Menu (Hidden by Default) -->
        <div id="mobile-menu" class="md:hidden hidden bg-blue-700 p-4 mt-2 rounded-lg">
            <button class="block w-full text-left text-lg tab px-4 py-2 text-white hover:bg-blue-600 transition font-semibold uppercase <?php echo $showResults ? '' : 'active'; ?>" data-tab="play-game">Play Game</button>
            <button class="block w-full text-left text-lg tab px-4 py-2 text-white hover:bg-blue-600 transition font-semibold uppercase" data-tab="how-to-play">How to Play</button>
            <button class="block w-full text-left text-lg tab px-4 py-2 text-white hover:bg-blue-600 transition font-semibold uppercase" data-tab="mechanics">Mechanics</button>
            <button class="block w-full text-left text-lg tab px-4 py-2 text-white hover:bg-blue-600 transition font-semibold uppercase <?php echo $showResults ? 'active' : ''; ?>" data-tab="leaderboard">Leaderboard</button>
            <button class="block w-full text-left text-lg tab px-4 py-2 text-white hover:bg-blue-600 transition font-semibold uppercase" data-tab="qr-generator">QR Code Generator</button>
        </div>
    </nav>

    <!-- Achievements Modal -->
    <div id="achievements-modal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-6 max-w-md w-full max-h-[80vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4 rounded-t-xl flex justify-between items-center text-white">
                <h3 class="text-3xl font-bold">Your Achievements</h3>
                <button class="text-2xl hover:text-red-400 transition" onclick="closeAchievementsModal()"><i class='bx bx-x'></i></button>
            </div>
            <div class="p-6 space-y-6">
                <div class="flex items-center space-x-4 p-4 bg-yellow-50 rounded-lg">
                    <i class='bx bx-medal text-6xl text-yellow-600'></i>
                    <div>
                        <h4 class="text-3xl font-semibold text-gray-800">1st Place</h4>
                        <p class="text-gray-600 text-2xl"><?php echo $achievements['first']; ?> times</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
                    <i class='bx bx-medal text-6xl text-gray-600'></i>
                    <div>
                        <h4 class="text-3xl font-semibold text-gray-800">2nd Place</h4>
                        <p class="text-gray-600 text-2xl"><?php echo $achievements['second']; ?> times</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 p-4 bg-orange-50 rounded-lg">
                    <i class='bx bx-medal text-6xl text-orange-600'></i>
                    <div>
                        <h4 class="text-3xl font-semibold text-gray-800">3rd Place</h4>
                        <p class="text-gray-600 text-2xl"><?php echo $achievements['third']; ?> times</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section id="content" class="container mx-auto py-8">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div id="successNotification" class="success-notification bg-green-500 text-white p-4 rounded-lg text-center max-w-2xl mx-auto animate-[slideIn_5s_forwards] text-2xl">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div id="errorNotification" class="error-notification bg-red-500 text-white p-4 rounded-lg text-center max-w-2xl mx-auto animate-[slideIn_5s_forwards] text-2xl">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Play Game Tab -->
        <div id="play-game" class="tab-content <?php echo $showResults ? 'hidden' : 'active'; ?>">
            <div class="bg-white rounded-xl shadow-xl p-6 max-w-4xl mx-auto animate-[fadeIn_0.5s] mt-10">
                <?php if (!$game || $showResults): ?>
                    <div class="text-center">
                        <h2 class="text-4xl font-bold text-gray-800 mb-6">Join a Brainzap Game</h2>
                        <!-- Manual Input Section (Default) -->
                        <div id="manual-input" class="bg-gray-50 p-6 rounded-lg shadow-md">
                            <h3 class="text-2xl font-semibold text-gray-700 mb-4">Enter Game Code</h3>
                            <form action="learn_quizziz.php" method="post">
                                <input type="text" name="gameCode" placeholder="Enter Game Code" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-2xl">
                                <button type="submit" class="mt-4 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold text-2xl"><i class='bx bx-play mr-2'></i>Join Game</button>
                            </form>
                            <div class="or-divider mt-4 flex items-center text-center text-gray-600">
                                <hr class="flex-grow border-gray-300">
                                <span class="px-4 font-semibold text-2xl">or</span>
                                <hr class="flex-grow border-gray-300">
                            </div>
                            <button onclick="switchToQR()" class="mt-4 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition font-semibold text-2xl w-full">Scan QR Code</button>
                        </div>
                        <!-- QR Scanner Section (Hidden by default) -->
                        <div id="qr-scanner-section" class="bg-gray-50 p-6 rounded-lg shadow-md hidden">
                            <h3 class="text-xl font-semibold text-gray-700 mb-4">Scan QR Code</h3>
                            <p class="text-gray-600 mb-4 text-2xl">Scan the QR code to join the game.</p>
                            <video id="qr-scanner" class="w-full max-w-sm mx-auto rounded-lg border-2 border-gray-300"></video>
                            <div class="qr-detected-container hidden mt-4">
                                <form action="learn_quizziz.php" method="post">
                                    <h4 class="text-2xl font-semibold text-gray-800 mb-2">QR Code Detected!</h4>
                                    <input type="hidden" id="detected-qr-code" name="qr-code">
                                    <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition font-semibold text-2xl">Join Game</button>
                                </form>
                            </div>
                            <div class="or-divider mt-4 flex items-center text-center text-gray-600">
                                <hr class="flex-grow border-gray-300">
                                <span class="px-4 font-semibold text-2xl">or</span>
                                <hr class="flex-grow border-gray-300">
                            </div>
                            <button onclick="switchToManual()" class="mt-4 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold text-2xl w-full">Enter Game Code Manually</button>
                        </div>
                        <?php if (!empty($previousGameCodes)): ?>
                            <div class="mt-8">
                                <h3 class="text-xl font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-4 text-2xl">Previous Games</h3>
                                <div class="flex flex-wrap justify-center gap-4">
                                    <?php foreach ($previousGameCodes as $code): ?>
                                        <form action="learn_quizziz.php" method="post">
                                            <input type="hidden" name="view_gameCode" value="<?php echo htmlspecialchars($code); ?>">
                                            <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-2xl"><?php echo htmlspecialchars($code); ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <h2 class="text-4xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($game['title']); ?></h2>
                        <p class="text-gray-600 mb-4 text-2xl"><?php echo htmlspecialchars($game['description']); ?></p>
                        <?php if (!empty($userResults)): ?>
                            <p class="text-2xl font-semibold text-gray-700">Your Score: <?php echo $userResults['correctAnswers'] . '/' . $userResults['totalAnswered']; ?> (<?php echo $userResults['totalPoints']; ?> points)</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($currentQuestion): ?>
                        <div class="question-box bg-gray-50 p-6 rounded-lg shadow-md mt-6 relative" data-question-id="<?php echo $currentQuestion['questionID']; ?>">
                            <div class="text-2xl font-bold text-blue-600 text-center mb-4">Question <?php echo $questionNumber; ?> of <?php echo $totalQuestions; ?></div>
                            <div class="timer absolute top-4 right-4 text-red-600 font-semibold bg-red-100 px-3 py-1 rounded-lg text-2xl" data-time-limit="<?php echo isset($_SESSION['timer_state'][$currentQuestion['questionID']]) ? $_SESSION['timer_state'][$currentQuestion['questionID']] : $currentQuestion['timeLimit']; ?>">00:<?php echo sprintf("%02d", isset($_SESSION['timer_state'][$currentQuestion['questionID']]) ? $_SESSION['timer_state'][$currentQuestion['questionID']] : $currentQuestion['timeLimit']); ?></div>
                            <p class="text-4xl font-semibold text-gray-800 bg-white p-4 rounded-lg shadow-sm text-center"><?php echo htmlspecialchars($currentQuestion['questionText']); ?></p>
                            <form id="question-form" action="learn_quizziz.php" method="post">
                                <input type="hidden" name="gameID" value="<?php echo $game['gameID']; ?>">
                                <input type="hidden" name="questionID" value="<?php echo $currentQuestion['questionID']; ?>">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <label class="option option-a p-6 rounded-lg cursor-pointer hover:scale-105 transition-all shadow-md text-center font-medium text-2xl">
                                        <input type="radio" name="userAnswer" value="A" class="hidden"><?php echo htmlspecialchars($currentQuestion['optionA']); ?>
                                    </label>
                                    <label class="option option-b p-6 rounded-lg cursor-pointer hover:scale-105 transition-all shadow-md text-center font-medium text-2xl">
                                        <input type="radio" name="userAnswer" value="B" class="hidden"><?php echo htmlspecialchars($currentQuestion['optionB']); ?>
                                    </label>
                                    <label class="option option-c p-6 rounded-lg cursor-pointer hover:scale-105 transition-all shadow-md text-center font-medium text-2xl">
                                        <input type="radio" name="userAnswer" value="C" class="hidden"><?php echo htmlspecialchars($currentQuestion['optionC']); ?>
                                    </label>
                                    <label class="option option-d p-6 rounded-lg cursor-pointer hover:scale-105 transition-all shadow-md text-center font-medium text-2xl">
                                        <input type="radio" name="userAnswer" value="D" class="hidden"><?php echo htmlspecialchars($currentQuestion['optionD']); ?>
                                    </label>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- How to Play Tab -->
        <div id="how-to-play" class="tab-content hidden">
            <h2 class="text-4xl font-bold text-gray-800 text-center mb-8">How to Play Brainzap</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-5xl mx-auto">
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-key text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Join the Game</h3>
                        <p class="text-gray-600 text-2xl">Enter a game code or scan a QR code to join a Brainzap quiz session.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-time-five text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Answer Questions</h3>
                        <p class="text-gray-600 text-2xl">Respond to each question within the time limit to earn points.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-trophy text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Climb the Leaderboard</h3>
                        <p class="text-gray-600 text-2xl">Earn points for correct answers and compete to top the leaderboard.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-bar-chart-alt-2 text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Review Results</h3>
                        <p class="text-gray-600 text-2xl">Check your performance and compare scores with others after the game.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mechanics Tab -->
        <div id="mechanics" class="tab-content hidden">
            <h2 class="text-4xl font-bold text-gray-800 text-center mb-8">Brainzap Game Mechanics</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-stopwatch text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Time Limit</h3>
                        <p class="text-gray-600 text-2xl">Each question has a specific time limit to keep the game challenging.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-star text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Points System</h3>
                        <p class="text-gray-600 text-2xl">Earn points based on question difficulty for each correct answer.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-list-ol text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Leaderboard Ranking</h3>
                        <p class="text-gray-600 text-2xl">Ranks are determined by total points, with ties broken by names.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-check-square text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Multiple Choice</h3>
                        <p class="text-gray-600 text-2xl">Select one of four options (A, B, C, or D) for each question.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaderboard Tab -->
        <div id="leaderboard" class="tab-content <?php echo $showResults ? 'active' : 'hidden'; ?>">
            <div class="flex flex-col md:flex-row gap-6 max-w-6xl mx-auto">
                <!-- Previous Games Sidebar -->
                <?php if (!empty($previousGameCodes)): ?>
                    <div class="md:w-1/3 bg-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-4xl font-semibold text-gray-800 mb-4 text-lg">Previous Games</h3>
                        <div class="space-y-2">
                            <?php foreach ($previousGameCodes as $code): ?>
                                <form action="learn_quizziz.php" method="post">
                                    <input type="hidden" name="view_gameCode" value="<?php echo htmlspecialchars($code); ?>">
                                    <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-left text-2xl"><?php echo htmlspecialchars($code); ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Leaderboard Content -->
                <div class="flex-1 bg-white p-6 rounded-lg shadow-lg">
                    <?php if ($game && $session && $showResults): ?>
                        <h2 class="text-4xl font-bold text-gray-800 text-center mb-6">Leaderboard</h2>
                        <?php if (!empty($gameCode)): ?>
                            <p class="text-3xl text-gray-800 text-center mb-4 text-2xl"><strong>Game Code:</strong> <span class="text-red-600"><?php echo htmlspecialchars($gameCode); ?></span></p><br>
                        <?php endif; ?>
                        <?php if (!empty($leaderboard)): ?>
                            <div class="flex flex-col md:flex-row justify-around gap-4 mb-8">
                                <?php if (isset($leaderboard[0])): ?>
                                    <div class="podium-box first p-4 rounded-lg text-center relative transform -translate-y-6">
                                        <span class="medal absolute top-0 right-0 text-4xl">🥇</span>
                                        <img src="<?php echo $leaderboard[0]['image'] ?: './img/noprofile.png'; ?>" alt="1st Place" class="w-24 h-24 rounded-full mx-auto mb-2 border-2 border-white shadow">
                                        <h4 class="text-xl font-semibold text-gray-800 bg-gray-100 p-2 rounded"><?php echo htmlspecialchars(strtoupper($leaderboard[0]['lastName'] . ', ' . $leaderboard[0]['firstName'])); ?></h4>
                                        <p class="text-gray-600 text-xl">Score: <?php echo $leaderboard[0]['correctAnswers'] . '/' . $totalQuestions; ?></p>
                                        <p class="text-gray-600 text-xl">Points: <?php echo $leaderboard[0]['totalScore']; ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($leaderboard[1])): ?>
                                    <div class="podium-box second p-4 rounded-lg text-center relative transform -translate-y-3">
                                        <span class="medal absolute top-0 right-0 text-4xl">🥈</span>
                                        <img src="<?php echo $leaderboard[1]['image'] ?: './img/noprofile.png'; ?>" alt="2nd Place" class="w-24 h-24 rounded-full mx-auto mb-2 border-2 border-white shadow">
                                        <h4 class="text-xl font-semibold text-gray-800 bg-gray-100 p-2 rounded"><?php echo htmlspecialchars(strtoupper($leaderboard[1]['lastName'] . ', ' . $leaderboard[1]['firstName'])); ?></h4>
                                        <p class="text-gray-600 text-xl">Score: <?php echo $leaderboard[1]['correctAnswers'] . '/' . $totalQuestions; ?></p>
                                        <p class="text-gray-600 text-xl">Points: <?php echo $leaderboard[1]['totalScore']; ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($leaderboard[2])): ?>
                                    <div class="podium-box third p-4 rounded-lg text-center relative">
                                        <span class="medal absolute top-0 right-0 text-4xl">🥉</span>
                                        <img src="<?php echo $leaderboard[2]['image'] ?: './img/noprofile.png'; ?>" alt="3rd Place" class="w-24 h-24 rounded-full mx-auto mb-2 border-2 border-white shadow">
                                        <h4 class="text-xl font-semibold text-gray-800 bg-gray-100 p-2 rounded"><?php echo htmlspecialchars(strtoupper($leaderboard[2]['lastName'] . ', ' . $leaderboard[2]['firstName'])); ?></h4>
                                        <p class="text-gray-600 text-xl">Score: <?php echo $leaderboard[2]['correctAnswers'] . '/' . $totalQuestions; ?></p>
                                        <p class="text-gray-600 text-xl">Points: <?php echo $leaderboard[2]['totalScore']; ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <table class="w-full border-collapse bg-white rounded-lg shadow-md">
                                <thead>
                                    <tr class="bg-blue-600 text-white uppercase">
                                        <th class="p-3 text-left text-xl">Rank</th>
                                        <th class="p-3 text-left text-xl">Profile</th>
                                        <th class="p-3 text-left text-xl">Name</th>
                                        <th class="p-3 text-left text-xl">Score</th>
                                        <th class="p-3 text-left text-xl">Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($index = 3; $index < count($leaderboard); $index++): ?>
                                        <tr class="hover:bg-gray-100">
                                            <td class="p-3 text-xl"><?php echo $index + 1; ?></td>
                                            <td class="p-3"><img src="<?php echo $leaderboard[$index]['image'] ?: './img/noprofile.png'; ?>" alt="Profile" class="w-10 h-10 rounded-full"></td>
                                            <td class="p-3 text-xl"><?php echo htmlspecialchars(strtoupper($leaderboard[$index]['lastName'] . ', ' . $leaderboard[$index]['firstName'])); ?></td>
                                            <td class="p-3 text-xl"><?php echo $leaderboard[$index]['correctAnswers'] . '/' . $totalQuestions; ?></td>
                                            <td class="p-3 text-xl"><?php echo $leaderboard[$index]['totalScore']; ?></td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-gray-600 text-center text-2xl">No leaderboard data available.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-gray-600 text-center text-2xl">Please join a game to view the leaderboard.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- QR Code Generator Tab -->
        <div id="qr-generator" class="tab-content hidden">
            <h2 class="text-4xl font-bold text-gray-800 text-center mb-6">QR Code Generator</h2>
            <div class="bg-white p-6 rounded-lg shadow-lg max-w-md mx-auto text-center">
                <input type="text" id="qr-game-code" placeholder="Enter Game Code (e.g., 5284F9A3)" class="w-full p-3 border border-gray-300 rounded-lg mb-4 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-2xl">
                <button onclick="generateQRCode()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold text-2xl">Generate QR Code</button>
                <div id="qr-code-result" class="mt-4"></div>
            </div>
        </div>

        <!-- Pre-Game Modal -->
        <?php if ($game && (!isset($_SESSION['game_started']) || !$_SESSION['game_started']) && !$showResults): ?>
            <div id="pre-game-modal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl p-6 max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4 rounded-t-xl flex justify-between items-center text-white">
                        <h3 class="text-4xl font-bold">Brainzap Quiz Details</h3>
                        <button class="text-4xl hover:text-red-400 transition" onclick="exitGame()"><i class='bx bx-x'></i></button>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-800 mb-2 text-2xl"><strong>Title:</strong> <?php echo htmlspecialchars($game['title']); ?></p>
                        <p class="text-gray-800 mb-2 text-2xl"><strong>Description:</strong> <?php echo htmlspecialchars($game['description']); ?></p>
                        <p class="text-gray-800 mb-4 text-2xl"><strong>Total Questions:</strong> <?php echo $totalQuestions; ?></p>
                        <p class="text-center text-gray-800 font-semibold bg-gray-100 p-3 rounded-lg text-2xl">Are you ready to start the quiz?</p>
                    </div>
                    <div class="flex justify-center space-x-4 p-4">
                        <button class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold text-2xl" onclick="startQuiz()">Yes</button>
                        <button class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition font-semibold text-2xl" onclick="exitGame()">No</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <script>
    // Tab Switching
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active class from all tabs and content
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            
            // Add active class to clicked tab and corresponding content
            const tabContentId = tab.dataset.tab;
            document.querySelectorAll(`.tab[data-tab="${tabContentId}"]`).forEach(t => t.classList.add('active'));
            document.getElementById(tabContentId).classList.remove('hidden');
            
            // Close mobile menu on tab click (for mobile devices)
            const mobileMenu = document.getElementById('mobile-menu');
            if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
            }
            
            // Stop scanner when switching tabs
            stopScanner();
        });
    });

    // Hamburger Menu Toggle
    document.getElementById('hamburger-btn').addEventListener('click', () => {
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenu.classList.toggle('hidden');
    });

    // Switch between Manual and QR sections
    function switchToQR() {
        document.getElementById('manual-input').classList.add('hidden');
        document.getElementById('qr-scanner-section').classList.remove('hidden');
        startScanner();
    }

    function switchToManual() {
        document.getElementById('manual-input').classList.remove('hidden');
        document.getElementById('qr-scanner-section').classList.add('hidden');
        stopScanner();
    }

    // Achievements Modal
    function openAchievementsModal() {
        document.getElementById('achievements-modal').classList.remove('hidden');
    }

    function closeAchievementsModal() {
        document.getElementById('achievements-modal').classList.add('hidden');
    }

    // QR Scanner
    let scanner = null;
    function startScanner() {
        const qrScanner = document.getElementById('qr-scanner');
        if (!qrScanner) return;

        scanner = new Instascan.Scanner({
            video: qrScanner,
            mirror: false
        });

        scanner.addListener('scan', function (content) {
            console.log('QR Code Scanned:', content);
            document.getElementById('detected-qr-code').value = content;
            document.querySelector('.qr-detected-container').classList.remove('hidden');
        });

        Instascan.Camera.getCameras()
            .then(function (cameras) {
                if (cameras.length > 0) {
                    scanner.start(cameras[0]);
                } else {
                    console.error('No cameras found.');
                    alert('No cameras found. Please ensure your camera is connected and accessible.');
                }
            })
            .catch(function (err) {
                console.error('Camera access error:', err);
                alert('Camera access error: ' + err.message);
            });
    }

    function stopScanner() {
        if (scanner) {
            scanner.stop();
            scanner = null;
        }
    }

    // QR Code Generator
    function generateQRCode() {
        const gameCode = document.getElementById('qr-game-code').value.trim();
        const qrResult = document.getElementById('qr-code-result');
        if (!gameCode) {
            qrResult.innerHTML = '<p class="bg-red-500 text-white p-4 rounded-lg text-lg">Please enter a game code.</p>';
            setTimeout(() => { qrResult.innerHTML = ''; }, 5000);
            return;
        }

        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(gameCode)}`;
        qrResult.innerHTML = `
            <img src="${qrUrl}" alt="QR Code for ${gameCode}" class="mx-auto rounded-lg border-2 border-gray-300">
            <a href="${qrUrl}" download="qrcode_${gameCode}.png" class="block mt-4 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition text-lg">Download QR Code</a>
        `;
    }

    // Timer Function
    function startTimer(questionBox) {
        const timerElement = questionBox.querySelector('.timer');
        let timeLeft = parseInt(timerElement.dataset.timeLimit);
        const form = questionBox.querySelector('form');
        const questionID = questionBox.dataset.questionId;

        if (questionBox.dataset.timerId) {
            clearInterval(parseInt(questionBox.dataset.timerId));
        }

        const interval = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(interval);
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'userAnswer';
                hiddenInput.value = '';
                form.appendChild(hiddenInput);
                form.submit();
                return;
            }
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            timeLeft--;

            // Periodically save timer state to server
            if (timeLeft % 2 === 0) { // Save every 2 seconds
                fetch('learn_quizziz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=update_timer&questionID=${questionID}&remainingTime=${timeLeft}`
                });
            }
        }, 1000);
        questionBox.dataset.timerId = interval;
    }

    // Modal Functions
    function startQuiz() {
        document.getElementById('pre-game-modal').style.display = 'none';
        document.querySelector('.question-box').classList.remove('hidden');
        const questionBox = document.querySelector('.question-box');
        if (questionBox) {
            startTimer(questionBox);
        }
        fetch('learn_quizziz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=set_game_started'
        });
    }

    function exitGame() {
        window.location.href = 'learn_quizziz.php';
    }

    function playSound(isCorrect) {
        const correctSound = document.getElementById('correctSound');
        const incorrectSound = document.getElementById('incorrectSound');
        if (isCorrect) {
            correctSound.play();
        } else {
            incorrectSound.play();
        }
    }

    function speakText(text) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US';
        utterance.volume = 1;
        utterance.rate = 1;
        utterance.pitch = 1;
        window.speechSynthesis.speak(utterance);
    }

    document.querySelectorAll('.option').forEach(option => {
        option.addEventListener('click', () => {
            const radio = option.querySelector('input');
            radio.checked = true;
            document.querySelectorAll('.option').forEach(opt => opt.classList.remove('ring-2', 'ring-blue-500'));
            option.classList.add('ring-2', 'ring-blue-500');
            const form = option.closest('form');
            const questionBox = option.closest('.question-box');
            if (questionBox.dataset.timerId) {
                clearInterval(parseInt(questionBox.dataset.timerId));
            }
            setTimeout(() => {
                form.submit();
            }, 1000);
        });
    });

    document.addEventListener('DOMContentLoaded', () => {
        const questionBox = document.querySelector('.question-box');
        if (questionBox && !<?php echo json_encode($showResults); ?>) {
            startTimer(questionBox);
        }

        // Handle answer feedback
        <?php if (isset($_SESSION['is_correct_answer'])): ?>
            const options = document.querySelectorAll('.option');
            options.forEach(option => {
                const radio = option.querySelector('input');
                if (radio.checked) {
                    if (<?php echo json_encode($_SESSION['is_correct_answer']); ?>) {
                        option.classList.remove('option-a', 'option-b', 'option-c', 'option-d');
                        option.classList.add('bg-green-500', 'text-white');
                    } else {
                        option.classList.remove('option-a', 'option-b', 'option-c', 'option-d');
                        option.classList.add('bg-red-500', 'text-white');
                    }
                    option.classList.add('ring-2', 'ring-gray-800');
                    options.forEach(opt => {
                        opt.style.pointerEvents = 'none';
                    });
                }
            });
        <?php endif; ?>

        const successNotification = document.getElementById('successNotification');
        const errorNotification = document.getElementById('errorNotification');
        if (successNotification) {
            const message = successNotification.textContent.trim();
            speakText(message);
            <?php if (isset($_SESSION['is_correct_answer'])): ?>
                playSound(<?php echo json_encode($_SESSION['is_correct_answer']); ?>);
                speakText(<?php echo json_encode($_SESSION['is_correct_answer'] ? "Correct answer" : "Incorrect answer"); ?>);
                <?php unset($_SESSION['is_correct_answer']); ?>
            <?php endif; ?>
            setTimeout(() => {
                successNotification.remove();
            }, 5000);
        }
        if (errorNotification) {
            speakText(errorNotification.textContent.trim());
            setTimeout(() => {
                errorNotification.remove();
            }, 5000);
        }
    });

    <?php if ($game && (!isset($_SESSION['game_started']) || !$_SESSION['game_started']) && !$showResults): ?>
        document.querySelector('.question-box').classList.add('hidden');
    <?php endif; ?>
    </script>
</body>
</html>
