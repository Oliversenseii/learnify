<?php
session_start();
require_once '../../config/db_connection.php';

if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Professor') {
    header("Location: ../../index.php");
    exit;
}

try {
    $dbConnection->beginTransaction();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $professorID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);

        // Add Game
        if ($action === 'add_game') {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $gameCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)); // Generate unique 6-character game code

            if ($title && $professorID) {
                // Check if game code is unique
                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM learn_quiz_games WHERE gameCode = :gameCode");
                $stmt->bindParam(':gameCode', $gameCode, PDO::PARAM_STR);
                $stmt->execute();
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $dbConnection->prepare("
                        INSERT INTO learn_quiz_games (professorID, title, description, gameCode, isActive)
                        VALUES (:professorID, :title, :description, :gameCode, 1)
                    ");
                    $stmt->bindParam(':professorID', $professorID, PDO::PARAM_INT);
                    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                    $stmt->bindParam(':gameCode', $gameCode, PDO::PARAM_STR);
                    $stmt->execute();
                    $_SESSION['success_message'] = "Game added successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to generate a unique game code. Please try again.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid input for game.";
            }
        }

        // Edit Game
        if ($action === 'edit_game') {
            $gameID = filter_var($_POST['gameID'], FILTER_VALIDATE_INT);
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $isActive = filter_var($_POST['isActive'], FILTER_VALIDATE_INT);

            if ($gameID && $title && $professorID && in_array($isActive, [0, 1])) {
                // Verify the game belongs to the professor
                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM learn_quiz_games WHERE gameID = :gameID AND professorID = :professorID");
                $stmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
                $stmt->bindParam(':professorID', $professorID, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    $stmt = $dbConnection->prepare("
                        UPDATE learn_quiz_games
                        SET title = :title, description = :description, isActive = :isActive
                        WHERE gameID = :gameID
                    ");
                    $stmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
                    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                    $stmt->bindParam(':isActive', $isActive, PDO::PARAM_INT);
                    $stmt->execute();
                    $_SESSION['success_message'] = "Game updated successfully.";
                } else {
                    $_SESSION['error_message'] = "Unauthorized to edit this game.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid input for game.";
            }
        }

        // Toggle Game Status
        if ($action === 'toggle_game') {
            $gameID = filter_var($_POST['gameID'], FILTER_VALIDATE_INT);
            $isActive = filter_var($_POST['isActive'], FILTER_VALIDATE_INT);

            if ($gameID && $professorID && in_array($isActive, [0, 1])) {
                // Verify the game belongs to the professor
                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM learn_quiz_games WHERE gameID = :gameID AND professorID = :professorID");
                $stmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
                $stmt->bindParam(':professorID', $professorID, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    $newStatus = $isActive ? 0 : 1;
                    $stmt = $dbConnection->prepare("
                        UPDATE learn_quiz_games
                        SET isActive = :isActive
                        WHERE gameID = :gameID
                    ");
                    $stmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
                    $stmt->bindParam(':isActive', $newStatus, PDO::PARAM_INT);
                    $stmt->execute();
                    $_SESSION['success_message'] = $newStatus ? "Game activated successfully." : "Game deactivated successfully.";
                } else {
                    $_SESSION['error_message'] = "Unauthorized to toggle this game.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid input for toggling game.";
            }
        }

        // Add Question(s)
        if ($action === 'add_question') {
            $gameID = filter_var($_POST['gameID'], FILTER_VALIDATE_INT);
            $questions = isset($_POST['questions']) && is_array($_POST['questions']) ? $_POST['questions'] : [];
            $success = true;

            if ($gameID && !empty($questions) && $professorID) {
                // Verify the game belongs to the professor
                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM learn_quiz_games WHERE gameID = :gameID AND professorID = :professorID");
                $stmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
                $stmt->bindParam(':professorID', $professorID, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    foreach ($questions as $index => $question) {
                        $questionText = trim($question['questionText']);
                        $optionA = trim($question['optionA']);
                        $optionB = trim($question['optionB']);
                        $optionC = trim($question['optionC']);
                        $optionD = trim($question['optionD']);
                        $correctAnswer = trim($question['correctAnswer']);
                        $timeLimit = filter_var($question['timeLimit'], FILTER_VALIDATE_INT);
                        $points = filter_var($question['points'], FILTER_VALIDATE_INT);

                        if ($questionText && $optionA && $optionB && $optionC && $optionD && in_array($correctAnswer, ['A', 'B', 'C', 'D']) && $timeLimit >= 5 && $points >= 10) {
                            $stmt = $dbConnection->prepare("
                                INSERT INTO learn_quiz_questions (gameID, questionText, optionA, optionB, optionC, optionD, correctAnswer, timeLimit, points)
                                VALUES (:gameID, :questionText, :optionA, :optionB, :optionC, :optionD, :correctAnswer, :timeLimit, :points)
                            ");
                            $stmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
                            $stmt->bindParam(':questionText', $questionText, PDO::PARAM_STR);
                            $stmt->bindParam(':optionA', $optionA, PDO::PARAM_STR);
                            $stmt->bindParam(':optionB', $optionB, PDO::PARAM_STR);
                            $stmt->bindParam(':optionC', $optionC, PDO::PARAM_STR);
                            $stmt->bindParam(':optionD', $optionD, PDO::PARAM_STR);
                            $stmt->bindParam(':correctAnswer', $correctAnswer, PDO::PARAM_STR);
                            $stmt->bindParam(':timeLimit', $timeLimit, PDO::PARAM_INT);
                            $stmt->bindParam(':points', $points, PDO::PARAM_INT);
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
                    $_SESSION['error_message'] = "Unauthorized to add questions to this game.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid input or no questions provided.";
            }
        }

        // Edit Question
        if ($action === 'edit_question') {
            $questionID = filter_var($_POST['questionID'], FILTER_VALIDATE_INT);
            $gameID = filter_var($_POST['gameID'], FILTER_VALIDATE_INT);
            $questionText = trim($_POST['questionText']);
            $optionA = trim($_POST['optionA']);
            $optionB = trim($_POST['optionB']);
            $optionC = trim($_POST['optionC']);
            $optionD = trim($_POST['optionD']);
            $correctAnswer = trim($_POST['correctAnswer']);
            $timeLimit = filter_var($_POST['timeLimit'], FILTER_VALIDATE_INT);
            $points = filter_var($_POST['points'], FILTER_VALIDATE_INT);

            if ($questionID && $gameID && $questionText && $optionA && $optionB && $optionC && $optionD && in_array($correctAnswer, ['A', 'B', 'C', 'D']) && $timeLimit >= 5 && $points >= 10 && $professorID) {
                // Verify the question belongs to a game owned by the professor
                $stmt = $dbConnection->prepare("
                    SELECT COUNT(*) 
                    FROM learn_quiz_questions q 
                    JOIN learn_quiz_games g ON q.gameID = g.gameID 
                    WHERE q.questionID = :questionID AND g.professorID = :professorID
                ");
                $stmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                $stmt->bindParam(':professorID', $professorID, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    $stmt = $dbConnection->prepare("
                        UPDATE learn_quiz_questions
                        SET gameID = :gameID, questionText = :questionText, optionA = :optionA, optionB = :optionB, 
                            optionC = :optionC, optionD = :optionD, correctAnswer = :correctAnswer, 
                            timeLimit = :timeLimit, points = :points
                        WHERE questionID = :questionID
                    ");
                    $stmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                    $stmt->bindParam(':gameID', $gameID, PDO::PARAM_INT);
                    $stmt->bindParam(':questionText', $questionText, PDO::PARAM_STR);
                    $stmt->bindParam(':optionA', $optionA, PDO::PARAM_STR);
                    $stmt->bindParam(':optionB', $optionB, PDO::PARAM_STR);
                    $stmt->bindParam(':optionC', $optionC, PDO::PARAM_STR);
                    $stmt->bindParam(':optionD', $optionD, PDO::PARAM_STR);
                    $stmt->bindParam(':correctAnswer', $correctAnswer, PDO::PARAM_STR);
                    $stmt->bindParam(':timeLimit', $timeLimit, PDO::PARAM_INT);
                    $stmt->bindParam(':points', $points, PDO::PARAM_INT);
                    $stmt->execute();
                    $_SESSION['success_message'] = "Question updated successfully.";
                } else {
                    $_SESSION['error_message'] = "Unauthorized to edit this question.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid input for question.";
            }
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $professorID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);

        // Delete Question
        if ($action === 'delete_question') {
            $questionID = filter_var($_GET['questionID'], FILTER_VALIDATE_INT);
            if ($questionID && $professorID) {
                // Verify the question belongs to a game owned by the professor
                $stmt = $dbConnection->prepare("
                    SELECT COUNT(*) 
                    FROM learn_quiz_questions q 
                    JOIN learn_quiz_games g ON q.gameID = g.gameID 
                    WHERE q.questionID = :questionID AND g.professorID = :professorID
                ");
                $stmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                $stmt->bindParam(':professorID', $professorID, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    $stmt = $dbConnection->prepare("DELETE FROM learn_quiz_questions WHERE questionID = :questionID");
                    $stmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                    $stmt->execute();
                    $_SESSION['success_message'] = "Question deleted successfully.";
                } else {
                    $_SESSION['error_message'] = "Unauthorized to delete this question.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid question ID.";
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
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : (isset($_POST['tab']) ? $_POST['tab'] : 'games');
header("Location: game_controller.php?tab=" . urlencode($activeTab));
exit;
?>