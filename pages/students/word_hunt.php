<?php
require_once './sessions/session_student.php';
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

    // Fetch boxes
    $boxStmt = $dbConnection->prepare("SELECT boxID, boxName, description, image FROM word_boxes WHERE archived = 0");
    $boxStmt->execute();
    $boxes = $boxStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch user progress
    $progressStmt = $dbConnection->prepare("
        SELECT progressID, boxID, categoryID, stars, completed, completionTime
        FROM word_user_progress
        WHERE userID = :userID
    ");
    $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $progressStmt->execute();
    $progress = $progressStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize progress by box and category
    $boxProgress = [];
    foreach ($progress as $p) {
        $boxProgress[$p['boxID']][$p['categoryID']] = [
            'progressID' => $p['progressID'],
            'stars' => $p['stars'],
            'completed' => $p['completed'],
            'completionTime' => $p['completionTime']
        ];
    }

    // Fetch completed boxes (certificates)
    $certStmt = $dbConnection->prepare("SELECT COUNT(*) as completedBoxes FROM word_certificates WHERE userID = :userID");
    $certStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $certStmt->execute();
    $completedBoxes = $certStmt->fetch(PDO::FETCH_ASSOC)['completedBoxes'];

    // Fetch badge based on completed boxes
    $badgeStmt = $dbConnection->prepare("
        SELECT level, badgeName, icon, description 
        FROM word_badges 
        WHERE level = :level
    ");
    $badgeLevel = min($completedBoxes, 7); // Cap at level 7
    $badgeStmt->bindParam(':level', $badgeLevel, PDO::PARAM_INT);
    $badgeStmt->execute();
    $currentBadge = $badgeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentBadge) {
        $currentBadge = [
            'level' => 0,
            'badgeName' => 'No Badge',
            'icon' => '❌',
            'description' => 'Complete boxes to earn your first badge!'
        ];
    }

    // Handle word game submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['categoryID'])) {
        try {
            $categoryID = filter_var($_POST['categoryID'], FILTER_VALIDATE_INT);
            $boxID = filter_var($_POST['boxID'], FILTER_VALIDATE_INT);
            $completionTime = filter_var($_POST['completionTime'], FILTER_VALIDATE_INT);
            $stars = filter_var($_POST['stars'], FILTER_VALIDATE_INT);
            $foundWords = json_decode($_POST['foundWords'], true);

            if (!$categoryID || !$boxID || !$completionTime || !$stars || !is_array($foundWords)) {
                error_log("Invalid submission data: categoryID=$categoryID, boxID=$boxID, completionTime=$completionTime, stars=$stars, foundWords=" . print_r($_POST['foundWords'], true));
                $_SESSION['error_message'] = "Invalid or incomplete submission data.";
                header("Location: word_hunt.php");
                exit;
            }

            $dbConnection->beginTransaction();

            // Save progress
            $progressStmt = $dbConnection->prepare("
                INSERT INTO word_user_progress (userID, boxID, categoryID, completionTime, stars, completed, attemptDate)
                VALUES (:userID, :boxID, :categoryID, :completionTime, :stars, 1, NOW())
            ");
            $progressStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $progressStmt->bindParam(':boxID', $boxID, PDO::PARAM_INT);
            $progressStmt->bindParam(':categoryID', $categoryID, PDO::PARAM_INT);
            $progressStmt->bindParam(':completionTime', $completionTime, PDO::PARAM_INT);
            $progressStmt->bindParam(':stars', $stars, PDO::PARAM_INT);
            $progressStmt->execute();
            $progressID = $dbConnection->lastInsertId();

            // Save found words
            foreach ($foundWords as $wordID) {
                $wordID = filter_var($wordID, FILTER_VALIDATE_INT);
                if ($wordID) {
                    // Verify word belongs to the category
                    $wordCheckStmt = $dbConnection->prepare("SELECT COUNT(*) FROM word_words WHERE wordID = :wordID AND categoryID = :categoryID");
                    $wordCheckStmt->bindParam(':wordID', $wordID, PDO::PARAM_INT);
                    $wordCheckStmt->bindParam(':categoryID', $categoryID, PDO::PARAM_INT);
                    $wordCheckStmt->execute();
                    if ($wordCheckStmt->fetchColumn() > 0) {
                        $foundStmt = $dbConnection->prepare("
                            INSERT INTO word_user_found_words (progressID, userID, categoryID, wordID, foundTime)
                            VALUES (:progressID, :userID, :categoryID, :wordID, NOW())
                        ");
                        $foundStmt->bindParam(':progressID', $progressID, PDO::PARAM_INT);
                        $foundStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                        $foundStmt->bindParam(':categoryID', $categoryID, PDO::PARAM_INT);
                        $foundStmt->bindParam(':wordID', $wordID, PDO::PARAM_INT);
                        $foundStmt->execute();
                    } else {
                        error_log("Invalid wordID $wordID for categoryID $categoryID");
                    }
                }
            }

            // Check if all categories in box are completed
            $catStmt = $dbConnection->prepare("SELECT COUNT(*) as totalCats FROM word_categories WHERE boxID = :boxID AND archived = 0");
            $catStmt->bindParam(':boxID', $boxID, PDO::PARAM_INT);
            $catStmt->execute();
            $totalCats = $catStmt->fetch(PDO::FETCH_ASSOC)['totalCats'];

            $compStmt = $dbConnection->prepare("SELECT COUNT(*) as compCats FROM word_user_progress WHERE userID = :userID AND boxID = :boxID AND completed = 1");
            $compStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $compStmt->bindParam(':boxID', $boxID, PDO::PARAM_INT);
            $compStmt->execute();
            $compCats = $compStmt->fetch(PDO::FETCH_ASSOC)['compCats'];

            if ($compCats >= $totalCats) {
                $certStmt = $dbConnection->prepare("
                    INSERT INTO word_certificates (userID, boxID, issueDate)
                    SELECT :userID, :boxID, NOW()
                    WHERE NOT EXISTS (
                        SELECT 1 FROM word_certificates WHERE userID = :userID AND boxID = :boxID
                    )
                ");
                $certStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $certStmt->bindParam(':boxID', $boxID, PDO::PARAM_INT);
                $certStmt->execute();
            }

            $dbConnection->commit();
            $_SESSION['success_message'] = "Category completed! Stars: $stars";
            header("Location: word_hunt.php");
            exit;
        } catch (PDOException $e) {
            $dbConnection->rollBack();
            error_log("Database Error during submission: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to save game progress. Please try again.";
            header("Location: word_hunt.php");
            exit;
        } catch (Exception $e) {
            $dbConnection->rollBack();
            error_log("General Error during submission: " . $e->getMessage());
            $_SESSION['error_message'] = "An unexpected error occurred. Please try again.";
            header("Location: word_hunt.php");
            exit;
        }
    }

    // Include notification count
    require_once './get_notification_count.php';
    $unreadCount = getUnreadAnnouncementCount($dbConnection, $userID);
    if (!isset($_SESSION['announcements_viewed'])) {
        $_SESSION['announcements_viewed'] = false;
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
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Word Hunt Game</title>
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
            --scrollbar-bg: #e5e7eb;
            --scrollbar-thumb: #4b5563;
            --bronze: #cd7f32;
            --silver: #c0c0c0;
            --gold: #ffd700;
            --incorrect: #ff6b6b;
            --highlight: #fef08a;
        }

        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        .game-container {
            margin: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .box {
            background: var(--grey);
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .box:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.1); 
            /* border: 1px solid rgba(255, 255, 255, 0.2);  */
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); 
        }

        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .box img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .box h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .box p {
            font-size: 1.1rem;
            color: var(--dark-grey);
            margin-bottom: 0.5rem;
            white-space: pre-wrap;
        }

        .progress-bar {
            height: 20px;
            margin-top: 10px;
            margin-bottom: 10px;
            background-color: #e0e0e0;
            border-radius: 0.25rem;
            overflow: hidden;
            width: 100%;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #2ecc71);
            transition: width 0.3s ease;
        }

        .locked {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .modal {
            z-index: 1000000;
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--light);
            max-width: 1000px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            position: relative;
            font-family: 'Poppins', sans-serif;
            animation: slideIn 0.3s ease-in-out;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-sizing: border-box;
            transition: all 0.3s ease-in-out;
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-bg);
        }

        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: var(--scrollbar-bg);
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #374151;
        }

        .modal.full-screen .modal-content {
            max-width: 100vw;
            max-height: 100vh;
            width: 98%;
            height: 98%;
            border-radius: 0;
            padding: 2rem;
            transform: scale(1.02);
            animation: scaleIn 0.3s ease-in-out;
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
            font-size: 1.75rem;
            font-weight: 600;
        }

        .modal-header .close-btn, .modal-header .toggle-fullscreen-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .modal-header .close-btn:hover, .modal-header .toggle-fullscreen-btn:hover {
            color: var(--red);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--grey);
            display: flex;
            justify-content: flex-end;
            background: var(--light);
        }

        .modal-footer .close-btn, .closed-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .modal-footer .close-btn:hover, .closed-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
            transform: translateY(-2px);
        }

        .play-btn, .view-results-btn, .next-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            justify-content: flex-start;
            gap: 0.5rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin: 0.25rem;
            text-decoration: none;
            visibility: visible !important;
        }

        .play-btn:hover, .view-results-btn:hover, .next-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
        }

        .view-results-btn {
            background: linear-gradient(135deg, #6610f2, #5208c9);
        }

        .view-results-btn:hover {
            background: linear-gradient(135deg, #5208c9, #4307a3);
        }

        .tts-btn, .shuffle-btn {
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin: 0.25rem;
        }

        .tts-btn {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .tts-btn:hover{
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
        }

        .shuffle-btn {
            background: linear-gradient(135deg, #6f42c1, #5a32a8);
        }

        .shuffle-btn:hover {
            background: linear-gradient(135deg, #5a32a8, #4a288f);
            transform: translateY(-2px);
        }

        .success-notification, .error-notification {
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 0.375rem;
            text-align: center;
            font-weight: 600;
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .notification-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border-radius: 50%;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            display: inline-block;
        }

        .category-title {
            color: var(--dark);
            font-size: 2rem;
            border-bottom: 3px solid #ccc;
            border-top: 3px solid #ccc;
            padding: 5px;
        }

        .category-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }

        .category-item.left-image .image-container {
            order: 1;
            flex: 0 0 30%;
        }

        .category-item.right-image .image-container {
            order: 1;
            flex: 0 0 30%;
            margin-top: 10px;
        }

        .category-item.left-image .content-container {
            order: 1;
            flex: 1;
        }

        .category-item.right-image .content-container {
            order: 2;
            flex: 1;
        }

        .category-item .image-container {
            width: 100%;
            aspect-ratio: 4 / 3;
            overflow: hidden;
            border-radius: 0.5rem;
        }

        .category-item img {
            width: 95%;
            height: 100%;
            object-fit: cover;
            border-radius: 1.5rem;
        }

        .category-item .content-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .category-item .content-container p {
            margin: 0;
            color: var(--dark);
            white-space: pre-wrap;
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            text-align: left;
        }

        .game-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            user-select: none;
            touch-action: manipulation;
        }

        .word-container {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .letter-bank {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-start;
            width: 40%;
            min-width: 200px;
        }

        .word-area {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            width: 75%;
            min-width: 300px;
        }

        .word-boxes {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .word-box {
            width: 40px;
            height: 40px;
            border: 2px solid var(--dark-grey);
            background: var(--grey);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            touch-action: none;
        }

        .word-box.filled {
            background: var(--green);
            color: white;
            border-color: var(--green);
        }

        .word-box.incorrect {
            background: var(--incorrect);
            color: white;
            border-color: var(--incorrect);
            cursor: grab;
        }

        .word-box.incorrect.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }

        .letter {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            text-transform: uppercase;
            cursor: grab;
            border-radius: 0.25rem;
            touch-action: none;
        }

        .letter:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-1px);
            transition: transform 0.2s ease;
        }

        .letter.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }

        .hint {
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: var(--dark);
            margin-top: 1rem;
            text-align: center;
            white-space: pre-wrap;
        }

        .hint .word-highlight {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border-radius: 0.25rem;
            padding: 0.2rem 0.3rem;
            transition: background-color 0.3s ease;
        }

        .timer {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .stars-display {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .badge-container {
            background: var(--light);
            padding: 0.6rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin: 1.5rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
            margin: 0 auto;
        }

        .badge-container h2 {
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .badge.bronze {
            background-color: var(--bronze);
            color: white;
        }

        .badge.silver {
            background-color: var(--silver);
            color: var(--dark);
        }

        .badge.gold {
            background-color: var(--gold);
            color: var(--dark);
        }

        .badge.trophy {
            background-color: var(--purple);
            color: white;
        }

        .badge-description {
            font-size: 0.9rem;
            color: var(--dark-grey);
            margin-top: 0.7rem;
            padding-bottom: 10px;
            white-space: pre-wrap;
        }

        .completed-boxes {
            border-top: 1px solid var(--dark);
            padding-top: 5px;
            color: var(--dark);
            text-transform: uppercase;
            font-weight: 600;
            font-size: clamp(1.5rem, 3vw, 2rem);
        }

        .back-btn {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        }

        .back-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .back-btn:active {
            transform: scale(0.95);
        }

        @media (max-width: 768px) {
            .game-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .category-item {
                flex-direction: column;
            }

            .category-item .content-container p {
                text-align: center;
            }

            .category-item.left-image .image-container,
            .category-item.right-image .image-container {
                flex: 0 0 100%;
                order: 1;
                max-height: 150px;
            }

            .category-item.left-image .content-container,
            .category-item.right-image .content-container {
                order: 2;
                flex: 1;
            }

            .category-item img {
                max-height: 150px;
            }

            .word-container {
                flex-direction: column;
                align-items: center;
            }

            .letter-bank, .word-area {
                width: 100%;
            }

            .word-box {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }

            .letter {
                width: 30px;
                height: 30px;
                font-size: 1rem;
            }

            .modal-content::-webkit-scrollbar {
                width: 6px;
            }

            .modal.full-screen .modal-content::-webkit-scrollbar {
                width: 8px;
            }
        }

        @media (max-width: 480px) {
            .game-container {
                margin: 1rem;
                padding: 0.5rem;
                grid-template-columns: 1fr;
            }

            .box {
                padding: 0.75rem;
            }

            .box img {
                height: 100px;
            }

            .box h3 {
                font-size: 1rem;
            }

            .box p {
                font-size: 0.8rem;
            }

            .modal-content {
                padding: 1rem;
            }

            .modal.full-screen .modal-content {
                padding: 1.5rem;
            }

            .modal-header h3 {
                font-size: 1.5rem;
            }

            .word-box {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }

            .letter {
                width: 25px;
                height: 25px;
                font-size: 0.9rem;
            }

            .badge-container h2 {
                font-size: 1.2rem;
            }

            .badge {
                font-size: 1rem;
                padding: 0.4rem 0.8rem;
            }

            .back-btn {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./studentDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./announcements.php?viewed=true">
                    <i class='bx bxs-bell'></i>
                    <span class="text">Announcements</span>
                    <?php if ($unreadCount > 0 && !$_SESSION['announcements_viewed']): ?>
                        <span class="notification-badge"><?php echo htmlspecialchars($unreadCount); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li class="active">
                <a href="./game.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Games</span>
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
                    <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>

        <main style="position: relative;">
            <a href="./game.php" class="back-btn" title="Back to Games">
                <i class='bx bx-arrow-back'></i>
            </a>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="badge-container">
                <h2>Current Badge</h2>
                <div class="badge <?php echo $currentBadge['level'] <= 2 ? 'bronze' : ($currentBadge['level'] <= 4 ? 'silver' : ($currentBadge['level'] <= 6 ? 'gold' : 'trophy')); ?>">
                    <span><?php echo htmlspecialchars($currentBadge['icon']); ?></span>
                    <span><?php echo htmlspecialchars($currentBadge['badgeName']); ?></span>
                </div>
                <p class="badge-description"><?php echo htmlspecialchars($currentBadge['description']); ?></p>
                <p class="completed-boxes">Completed Boxes: <?php echo htmlspecialchars($completedBoxes); ?></p>
            </div>

            <div class="game-container">
                <?php foreach ($boxes as $box): 
                    $progressPercentage = 0;
                    $totalCats = 0;
                    $completedCats = 0;

                    // Fetch total categories
                    $catStmt = $dbConnection->prepare("SELECT COUNT(*) as totalCats FROM word_categories WHERE boxID = :boxID AND archived = 0");
                    $catStmt->bindParam(':boxID', $box['boxID'], PDO::PARAM_INT);
                    $catStmt->execute();
                    $totalCats = $catStmt->fetch(PDO::FETCH_ASSOC)['totalCats'];

                    // Fetch completed categories
                    if (isset($boxProgress[$box['boxID']])) {
                        $completedCats = count(array_filter($boxProgress[$box['boxID']], fn($p) => $p['completed']));
                    }
                    $progressPercentage = $totalCats > 0 ? ($completedCats / $totalCats) * 100 : 0;
                    $boxImage = !empty($box['image']) ? htmlspecialchars($box['image']) : './img/default_box.jpg';
                ?>
                    <div class="box" onclick="openModal('box-modal-<?php echo $box['boxID']; ?>')">
                        <img src="<?php echo $boxImage; ?>" alt="<?php echo htmlspecialchars($box['boxName']); ?>">
                        <h3><?php echo htmlspecialchars($box['boxName']); ?></h3>
                        <p><?php echo htmlspecialchars($box['description']); ?></p>
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: <?php echo $progressPercentage; ?>%"></div>
                        </div>
                        <p><?php echo round($progressPercentage); ?>% Complete</p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Box Modals -->
            <?php foreach ($boxes as $box): 
                // Fetch categories
                $catStmt = $dbConnection->prepare("SELECT categoryID, categoryName, description, image FROM word_categories WHERE boxID = :boxID AND archived = 0");
                $catStmt->bindParam(':boxID', $box['boxID'], PDO::PARAM_INT);
                $catStmt->execute();
                $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

                // Fetch certificate
                $certStmt = $dbConnection->prepare("SELECT certificateID FROM word_certificates WHERE userID = :userID AND boxID = :boxID");
                $certStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $certStmt->bindParam(':boxID', $box['boxID'], PDO::PARAM_INT);
                $certStmt->execute();
                $certificate = $certStmt->fetch(PDO::FETCH_ASSOC);
            ?>
                <div id="box-modal-<?php echo $box['boxID']; ?>" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3><?php echo htmlspecialchars($box['boxName']); ?></h3>
                            <div>
                                <button class="toggle-fullscreen-btn" onclick="toggleFullscreen('box-modal-<?php echo $box['boxID']; ?>')" aria-label="Toggle fullscreen">
                                    <i class='bx bx-fullscreen'></i>
                                </button>
                                <button class="close-btn" onclick="closeModal('box-modal-<?php echo $box['boxID']; ?>')" aria-label="Close modal">
                                    <i class='bx bx-x'></i>
                                </button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <div>
                                <h4 class="category-title">Categories</h4>
                                <?php foreach ($categories as $index => $category): 
                                    $locked = false;
                                    $lockMessage = '';
                                    $prevCatID = null;

                                    // Check if previous category is completed
                                    if ($index > 0) {
                                        $prevCatID = $categories[$index - 1]['categoryID'];
                                        $locked = !isset($boxProgress[$box['boxID']][$prevCatID]) || !$boxProgress[$box['boxID']][$prevCatID]['completed'];
                                        if ($locked) {
                                            $lockMessage = "Complete the previous category to unlock this one.";
                                        }
                                    }
                                    $progressID = isset($boxProgress[$box['boxID']][$category['categoryID']]) ? $boxProgress[$box['boxID']][$category['categoryID']]['progressID'] : null;
                                ?>
                                    <div class="category-item <?php echo $index % 2 == 0 ? 'right-image' : 'left-image'; ?>">
                                        <?php if ($category['image']): ?>
                                            <div class="image-container">
                                                <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="Category Image">
                                            </div>
                                        <?php endif; ?>
                                        <div class="content-container">
                                            <p><strong><?php echo htmlspecialchars($category['categoryName']); ?></strong></p>
                                            <p><?php echo htmlspecialchars($category['description']); ?></p>
                                            <div>
                                                <button class="play-btn <?php echo $locked ? 'locked' : ''; ?>" 
                                                        <?php echo $locked ? "onclick=\"openModal('locked-modal-{$category['categoryID']}')\"" : "onclick=\"openGameModal('game-modal-{$category['categoryID']}')\""; ?>>
                                                    <i class='bx bx-play'></i> Play
                                                </button>
                                                <?php if ($progressID): ?>
                                                    <button class="view-results-btn" onclick="openModal('results-modal-<?php echo $progressID; ?>')">
                                                        <i class='bx bx-list-ul'></i> View Results
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Locked Category Modal -->
                                    <?php if ($locked): ?>
                                        <div id="locked-modal-<?php echo $category['categoryID']; ?>" class="modal">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h3>Category Locked</h3>
                                                    <button class="close-btn" onclick="closeModal('locked-modal-<?php echo $category['categoryID']; ?>')" aria-label="Close modal">
                                                        <i class='bx bx-x'></i>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><?php echo htmlspecialchars($lockMessage); ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button class="close-btn" onclick="closeModal('locked-modal-<?php echo $category['categoryID']; ?>')">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <!-- Results Modal -->
                                    <?php if ($progressID):
                                        $foundStmt = $dbConnection->prepare("
                                            SELECT w.word, w.hint
                                            FROM word_user_found_words ufw
                                            JOIN word_words w ON ufw.wordID = w.wordID
                                            WHERE ufw.progressID = :progressID
                                        ");
                                        $foundStmt->bindParam(':progressID', $progressID, PDO::PARAM_INT);
                                        $foundStmt->execute();
                                        $foundWords = $foundStmt->fetchAll(PDO::FETCH_ASSOC);

                                        $scoreStmt = $dbConnection->prepare("
                                            SELECT stars, completionTime
                                            FROM word_user_progress
                                            WHERE progressID = :progressID
                                        ");
                                        $scoreStmt->bindParam(':progressID', $progressID, PDO::PARAM_INT);
                                        $scoreStmt->execute();
                                        $scoreData = $scoreStmt->fetch(PDO::FETCH_ASSOC);
                                    ?>
                                        <div id="results-modal-<?php echo $progressID; ?>" class="modal">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h3>Results: <?php echo htmlspecialchars($category['categoryName']); ?></h3>
                                                    <div>
                                                        <button class="toggle-fullscreen-btn" onclick="toggleFullscreen('results-modal-<?php echo $progressID; ?>')" aria-label="Toggle fullscreen">
                                                            <i class='bx bx-fullscreen'></i>
                                                        </button>
                                                        <button class="close-btn" onclick="closeModal('results-modal-<?php echo $progressID; ?>')" aria-label="Close modal">
                                                            <i class='bx bx-x'></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="stars-display">
                                                        Stars: <?php echo str_repeat('⭐', $scoreData['stars']); ?>
                                                        <br>
                                                        (Time: <?php echo htmlspecialchars(floor($scoreData['completionTime'] / 60) . ' minute' . ($scoreData['completionTime'] >= 120 ? 's' : '') . ', ' . ($scoreData['completionTime'] % 60) . ' second' . ($scoreData['completionTime'] % 60 !== 1 ? 's' : '')); ?>)
                                                    </div>
                                                    <div>
                                                        <?php foreach ($foundWords as $index => $word): ?>
                                                            <div class="item-info">
                                                                <p><strong><?php echo ($index + 1) . '. ' . htmlspecialchars($word['word']); ?></strong></p>
                                                                <p>Hint: <?php echo htmlspecialchars($word['hint']); ?></p>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button class="close-btn" onclick="closeModal('results-modal-<?php echo $progressID; ?>')">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($certificate): ?>
                                <div>
                                    <h4>Certificate</h4>
                                    <a href="word_certificate.php?boxID=<?php echo $box['boxID']; ?>" class="play-btn" target="_blank">
                                        <i class='bx bx-download'></i> View Certificate
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button class="close-btn" onclick="closeModal('box-modal-<?php echo $box['boxID']; ?>')">Close</button>
                        </div>
                    </div>
                </div>

                <!-- Game Modals -->
                <?php foreach ($categories as $category): 
                    $wordStmt = $dbConnection->prepare("SELECT wordID, word, hint FROM word_words WHERE categoryID = :categoryID AND archived = 0 ORDER BY RAND()");
                    $wordStmt->bindParam(':categoryID', $category['categoryID'], PDO::PARAM_INT);
                    $wordStmt->execute();
                    $words = $wordStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                    <div id="game-modal-<?php echo $category['categoryID']; ?>" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3><?php echo htmlspecialchars($category['categoryName']); ?></h3>
                                <div>
                                    <button class="toggle-fullscreen-btn" onclick="toggleFullscreen('game-modal-<?php echo $category['categoryID']; ?>')" aria-label="Toggle fullscreen">
                                        <i class='bx bx-fullscreen'></i>
                                    </button>
                                    <button class="close-btn" onclick="closeModal('game-modal-<?php echo $category['categoryID']; ?>')" aria-label="Close modal">
                                        <i class='bx bx-x'></i>
                                    </button>
                                </div>
                            </div>
                            <div class="modal-body">
                                <form id="game-form-<?php echo $category['categoryID']; ?>" action="word_hunt.php" method="post" onsubmit="return submitGame('game-form-<?php echo $category['categoryID']; ?>')">
                                    <input type="hidden" name="categoryID" value="<?php echo $category['categoryID']; ?>">
                                    <input type="hidden" name="boxID" value="<?php echo $box['boxID']; ?>">
                                    <input type="hidden" name="completionTime" id="completion-time-<?php echo $category['categoryID']; ?>">
                                    <input type="hidden" name="stars" id="stars-<?php echo $category['categoryID']; ?>">
                                    <input type="hidden" name="foundWords" id="found-words-<?php echo $category['categoryID']; ?>">
                                    <div class="game-area">
                                        <div class="timer" id="timer-<?php echo $category['categoryID']; ?>">Time: 0 minute, 0 seconds</div>
                                        <div class="stars-display" id="stars-display-<?php echo $category['categoryID']; ?>">Stars: ★★★</div>
                                        <div class="word-area" id="word-area-<?php echo $category['categoryID']; ?>">
                                            <!-- Word containers will be populated by JavaScript -->
                                        </div>
                                        <button type="submit" class="play-btn" id="submit-btn-<?php echo $category['categoryID']; ?>">Submit</button>
                                        <button type="button" class="closed-btn" onclick="closeModal('game-modal-<?php echo $category['categoryID']; ?>')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </main>
    </section>

    <script>
        const wordsByCategory = {
            <?php
            $categoryEntries = [];
            foreach ($boxes as $box):
                $catStmt = $dbConnection->prepare("SELECT categoryID FROM word_categories WHERE boxID = :boxID AND archived = 0");
                $catStmt->bindParam(':boxID', $box['boxID'], PDO::PARAM_INT);
                $catStmt->execute();
                $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($categories as $category):
                    $wordStmt = $dbConnection->prepare("SELECT wordID, word, hint FROM word_words WHERE categoryID = :categoryID AND archived = 0 ORDER BY RAND()");
                    $wordStmt->bindParam(':categoryID', $category['categoryID'], PDO::PARAM_INT);
                    $wordStmt->execute();
                    $words = $wordStmt->fetchAll(PDO::FETCH_ASSOC);
                    $categoryEntries[] = "'{$category['categoryID']}': " . json_encode($words);
                endforeach;
            endforeach;
            echo implode(',', $categoryEntries);
            ?>};

        let timers = {};
        let foundWordsByCategory = {};

        function openModal(modalId) {
            try {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'flex';
                    console.log(`Modal opened: ${modalId}`);
                } else {
                    console.error(`Modal with ID ${modalId} not found`);
                }
            } catch (e) {
                console.error('Error opening modal:', e);
            }
        }

        function closeModal(modalId) {
            try {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('full-screen');
                    const toggleBtn = modal.querySelector('.toggle-fullscreen-btn i');
                    if (toggleBtn) {
                        toggleBtn.classList.remove('bx-exit-fullscreen');
                        toggleBtn.classList.add('bx-fullscreen');
                    }
                    if (modalId.startsWith('game-modal-')) {
                        stopTimer(modalId);
                    }
                    console.log(`Modal closed: ${modalId}`);
                }
            } catch (e) {
                console.error('Error closing modal:', e);
            }
        }

        function toggleFullscreen(modalId) {
            try {
                const modal = document.getElementById(modalId);
                const toggleBtn = modal.querySelector('.toggle-fullscreen-btn i');
                modal.classList.toggle('full-screen');
                if (modal.classList.contains('full-screen')) {
                    toggleBtn.classList.remove('bx-fullscreen');
                    toggleBtn.classList.add('bx-exit-fullscreen');
                    modal.querySelector('.modal-content').style.animation = 'scaleIn 0.3s ease-in-out';
                } else {
                    toggleBtn.classList.remove('bx-exit-fullscreen');
                    toggleBtn.classList.add('bx-fullscreen');
                    modal.querySelector('.modal-content').style.animation = 'scaleOut 0.3s ease-in-out';
                }
                console.log(`Toggled fullscreen for modal: ${modalId}`);
            } catch (e) {
                console.error('Error toggling fullscreen:', e);
            }
        }

        function openGameModal(modalId) {
            try {
                openModal(modalId);
                const categoryID = modalId.split('-').pop();
                foundWordsByCategory[categoryID] = [];
                console.log(`Starting game for category ${categoryID}`);
                showAllWords(modalId);
                startTimer(modalId);
            } catch (e) {
                console.error('Error opening game modal:', e);
                alert('Failed to start game. Please try again.');
            }
        }

        function speakText(text, hintElement) {
            try {
                if ('speechSynthesis' in window) {
                    // Stop any ongoing speech
                    speechSynthesis.cancel();

                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.lang = 'en-US';
                    utterance.volume = 1;
                    utterance.rate = 1;
                    utterance.pitch = 1;

                    // Split text into words and get corresponding spans
                    const words = text.split(/\s+/).filter(word => word.length > 0);
                    const wordSpans = hintElement.querySelectorAll('.word-span');

                    // Ensure the number of words matches the number of spans
                    if (wordSpans.length !== words.length) {
                        console.warn(`Mismatch between words (${words.length}) and spans (${wordSpans.length}) for text: ${text}`);
                        return;
                    }

                    let wordIndex = 0;

                    // Handle word boundary events
                    utterance.onboundary = (event) => {
                        if (event.name === 'word') {
                            // Remove highlight from previous word
                            if (wordIndex > 0) {
                                wordSpans[wordIndex - 1].classList.remove('word-highlight');
                            }
                            // Highlight current word
                            if (wordIndex < wordSpans.length) {
                                wordSpans[wordIndex].classList.add('word-highlight');
                                console.log(`Highlighting word: ${words[wordIndex]}, index: ${wordIndex}`);
                                wordIndex++;
                            }
                        }
                    };

                    // Clean up highlights when speech ends
                    utterance.onend = () => {
                        // Remove highlight from the last word
                        if (wordIndex > 0 && wordIndex <= wordSpans.length) {
                            wordSpans[wordIndex - 1].classList.remove('word-highlight');
                        }
                        console.log(`Speech ended for text: ${text}`);
                    };

                    speechSynthesis.speak(utterance);
                    console.log(`Speaking text: ${text}`);
                } else {
                    console.warn('Text-to-speech not supported in this browser.');
                }
            } catch (e) {
                console.error('Error in text-to-speech:', e);
            }
        }

        function shuffleLetters(wordID) {
            try {
                const letterBank = document.getElementById(`letter-bank-${wordID}`);
                const letters = Array.from(letterBank.querySelectorAll('.letter:not(.used)'));
                const parent = letterBank;
                while (parent.firstChild) {
                    parent.removeChild(parent.firstChild);
                }
                letters.sort(() => Math.random() - 0.5).forEach(letter => {
                    parent.appendChild(letter);
                });
                console.log(`Shuffled letters for wordID: ${wordID}`);
            } catch (e) {
                console.error('Error shuffling letters:', e);
            }
        }

        function showAllWords(modalId) {
            try {
                const categoryID = modalId.split('-').pop();
                const wordArea = document.getElementById(`word-area-${categoryID}`);
                const submitBtn = document.getElementById(`submit-btn-${categoryID}`);
                const words = wordsByCategory[categoryID] || [];

                if (!wordArea || !submitBtn) {
                    console.error(`Game elements not found for category: ${categoryID}`);
                    alert('Game setup failed. Please try again.');
                    return;
                }

                submitBtn.style.display = 'inline-flex';
                console.log(`Initialized submit button for category ${categoryID}: display=inline-flex`);

                if (words.length === 0) {
                    console.warn(`No words found for category: ${categoryID}`);
                    wordArea.innerHTML = '<p>No words available in this category.</p>';
                    return;
                }

                wordArea.innerHTML = words.map(word => {
                    // Split hint into words and wrap each in a span
                    const hintWords = word.hint.split(/\s+/).filter(w => w.length > 0);
                    const hintHtml = hintWords.map((w, i) => `<span class="word-span" id="word-span-${word.wordID}-${i}">${w}</span>`).join(' ');
                    return `
                        <div class="word-container" data-word-id="${word.wordID}" data-word="${word.word}">
                            <div class="letter-bank" id="letter-bank-${word.wordID}">
                                <!-- Letters will be populated by JavaScript -->
                            </div>
                            <div class="word-area">
                                <div class="word-boxes" id="word-boxes-${word.wordID}">
                                    ${Array.from(word.word).map((_, i) => `<div class="word-box" data-index="${i}"></div>`).join('')}
                                </div>
                                <p class="hint" id="hint-${word.wordID}">${hintHtml}</p>
                                <div>
                                    <button type="button" class="tts-btn" onclick="speakText('${word.hint.replace(/'/g, "\\'")}', document.getElementById('hint-${word.wordID}'))">
                                        <i class='bx bx-volume-full'></i> Read Hint
                                    </button>
                                    <button type="button" class="shuffle-btn" onclick="shuffleLetters(${word.wordID})">
                                        <i class='bx bx-shuffle'></i> Shuffle
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                words.forEach(word => {
                    const letterBank = document.getElementById(`letter-bank-${word.wordID}`);
                    let allLetters = word.word.split('');
                    const extraLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
                    const extraCount = Math.max(3, Math.ceil(word.word.length * 0.5));
                    for (let i = 0; i < extraCount; i++) {
                        allLetters.push(extraLetters[Math.floor(Math.random() * extraLetters.length)]);
                    }
                    allLetters = allLetters.sort(() => Math.random() - 0.5);
                    allLetters.forEach(letter => {
                        const letterDiv = document.createElement('div');
                        letterDiv.className = 'letter';
                        letterDiv.textContent = letter;
                        letterDiv.setAttribute('draggable', 'true');
                        letterBank.appendChild(letterDiv);
                    });
                    setupDragAndDrop(modalId, word.wordID, word.word);
                });

                const allWords = document.querySelectorAll(`#${modalId} .word-container`);
                checkWordCompletion(allWords[0], modalId);
            } catch (e) {
                console.error('Error showing all words:', e);
                alert('Failed to load words. Please try again.');
            }
        }

        function setupDragAndDrop(modalId, wordID, correctWord) {
            try {
                const categoryID = modalId.split('-').pop();
                const letterBank = document.getElementById(`letter-bank-${wordID}`);
                const wordBoxes = document.querySelectorAll(`#word-boxes-${wordID} .word-box`);
                const letterElements = letterBank.querySelectorAll('.letter');

                letterBank.removeEventListener('dragover', handleDragOver);
                letterBank.removeEventListener('drop', handleLetterBankDrop);
                letterBank.addEventListener('dragover', handleDragOver);
                letterBank.addEventListener('drop', (e) => handleLetterBankDrop(e, letterBank, categoryID));

                wordBoxes.forEach(box => {
                    box.removeEventListener('dragstart', handleBoxDragStart);
                    box.removeEventListener('dragover', handleDragOver);
                    box.removeEventListener('drop', handleBoxDrop);
                    if (box.textContent && box.classList.contains('incorrect')) {
                        box.setAttribute('draggable', 'true');
                        box.addEventListener('dragstart', handleBoxDragStart);
                    }
                    box.addEventListener('dragover', handleDragOver);
                    box.addEventListener('drop', (e) => handleBoxDrop(e, box, correctWord, modalId));
                });

                letterElements.forEach(letter => {
                    letter.removeEventListener('dragstart', handleLetterDragStart);
                    letter.removeEventListener('dragend', handleDragEnd);
                    letter.addEventListener('dragstart', handleLetterDragStart);
                    letter.addEventListener('dragend', handleDragEnd);
                });

                function handleLetterDragStart(e) {
                    try {
                        e.dataTransfer.setData('text/plain', e.target.textContent);
                        e.dataTransfer.setData('source', 'letter-bank');
                        e.target.classList.add('dragging');
                        console.log('Letter drag start:', e.target.textContent);
                    } catch (e) {
                        console.error('Letter drag start error:', e);
                    }
                }

                function handleBoxDragStart(e) {
                    try {
                        e.dataTransfer.setData('text/plain', e.target.textContent);
                        e.dataTransfer.setData('source', 'word-box');
                        e.dataTransfer.setData('box-index', e.target.dataset.index);
                        e.target.classList.add('dragging');
                        console.log('Box drag start:', e.target.textContent, 'Index:', e.target.dataset.index);
                    } catch (e) {
                        console.error('Box drag start error:', e);
                    }
                }

                function handleDragEnd(e) {
                    try {
                        e.target.classList.remove('dragging');
                        console.log('Drag end:', e.target.textContent);
                    } catch (e) {
                        console.error('Drag end error:', e);
                    }
                }

                function handleDragOver(e) {
                    e.preventDefault();
                }

                function handleBoxDrop(e, box, correctWord, modalId) {
                    try {
                        e.preventDefault();
                        const letter = e.dataTransfer.getData('text/plain');
                        const source = e.dataTransfer.getData('source');
                        const index = parseInt(box.dataset.index);
                        console.log(`Box drop attempt: Letter=${letter}, Index=${index}, Correct=${correctWord[index]}, Source=${source}`);

                        if (!box.textContent) {
                            box.textContent = letter;
                            box.classList.remove('filled', 'incorrect');
                            box.classList.add(letter.toUpperCase() === correctWord[index].toUpperCase() ? 'filled' : 'incorrect');
                            if (box.classList.contains('incorrect')) {
                                box.setAttribute('draggable', 'true');
                                box.addEventListener('dragstart', handleBoxDragStart);
                            } else {
                                box.setAttribute('draggable', 'false');
                                box.removeEventListener('dragstart', handleBoxDragStart);
                            }
                            if (source === 'letter-bank') {
                                const letterElement = Array.from(letterElements).find(l => l.textContent === letter && !l.classList.contains('used'));
                                if (letterElement) {
                                    letterElement.classList.add('used');
                                    letterElement.style.display = 'none';
                                }
                            } else if (source === 'word-box') {
                                const sourceBox = document.querySelector(`#word-boxes-${wordID} .word-box[data-index="${e.dataTransfer.getData('box-index')}"]`);
                                if (sourceBox && sourceBox !== box) {
                                    sourceBox.textContent = '';
                                    sourceBox.classList.remove('filled', 'incorrect');
                                    sourceBox.setAttribute('draggable', 'false');
                                    sourceBox.removeEventListener('dragstart', handleBoxDragStart);
                                }
                            }
                            checkWordCompletion(box.closest('.word-container'), modalId);
                        } else {
                            console.log('Box already filled:', box.textContent);
                        }
                    } catch (e) {
                        console.error('Box drop error:', e);
                    }
                }

                function handleLetterBankDrop(e, letterBank, categoryID) {
                    try {
                        e.preventDefault();
                        const letter = e.dataTransfer.getData('text/plain');
                        const source = e.dataTransfer.getData('source');
                        const boxIndex = e.dataTransfer.getData('box-index');
                        console.log(`Letter bank drop: Letter=${letter}, Source=${source}, BoxIndex=${boxIndex}`);

                        if (source === 'word-box') {
                            const sourceBox = document.querySelector(`#word-boxes-${wordID} .word-box[data-index="${boxIndex}"]`);
                            if (sourceBox) {
                                sourceBox.textContent = '';
                                sourceBox.classList.remove('filled', 'incorrect');
                                sourceBox.setAttribute('draggable', 'false');
                                sourceBox.removeEventListener('dragstart', handleBoxDragStart);
                            }
                            const letterDiv = document.createElement('div');
                            letterDiv.className = 'letter';
                            letterDiv.textContent = letter;
                            letterDiv.setAttribute('draggable', 'true');
                            letterDiv.addEventListener('dragstart', handleLetterDragStart);
                            letterDiv.addEventListener('dragend', handleDragEnd);
                            letterBank.appendChild(letterDiv);
                        }
                    } catch (e) {
                        console.error('Letter bank drop error:', e);
                    }
                }
            } catch (e) {
                console.error('Error setting up drag and drop:', e);
            }
        }

        function checkWordCompletion(wordContainer, modalId) {
            try {
                const wordBoxes = wordContainer.querySelectorAll('.word-box');
                const word = wordContainer.dataset.word;
                const wordID = wordContainer.dataset.wordId;
                let completed = true;
                let formedWord = '';
                wordBoxes.forEach(box => {
                    formedWord += box.textContent;
                    if (!box.textContent) completed = false;
                });
                const categoryID = modalId.split('-').pop();
                console.log(`Checking word completion: Formed=${formedWord}, Expected=${word}, WordID=${wordID}, Category=${categoryID}`);
                if (completed && formedWord.toUpperCase() === word.toUpperCase()) {
                    wordBoxes.forEach(box => {
                        box.classList.remove('incorrect');
                        box.classList.add('filled');
                        box.setAttribute('draggable', 'false');
                        box.removeEventListener('dragstart', handleBoxDragStart);
                    });
                    speakText('Correct');
                    if (!foundWordsByCategory[categoryID].includes(wordID)) {
                        foundWordsByCategory[categoryID].push(wordID);
                    }
                    console.log(`Word completed: ${word}, WordID: ${wordID}, Category: ${categoryID}, FoundWords: ${JSON.stringify(foundWordsByCategory[categoryID])}`);

                    // Check if all words in the category are completed
                    const allWords = document.querySelectorAll(`#${modalId} .word-container`);
                    const allCompleted = Array.from(allWords).every(container => {
                        const boxes = container.querySelectorAll('.word-box');
                        let wordFormed = '';
                        boxes.forEach(box => wordFormed += box.textContent);
                        return wordFormed.toUpperCase() === container.dataset.word.toUpperCase();
                    });
                    if (allCompleted) {
                        speakText('Congratulations! You have completed the category!');
                    }
                }
            } catch (e) {
                console.error('Error checking word completion:', e);
            }
        }

        function startTimer(modalId) {
            try {
                const categoryID = modalId.split('-').pop();
                const timerDisplay = document.getElementById(`timer-${categoryID}`);
                let seconds = 0;
                timers[modalId] = setInterval(() => {
                    seconds++;
                    const minutes = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    timerDisplay.textContent = `Time: ${minutes} minute${minutes !== 1 ? 's' : ''}, ${secs} second${secs !== 1 ? 's' : ''}`;
                    updateStars(modalId, seconds);
                }, 1000);
                console.log(`Timer started for modal: ${modalId}`);
            } catch (e) {
                console.error('Error starting timer:', e);
            }
        }

        function stopTimer(modalId) {
            try {
                if (timers[modalId]) {
                    clearInterval(timers[modalId]);
                    delete timers[modalId];
                    console.log(`Timer stopped for modal: ${modalId}`);
                }
            } catch (e) {
                console.error('Error stopping timer:', e);
            }
        }

        function updateStars(modalId, seconds) {
            try {
                const categoryID = modalId.split('-').pop();
                const starsDisplay = document.getElementById(`stars-display-${categoryID}`);
                const starsInput = document.getElementById(`stars-${categoryID}`);
                let stars = 3;
                if (seconds > 300) stars = 2;
                if (seconds > 600) stars = 1;
                starsDisplay.textContent = `Stars: ${'⭐'.repeat(stars)}${'☆'.repeat(3 - stars)}`;
                starsInput.value = stars;
                console.log(`Updated stars: ${stars} for category ${categoryID}, seconds: ${seconds}`);
            } catch (e) {
                console.error('Error updating stars:', e);
            }
        }

        function submitGame(formId) {
            try {
                const form = document.getElementById(formId);
                const modal = form.closest('.modal');
                const categoryID = formId.split('-').pop();
                const words = wordsByCategory[categoryID] || [];
                const foundWords = foundWordsByCategory[categoryID] || [];
                const completionTimeInput = document.getElementById(`completion-time-${categoryID}`);
                const timerDisplay = document.getElementById(`timer-${categoryID}`);
                const foundWordsInput = document.getElementById(`found-words-${categoryID}`);
                const starsInput = document.getElementById(`stars-${categoryID}`);

                // Validate form data
                // if (!words.length || !foundWords.length) {
                //     console.error(`Invalid submission: No words or no found words for category ${categoryID}`);
                //     alert('Please complete at least one word before submitting.');
                //     return false;
                // }

                const timeText = timerDisplay.textContent.replace('Time: ', '').split(', ');
                const minutes = parseInt(timeText[0]) || 0;
                const seconds = parseInt(timeText[1]) || 0;
                const totalSeconds = minutes * 60 + seconds;

                if (!totalSeconds || !starsInput.value) {
                    console.error(`Invalid submission: Time=${totalSeconds}, Stars=${starsInput.value}`);
                    alert('Game data is incomplete. Please try again.');
                    return false;
                }

                completionTimeInput.value = totalSeconds;
                foundWordsInput.value = JSON.stringify(foundWords);

                console.log(`Submitting form: Time=${totalSeconds}s, Stars=${starsInput.value}, FoundWords=${foundWordsInput.value}`);
                
                stopTimer(modal.id);
                return true; // Allow form submission
            } catch (e) {
                console.error('Error submitting game:', e);
                alert('Failed to submit game. Please try again.');
                return false;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            <?php foreach ($boxes as $box): 
                $catStmt = $dbConnection->prepare("SELECT categoryID FROM word_categories WHERE boxID = :boxID AND archived = 0");
                $catStmt->bindParam(':boxID', $box['boxID'], PDO::PARAM_INT);
                $catStmt->execute();
                $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($categories as $category): ?>
                    const form<?php echo $category['categoryID']; ?> = document.getElementById('game-form-<?php echo $category['categoryID']; ?>');
                    if (form<?php echo $category['categoryID']; ?>) {
                        form<?php echo $category['categoryID']; ?>.addEventListener('submit', (e) => {
                            if (!submitGame('game-form-<?php echo $category['categoryID']; ?>')) {
                                e.preventDefault();
                            }
                        });
                    } else {
                        console.error(`Form not found for category <?php echo $category['categoryID']; ?>`);
                    }
                <?php endforeach; ?>
            <?php endforeach; ?>
        });

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>