<?php
date_default_timezone_set('Asia/Manila');

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

    // Check if certificate exists
    $certStmt = $dbConnection->prepare("SELECT certificateID FROM speech_to_text_certificate WHERE userID = :userID");
    $certStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $certStmt->execute();
    $hasCertificate = $certStmt->fetch(PDO::FETCH_ASSOC) !== false;

    // Handle level selection
    $selectedLevel = isset($_GET['level']) ? filter_var($_GET['level'], FILTER_VALIDATE_INT) : null;
    if ($selectedLevel !== null && $selectedLevel >= 1 && $selectedLevel <= 30) {
        $progressStmt = $dbConnection->prepare("SELECT currentLevel, totalPoints FROM speech_to_text_user_progress_image WHERE userID = :userID");
        $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $progressStmt->execute();
        $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
        $currentLevel = $progress['currentLevel'] ?? 1;
        if ($selectedLevel > $currentLevel) {
            $selectedLevel = $currentLevel;
        }
    } else {
        $selectedLevel = null;
    }

    // Fetch stars for previous level
    $stars = 0;
    if ($selectedLevel && $selectedLevel) {
        $prevLevel = $selectedLevel;
        $prevImageStmt = $dbConnection->prepare("SELECT imageID FROM speech_to_text_game_images WHERE level = :prevLevel");
        $prevImageStmt->bindParam(':prevLevel', $prevLevel, PDO::PARAM_INT);
        $prevImageStmt->execute();
        $prevImage = $prevImageStmt->fetch(PDO::FETCH_ASSOC);
        if ($prevImage) {
            $attemptStmt = $dbConnection->prepare("
                SELECT stars 
                FROM speech_to_text_user_attempts 
                WHERE userID = :userID AND imageID = :imageID AND isCorrect = 1
                ORDER BY attemptDate DESC
                LIMIT 1
            ");
            $attemptStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $attemptStmt->bindParam(':imageID', $prevImage['imageID'], PDO::PARAM_INT);
            $attemptStmt->execute();
            $attempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);
            if ($attempt && $attempt['stars'] > 0) {
                $stars = $attempt['stars'];
            }
        }
    }

    // Handle reveal letter
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reveal_letter']) && isset($_POST['imageID'])) {
        $imageID = filter_var($_POST['imageID'], FILTER_VALIDATE_INT);
        $progressStmt = $dbConnection->prepare("SELECT totalPoints FROM speech_to_text_user_progress_image WHERE userID = :userID");
        $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $progressStmt->execute();
        $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
        $totalPoints = $progress['totalPoints'] ?? 0;

        if ($totalPoints >= 30) {
            $points = -30;
            $progressStmt = $dbConnection->prepare("UPDATE speech_to_text_user_progress_image SET totalPoints = totalPoints + :points WHERE userID = :userID");
            $progressStmt->bindParam(':points', $points, PDO::PARAM_INT);
            $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $progressStmt->execute();

            $answerStmt = $dbConnection->prepare("SELECT correctAnswer, level FROM speech_to_text_game_images WHERE imageID = :imageID");
            $answerStmt->bindParam(':imageID', $imageID, PDO::PARAM_INT);
            $answerStmt->execute();
            $answerData = $answerStmt->fetch(PDO::FETCH_ASSOC);
            $correctAnswer = $answerData['correctAnswer'];
            $level = $answerData['level'];

            // Choose random non-revealed letter index
            $answerChars = str_split(str_replace(' ', '', $correctAnswer));
            $revealed = $_SESSION['revealed_letters'][$level] ?? [];
            $available = [];
            for ($i = 0; $i < count($answerChars); $i++) {
                if (!in_array($i, $revealed)) {
                    $available[] = $i;
                }
            }
            if (!empty($available)) {
                $randIndex = $available[array_rand($available)];
                $_SESSION['revealed_letters'][$level][] = $randIndex;
            }

            $_SESSION['result_message'] = "Letter revealed! -30 coins.";
            $_SESSION['result_status'] = 'success';
        } else {
            $_SESSION['result_message'] = "Not enough coins to reveal letter (need 30 coins).";
            $_SESSION['result_status'] = 'error';
        }
        header("Location: imageGame.php?level=$level");
        exit;
    }

    // Handle answer submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imageID']) && isset($_POST['userAnswer'])) {
        $imageID = filter_var($_POST['imageID'], FILTER_VALIDATE_INT);
        if ($imageID === false) {
            error_log("Invalid imageID: $imageID");
            $_SESSION['result_message'] = "Invalid image selection.";
            $_SESSION['result_status'] = 'error';
            header("Location: imageGame.php");
            exit;
        }

        $answerStmt = $dbConnection->prepare("SELECT correctAnswer, level FROM speech_to_text_game_images WHERE imageID = :imageID");
        $answerStmt->bindParam(':imageID', $imageID, PDO::PARAM_INT);
        $answerStmt->execute();
        $answerData = $answerStmt->fetch(PDO::FETCH_ASSOC);
        if (!$answerData) {
            error_log("No data found for imageID: $imageID");
            $_SESSION['result_message'] = "Image data not found.";
            $_SESSION['result_status'] = 'error';
            header("Location: imageGame.php");
            exit;
        }
        $correctAnswer = $answerData['correctAnswer'];
        $level = $answerData['level'];

        $isCorrect = false;
        $points = 0;
        $userAnswer = '';

        // Count attempts for this level
        $attemptCountStmt = $dbConnection->prepare("
            SELECT COUNT(*) as attemptCount 
            FROM speech_to_text_user_attempts 
            WHERE userID = :userID AND imageID = :imageID
        ");
        $attemptCountStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $attemptCountStmt->bindParam(':imageID', $imageID, PDO::PARAM_INT);
        $attemptCountStmt->execute();
        $attemptCount = $attemptCountStmt->fetch(PDO::FETCH_ASSOC)['attemptCount'] + 1;

        // Check if lifeline is used
        if (isset($_POST['useLifeline']) && $_POST['useLifeline'] === 'true') {
            $progressStmt = $dbConnection->prepare("SELECT totalPoints FROM speech_to_text_user_progress_image WHERE userID = :userID");
            $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $progressStmt->execute();
            $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
            $totalPoints = $progress['totalPoints'] ?? 0;

            if ($totalPoints >= 150) {
                $isCorrect = true;
                $points = -150;
                $userAnswer = $correctAnswer;
                $_SESSION['result_message'] = "Lifeline used! Level skipped. -150 coins. 😊";
                $_SESSION['result_status'] = 'success';
            } else {
                $_SESSION['result_message'] = "Not enough coins to use lifeline (need 150 coins). Keep trying! 💪";
                $_SESSION['result_status'] = 'error';
                header("Location: imageGame.php" . ($selectedLevel ? "?level=$level" : ""));
                exit;
            }
        } else {
            $userAnswer = trim(filter_var($_POST['userAnswer'], FILTER_SANITIZE_STRING));
            if (!preg_match('/^[a-zA-Z\s]+$/', $userAnswer)) {
                $_SESSION['result_message'] = "Invalid input! Only letters and spaces are allowed.";
                $_SESSION['result_status'] = 'error';
                header("Location: imageGame.php" . ($selectedLevel ? "?level=$level" : ""));
                exit;
            }
            $cleanedUserAnswer = preg_replace('/\s+/', ' ', trim(preg_replace('/[[:punct:]]/', '', strtolower($userAnswer))));
            $cleanedCorrectAnswer = preg_replace('/\s+/', ' ', trim(preg_replace('/[[:punct:]]/', '', strtolower($correctAnswer))));
            $isCorrect = $cleanedUserAnswer === $cleanedCorrectAnswer;
            $points = $isCorrect ? 15 : 0;

            $message = $isCorrect 
                ? "Correct! +15 coins! 🎉 " . ($level < 30 ? "Moving to Level " . ($level + 1) : "Game Completed! 🏆")
                : "Wrong! Try again. You got this! 😄";

            if ($isCorrect) {
                $stars = $attemptCount <= 3 ? 3 : ($attemptCount <= 6 ? 2 : 1);
                $message .= " You earned $stars star" . ($stars > 1 ? "s" : "") . "!";

                if ($level == 20) {
                    $randomReward = [200, 300, 400][array_rand([200, 300, 400])];
                    $points += $randomReward;
                    $message .= " +$randomReward bonus coins!";
                }
            }

            $_SESSION['result_message'] = $message;
            $_SESSION['result_status'] = $isCorrect ? 'success' : 'error';
        }

        error_log("User Answer: '$userAnswer' | Correct Answer: '$correctAnswer' | Is Correct: " . ($isCorrect ? 'Yes' : 'No'));

        $stars = isset($stars) ? $stars : 0;
        $attemptStmt = $dbConnection->prepare("
            INSERT INTO speech_to_text_user_attempts (userID, imageID, userAnswer, isCorrect, points, attemptDate, stars)
            VALUES (:userID, :imageID, :userAnswer, :isCorrect, :points, NOW(), :stars)
        ");
        $attemptStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $attemptStmt->bindParam(':imageID', $imageID, PDO::PARAM_INT);
        $attemptStmt->bindParam(':userAnswer', $userAnswer, PDO::PARAM_STR);
        $attemptStmt->bindParam(':isCorrect', $isCorrect, PDO::PARAM_BOOL);
        $attemptStmt->bindParam(':points', $points, PDO::PARAM_INT);
        $attemptStmt->bindParam(':stars', $stars, PDO::PARAM_INT);
        $attemptStmt->execute();

        $newLevel = $isCorrect ? ($level == 30 ? 31 : $level + 1) : $level;
        $progressStmt = $dbConnection->prepare("
            INSERT INTO speech_to_text_user_progress_image (userID, currentLevel, totalAttempts, totalCorrect, totalPoints, lastAttemptDate)
            VALUES (:userID, :newLevel, 1, :isCorrect, :points, NOW())
            ON DUPLICATE KEY UPDATE 
                currentLevel = :newLevel,
                totalAttempts = totalAttempts + 1,
                totalCorrect = totalCorrect + :isCorrect,
                totalPoints = totalPoints + :points,
                lastAttemptDate = NOW()
        ");
        $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $progressStmt->bindParam(':newLevel', $newLevel, PDO::PARAM_INT);
        $progressStmt->bindParam(':isCorrect', $isCorrect, PDO::PARAM_INT);
        $progressStmt->bindParam(':points', $points, PDO::PARAM_INT);
        if (!$progressStmt->execute()) {
            error_log("Failed to update user progress for userID: $userID, newLevel: $newLevel");
        } else {
            error_log("Updated user progress: userID: $userID, level: $level, newLevel: $newLevel, isCorrect: $isCorrect");
        }

        if ($isCorrect && $level == 30) {
            $certStmt = $dbConnection->prepare("
                INSERT INTO speech_to_text_certificate (userID, completionDate)
                VALUES (:userID, NOW())
                ON DUPLICATE KEY UPDATE completionDate = NOW()
            ");
            $certStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            if ($certStmt->execute()) {
                error_log("Certificate generated for userID: $userID");
                $_SESSION['result_message'] .= " Congratulations! You've completed all levels! 🎊";
            } else {
                error_log("Failed to generate certificate for userID: $userID");
                $_SESSION['result_message'] .= " Completed Level 30, but certificate generation failed.";
                $_SESSION['result_status'] = 'error';
            }
        }

        if ($isCorrect) {
            unset($_SESSION['revealed_letters'][$level]);
        }

        header("Location: imageGame.php" . ($selectedLevel ? "?level=$newLevel" : ""));
        exit;
    }

    // Handle daily bonus claim
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_bonus'])) {
        $today = date('Y-m-d');
        $claimCheckStmt = $dbConnection->prepare("SELECT COUNT(*) FROM speech_to_text_daily_bonus WHERE userID = :userID AND claimDate = :today");
        $claimCheckStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $claimCheckStmt->bindParam(':today', $today, PDO::PARAM_STR);
        $claimCheckStmt->execute();
        if ($claimCheckStmt->fetchColumn() == 0) {
            $day = date('l');
            $dayRewards = [
                'Monday' => 10,
                'Tuesday' => 15,
                'Wednesday' => 20,
                'Thursday' => 25,
                'Friday' => 30,
                'Saturday' => 35,
                'Sunday' => 150
            ];
            $bonusPoints = $dayRewards[$day] ?? 0;
            $points = $bonusPoints;

            $progressStmt = $dbConnection->prepare("UPDATE speech_to_text_user_progress_image SET totalPoints = totalPoints + :points WHERE userID = :userID");
            $progressStmt->bindParam(':points', $points, PDO::PARAM_INT);
            $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $progressStmt->execute();

            $bonusStmt = $dbConnection->prepare("INSERT INTO speech_to_text_daily_bonus (userID, claimDate, points) VALUES (:userID, :today, :points)");
            $bonusStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $bonusStmt->bindParam(':today', $today, PDO::PARAM_STR);
            $bonusStmt->bindParam(':points', $points, PDO::PARAM_INT);
            $bonusStmt->execute();

            $_SESSION['result_message'] = "Daily bonus claimed! +$bonusPoints coins.";
            $_SESSION['result_status'] = 'success';
        } else {
            $_SESSION['result_message'] = "You have already claimed today's bonus.";
            $_SESSION['result_status'] = 'error';
        }
        header("Location: imageGame.php");
        exit;
    }

    $progressStmt = $dbConnection->prepare("
        SELECT currentLevel, totalAttempts, totalCorrect, totalPoints 
        FROM speech_to_text_user_progress_image 
        WHERE userID = :userID
    ");
    $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $progressStmt->execute();
    $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
    $currentLevel = $progress['currentLevel'] ?? 1;
    $totalPoints = $progress['totalPoints'] ?? 0;

    $displayLevel = $selectedLevel ?? $currentLevel;

    $imageStmt = $dbConnection->prepare("
        SELECT imageID, imageFile1, imageFile2, imageFile3, correctAnswer, description, level 
        FROM speech_to_text_game_images 
        WHERE level = :level AND archived = 0
    ");
    $imageStmt->bindParam(':level', $displayLevel, PDO::PARAM_INT);
    $imageStmt->execute();
    $image = $imageStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $isViewOnly = $displayLevel < $currentLevel || ($hasCertificate && $displayLevel <= 30);

    $latestAttemptStmt = $dbConnection->prepare("
        SELECT ua.userAnswer 
        FROM speech_to_text_user_attempts ua
        WHERE ua.userID = :userID AND ua.imageID IN (
            SELECT imageID FROM speech_to_text_game_images WHERE level = :level
        )
        ORDER BY ua.attemptDate DESC
        LIMIT 1
    ");
    $latestAttemptStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $latestAttemptStmt->bindParam(':level', $displayLevel, PDO::PARAM_INT);
    $latestAttemptStmt->execute();
    $latestAttempt = $latestAttemptStmt->fetch(PDO::FETCH_ASSOC);
    $latestAnswer = $latestAttempt ? htmlspecialchars($latestAttempt['userAnswer']) : '';

    $descriptionWords = $image && $image['description'] ? explode(' ', htmlspecialchars($image['description'])) : [];
    $highlightableDescription = $image && $image['description'] ? implode(' ', array_map(function($word, $index) {
        return "<span class='word' data-index='$index'>$word</span>";
    }, $descriptionWords, array_keys($descriptionWords))) : '';

    // Prepare letter tiles and word structure
    $answerWords = $image ? explode(' ', trim($image['correctAnswer'])) : [];
    $answerChars = $image ? str_split(str_replace(' ', '', $image['correctAnswer'])) : [];
    $answerCharCount = count($answerChars);
    $revealedLetters = $_SESSION['revealed_letters'][$displayLevel] ?? [];
    $wordBoundaries = [];
    $charIndex = 0;
    foreach ($answerWords as $word) {
        $wordLength = strlen($word);
        $wordBoundaries[] = ['start' => $charIndex, 'length' => $wordLength];
        $charIndex += $wordLength;
    }

    $highestBadge = ['icon' => '🌟', 'text' => 'Starter', 'desc' => 'Begin your journey!', 'bg' => 'from-[#4b5563] to-[#6b7280]'];
    if ($hasCertificate) {
        $highestBadge = ['icon' => '🏆', 'text' => 'Master', 'desc' => 'Completed All Levels', 'bg' => 'from-[#1d4ed8] to-[#3b82f6]'];
    } elseif ($currentLevel > 20) {
        $highestBadge = ['icon' => '🥇', 'text' => 'Expert', 'desc' => 'Completed Level 20', 'bg' => 'from-[#1d4ed8] to-[#3b82f6]'];
    } elseif ($currentLevel > 10) {
        $highestBadge = ['icon' => '🥈', 'text' => 'Intermediate', 'desc' => 'Completed Level 10', 'bg' => 'from-[#4b5563] to-[#6b7280]'];
    } elseif ($currentLevel > 1) {
        $highestBadge = ['icon' => '🥉', 'text' => 'Beginner', 'desc' => 'Completed Level 1', 'bg' => 'from-[#4b5563] to-[#6b7280]'];
    }

    $today = date('Y-m-d');
    $claimCheckStmt = $dbConnection->prepare("SELECT COUNT(*) FROM speech_to_text_daily_bonus WHERE userID = :userID AND claimDate = :today");
    $claimCheckStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $claimCheckStmt->bindParam(':today', $today, PDO::PARAM_STR);
    $claimCheckStmt->execute();
    $claimedToday = $claimCheckStmt->fetchColumn() > 0;

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['result_message'] = "Database error occurred.";
    $_SESSION['result_status'] = 'error';
}

require_once './get_notification_count.php';
$unreadCount = getUnreadAnnouncementCount($dbConnection, $userID);
if (!isset($_SESSION['announcements_viewed'])) {
    $_SESSION['announcements_viewed'] = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <title>Pixora - Learnify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #111827, #1f2937);
            color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .coin-pulse {
            animation: coinPulse 1.5s infinite ease-in-out;
        }
        @keyframes coinPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .success-shake {
            animation: successShake 0.5s;
        }
        @keyframes successShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .error-shake {
            animation: errorShake 0.5s;
        }
        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50% { transform: translateX(-10px); }
            20%, 40% { transform: translateX(10px); }
        }
        .highlight-pulse {
            animation: highlightPulse 1s infinite;
        }
        @keyframes highlightPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .tile-fill {
            animation: tileFill 0.3s ease;
        }
        @keyframes tileFill {
            0% { transform: scale(0.9); opacity: 0.5; }
            100% { transform: scale(1); opacity: 1; }
        }
        .badge-appear {
            animation: badgeAppear 0.5s ease-in-out;
        }
        @keyframes badgeAppear {
            0% { opacity: 0; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        .game-container {
            max-width: 900px !important;
            width: 100% !important;
            background: #1f2937 !important;
            border-radius: 1rem !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
            padding: 2rem !important;
            overflow-x: hidden !important;
        }
        .game-text {
            color: #d1d5db !important;
            font-weight: 600 !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2) !important;
        }
        .rule-card, .mechanic-card, .bonus-card {
            background: #374151 !important;
            border: 1px solid #4b5563 !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease !important;
        }
        .rule-card:hover, .mechanic-card:hover, .bonus-card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3) !important;
        }
        .image-grid img {
            width: 100% !important;
            height: 230px !important;
            object-fit: cover !important;
            border-radius: 0.5rem !important;
            border: 3px solid #3b82f6 !important;
        }
        .input-error {
            border-color: #ef4444 !important;
            animation: errorShake 0.5s !important;
        }
        .answer-tile {
            width: 40px !important;
            height: 48px !important;
            background: #4b5563 !important;
            border: 2px dashed #3b82f6 !important;
            border-radius: 8px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.5rem !important;
            font-weight: bold !important;
            color: #f3f4f6 !important;
            text-transform: uppercase !important;
            cursor: text !important;
            transition: all 0.3s ease !important;
            margin: 2px !important;
        }
        .answer-tile.filled {
            background: #3b82f6 !important;
            border: 2px solid #3b82f6 !important;
            color: white !important;
        }
        .answer-tile.revealed {
            background: #3b82f6 !important;
            color: white !important;
            border: 2px solid #3b82f6 !important;
            cursor: default !important;
        }
        .answer-tile:focus {
            outline: none !important;
            border-color: #60a5fa !important;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.3) !important;
        }
        .answer-tile:empty:before {
            content: attr(placeholder) !important;
            color: #9ca3af !important;
        }
        .word-group {
            display: inline-flex !important;
            flex-wrap: wrap !important;
            gap: 4px !important;
            margin-right: 8px !important;
        }
        .tile-container {
            display: flex !important;
            flex-wrap: wrap !important;
            justify-content: center !important;
            gap: 8px !important;
            max-width: 100% !important;
            overflow-x: auto !important;
        }
        .today {
            background: #1d4ed8 !important;
            color: white !important;
        }
        .claimed {
            background: #4b5563 !important;
            opacity: 0.6 !important;
        }
        .title-container {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            background: linear-gradient(to right, #111827, #1f2937) !important;
            margin-bottom: 1rem !important;
            padding: 0 1rem !important;
        }
        .title {
            flex: 1 !important;
            font-size: 2.5rem !important;
            font-weight: bold !important;
            color: #d1d5db !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2) !important;
            text-align: center !important;
            padding: 1rem !important;
        }
        .daily-bonus-container {
            max-width: 100% !important;
            overflow-x: auto !important;
            padding: 1rem !important;
        }
        .calendar-grid {
            display: grid !important;
            grid-template-columns: repeat(7, 1fr) !important;
            gap: 0.5rem !important;
            text-align: center !important;
        }
        .calendar-day {
            padding: 1rem !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2) !important;
            min-width: 60px !important;
            font-size: 0.9rem !important;
        }
        .calendar-day i {
            font-size: 1.5rem !important;
            margin-bottom: 0.5rem !important;
            display: block !important;
        }
        .tooltip {
            position: relative !important;
        }
        .tooltip::after {
            content: attr(data-tooltip) !important;
            position: absolute !important;
            bottom: 100% !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            background: #374151 !important;
            color: #f3f4f6 !important;
            padding: 0.5rem !important;
            border-radius: 0.25rem !important;
            font-size: 0.8rem !important;
            white-space: nowrap !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transition: opacity 0.2s, visibility 0.2s !important;
            z-index: 10 !important;
        }
        .tooltip:hover::after {
            opacity: 1 !important;
            visibility: visible !important;
        }
        @media screen and (max-width: 768px) {
            .image-grid {
                display: grid !important;
                grid-template-columns: repeat(1, 1fr) !important;
                gap: 1rem !important;
            }
            .image-grid img {
                width: 100% !important;
                height: 200px !important;
                object-fit: cover !important;
                justify-self: center !important;
            }
            .answer-tile {
                width: 32px !important;
                height: 40px !important;
                font-size: 1.2rem !important;
                margin: 2px !important;
            }
            .word-group {
                margin-right: 6px !important;
                gap: 3px !important;
            }
            .tile-container {
                gap: 6px !important;
            }
            .title {
                font-size: 2rem !important;
            }
            .daily-bonus-container {
                padding: 0.75rem !important;
            }
            .calendar-grid {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 0.4rem !important;
            }
            .calendar-day {
                padding: 0.75rem !important;
                min-width: 50px !important;
                font-size: 0.8rem !important;
            }
            .calendar-day i {
                font-size: 1.25rem !important;
            }
            .tooltip::after {
                font-size: 0.7rem !important;
                max-width: 120px !important;
                white-space: normal !important;
            }
        }
        @media screen and (max-width: 640px) {
            .answer-tile {
                width: 28px !important;
                height: 36px !important;
                font-size: 1rem !important;
                margin: 1px !important;
            }
            .word-group {
                margin-right: 4px !important;
                gap: 2px !important;
            }
            .tile-container {
                gap: 4px !important;
                padding: 0 4px !important;
            }
            .game-container {
                padding: 1rem !important;
            }
            .title-container {
                justify-content: flex-start !important;
            }
            .title {
                text-align: left !important;
                font-size: 1.5rem !important;
                padding: 0.5rem !important;
            }
            .daily-bonus-container {
                padding: 0.5rem !important;
            }
            .calendar-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 0.3rem !important;
            }
            .calendar-day {
                padding: 0.5rem !important;
                min-width: 0 !important;
                font-size: 0.7rem !important;
            }
            .calendar-day i {
                font-size: 1rem !important;
            }
            .daily-bonus-container h2 {
                font-size: 1.25rem !important;
            }
            .daily-bonus-container button {
                padding: 0.5rem 1rem !important;
                font-size: 0.9rem !important;
            }
            .tooltip::after {
                font-size: 0.6rem !important;
                max-width: 100px !important;
            }
        }
        @media screen and (max-width: 480px) {
            .calendar-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.2rem !important;
            }
            .calendar-day {
                padding: 0.4rem !important;
                font-size: 0.65rem !important;
            }
            .calendar-day i {
                font-size: 0.9rem !important;
            }
            .daily-bonus-container h2 {
                font-size: 1rem !important;
            }
            .daily-bonus-container button {
                padding: 0.4rem 0.8rem !important;
                font-size: 0.8rem !important;
            }
            #next-bonus-timer {
                font-size: 0.8rem !important;
            }
            .tooltip::after {
                font-size: 0.55rem !important;
                max-width: 80px !important;
            }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <nav class="bg-gradient-to-r from-[#111827] to-[#1f2937] p-4 flex items-center justify-between fixed w-full top-0 z-50 shadow-lg">
        <div class="flex items-center gap-4">
            <a href="./game.php" class="bg-[#374151] text-[#d1d5db] w-10 h-10 rounded-full flex items-center justify-center text-xl hover:bg-[#3b82f6] hover:text-white transition transform hover:-rotate-12 hover:scale-110" title="Back to Games">
                <i class='bx bx-arrow-back'></i>
            </a>
            <div class="navbar-tabs hidden md:flex gap-2">
                <div class="nav-tab bg-[#374151] text-[#d1d5db] px-4 py-2 rounded-lg font-semibold uppercase text-sm cursor-pointer hover:bg-[#3b82f6] hover:text-white transition active" data-tab="play-game">Play Game</div>
                <div class="nav-tab text-[#d1d5db] px-4 py-2 rounded-lg font-semibold uppercase text-sm cursor-pointer hover:bg-[#3b82f6] hover:text-white transition" data-tab="how-to-play">How to Play</div>
                <div class="nav-tab text-[#d1d5db] px-4 py-2 rounded-lg font-semibold uppercase text-sm cursor-pointer hover:bg-[#3b82f6] hover:text-white transition" data-tab="mechanics">Mechanics</div>
                <div class="nav-tab text-[#d1d5db] px-4 py-2 rounded-lg font-semibold uppercase text-sm cursor-pointer hover:bg-[#3b82f6] hover:text-white transition" data-tab="daily-bonus">Daily Bonus</div>
            </div>
            <button class="md:hidden text-[#d1d5db] text-2xl" id="hamburger-btn">
                <i class='bx bx-menu'></i>
            </button>
        </div>
        <div class="relative">
            <div class="flex items-center gap-2 cursor-pointer" id="profile-toggle">
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile" class="w-10 h-10 rounded-full border-2 border-[#4b5563]">
                <span class="text-[#f3f4f6] font-semibold hidden md:block"><?php echo $_SESSION['firstName']; ?></span>
            </div>
            <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-64 bg-[#374151] rounded-lg shadow-xl p-4 z-50">
                <div class="text-center">
                    <img src="<?php echo $_SESSION['image']; ?>" alt="Profile" class="w-16 h-16 rounded-full mx-auto mb-2">
                    <h3 class="text-lg font-semibold text-[#f3f4f6]"><?php echo $_SESSION['firstName']; ?></h3>
                    <p class="text-xl text-[#d1d5db]">Current Level: <?php echo htmlspecialchars($currentLevel); ?></p>
                    <div class="mt-2 border-t pt-2">
                        <h4 class="text-lg font-semibold text-[#d1d5db]">Your Badge</h4>
                        <div class="flex flex-wrap gap-2 justify-center mt-1">
                            <span class='bg-gradient-to-r <?php echo $highestBadge['bg']; ?> px-4 py-3 rounded-full text-lg text-[#f3f4f6]'>
                                <?php echo $highestBadge['icon'] . ' ' . $highestBadge['text']; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <?php if (isset($_SESSION['result_message'])): ?>
    <div id="resultModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50">
        <div class="bg-<?php echo $_SESSION['result_status'] == 'success' ? '[#1d4ed8]' : '[#ef4444]'; ?> text-white font-semibold p-4 rounded-lg text-center <?php echo $_SESSION['result_status'] == 'success' ? 'success-shake' : 'error-shake'; ?>">
            <span class="absolute top-2 right-2 text-2xl cursor-pointer hover:text-gray-300 transition" onclick="closeResultModal()">×</span>
            <?php echo $_SESSION['result_message']; ?>
        </div>
    </div>
    <script>
        document.getElementById('resultModal').classList.remove('hidden');
    </script>
    <?php unset($_SESSION['result_message'], $_SESSION['result_status']); ?>
    <?php endif; ?>

    <div class="md:hidden fixed top-16 left-0 w-full bg-gradient-to-r from-[#111827] to-[#1f2937] shadow-lg z-40 hidden" id="mobile-menu">
        <div class="flex flex-col p-4 gap-2">
            <div class="nav-tab bg-[#374151] text-[#d1d5db] px-4 py-2 rounded-lg font-semibold uppercase text-sm cursor-pointer hover:bg-[#3b82f6] hover:text-white transition active" data-tab="play-game">Play Game</div>
            <div class="nav-tab text-[#d1d5db] px-4 py-2 rounded-lg font-semibold uppercase text-sm cursor-pointer hover:bg-[#3b82f6] hover:text-white transition" data-tab="how-to-play">How to Play</div>
            <div class="nav-tab text-[#d1d5db] px-4 py-2 rounded-lg font-semibold uppercase text-sm cursor-pointer hover:bg-[#3b82f6] hover:text-white transition" data-tab="mechanics">Mechanics</div>
            <div class="nav-tab text-[#d1d5db] px-4 py-2 rounded-lg font-semibold uppercase text-sm cursor-pointer hover:bg-[#3b82f6] hover:text-white transition" data-tab="daily-bonus">Daily Bonus</div>
        </div>
    </div>

    <div class="main-content mt-20 md:mt-16 p-4 max-w-7xl mx-auto w-full">
        <div class="tab-content active" id="play-game">
            <div class="game-container bg-[#1f2937] rounded-lg p-6 shadow-lg fade-in-up">
                <div class="flex items-center bg-gradient-to-r from-[#111827] to-[#1f2937] mb-4 px-4">
                    <div class="flex-1"></div>
                    <h1 class="flex-1 text-5xl font-bold game-text text-center p-4">🖼️Pixora🖼️</h1>
                    <div class="flex-1 flex justify-end">
                        <div class="flex items-center gap-2 bg-[#374151] text-[#f3f4f6] px-4 py-2 rounded-full font-semibold coin-pulse shadow-md">
                            <i class='bx bxs-coin-stack text-2xl'></i>
                            <span class="text-lg"><?php echo htmlspecialchars($totalPoints); ?> Coins</span>
                        </div>
                    </div>
                </div>
                <?php if ($hasCertificate): ?>
                    <a href="speechGameCert.php" class="block mx-auto bg-[#1d4ed8] text-white py-2 px-4 rounded-lg text-center text-lg font-semibold hover:bg-[#3b82f6] transition shadow-md my-4 w-48" target="_blank">View Certificate 🏅</a>
                <?php endif; ?>

                <?php if ($displayLevel > 30): ?>
                    <div class="bg-[#374151] p-4 rounded-lg text-center my-4">
                        <p class="text-2xl font-bold game-text">Coming Soon!</p>
                        <p class="text-xl game-text">New levels are on the way! Check back later. 🚀</p>
                    </div>
                    <a href="imageGame.php?level=30" class="bg-[#3b82f6] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#60a5fa] transition mt-4">Back to Level 30</a>
                <?php elseif ($image): ?>
                    <div class="flex justify-between items-center mb-4">
                        <a href="imageGame.php?level=<?php echo max(1, $displayLevel - 1); ?>" 
                           class="bg-[#3b82f6] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#60a5fa] transition <?php if ($displayLevel <= 1) echo 'bg-[#4b5563] cursor-not-allowed opacity-60'; ?>" 
                           <?php if ($displayLevel <= 1) echo 'disabled'; ?>>
                            <i class='bx bx-chevron-left text-xl'></i>
                        </a>
                        <div class="text-center">
                            <h2 class="text-2xl font-bold game-text">Level <?php echo htmlspecialchars($displayLevel); ?></h2>
                            <?php if ($stars > 0): ?>
                                <div class="text-2xl"><?php echo str_repeat('⭐', $stars); ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="imageGame.php?level=<?php echo $displayLevel + 1; ?>" 
                           class="bg-[#3b82f6] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#60a5fa] transition <?php if ($displayLevel >= $currentLevel || $displayLevel >= 30) echo 'bg-[#4b5563] cursor-not-allowed opacity-60'; ?>" 
                           <?php if ($displayLevel >= $currentLevel || $displayLevel >= 30) echo 'disabled'; ?>>
                            <i class='bx bx-chevron-right text-xl'></i>
                        </a>
                    </div>
                    <div class="image-grid grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <img src="../../Uploads/speech_game/<?php echo htmlspecialchars($image['imageFile1']); ?>" 
                             alt="Image part 1" class="w-full h-48 rounded-lg border-4 border-[#3b82f6] object-cover cursor-pointer hover:scale-105 hover:rotate-2 transition shadow-md" 
                             onclick="openImageModal('../../Uploads/speech_game/<?php echo htmlspecialchars($image['imageFile1']); ?>')">
                        <?php if ($image['imageFile2']): ?>
                            <img src="../../Uploads/speech_game/<?php echo htmlspecialchars($image['imageFile2']); ?>" 
                                 alt="Image part 2" class="w-full h-48 rounded-lg border-4 border-[#3b82f6] object-cover cursor-pointer hover:scale-105 hover:rotate-2 transition shadow-md" 
                                 onclick="openImageModal('../../Uploads/speech_game/<?php echo htmlspecialchars($image['imageFile2']); ?>')">
                        <?php endif; ?>
                        <?php if ($image['imageFile3']): ?>
                            <img src="../../Uploads/speech_game/<?php echo htmlspecialchars($image['imageFile3']); ?>" 
                                 alt="Image part 3" class="w-full h-48 rounded-lg border-4 border-[#3b82f6] object-cover cursor-pointer hover:scale-105 hover:rotate-2 transition shadow-md" 
                                 onclick="openImageModal('../../Uploads/speech_game/<?php echo htmlspecialchars($image['imageFile3']); ?>')">
                        <?php endif; ?>
                    </div>

                    <?php if ($isViewOnly): ?>
                        <div class="bg-[#374151] p-4 rounded-lg text-center my-4">
                            <p class="text-lg game-text">Completed Level 🎉</p>
                            <p class="text-4xl font-bold game-text uppercase"><?php echo htmlspecialchars($image['correctAnswer']); ?></p>
                        </div>
                        <?php if ($image['description']): ?>
                            <div class="text-center text-sm game-text my-2 text-xl">
                                <strong></strong> <span class="highlightable-text"><?php echo $highlightableDescription; ?></span>
                            </div>
                            <button type="button" id="hint-btn" class="bg-[#374151] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#60a5fa] transition">Read Hint 🔍</button>
                            <p id="hint-text" class="hidden text-sm game-text mt-2"><?php echo htmlspecialchars($image['description']); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="flex justify-center gap-2 mb-6 flex-wrap">
                            <?php 
                            $charIndex = 0;
                            foreach ($answerWords as $wordIndex => $word):
                            ?>
                                <div class="word-group">
                                    <?php for ($i = 0; $i < strlen($word); $i++): ?>
                                        <div class="answer-tile <?php echo in_array($charIndex, $revealedLetters) ? 'revealed' : ''; ?>" 
                                             contenteditable="<?php echo in_array($charIndex, $revealedLetters) ? 'false' : 'true'; ?>" 
                                             data-index="<?php echo $charIndex; ?>" 
                                             data-letter="<?php echo htmlspecialchars($answerChars[$charIndex]); ?>"
                                             placeholder="_"
                                             oninput="handleTileInput(this)">
                                            <?php echo in_array($charIndex, $revealedLetters) ? htmlspecialchars(strtoupper($answerChars[$charIndex])) : ''; ?>
                                        </div>
                                        <?php $charIndex++; ?>
                                    <?php endfor; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" id="guess-form" class="flex flex-col sm:flex-row gap-3 justify-center mb-4">
                            <input type="hidden" name="imageID" value="<?php echo $image['imageID']; ?>">
                            <input type="hidden" name="userAnswer" id="hidden-user-answer">
                            <button type="submit" id="submit-btn" class="bg-[#3b82f6] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#60a5fa] transition">Guess!</button>
                        </form>
                        <div class="flex justify-center gap-4 mb-4">
                            <form method="post">
                                <input type="hidden" name="imageID" value="<?php echo $image['imageID']; ?>">
                                <input type="hidden" name="reveal_letter" value="true">
                                <button type="submit" class="bg-[#374151] text-m text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#6b7280] transition <?php if ($totalPoints < 30 || count($revealedLetters) >= $answerCharCount) echo 'bg-[#4b5563] cursor-not-allowed opacity-60'; ?>" <?php if ($totalPoints < 30 || count($revealedLetters) >= $answerCharCount) echo 'disabled'; ?>>Reveal Letter (30 Coins)</button>
                            </form>
                            <button type="button" id="lifeline-btn" class="bg-[#374151] text-m text-white px-5 py-4 rounded-lg font-semibold hover:bg-[#6b7280] transition <?php if ($totalPoints < 150) echo 'bg-[#4b5563] cursor-not-allowed opacity-60'; ?>" <?php if ($totalPoints < 150) echo 'disabled'; ?> onclick="useLifeline()">Skip Lifeline (150 Coins)</button>
                        </div>
                        <?php if ($latestAnswer): ?>
                            <div class="text-sm game-text mt-2 text-center">Last Guess: <?php echo $latestAnswer; ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-[#374151] p-4 rounded-lg text-center my-4">
                        <p class="text-lg font-bold game-text">Coming Soon!</p>
                        <p class="text-sm game-text">New levels are on the way! Check back later. 🚀</p>
                    </div>
                    <?php if ($hasCertificate): ?>
                        <a href="imageGame.php?level=30" class="bg-[#3b82f6] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#60a5fa] transition mt-4">Back to Level 30</a>
                    <?php endif; ?>
                <?php endif; ?>
                <button class="bg-[#374151] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#60a5fa] transition mt-4" onclick="openLevelModal()">Choose Level 📊</button>
            </div>

            <div id="levelModal" class="fixed inset-0 bg-black bg-opacity-70 hidden flex items-center justify-center z-50">
                <div class="bg-[#374151] p-6 rounded-lg max-w-xl w-full max-h-[85vh] overflow-y-auto shadow-xl fade-in-up">
                    <span class="absolute top-4 right-4 text-2xl cursor-pointer hover:text-[#ef4444] transition transform hover:rotate-90" onclick="closeLevelModal()">×</span>
                    <h2 class="text-xl font-bold game-text uppercase mb-4">Choose Level</h2>
                    <div class="grid grid-cols-5 sm:grid-cols-6 gap-3">
                        <?php for ($i = 1; $i <= 30; $i++): ?>
                            <a href="<?php echo $i <= $currentLevel ? "imageGame.php?level=$i" : '#'; ?>" 
                               class="level-btn text-center p-3 rounded-lg font-semibold text-sm transition <?php echo $i == $displayLevel ? 'bg-[#3b82f6] text-white' : ($i > $currentLevel ? 'bg-[#4b5563] cursor-not-allowed opacity-60' : 'bg-[#6b7280] hover:bg-[#3b82f6] hover:text-white'); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-90 hidden flex items-center justify-center z-50">
                <div class="p-4 flex items-center justify-center">
                    <span class="absolute top-4 right-4 text-2xl text-[#f3f4f6] cursor-pointer hover:text-[#ef4444] transition transform hover:rotate-90" onclick="closeImageModal()">×</span>
                    <img id="fullscreenImage" src="" alt="Full-screen Image" class="max-w-full max-h-[90vh] rounded-lg object-contain">
                </div>
            </div>
        </div>

        <div class="tab-content hidden" id="how-to-play">
            <div class="bg-[#1f2937] p-6 rounded-lg shadow-lg fade-in-up">
                <h2 class="text-2xl font-bold game-text uppercase mb-6 text-center">How to Play</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="rule-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">🖼️</span>
                            <h3 class="text-xl font-semibold game-text">Images</h3>
                        </div>
                        <ul class="text-[#d1d5db] text-base list-disc list-inside pl-4">
                            <li>Look at the 1 to 3 images provided – they all connect to one word!</li>
                        </ul>
                    </div>
                    <div class="rule-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">💰</span>
                            <h3 class="text-xl font-semibold game-text">Guessing</h3>
                        </div>
                        <ul class="text-[#d1d5db] text-base list-disc list-inside pl-4">
                            <li>Click the letter tiles to type your guess and hit "Guess!" to submit.</li>
                            <li>Correct guesses earn you 15 coins and stars based on attempts.</li>
                        </ul>
                    </div>
                    <div class="rule-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">⭐</span>
                            <h3 class="text-xl font-semibold game-text">Stars</h3>
                        </div>
                        <ul class="text-[#d1d5db] text-base list-disc list-inside pl-4">
                            <li>⭐⭐⭐ 1 to 3 attempts</li>
                            <li>⭐⭐ 4 to 6 attempts</li>
                            <li>⭐ 7 attempts</li>
                        </ul>
                    </div>
                    <div class="rule-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">🏆</span>
                            <h3 class="text-xl font-semibold game-text">Lifelines & Completion</h3>
                        </div>
                        <ul class="text-[#d1d5db] text-base list-disc list-inside pl-4">
                            <li>If you're stuck, use a lifeline for 150 coins to skip the level or reveal a letter for 30 coins.</li>
                            <li>Complete all 30 levels to unlock your certificate!</li>
                        </ul>
                    </div>
                    <div class="rule-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">⚙️</span>
                            <h3 class="text-xl font-semibold game-text">Answer Processing</h3>
                        </div>
                        <ul class="text-[#d1d5db] text-base list-disc list-inside pl-4">
                            <li>Your guess is processed by removing punctuation, normalizing spaces, and ignoring case for matching against the correct answer.</li>
                        </ul>
                    </div>
                    <div class="rule-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">🎁</span>
                            <h3 class="text-xl font-semibold game-text">Rewards</h3>
                        </div>
                        <ul class="text-[#d1d5db] text-base list-disc list-inside pl-4">
                            <li>Complete Level 20 for a random reward of 200, 300, or 400 coins!</li>
                        </ul>
                    </div>
                    <div class="rule-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">😊</span>
                            <h3 class="text-xl font-semibold game-text">Fun</h3>
                        </div>
                        <ul class="text-[#d1d5db] text-base list-disc list-inside pl-4">
                            <li>Have fun and learn new words along the way!</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-content hidden" id="mechanics">
            <div class="bg-[#1f2937] p-6 rounded-lg shadow-lg fade-in-up">
                <h2 class="text-2xl font-bold game-text uppercase mb-6 text-center">Game Mechanics</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mechanic-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">🧩</span>
                            <h3 class="text-xl font-semibold game-text">Puzzle Images</h3>
                        </div>
                        <p class="text-[#d1d5db] text-base">Each level features 1-3 images that hint at a single word or phrase.</p>
                    </div>
                    <div class="mechanic-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">📝</span>
                            <h3 class="text-xl font-semibold game-text">Answer Submission</h3>
                        </div>
                        <p class="text-[#d1d5db] text-base">Click letter tiles to enter your answer (case-insensitive, punctuation ignored) and submit.</p>
                    </div>
                    <div class="mechanic-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">🎯</span>
                            <h3 class="text-xl font-semibold game-text">Correct Answers</h3>
                        </div>
                        <p class="text-[#d1d5db] text-base">Correct answers earn +15 coins, stars based on attempts, and advance you to the next level.</p>
                    </div>
                    <div class="mechanic-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">🔄</span>
                            <h3 class="text-xl font-semibold game-text">Wrong Answers</h3>
                        </div>
                        <p class="text-[#d1d5db] text-base">No penalty for wrong answers – try again with hints from previous guesses.</p>
                    </div>
                    <div class="mechanic-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">⚡</span>
                            <h3 class="text-xl font-semibold game-text">Lifelines</h3>
                        </div>
                        <p class="text-[#d1d5db] text-base">Skip a level for 150 coins or reveal a letter for 30 coins if you have enough.</p>
                    </div>
                    <div class="mechanic-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">📈</span>
                            <h3 class="text-xl font-semibold game-text">Progress Tracking</h3>
                        </div>
                        <p class="text-[#d1d5db] text-base">Progress tracked across 30 levels; complete all for a certificate.</p>
                    </div>
                    <div class="mechanic-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">🔍</span>
                            <h3 class="text-xl font-semibold game-text">Review Levels</h3>
                        </div>
                        <p class="text-[#d1d5db] text-base">Review completed levels for answers and educational hints.</p>
                    </div>
                    <div class="mechanic-card p-4 rounded-lg shadow-md bg-gray-800">
                        <div class="flex items-center mb-2">
                            <span class="text-4xl mr-3">🎮</span>
                            <h3 class="text-xl font-semibold game-text">Interactive Elements</h3>
                        </div>
                        <p class="text-[#d1d5db] text-base">Enjoy zoomable images and speech feedback for engagement.</p>
                    </div>
                </div>
                <h3 class="text-xl font-semibold game-text mt-6 mb-2 text-center">Badges Explained</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gradient-to-r from-[#4b5563] to-[#6b7280] p-4 rounded-lg text-center badge-appear">
                        <span class="text-4xl mb-2 block">🥉</span>
                        <h4 class="text-xl font-semibold game-text">Beginner</h4>
                        <p class="text-base text-[#d1d5db]">Complete Level 1 to earn this badge, marking your first step in the game!</p>
                    </div>
                    <div class="bg-gradient-to-r from-[#4b5563] to-[#6b7280] p-4 rounded-lg text-center badge-appear">
                        <span class="text-4xl mb-2 block">🥈</span>
                        <h4 class="text-xl font-semibold game-text">Intermediate</h4>
                        <p class="text-base text-[#d1d5db]">Reach Level 11 to show you're mastering the challenges!</p>
                    </div>
                    <div class="bg-gradient-to-r from-[#1d4ed8] to-[#3b82f6] p-4 rounded-lg text-center badge-appear">
                        <span class="text-4xl mb-2 block">🥇</span>
                        <h4 class="text-xl font-semibold game-text">Expert</h4>
                        <p class="text-base text-[#d1d5db]">Hit Level 21 to prove your expertise in solving puzzles!</p>
                    </div>
                    <div class="bg-gradient-to-r from-[#1d4ed8] to-[#3b82f6] p-4 rounded-lg text-center badge-appear">
                        <span class="text-4xl mb-2 block">🏆</span>
                        <h4 class="text-xl font-semibold game-text">Master</h4>
                        <p class="text-base text-[#d1d5db]">Complete all 30 levels to become a true master of Pixora!</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-content hidden" id="daily-bonus">
            <div class="daily-bonus-container bg-[#1f2937] rounded-xl shadow-2xl max-w-6xl mx-auto fade-in-up p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-3xl font-bold game-text uppercase text-center m-auto bg-gradient-to-r from-[#3b82f6] to-[#60a5fa] text-transparent bg-clip-text">📅 <?php echo date('F Y'); ?> Daily Bonus</h2>
                </div>
                <div class="calendar-grid text-center text-lg">
                    <?php
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    $dayRewards = [10, 15, 20, 25, 30, 35, 150];
                    $todayDay = date('l');
                    $currentMonth = date('n'); // Current month (1-12)
                    $currentYear = date('Y'); // Current year
                    $monthDays = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear); // Days in current month
                    $firstDay = date('N', strtotime("$currentYear-$currentMonth-01")) - 1; // First day of the month (0-6)

                    // Empty slots for days before the first day of the month
                    for ($i = 0; $i < $firstDay; $i++):
                        ?>
                        <div class="calendar-day p-4 rounded-lg bg-gray-700 opacity-50"></div>
                    <?php endfor; ?>

                    <?php
                    // Generate calendar for the current month
                    for ($day = 1; $day <= $monthDays; $day++):
                        $date = sprintf('%s-%02d-%02d', $currentYear, $currentMonth, $day);
                        $weekDay = date('l', strtotime($date));
                        $weekIndex = array_search($weekDay, $days);
                        $isToday = $date === date('Y-m-d');
                        $claimedStmt = $dbConnection->prepare("SELECT COUNT(*) FROM speech_to_text_daily_bonus WHERE userID = :userID AND claimDate = :bonusDate");
                        $claimedStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                        $claimedStmt->bindParam(':bonusDate', $date, PDO::PARAM_STR);
                        $claimedStmt->execute();
                        $isClaimed = $claimedStmt->fetchColumn() > 0;
                        $icon = $isClaimed ? '✓' : 'X'; // Checkmark for claimed, X for unclaimed
                        $iconColor = $isClaimed ? 'bg-green-500' : 'bg-red-500'; // Green for claimed, red for unclaimed
                        $iconAnimation = $isClaimed ? 'animate-check-pulse' : 'animate-x-pulse'; // Animation for icons
                        $tooltip = $isClaimed ? "Claimed: {$dayRewards[$weekIndex]} coins" : ($isToday ? "Claim today for {$dayRewards[$weekIndex]} coins!" : "Available: {$dayRewards[$weekIndex]} coins");
                        ?>
                        <div class="calendar-day p-4 rounded-lg shadow-md <?php echo $isToday ? 'today bg-gradient-to-br from-[#1d4ed8] to-[#3b82f6]' : ($isClaimed ? 'claimed bg-gradient-to-br from-[#374151] to-[#4b5563]' : 'bg-gradient-to-br from-[#374151] to-[#4b5563] animate-glow'); ?> tooltip relative backdrop-blur-sm" data-tooltip="<?php echo $tooltip; ?>">
                            <div class="absolute top-2 right-2 w-6 h-6 rounded-full <?php echo $iconColor; ?> flex items-center justify-center text-white text-sm font-bold shadow-md <?php echo $iconAnimation; ?>">
                                <?php echo $icon; ?>
                            </div>
                            <p class="font-semibold text-base text-[#f3f4f6] mb-1"><?php echo date('M d', strtotime($date)); ?></p>
                            <p class="text-sm text-[#d1d5db] flex items-center justify-center gap-1">
                                <i class='bx bxs-coin-stack text-yellow-400'></i>
                                <?php echo $dayRewards[$weekIndex]; ?> coins
                            </p>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="text-center mt-6">
                    <form method="post">
                        <input type="hidden" name="claim_bonus" value="true">
                        <button type="submit" class="bg-gradient-to-r from-[#3b82f6] to-[#60a5fa] text-white px-6 py-3 rounded-lg font-semibold hover:from-[#60a5fa] hover:to-[#93c5fd] transition transform hover:scale-105 shadow-md <?php if ($claimedToday) echo 'bg-[#4b5563] cursor-not-allowed opacity-60'; ?>" <?php if ($claimedToday) echo 'disabled'; ?>>
                            <?php echo $claimedToday ? 'Claimed Today' : 'Claim Today\'s Bonus'; ?>
                        </button>
                    </form>
                    <div id="next-bonus-timer" class="text-lg game-text mt-4">Next bonus in: <span id="countdown"></span></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active', 'bg-[#374151]', 'text-[#d1d5db]'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
                tab.classList.add('active', 'bg-[#374151]', 'text-[#d1d5db]');
                const content = document.getElementById(tab.dataset.tab);
                content.classList.remove('hidden');
                content.classList.add('fade-in-up');
            });
        });

        const hamburgerBtn = document.getElementById('hamburger-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        hamburgerBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        const profileToggle = document.getElementById('profile-toggle');
        const profileDropdown = document.getElementById('profile-dropdown');
        profileToggle.addEventListener('click', () => {
            profileDropdown.classList.toggle('hidden');
        });

        function openLevelModal() {
            const modal = document.getElementById('levelModal');
            modal.classList.remove('hidden');
            modal.classList.add('fade-in-up');
        }

        function closeLevelModal() {
            document.getElementById('levelModal').classList.add('hidden');
        }

        function openImageModal(imageSrc) {
            document.getElementById('fullscreenImage').src = imageSrc;
            const modal = document.getElementById('imageModal');
            modal.classList.remove('hidden');
            modal.classList.add('fade-in-up');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
        }

        function closeResultModal() {
            document.getElementById('resultModal').classList.add('hidden');
        }

        window.onclick = function(event) {
            const levelModal = document.getElementById('levelModal');
            const imageModal = document.getElementById('imageModal');
            const resultModal = document.getElementById('resultModal');
            if (event.target === levelModal) closeLevelModal();
            if (event.target === imageModal) closeImageModal();
            if (event.target === resultModal) closeResultModal();
            if (!profileToggle.contains(event.target) && !profileDropdown.contains(event.target)) {
                profileDropdown.classList.add('hidden');
            }
        };

        const SpeechSynthesisUtterance = window.SpeechSynthesisUtterance || window.webkitSpeechSynthesisUtterance;
        const speechSynthesis = window.speechSynthesis || window.webkitSpeechSynthesis;

        document.addEventListener('DOMContentLoaded', () => {
            const resultMessage = document.querySelector('#resultModal');
            if (resultMessage && SpeechSynthesisUtterance && speechSynthesis) {
                let utteranceText = resultMessage.querySelector('div').textContent;
                const utterance = new SpeechSynthesisUtterance(utteranceText);
                utterance.lang = 'en-US';
                utterance.volume = 1;
                utterance.rate = 1;
                utterance.pitch = 1.1;
                speechSynthesis.speak(utterance);
                setTimeout(() => {
                    closeResultModal();
                }, 5000);
            }

            function handleTileInput(tile) {
                let text = tile.textContent.trim().toUpperCase().replace(/[^A-Z]/g, '');
                if (text.length > 1) {
                    text = text.slice(0, 1);
                }
                tile.textContent = text;
                if (text) {
                    tile.classList.add('filled', 'tile-fill');
                } else {
                    tile.classList.remove('filled', 'tile-fill');
                }
                const range = document.createRange();
                const sel = window.getSelection();
                range.selectNodeContents(tile);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);

                // Move to next editable tile if a letter was entered
                if (text) {
                    const tiles = Array.from(document.querySelectorAll('.answer-tile:not(.revealed)'));
                    const currentIndex = tiles.indexOf(tile);
                    const nextTile = tiles[currentIndex + 1];
                    if (nextTile) {
                        nextTile.focus();
                    }
                }
            }

            document.querySelectorAll('.answer-tile:not(.revealed)').forEach(tile => {
                tile.addEventListener('input', () => handleTileInput(tile));

                tile.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const text = (e.clipboardData || window.clipboardData).getData('text').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 1);
                    tile.textContent = text;
                    handleTileInput(tile);
                });

                tile.addEventListener('keydown', (e) => {
                    const tiles = Array.from(document.querySelectorAll('.answer-tile:not(.revealed)'));
                    const currentIndex = tiles.indexOf(tile);
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('submit-btn').click();
                    } else if (e.key === 'Backspace' && !tile.textContent) {
                        e.preventDefault();
                        const prevTile = tiles[currentIndex - 1];
                        if (prevTile) {
                            prevTile.focus();
                            prevTile.textContent = '';
                            prevTile.classList.remove('filled', 'tile-fill');
                        }
                    } else if (e.key === 'ArrowLeft' && currentIndex > 0) {
                        e.preventDefault();
                        tiles[currentIndex - 1].focus();
                    } else if (e.key === 'ArrowRight' && currentIndex < tiles.length - 1) {
                        e.preventDefault();
                        tiles[currentIndex + 1].focus();
                    }
                });
            });

            document.getElementById('guess-form').addEventListener('submit', (e) => {
                const tiles = document.querySelectorAll('.answer-tile');
                const wordBoundaries = <?php echo json_encode($wordBoundaries); ?>;
                let userAnswer = '';
                let charIndex = 0;
                wordBoundaries.forEach((boundary, index) => {
                    let word = '';
                    for (let i = 0; i < boundary.length; i++) {
                        word += tiles[charIndex].textContent.trim().toUpperCase() || '';
                        charIndex++;
                    }
                    userAnswer += word;
                    if (index < wordBoundaries.length - 1) {
                        userAnswer += ' ';
                    }
                });
                document.getElementById('hidden-user-answer').value = userAnswer;
            });

            const countdownElement = document.getElementById('countdown');
            if (countdownElement) {
                function updateCountdown() {
                    const now = new Date();
                    const tomorrow = new Date(now);
                    tomorrow.setDate(now.getDate() + 1);
                    tomorrow.setHours(0, 0, 0, 0);
                    const timeLeft = tomorrow - now;
                    const hours = Math.floor(timeLeft / (1000 * 60 * 60));
                    const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                    countdownElement.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                }
                updateCountdown();
                setInterval(updateCountdown, 1000);
            }
        });

        const hintBtn = document.getElementById('hint-btn');
        const hintText = document.getElementById('hint-text');
        const highlightableText = document.querySelector('.highlightable-text');

        if (hintBtn && hintText && SpeechSynthesisUtterance && speechSynthesis) {
            hintBtn.addEventListener('click', () => {
                speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(hintText.textContent);
                utterance.lang = 'en-US';
                utterance.volume = 1;
                utterance.rate = 0.9;
                utterance.pitch = 1;

                if ('onboundary' in utterance) {
                    const words = highlightableText ? highlightableText.querySelectorAll('.word') : [];
                    let currentWordIndex = 0;

                    utterance.onboundary = (event) => {
                        if (event.name === 'word' && highlightableText) {
                            if (currentWordIndex > 0) {
                                words[currentWordIndex - 1].classList.remove('bg-[#1d4ed8]', 'text-white', 'highlight-pulse');
                            }
                            if (currentWordIndex < words.length) {
                                words[currentWordIndex].classList.add('bg-[#1d4ed8]', 'text-white', 'highlight-pulse');
                                currentWordIndex++;
                            }
                        }
                    };

                    utterance.onend = () => {
                        if (highlightableText) {
                            words.forEach(word => word.classList.remove('bg-[#1d4ed8]', 'text-white', 'highlight-pulse'));
                        }
                    };
                }

                speechSynthesis.speak(utterance);
            });
        } else if (hintBtn) {
            hintBtn.style.display = 'none';
        }

        function useLifeline() {
            if (confirm('Ready to use 150 coins to skip this level? It\'s okay to ask for help! 😊')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'imageGame.php';
                form.innerHTML = `
                    <input type="hidden" name="imageID" value="<?php echo $image['imageID'] ?? ''; ?>">
                    <input type="hidden" name="useLifeline" value="true">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>