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

// Suppress PHP errors from being output to the browser
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

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

    // Fetch badges
    $badgesStmt = $dbConnection->prepare("
        SELECT b.badgeName, b.description, b.imageURL, ub.awardedDate
        FROM brainpix_user_badges ub
        JOIN brainpix_badges b ON ub.badgeID = b.badgeID
        WHERE ub.userID = :userID
        ORDER BY ub.awardedDate DESC
    ");
    $badgesStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $badgesStmt->execute();
    $badges = $badgesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize variables
    $maps = [];
    $levels = [];
    $currentLevel = null;
    $previousLevel = null;
    $showBadges = isset($_GET['show_badges']) && $_GET['show_badges'] === 'true';
    $mapID = isset($_GET['mapID']) ? filter_var($_GET['mapID'], FILTER_VALIDATE_INT) : null;
    $levelID = isset($_GET['levelID']) ? filter_var($_GET['levelID'], FILTER_VALIDATE_INT) : null;

    // Fetch all maps
    $mapsStmt = $dbConnection->prepare("SELECT * FROM brainpix_maps ORDER BY orderNum ASC");
    $mapsStmt->execute();
    $maps = $mapsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Add completion percentage to maps
    $totalCompletedLevels = 0;
    foreach ($maps as &$map) {
        $progressStmt = $dbConnection->prepare("
            SELECT COUNT(*) as completed
            FROM brainpix_user_progress up
            JOIN brainpix_levels l ON up.levelID = l.levelID
            WHERE up.userID = :userID AND l.mapID = :mapID AND up.completed = 1
        ");
        $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $progressStmt->bindParam(':mapID', $map['mapID'], PDO::PARAM_INT);
        $progressStmt->execute();
        $completed = $progressStmt->fetch(PDO::FETCH_ASSOC)['completed'];
        $map['completed_levels'] = $completed;
        $map['completion'] = ($completed / 30) * 100;
        $totalCompletedLevels += $completed;
    }
    unset($map);

    // Determine unlocked maps sequentially
    $previousCompleted = true;
    foreach ($maps as &$map) {
        $map['unlocked'] = $previousCompleted;
        $previousCompleted = ($map['completion'] >= 100);
    }
    unset($map);

    // Handle map selection and levels
    if ($mapID) {
        // Validate if the selected map is unlocked
        $selectedMap = null;
        foreach ($maps as $m) {
            if ($m['mapID'] == $mapID) {
                $selectedMap = $m;
                break;
            }
        }
        if (!$selectedMap || !$selectedMap['unlocked']) {
            $_SESSION['error_message'] = "You must complete the previous maps to unlock this one.";
            header("Location: brainpix.php");
            exit;
        }

        // Fetch levels for the map
        $levelsStmt = $dbConnection->prepare("
            SELECT l.levelID, l.levelNum, l.imageURL, l.correctAnswer, l.hint,
                   COALESCE(up.completed, 0) as completed,
                   COALESCE(up.attempts, 0) as attempts
            FROM brainpix_levels l
            LEFT JOIN brainpix_user_progress up ON l.levelID = up.levelID AND up.userID = :userID
            WHERE l.mapID = :mapID
            ORDER BY l.levelNum ASC
        ");
        $levelsStmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
        $levelsStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $levelsStmt->execute();
        $levels = $levelsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Determine unlocked levels (sequential: must complete previous to unlock next)
        $unlockedUpTo = 0;
        foreach ($levels as $level) {
            if ($level['completed'] == 1) {
                $unlockedUpTo = $level['levelNum'];
            } else {
                break;
            }
        }
        if (empty($levels)) {
            $_SESSION['error_message'] = "No levels found for this map.";
        }
    }

    // Handle level play
    if ($levelID && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $levelStmt = $dbConnection->prepare("
            SELECT l.*, m.mapName, COALESCE(up.completed, 0) as completed
            FROM brainpix_levels l
            JOIN brainpix_maps m ON l.mapID = m.mapID
            LEFT JOIN brainpix_user_progress up ON l.levelID = up.levelID AND up.userID = :userID
            WHERE l.levelID = :levelID
        ");
        $levelStmt->bindParam(':levelID', $levelID, PDO::PARAM_INT);
        $levelStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $levelStmt->execute();
        $currentLevel = $levelStmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentLevel || $currentLevel['completed'] == 1) {
            // Redirect to level selection if invalid or already completed
            header("Location: brainpix.php?mapID=" . ($currentLevel['mapID'] ?? $mapID));
            exit;
        }
        // Fetch previous level (levelNum - 1) if it exists
        if ($currentLevel['levelNum'] > 1) {
            $prevLevelStmt = $dbConnection->prepare("
                SELECT l.levelID, l.levelNum, l.correctAnswer, COALESCE(up.completed, 0) as completed
                FROM brainpix_levels l
                LEFT JOIN brainpix_user_progress up ON l.levelID = up.levelID AND up.userID = :userID
                WHERE l.mapID = :mapID AND l.levelNum = :prevLevelNum
            ");
            $prevLevelNum = $currentLevel['levelNum'] - 1;
            $prevLevelStmt->bindParam(':mapID', $currentLevel['mapID'], PDO::PARAM_INT);
            $prevLevelStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $prevLevelStmt->bindParam(':prevLevelNum', $prevLevelNum, PDO::PARAM_INT);
            $prevLevelStmt->execute();
            $previousLevel = $prevLevelStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Handle answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['levelID']) && isset($_POST['userAnswer'])) {
    $levelID = filter_var($_POST['levelID'], FILTER_VALIDATE_INT);
    $userAnswer = trim(filter_var($_POST['userAnswer'], FILTER_SANITIZE_STRING));
    $levelStmt = $dbConnection->prepare("SELECT mapID, correctAnswer FROM brainpix_levels WHERE levelID = :levelID");
    $levelStmt->bindParam(':levelID', $levelID, PDO::PARAM_INT);
    $levelStmt->execute();
    $level = $levelStmt->fetch(PDO::FETCH_ASSOC);
    if ($level) {
        $isCorrect = strtolower($userAnswer) === strtolower($level['correctAnswer']);
        if ($isCorrect) {
            // Update or insert progress only if the answer is correct
            $progressStmt = $dbConnection->prepare("
                INSERT INTO brainpix_user_progress (userID, levelID, completed, attempts, lastAttempt)
                VALUES (:userID, :levelID, 1, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    completed = 1,
                    attempts = attempts + 1,
                    lastAttempt = NOW()
            ");
            $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $progressStmt->bindParam(':levelID', $levelID, PDO::PARAM_INT);
            $progressStmt->execute();

            $_SESSION['success_message'] = "Correct! Level completed.";
            // Check if map completed
            $completedLevelsStmt = $dbConnection->prepare("
                SELECT COUNT(*) as completed
                FROM brainpix_user_progress up
                JOIN brainpix_levels l ON up.levelID = l.levelID
                WHERE up.userID = :userID AND l.mapID = :mapID AND up.completed = 1
            ");
            $completedLevelsStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $completedLevelsStmt->bindParam(':mapID', $level['mapID'], PDO::PARAM_INT);
            $completedLevelsStmt->execute();
            $completedCount = $completedLevelsStmt->fetch(PDO::FETCH_ASSOC)['completed'];
            if ($completedCount >= 30) {
                // Award badge
                $badgeStmt = $dbConnection->prepare("SELECT badgeID FROM brainpix_badges WHERE mapID = :mapID");
                $badgeStmt->bindParam(':mapID', $level['mapID'], PDO::PARAM_INT);
                $badgeStmt->execute();
                $badge = $badgeStmt->fetch(PDO::FETCH_ASSOC);
                if ($badge) {
                    $insertBadgeStmt = $dbConnection->prepare("
                        INSERT IGNORE INTO brainpix_user_badges (userID, badgeID)
                        VALUES (:userID, :badgeID)
                    ");
                    $insertBadgeStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                    $insertBadgeStmt->bindParam(':badgeID', $badge['badgeID'], PDO::PARAM_INT);
                    $insertBadgeStmt->execute();
                    $_SESSION['success_message'] .= " Badge awarded!";
                }
            }
            header("Location: brainpix.php?mapID=" . $level['mapID']);
            exit;
        } else {
            // If incorrect, do not insert into brainpix_user_progress
            $_SESSION['error_message'] = "Incorrect. Try again!";
            header("Location: brainpix.php?mapID=" . $level['mapID'] . "&levelID=" . $levelID);
            exit;
        }
    }
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
    <title>BrainPix - Puzzle Game</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
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
        /* Active Tab Highlight */
        .tab.active {
            background-color: #ffffff;
            color: #1e40af;
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
        .level-locked { opacity: 0.5; pointer-events: none; }
        /* Full Screen Styles */
        #game-container:fullscreen {
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 1rem;
        }
        #game-container:-webkit-full-screen {
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 1rem;
        }
        #game-container:-moz-full-screen {
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 1rem;
        }
        #game-container img {
            max-height: 50vh;
            object-fit: contain;
        }
        #game-container:fullscreen img {
            max-height: 60vh;
        }
        #game-container:-webkit-full-screen img {
            max-height: 60vh;
        }
        #game-container:-moz-full-screen img {
            max-height: 60vh;
        }
        /* Letter Box Styles */
        .letter-box {
            width: 3rem;
            height: 3rem;
            border: 2px solid #4b5563;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 600;
            text-transform: uppercase;
            background-color: #ffffff;
            color: #1f2937;
            margin: 0 0.25rem;
            margin-bottom: 10px;
            text-align: center;
        }
        .space-box {
            width: 1rem;
            height: 2.5rem;
            margin: 0 0.25rem;
        }
        .letter-input {
            width: 3rem;
            height: 3rem;
            border: 2px solid #4b5563;
            font-size: 1.25rem;
            font-weight: 600;
            text-transform: uppercase;
            text-align: center;
            padding: 0;
            margin: 0 0.25rem;
            background-color: #ffffff;
            color: #1f2937;
        }
        .letter-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
        }
        .badge-image {
            width: 12rem;
            height: 12rem;
            object-fit: cover;
            border-radius: 8px;
        }
        @keyframes moveHand {
            0% { transform: translateX(0); }
            50% { transform: translateX(10px); }
            100% { transform: translateX(0); }
        }
        .animate-moveHand {
            animation: moveHand 1s ease-in-out infinite;
        }
        #help-modal .modal-content {
            max-height: 90vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        @media screen and (max-width: 640px) {
            #help-modal .modal-content {
                max-width: 90%;
                padding: 1rem;
            }
            #help-modal .modal-content img {
                max-width: 100%;
                height: auto;
            }
            #help-modal .modal-content p {
                font-size: 1rem;
            }
            #help-modal .modal-content button {
                font-size: 1rem;
                padding: 0.75rem 1rem;
            }
            .letter-box, .space-box {
                width: 2rem;
                height: 2rem;
                font-size: 1rem;
                margin: 0 0.15rem;
            }
            .letter-input {
                margin-top: 10px;
            }
        }

        @media screen and (max-width: 460px) {
            .letter-box, .space-box {
                width: 1.1rem;
                height: 1.1rem;
                margin-bottom: 10px;
            }
            .letter-input {
                width: 2rem;
                height: 2rem;
            }
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
            <div class="hidden md:flex space-x-4">
                <button class="text-lg tab px-4 py-2 text-white rounded-lg hover:bg-blue-700 transition font-semibold uppercase active" data-tab="play-game">Play Game</button>
                <button class="text-lg tab px-4 py-2 text-white rounded-lg hover:bg-blue-700 transition font-semibold uppercase" data-tab="how-to-play">How to Play</button>
                <button class="text-lg tab px-4 py-2 text-white rounded-lg hover:bg-blue-700 transition font-semibold uppercase" data-tab="mechanics">Mechanics</button>
                <button class="text-lg tab px-4 py-2 text-white rounded-lg hover:bg-blue-700 transition font-semibold uppercase" data-tab="badges">Badges</button>
            </div>
            <div class="profile flex items-center space-x-2 text-white cursor-pointer" onclick="openBadgesModal()">
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image" class="w-10 h-10 rounded-full object-cover">
                <div class="text-right">
                    <p class="font-semibold text-lg"><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <p class="text-sm text-gray-200"><?php echo htmlspecialchars($_SESSION['userType']); ?></p>
                </div>
            </div>
            <button class="md:hidden text-white text-2xl focus:outline-none" id="hamburger-btn">
                <i class='bx bx-menu'></i>
            </button>
        </div>
        <div id="mobile-menu" class="md:hidden hidden bg-blue-700 p-4 mt-2 rounded-lg">
            <button class="block w-full text-left text-lg tab px-4 py-2 text-white hover:bg-blue-600 transition font-semibold uppercase active" data-tab="play-game">Play Game</button>
            <button class="block w-full text-left text-lg tab px-4 py-2 text-white hover:bg-blue-600 transition font-semibold uppercase" data-tab="how-to-play">How to Play</button>
            <button class="block w-full text-left text-lg tab px-4 py-2 text-white hover:bg-blue-600 transition font-semibold uppercase" data-tab="mechanics">Mechanics</button>
            <button class="block w-full text-left text-lg tab px-4 py-2 text-white hover:bg-blue-600 transition font-semibold uppercase" data-tab="badges">Badges</button>
        </div>
    </nav>
    <!-- Badges Modal -->
    <div id="badges-modal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-6 max-w-md w-full max-h-[80vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4 rounded-t-xl flex justify-between items-center text-white">
                <h3 class="text-3xl font-bold">Your Badges</h3>
                <button class="text-2xl hover:text-red-400 transition" onclick="closeBadgesModal()"><i class='bx bx-x'></i></button>
            </div>
            <div class="p-6 space-y-6">
                <?php if (!empty($badges)): ?>
                    <?php foreach ($badges as $badge): ?>
                        <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
                            <img src="<?php echo $badge['imageURL'] ?: './img/badge_default.png'; ?>" alt="<?php echo htmlspecialchars($badge['badgeName']); ?>" class="w-16 h-16">
                            <div>
                                <h4 class="text-3xl font-semibold text-gray-800"><?php echo htmlspecialchars($badge['badgeName']); ?></h4>
                                <p class="text-gray-600 text-2xl"><?php echo htmlspecialchars($badge['description']); ?></p>
                                <strong>Awarded:</strong> <?php echo (new DateTime($badge['awardedDate']))->format('F j, Y, g:i A'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-600 text-center text-2xl">No badges earned yet. Complete maps to earn them!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Level Modal -->
    <div id="level-modal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-6 max-w-[800px] max-h-[80vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4 rounded-t-xl flex justify-between items-center text-white">
                <h3 class="text-3xl font-bold" id="level-modal-title">Level Details</h3>
                <button class="text-2xl hover:text-red-400 transition" onclick="closeLevelModal()"><i class='bx bx-x'></i></button>
            </div>
            <div class="p-6 space-y-6">
                <img id="level-modal-image" src="" alt="Level Image" class="max-w-full h-auto mx-auto mb-6 rounded-lg shadow-md border border-gray-500">
                <div>
                    <h4 class="text-2xl text-center mb-4 font-semibold text-gray-800">Correct Answer:</h4>
                    <div class="flex justify-center flex-wrap" id="level-modal-answer"></div>
                </div>
                <!-- <p id="level-modal-hint" class="text-gray-600 text-2xl text-center">Hint: </p> -->
            </div>
        </div>
    </div>
    <!-- Help Modal for Newcomers -->
    <div id="help-modal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 <?php echo ($totalCompletedLevels == 0) ? '' : 'hidden'; ?>">
        <div class="bg-white rounded-xl max-w-2xl w-full modal-content">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4 rounded-t-xl flex justify-between items-center text-white">
                <h3 class="text-3xl font-bold">Welcome to BrainPix!</h3>
            </div>
            <div class="p-6 space-y-6 text-gray-700 text-xl">
                <div class="space-y-4">
                    <p>BrainPix is a puzzle game where you guess words or phrases based on images.</p>
                    <p>Select a roadmap to start, but you must complete previous roadmaps to unlock the next ones.</p>
                    <p>Within a map, levels unlock sequentially as you complete them.</p>
                    <p>Complete all levels in a map to earn badges. Good luck!</p>
                    <p><strong>Example Puzzle:</strong></p>
                    <img src="./img/sample_level1.png" alt="Sample Puzzle Image" class="max-w-full h-auto mx-auto mb-4 rounded-lg shadow-md border border-gray-500">
                    <p><strong>Answer:</strong></p>
                    <div class="flex justify-center flex-wrap mb-4">
                        <?php
                        $sampleAnswer = "HISTORY REPEATS ITSELF";
                        $characters = str_split(strtoupper($sampleAnswer));
                        foreach ($characters as $char):
                            if ($char === ' '): ?>
                                <div class="space-box"></div>
                            <?php else: ?>
                                <div class="letter-box"><?php echo htmlspecialchars($char); ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <button class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 hover:scale-105 transition transform duration-200 font-bold text-xl w-full shadow-md" onclick="closeHelpModal()">
                        <i class='bx bxs-hand-right text-4xl text-yellow-400 mr-2 animate-moveHand'></i>
                        Got It
                        <i class='bx bxs-hand-left text-4xl text-yellow-400 ml-2 animate-moveHand'></i>
                    </button>
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
        <div id="play-game" class="tab-content active">
            <div class="bg-white rounded-xl shadow-xl p-6 max-w-6xl mx-auto animate-[fadeIn_0.5s] mt-10">
                <?php if (!$mapID): ?>
                    <div class="text-center">
                        <h2 class="text-4xl font-bold text-gray-800 mb-6">Select a Roadmap</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($maps as $map): ?>
                                <?php if ($map['unlocked']): ?>
                                    <a href="brainpix.php?mapID=<?php echo $map['mapID']; ?>" class="bg-gray-50 p-6 rounded-lg shadow-md hover:bg-blue-50 transition">
                                        <h3 class="text-2xl font-semibold text-gray-700"><?php echo htmlspecialchars($map['mapName']); ?></h3>
                                        <p class="text-gray-600 text-xl"><?php echo htmlspecialchars($map['description']); ?></p>
                                        <div class="mt-2 bg-blue-200 rounded-full h-2.5">
                                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $map['completion']; ?>%"></div>
                                        </div>
                                        <p class="text-gray-600 text-xl">Completion: <?php echo round($map['completion']); ?>%</p>
                                    </a>
                                <?php else: ?>
                                    <div class="bg-gray-50 p-6 rounded-lg shadow-md relative">
                                        <h3 class="text-2xl font-semibold text-gray-700"><?php echo htmlspecialchars($map['mapName']); ?></h3>
                                        <p class="text-gray-600 text-xl"><?php echo htmlspecialchars($map['description']); ?></p>
                                        <div class="mt-2 bg-blue-200 rounded-full h-2.5">
                                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $map['completion']; ?>%"></div>
                                        </div>
                                        <p class="text-gray-600 text-xl">Completion: <?php echo round($map['completion']); ?>%</p>
                                        <div class="absolute inset-0 bg-gray-300 opacity-50 flex items-center justify-center rounded-lg">
                                            <i class='bx bx-lock text-6xl text-gray-600'></i>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($mapID && !$levelID): ?>
                    <div class="text-center">
                        <h2 class="text-4xl font-bold text-gray-800 mb-6">Levels in <?php echo htmlspecialchars($maps[array_search($mapID, array_column($maps, 'mapID'))]['mapName']); ?></h2>
                        <div class="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-4">
                            <?php $prevCompleted = true; ?>
                            <?php foreach ($levels as $index => $level): ?>
                                <?php $isUnlocked = ($level['levelNum'] == 1 || $prevCompleted); ?>
                                <?php if ($level['completed']): ?>
                                    <button onclick="openLevelModal(<?php echo $level['levelID']; ?>, <?php echo $level['levelNum']; ?>, '<?php echo addslashes(htmlspecialchars($level['imageURL'])); ?>', '<?php echo addslashes(htmlspecialchars($level['correctAnswer'])); ?>', '<?php echo addslashes(htmlspecialchars($level['hint'])); ?>')" class="p-4 rounded-lg shadow-md text-center font-medium text-2xl bg-green-200 cursor-pointer">
                                        Level <?php echo $level['levelNum']; ?><i class='bx bx-check'></i>
                                    </button>
                                <?php else: ?>
                                    <a href="<?php echo $isUnlocked ? 'brainpix.php?mapID=' . $mapID . '&levelID=' . $level['levelID'] : '#'; ?>" class="p-4 rounded-lg shadow-md text-center font-medium text-2xl <?php echo $isUnlocked ? 'bg-yellow-200' : 'bg-gray-200 level-locked'; ?>">
                                        Level <?php echo $level['levelNum']; ?>
                                    </a>
                                <?php endif; ?>
                                <?php $prevCompleted = $level['completed'] == 1; ?>
                            <?php endforeach; ?>
                        </div>
                        <a href="brainpix.php" class="mt-6 inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition font-semibold text-2xl"><i class='bx bx-arrow-back mr-2'></i>Back to Maps</a>
                    </div>
                <?php else: ?>
                    <div id="game-container" class="text-center">
                        <div class="flex justify-center mb-4">
                            <h2 class="text-4xl font-bold text-gray-800">Level <?php echo $currentLevel['levelNum']; ?> - <?php echo htmlspecialchars($currentLevel['mapName']); ?></h2>
                        </div>
                        <img src="<?php echo htmlspecialchars($currentLevel['imageURL']); ?>" alt="Puzzle Image" class="max-w-full h-auto mx-auto mb-6 rounded-lg shadow-md border border-gray-500">
                        <div class="flex items-center justify-center mb-4">
                        </div>
                        <form id="answer-form" action="brainpix.php" method="post">
                            <input type="hidden" name="levelID" value="<?php echo $currentLevel['levelID']; ?>">
                            <input type="hidden" name="userAnswer" id="userAnswer">
                            <div class="flex justify-center flex-wrap mb-4">
                                <?php
                                $answer = strtoupper($currentLevel['correctAnswer']);
                                $characters = str_split($answer);
                                $inputIndex = 0;
                                foreach ($characters as $index => $char):
                                    if ($char === ' '): ?>
                                        <div class="space-box"></div>
                                    <?php else: ?>
                                        <input type="text" class="letter-input" maxlength="1" data-index="<?php echo $inputIndex; ?>" oninput="handleInput(this, <?php echo $inputIndex; ?>)" onkeydown="handleKeydown(event, <?php echo $inputIndex; ?>)">
                                        <?php $inputIndex++; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold text-2xl"><i class='bx bx-send mr-2'></i>Submit Answer</button>
                        </form>
                        <div class="mt-4 flex justify-center space-x-4">
                            <a href="brainpix.php?mapID=<?php echo $currentLevel['mapID']; ?>" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition font-semibold text-2xl"><i class='bx bx-arrow-back mr-2'></i>Back to Levels</a>
                            <button id="fullscreen-btn" onclick="toggleFullScreen()" class="ml-4 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition font-semibold text-xl"><i class='bx bx-fullscreen mr-2'></i><span id="fullscreen-text">Enter Full Screen</span></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div id="how-to-play" class="tab-content hidden">
            <h2 class="text-4xl font-bold text-gray-800 text-center mb-8">How to Play Thinking-It-Out</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-5xl mx-auto">
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-map text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Select a Roadmap</h3>
                        <p class="text-gray-600 text-2xl">Choose from 10 roadmaps related to SHS G11/G12 topics.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-brain text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Solve Levels</h3>
                        <p class="text-gray-600 text-2xl">View the image and guess the correct phrase or word.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-trophy text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Earn Badges</h3>
                        <p class="text-gray-600 text-2xl">Complete all 30 levels in a map to earn and upgrade your badge.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-bar-chart-alt-2 text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Track Progress</h3>
                        <p class="text-gray-600 text-2xl">See your completion percentage for each roadmap.</p>
                    </div>
                </div>
            </div>
        </div>
        <div id="mechanics" class="tab-content hidden">
            <h2 class="text-4xl font-bold text-gray-800 text-center mb-8">Thinking-It-Out Game Mechanics</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-lock text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Sequential Unlocking</h3>
                        <p class="text-gray-600 text-2xl">Levels unlock one by one as you complete the previous.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-help-circle text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Lessons Provided</h3>
                        <p class="text-gray-600 text-2xl">Each puzzle has a lesson after you complete the level.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-refresh text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Unlimited Attempts</h3>
                        <p class="text-gray-600 text-2xl">Try as many times as needed; attempts are tracked.</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg flex items-start space-x-4">
                    <i class='bx bx-medal text-5xl text-blue-600'></i>
                    <div>
                        <h3 class="text-3xl font-semibold text-gray-800">Badge Upgrades</h3>
                        <p class="text-gray-600 text-2xl">Complete a map to upgrade and earn the level badge.</p>
                    </div>
                </div>
            </div>
        </div>
        <div id="badges" class="tab-content hidden">
            <h2 class="text-5xl font-bold text-gray-800 text-center mb-10">Your Badges</h2>
            <?php if (!empty($badges)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-9xl mx-auto">
                    <?php foreach ($badges as $index => $badge): ?>
                        <div id="badge-<?php echo $index; ?>" class="bg-white p-8 rounded-lg shadow-lg flex items-center space-x-6">
                            <img src="<?php echo $badge['imageURL'] ?: './img/badge_default.png'; ?>" alt="<?php echo htmlspecialchars($badge['badgeName']); ?>" class="badge-image">
                            <div class="flex-1">
                                <h3 class="text-5xl font-semibold text-gray-800"><?php echo htmlspecialchars($badge['badgeName']); ?></h3>
                                <p class="text-gray-600 text-2xl mt-2 mb-2"><?php echo htmlspecialchars($badge['description']); ?></p>
                                <p class="text-gray-500 text-lg">
                                    <strong>Awarded:</strong> <?php echo (new DateTime($badge['awardedDate']))->format('F j, Y, g:i A'); ?>
                                </p>
                                <button class="download-btn mt-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition" onclick="downloadBadge(<?php echo $index; ?>)">Download as JPG</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-600 text-center text-2xl">No badges earned yet. Complete maps to earn them!</p>
                <?php endif; ?>
            </div>
        </section>
    <script>
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
                const tabContentId = tab.dataset.tab;
                document.querySelectorAll(`.tab[data-tab="${tabContentId}"]`).forEach(t => t.classList.add('active'));
                document.getElementById(tabContentId).classList.remove('hidden');
                const mobileMenu = document.getElementById('mobile-menu');
                if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                    mobileMenu.classList.add('hidden');
                }
            });
        });
        document.getElementById('hamburger-btn').addEventListener('click', () => {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
        });
        function openBadgesModal() {
            document.getElementById('badges-modal').classList.remove('hidden');
        }
        function closeBadgesModal() {
            document.getElementById('badges-modal').classList.add('hidden');
        }
        function openLevelModal(levelID, levelNum, imageURL, correctAnswer, hint) {
            const modal = document.getElementById('level-modal');
            const title = document.getElementById('level-modal-title');
            const image = document.getElementById('level-modal-image');
            const answerContainer = document.getElementById('level-modal-answer');
            const hintElement = document.getElementById('level-modal-hint');
            title.textContent = `Level ${levelNum} Details`;
            image.src = imageURL;
            answerContainer.innerHTML = '';
            hintElement.textContent = `Hint: ${hint}`;
            const characters = correctAnswer.toUpperCase().split('');
            characters.forEach(char => {
                if (char === ' ') {
                    answerContainer.innerHTML += '<div class="space-box"></div>';
                } else {
                    answerContainer.innerHTML += `<div class="letter-box">${char}</div>`;
                }
            });
            modal.classList.remove('hidden');
        }
        function closeLevelModal() {
            document.getElementById('level-modal').classList.add('hidden');
        }
        function closeHelpModal() {
            document.getElementById('help-modal').classList.add('hidden');
        }
        const successNotification = document.getElementById('successNotification');
        const errorNotification = document.getElementById('errorNotification');
        if (successNotification) {
            playSound(true);
            setTimeout(() => {
                successNotification.remove();
            }, 5000);
        }
        if (errorNotification) {
            playSound(false);
            setTimeout(() => {
                errorNotification.remove();
            }, 5000);
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
        function toggleFullScreen() {
            const gameContainer = document.getElementById('game-container');
            const fullscreenBtn = document.getElementById('fullscreen-btn');
            const fullscreenText = document.getElementById('fullscreen-text');
            if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.mozFullScreenElement) {
                if (gameContainer.requestFullscreen) {
                    gameContainer.requestFullscreen();
                } else if (gameContainer.webkitRequestFullscreen) {
                    gameContainer.webkitRequestFullscreen();
                } else if (gameContainer.mozRequestFullScreen) {
                    gameContainer.mozRequestFullScreen();
                } else {
                    alert('Full-screen mode is not supported in this browser.');
                    return;
                }
                fullscreenText.textContent = 'Exit Full Screen';
                fullscreenBtn.querySelector('i').classList.remove('bx-fullscreen');
                fullscreenBtn.querySelector('i').classList.add('bx-exit-fullscreen');
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                }
                fullscreenText.textContent = 'Enter Full Screen';
                fullscreenBtn.querySelector('i').classList.remove('bx-exit-fullscreen');
                fullscreenBtn.querySelector('i').classList.add('bx-fullscreen');
            }
        }
        document.addEventListener('fullscreenchange', updateFullScreenButton);
        document.addEventListener('webkitfullscreenchange', updateFullScreenButton);
        document.addEventListener('mozfullscreenchange', updateFullScreenButton);
        function updateFullScreenButton() {
            const fullscreenBtn = document.getElementById('fullscreen-btn');
            const fullscreenText = document.getElementById('fullscreen-text');
            if (!fullscreenBtn || !fullscreenText) return;
            if (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement) {
                fullscreenText.textContent = 'Exit Full Screen';
                fullscreenBtn.querySelector('i').classList.remove('bx-fullscreen');
                fullscreenBtn.querySelector('i').classList.add('bx-exit-fullscreen');
            } else {
                fullscreenText.textContent = 'Enter Full Screen';
                fullscreenBtn.querySelector('i').classList.remove('bx-exit-fullscreen');
                fullscreenBtn.querySelector('i').classList.add('bx-fullscreen');
            }
        }
        function handleInput(element, index) {
            const value = element.value.toUpperCase();
            if (value.length === 1 && /^[A-Za-z]$/.test(value)) {
                element.value = value;
                const inputs = document.querySelectorAll('.letter-input');
                let nextIndex = index + 1;
                if (nextIndex < inputs.length) {
                    inputs[nextIndex].focus();
                }
            } else {
                element.value = '';
            }
        }
        function handleKeydown(event, index) {
            const inputs = document.querySelectorAll('.letter-input');
            if (event.key === 'Backspace' && !event.target.value && index > 0) {
                let prevIndex = index - 1;
                if (prevIndex >= 0) {
                    inputs[prevIndex].focus();
                }
            } else if (event.key === 'ArrowLeft' && index > 0) {
                let prevIndex = index - 1;
                if (prevIndex >= 0) {
                    inputs[prevIndex].focus();
                }
            } else if (event.key === 'ArrowRight' && index < inputs.length - 1) {
                let nextIndex = index + 1;
                if (nextIndex < inputs.length) {
                    inputs[nextIndex].focus();
                }
            }
        }
        function downloadBadge(index) {
            const badgeElement = document.getElementById(`badge-${index}`);
            const downloadBtn = badgeElement.querySelector('.download-btn');
            downloadBtn.style.display = 'none';
            html2canvas(badgeElement, { scale: 2 }).then(canvas => {
                const link = document.createElement('a');
                link.download = `badge-${index}.jpg`;
                link.href = canvas.toDataURL('image/jpeg', 0.9);
                link.click();
                downloadBtn.style.display = '';
            });
        }
        <?php if ($levelID && $currentLevel): ?>
            document.getElementById('answer-form').addEventListener('submit', function(event) {
                const inputs = document.querySelectorAll('.letter-input');
                let answer = '';
                let inputIndex = 0;
                const correctAnswer = <?php echo json_encode(str_split(strtoupper($currentLevel['correctAnswer']))); ?>;
                for (let i = 0; i < correctAnswer.length; i++) {
                    if (correctAnswer[i] === ' ') {
                        answer += ' ';
                    } else {
                        answer += inputs[inputIndex] ? (inputs[inputIndex].value || '') : '';
                        inputIndex++;
                    }
                }
                document.getElementById('userAnswer').value = answer.trim();
            });
        <?php endif; ?>
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</body>
</html>