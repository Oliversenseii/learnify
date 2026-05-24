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

        // Add Test Type
        if ($action === 'add_test_type') {
            $testTypeName = trim($_POST['testTypeName']);
            $totalQuestions = filter_var($_POST['totalQuestions'], FILTER_VALIDATE_INT);
            $passingScore = filter_var($_POST['passingScore'], FILTER_VALIDATE_INT);

            if ($testTypeName && $totalQuestions > 0 && $passingScore >= 0) {
                $stmt = $dbConnection->prepare("
                    INSERT INTO game_test_types (testTypeName, totalQuestions, passingScore, archived)
                    VALUES (:testTypeName, :totalQuestions, :passingScore, 0)
                ");
                $stmt->bindParam(':testTypeName', $testTypeName, PDO::PARAM_STR);
                $stmt->bindParam(':totalQuestions', $totalQuestions, PDO::PARAM_INT);
                $stmt->bindParam(':passingScore', $passingScore, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Test type added successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid input for test type.";
            }
        }

        // Edit Test Type
        if ($action === 'edit_test_type') {
            $testTypeID = filter_var($_POST['testTypeID'], FILTER_VALIDATE_INT);
            $testTypeName = trim($_POST['testTypeName']);
            $totalQuestions = filter_var($_POST['totalQuestions'], FILTER_VALIDATE_INT);
            $passingScore = filter_var($_POST['passingScore'], FILTER_VALIDATE_INT);

            if ($testTypeID && $testTypeName && $totalQuestions > 0 && $passingScore >= 0) {
                $stmt = $dbConnection->prepare("
                    UPDATE game_test_types
                    SET testTypeName = :testTypeName, totalQuestions = :totalQuestions, passingScore = :passingScore
                    WHERE testTypeID = :testTypeID
                ");
                $stmt->bindParam(':testTypeID', $testTypeID, PDO::PARAM_INT);
                $stmt->bindParam(':testTypeName', $testTypeName, PDO::PARAM_STR);
                $stmt->bindParam(':totalQuestions', $totalQuestions, PDO::PARAM_INT);
                $stmt->bindParam(':passingScore', $passingScore, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Test type updated successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid input for test type.";
            }
        }

        // Add Test
        if ($action === 'add_test') {
            $title = trim($_POST['title']);
            $strandID = filter_var($_POST['strandID'], FILTER_VALIDATE_INT);
            $testTypeID = filter_var($_POST['testTypeID'], FILTER_VALIDATE_INT);

            if ($title && $strandID && $testTypeID) {
                $stmt = $dbConnection->prepare("
                    INSERT INTO game_tests (title, strandID, testTypeID, archived)
                    VALUES (:title, :strandID, :testTypeID, 0)
                ");
                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':strandID', $strandID, PDO::PARAM_INT);
                $stmt->bindParam(':testTypeID', $testTypeID, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Test added successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid input for test.";
            }
        }

        // Edit Test
        if ($action === 'edit_test') {
            $testID = filter_var($_POST['testID'], FILTER_VALIDATE_INT);
            $title = trim($_POST['title']);
            $strandID = filter_var($_POST['strandID'], FILTER_VALIDATE_INT);
            $testTypeID = filter_var($_POST['testTypeID'], FILTER_VALIDATE_INT);

            if ($testID && $title && $strandID && $testTypeID) {
                $stmt = $dbConnection->prepare("
                    UPDATE game_tests
                    SET title = :title, strandID = :strandID, testTypeID = :testTypeID
                    WHERE testID = :testID
                ");
                $stmt->bindParam(':testID', $testID, PDO::PARAM_INT);
                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':strandID', $strandID, PDO::PARAM_INT);
                $stmt->bindParam(':testTypeID', $testTypeID, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Test updated successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid input for test.";
            }
        }

        // Add Question
        if ($action === 'add_question') {
            $testID = filter_var($_POST['testID'], FILTER_VALIDATE_INT);
            $questions = isset($_POST['questions']) && is_array($_POST['questions']) ? $_POST['questions'] : [];
            $success = true;

            if ($testID && !empty($questions)) {
                foreach ($questions as $index => $question) {
                    $questionText = trim($question['questionText']);
                    $optionA = trim($question['optionA']);
                    $optionB = trim($question['optionB']);
                    $optionC = trim($question['optionC']);
                    $optionD = trim($question['optionD']);
                    $correctAnswer = trim($question['correctAnswer']);

                    if ($questionText && $optionA && $optionB && $optionC && $optionD && in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
                        $stmt = $dbConnection->prepare("
                            INSERT INTO game_questions (testID, questionText, optionA, optionB, optionC, optionD, correctAnswer, archived)
                            VALUES (:testID, :questionText, :optionA, :optionB, :optionC, :optionD, :correctAnswer, 0)
                        ");
                        $stmt->bindParam(':testID', $testID, PDO::PARAM_INT);
                        $stmt->bindParam(':questionText', $questionText, PDO::PARAM_STR);
                        $stmt->bindParam(':optionA', $optionA, PDO::PARAM_STR);
                        $stmt->bindParam(':optionB', $optionB, PDO::PARAM_STR);
                        $stmt->bindParam(':optionC', $optionC, PDO::PARAM_STR);
                        $stmt->bindParam(':optionD', $optionD, PDO::PARAM_STR);
                        $stmt->bindParam(':correctAnswer', $correctAnswer, PDO::PARAM_STR);
                        $stmt->execute();
                    } else {
                        $success = false;
                        $_SESSION['error_message'] = "Invalid input for question " . ($index + 1) . ".";
                        break;
                    }
                }
                if ($success) {
                    $_SESSION['success_message'] = "Question(s) added successfully.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid input or no questions provided.";
            }
        }

        // Edit Question
        if ($action === 'edit_question') {
            $questionID = filter_var($_POST['questionID'], FILTER_VALIDATE_INT);
            $testID = filter_var($_POST['testID'], FILTER_VALIDATE_INT);
            $questionText = trim($_POST['questionText']);
            $optionA = trim($_POST['optionA']);
            $optionB = trim($_POST['optionB']);
            $optionC = trim($_POST['optionC']);
            $optionD = trim($_POST['optionD']);
            $correctAnswer = trim($_POST['correctAnswer']);

            if ($questionID && $testID && $questionText && $optionA && $optionB && $optionC && $optionD && in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
                $stmt = $dbConnection->prepare("
                    UPDATE game_questions
                    SET testID = :testID, questionText = :questionText, optionA = :optionA, optionB = :optionB, 
                        optionC = :optionC, optionD = :optionD, correctAnswer = :correctAnswer
                    WHERE questionID = :questionID
                ");
                $stmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                $stmt->bindParam(':testID', $testID, PDO::PARAM_INT);
                $stmt->bindParam(':questionText', $questionText, PDO::PARAM_STR);
                $stmt->bindParam(':optionA', $optionA, PDO::PARAM_STR);
                $stmt->bindParam(':optionB', $optionB, PDO::PARAM_STR);
                $stmt->bindParam(':optionC', $optionC, PDO::PARAM_STR);
                $stmt->bindParam(':optionD', $optionD, PDO::PARAM_STR);
                $stmt->bindParam(':correctAnswer', $correctAnswer, PDO::PARAM_STR);
                $stmt->execute();
                $_SESSION['success_message'] = "Question updated successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid input for question.";
            }
        }

        // Add Lecture
        if ($action === 'add_lecture') {
            $strandID = filter_var($_POST['strandID'], FILTER_VALIDATE_INT);
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $imagePath = null;

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                    $uploadDir = '../../Uploads/lectures/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $imagePath = $uploadDir . uniqid() . '-' . basename($file['name']);
                    if (!move_uploaded_file($file['tmp_name'], $imagePath)) {
                        $_SESSION['error_message'] = "Failed to upload image.";
                        $imagePath = null;
                    }
                } else {
                    $_SESSION['error_message'] = "Invalid image file or size exceeds 5MB.";
                }
            }

            if ($strandID && $title && $content) {
                $stmt = $dbConnection->prepare("
                    INSERT INTO game_lectures (strandID, title, content, image, archived)
                    VALUES (:strandID, :title, :content, :image, 0)
                ");
                $stmt->bindParam(':strandID', $strandID, PDO::PARAM_INT);
                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':content', $content, PDO::PARAM_STR);
                $stmt->bindParam(':image', $imagePath, PDO::PARAM_STR);
                $stmt->execute();
                $_SESSION['success_message'] = "Lecture added successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid input for lecture.";
            }
        }

        // Edit Lecture
        if ($action === 'edit_lecture') {
            $lectureID = filter_var($_POST['lectureID'], FILTER_VALIDATE_INT);
            $strandID = filter_var($_POST['strandID'], FILTER_VALIDATE_INT);
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $imagePath = null;

            // Fetch existing image path
            $stmt = $dbConnection->prepare("SELECT image FROM game_lectures WHERE lectureID = :lectureID");
            $stmt->bindParam(':lectureID', $lectureID, PDO::PARAM_INT);
            $stmt->execute();
            $existingLecture = $stmt->fetch(PDO::FETCH_ASSOC);
            $imagePath = $existingLecture['image'];

            // Handle new image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                    $uploadDir = '../../Uploads/lectures/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $imagePath = $uploadDir . uniqid() . '-' . basename($file['name']);
                    if (move_uploaded_file($file['tmp_name'], $imagePath)) {
                        // Delete old image if it exists
                        if ($existingLecture['image'] && file_exists($existingLecture['image'])) {
                            unlink($existingLecture['image']);
                        }
                    } else {
                        $_SESSION['error_message'] = "Failed to upload new image.";
                        $imagePath = $existingLecture['image'];
                    }
                } else {
                    $_SESSION['error_message'] = "Invalid image file or size exceeds 5MB.";
                    $imagePath = $existingLecture['image'];
                }
            }

            if ($lectureID && $strandID && $title && $content) {
                $stmt = $dbConnection->prepare("
                    UPDATE game_lectures
                    SET strandID = :strandID, title = :title, content = :content, image = :image
                    WHERE lectureID = :lectureID
                ");
                $stmt->bindParam(':lectureID', $lectureID, PDO::PARAM_INT);
                $stmt->bindParam(':strandID', $strandID, PDO::PARAM_INT);
                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':content', $content, PDO::PARAM_STR);
                $stmt->bindParam(':image', $imagePath, PDO::PARAM_STR);
                $stmt->execute();
                $_SESSION['success_message'] = "Lecture updated successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid input for lecture.";
            }
        }

        // Add Badge
        if ($action === 'add_badge') {
            $level = filter_var($_POST['level'], FILTER_VALIDATE_INT);
            $badgeName = trim($_POST['badgeName']);
            $icon = trim($_POST['icon']);
            $description = trim($_POST['description']);

            if ($level >= 0 && $badgeName && $icon && $description) {
                // Check if level already exists
                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM game_badges WHERE level = :level");
                $stmt->bindParam(':level', $level, PDO::PARAM_INT);
                $stmt->execute();
                $count = $stmt->fetchColumn();

                if ($count == 0) {
                    $stmt = $dbConnection->prepare("
                        INSERT INTO game_badges (level, badgeName, icon, description, archived)
                        VALUES (:level, :badgeName, :icon, :description, 0)
                    ");
                    $stmt->bindParam(':level', $level, PDO::PARAM_INT);
                    $stmt->bindParam(':badgeName', $badgeName, PDO::PARAM_STR);
                    $stmt->bindParam(':icon', $icon, PDO::PARAM_STR);
                    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                    $stmt->execute();
                    $_SESSION['success_message'] = "Badge added successfully.";
                } else {
                    $_SESSION['error_message'] = "A badge with this level already exists.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid input for badge.";
            }
        }

        // Edit Badge
        if ($action === 'edit_badge') {
            $level = filter_var($_POST['level'], FILTER_VALIDATE_INT);
            $badgeName = trim($_POST['badgeName']);
            $icon = trim($_POST['icon']);
            $description = trim($_POST['description']);

            if ($level >= 0 && $badgeName && $icon && $description) {
                $stmt = $dbConnection->prepare("
                    UPDATE game_badges
                    SET badgeName = :badgeName, icon = :icon, description = :description
                    WHERE level = :level
                ");
                $stmt->bindParam(':level', $level, PDO::PARAM_INT);
                $stmt->bindParam(':badgeName', $badgeName, PDO::PARAM_STR);
                $stmt->bindParam(':icon', $icon, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->execute();
                $_SESSION['success_message'] = "Badge updated successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid input for badge.";
            }
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // Archive Test Type
        if ($action === 'archive_test_type') {
            $testTypeID = filter_var($_GET['testTypeID'], FILTER_VALIDATE_INT);
            if ($testTypeID) {
                $stmt = $dbConnection->prepare("UPDATE game_test_types SET archived = 1 WHERE testTypeID = :testTypeID");
                $stmt->bindParam(':testTypeID', $testTypeID, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Test type archived successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid test type ID.";
            }
        }

        // Archive Test
        if ($action === 'archive_test') {
            $testID = filter_var($_GET['testID'], FILTER_VALIDATE_INT);
            if ($testID) {
                $stmt = $dbConnection->prepare("UPDATE game_tests SET archived = 1 WHERE testID = :testID");
                $stmt->bindParam(':testID', $testID, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Test archived successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid test ID.";
            }
        }

        // Archive Question
        if ($action === 'archive_question') {
            $questionID = filter_var($_GET['questionID'], FILTER_VALIDATE_INT);
            if ($questionID) {
                $stmt = $dbConnection->prepare("UPDATE game_questions SET archived = 1 WHERE questionID = :questionID");
                $stmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Question archived successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid question ID.";
            }
        }

        // Archive Lecture
        if ($action === 'archive_lecture') {
            $lectureID = filter_var($_GET['lectureID'], FILTER_VALIDATE_INT);
            if ($lectureID) {
                // Fetch and delete associated image
                $stmt = $dbConnection->prepare("SELECT image FROM game_lectures WHERE lectureID = :lectureID");
                $stmt->bindParam(':lectureID', $lectureID, PDO::PARAM_INT);
                $stmt->execute();
                $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($lecture['image'] && file_exists($lecture['image'])) {
                    unlink($lecture['image']);
                }

                $stmt = $dbConnection->prepare("UPDATE game_lectures SET archived = 1 WHERE lectureID = :lectureID");
                $stmt->bindParam(':lectureID', $lectureID, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Lecture archived successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid lecture ID.";
            }
        }

        // Archive Badge
        if ($action === 'archive_badge') {
            $level = filter_var($_GET['level'], FILTER_VALIDATE_INT);
            if ($level !== false && $level >= 0) {
                $stmt = $dbConnection->prepare("UPDATE game_badges SET archived = 1 WHERE level = :level");
                $stmt->bindParam(':level', $level, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Badge archived successfully.";
            } else {
                $_SESSION['error_message'] = "Invalid badge level.";
            }
        }
    }

    $dbConnection->commit();
} catch (PDOException $e) {
    $dbConnection->rollBack();
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred.";
}

// Redirect back to game_controller.php with the active tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : (isset($_POST['tab']) ? $_POST['tab'] : 'test_types');
header("Location: game_controller.php?tab=" . urlencode($activeTab));
exit;
?>