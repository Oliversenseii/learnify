<?php
session_start();
require_once '../../config/db_connection.php';

if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php");
    exit;
}

try {
    $dbConnection->beginTransaction();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        // Add Image
        if ($action === 'add_image') {
            $level = filter_var($_POST['level'], FILTER_VALIDATE_INT);
            $correctAnswer = trim($_POST['correctAnswer']);
            $description = trim($_POST['description'] ?? '');
            $imageFields = ['imageFile1', 'imageFile2', 'imageFile3'];
            $imagePaths = [];
            $uploadDir = '../../uploads/speech_game/';
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if ($level < 1 || $level > 50 || empty($correctAnswer)) {
                $_SESSION['error_message'] = "Invalid level or correct answer.";
                header("Location: game_controller_2.php?tab=images");
                exit;
            }

            // Check if level already exists
            $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM speech_to_text_game_images WHERE level = :level AND archived = 0");
            $stmt->bindParam(':level', $level, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Level $level already exists.";
                header("Location: game_controller_2.php?tab=images");
                exit;
            }

            // Handle image uploads
            foreach ($imageFields as $index => $field) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$field];
                    if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $imagePaths[$field] = $uploadDir . uniqid() . '-' . basename($file['name']);
                        if (!move_uploaded_file($file['tmp_name'], $imagePaths[$field])) {
                            $_SESSION['error_message'] = "Failed to upload $field.";
                            header("Location: game_controller_2.php?tab=images");
                            exit;
                        }
                    } else {
                        $_SESSION['error_message'] = "Invalid file type or size for $field.";
                        header("Location: game_controller_2.php?tab=images");
                        exit;
                    }
                } elseif ($index < 2) { // imageFile1 and imageFile2 are required
                    $_SESSION['error_message'] = "$field is required.";
                    header("Location: game_controller_2.php?tab=images");
                    exit;
                }
            }

            // Prepare imageFile3 variable
            $imageFile3 = isset($imagePaths['imageFile3']) ? $imagePaths['imageFile3'] : null;

            $stmt = $dbConnection->prepare("
                INSERT INTO speech_to_text_game_images (imageFile1, imageFile2, imageFile3, correctAnswer, description, level, archived)
                VALUES (:imageFile1, :imageFile2, :imageFile3, :correctAnswer, :description, :level, 0)
            ");
            $stmt->bindParam(':imageFile1', $imagePaths['imageFile1'], PDO::PARAM_STR);
            $stmt->bindParam(':imageFile2', $imagePaths['imageFile2'], PDO::PARAM_STR);
            $stmt->bindParam(':imageFile3', $imageFile3, PDO::PARAM_STR);
            $stmt->bindParam(':correctAnswer', $correctAnswer, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':level', $level, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Image added successfully.";
        }

        // Edit Image
        if ($action === 'edit_image') {
            $imageID = filter_var($_POST['imageID'], FILTER_VALIDATE_INT);
            $level = filter_var($_POST['level'], FILTER_VALIDATE_INT);
            $correctAnswer = trim($_POST['correctAnswer']);
            $description = trim($_POST['description'] ?? '');
            $archived = filter_var($_POST['archived'], FILTER_VALIDATE_INT);
            $imageFields = ['imageFile1', 'imageFile2', 'imageFile3'];
            $imagePaths = [];
            $uploadDir = '../../uploads/speech_game/';
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if ($imageID === false || $level < 1 || $level > 50 || empty($correctAnswer) || !in_array($archived, [0, 1])) {
                $_SESSION['error_message'] = "Invalid input for image.";
                header("Location: game_controller_2.php?tab=images");
                exit;
            }

            // Fetch existing images
            $stmt = $dbConnection->prepare("SELECT imageFile1, imageFile2, imageFile3 FROM speech_to_text_game_images WHERE imageID = :imageID");
            $stmt->bindParam(':imageID', $imageID, PDO::PARAM_INT);
            $stmt->execute();
            $existingImage = $stmt->fetch(PDO::FETCH_ASSOC);
            $imagePaths = $existingImage;

            // Handle image uploads
            foreach ($imageFields as $index => $field) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$field];
                    if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $newPath = $uploadDir . uniqid() . '-' . basename($file['name']);
                        if (move_uploaded_file($file['tmp_name'], $newPath)) {
                            if ($existingImage[$field] && file_exists($existingImage[$field])) {
                                unlink($existingImage[$field]);
                            }
                            $imagePaths[$field] = $newPath;
                        } else {
                            $_SESSION['error_message'] = "Failed to upload $field.";
                            header("Location: game_controller_2.php?tab=images");
                            exit;
                        }
                    } else {
                        $_SESSION['error_message'] = "Invalid file type or size for $field.";
                        header("Location: game_controller_2.php?tab=images");
                        exit;
                    }
                }
            }

            // Check if level is unique (excluding current imageID)
            $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM speech_to_text_game_images WHERE level = :level AND imageID != :imageID AND archived = 0");
            $stmt->bindParam(':level', $level, PDO::PARAM_INT);
            $stmt->bindParam(':imageID', $imageID, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Level $level already exists for another image.";
                header("Location: game_controller_2.php?tab=images");
                exit;
            }

            // Prepare imageFile3 variable
            $imageFile3 = isset($imagePaths['imageFile3']) ? $imagePaths['imageFile3'] : null;

            $stmt = $dbConnection->prepare("
                UPDATE speech_to_text_game_images
                SET imageFile1 = :imageFile1, imageFile2 = :imageFile2, imageFile3 = :imageFile3,
                    correctAnswer = :correctAnswer, description = :description, level = :level, archived = :archived
                WHERE imageID = :imageID
            ");
            $stmt->bindParam(':imageID', $imageID, PDO::PARAM_INT);
            $stmt->bindParam(':imageFile1', $imagePaths['imageFile1'], PDO::PARAM_STR);
            $stmt->bindParam(':imageFile2', $imagePaths['imageFile2'], PDO::PARAM_STR);
            $stmt->bindParam(':imageFile3', $imageFile3, PDO::PARAM_STR);
            $stmt->bindParam(':correctAnswer', $correctAnswer, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':level', $level, PDO::PARAM_INT);
            $stmt->bindParam(':archived', $archived, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Image updated successfully.";
        }

        // Edit Progress
        if ($action === 'edit_progress') {
            $progressID = filter_var($_POST['progressID'], FILTER_VALIDATE_INT);
            $userID = filter_var($_POST['userID'], FILTER_VALIDATE_INT);
            $currentLevel = filter_var($_POST['currentLevel'], FILTER_VALIDATE_INT);
            $totalAttempts = filter_var($_POST['totalAttempts'], FILTER_VALIDATE_INT);
            $totalCorrect = filter_var($_POST['totalCorrect'], FILTER_VALIDATE_INT);
            $totalPoints = filter_var($_POST['totalPoints'], FILTER_VALIDATE_INT);

            if ($progressID === false || $userID === false || $currentLevel < 1 || $currentLevel > 50 || $totalAttempts < 0 || $totalCorrect < 0 || $totalPoints < 0) {
                $_SESSION['error_message'] = "Invalid input for progress.";
                header("Location: game_controller_2.php?tab=progress");
                exit;
            }

            $stmt = $dbConnection->prepare("
                UPDATE speech_to_text_user_progress_image
                SET currentLevel = :currentLevel, totalAttempts = :totalAttempts, totalCorrect = :totalCorrect, totalPoints = :totalPoints
                WHERE progressID = :progressID AND userID = :userID
            ");
            $stmt->bindParam(':progressID', $progressID, PDO::PARAM_INT);
            $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $stmt->bindParam(':currentLevel', $currentLevel, PDO::PARAM_INT);
            $stmt->bindParam(':totalAttempts', $totalAttempts, PDO::PARAM_INT);
            $stmt->bindParam(':totalCorrect', $totalCorrect, PDO::PARAM_INT);
            $stmt->bindParam(':totalPoints', $totalPoints, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Progress updated successfully.";
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // Archive Image
        if ($action === 'archive_image') {
            $imageID = filter_var($_GET['imageID'], FILTER_VALIDATE_INT);
            if ($imageID === false) {
                $_SESSION['error_message'] = "Invalid image ID.";
                header("Location: game_controller_2.php?tab=images");
                exit;
            }

            // Fetch and delete associated images
            $stmt = $dbConnection->prepare("SELECT imageFile1, imageFile2, imageFile3 FROM speech_to_text_game_images WHERE imageID = :imageID");
            $stmt->bindParam(':imageID', $imageID, PDO::PARAM_INT);
            $stmt->execute();
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            foreach (['imageFile1', 'imageFile2', 'imageFile3'] as $field) {
                if ($image[$field] && file_exists($image[$field])) {
                    unlink($image[$field]);
                }
            }

            $stmt = $dbConnection->prepare("UPDATE speech_to_text_game_images SET archived = 1 WHERE imageID = :imageID");
            $stmt->bindParam(':imageID', $imageID, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Image archived successfully.";
        }
    }

    $dbConnection->commit();
} catch (PDOException $e) {
    $dbConnection->rollBack();
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred.";
}

// Redirect back to game_controller_2.php with the active tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : (isset($_POST['tab']) ? $_POST['tab'] : 'images');
header("Location: game_controller_2.php?tab=" . urlencode($activeTab));
exit;
?>