<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';

// CRITICAL: FORCE PHILIPPINE TIME (GMT+8) SA MYSQL & PHP
$dbConnection->exec("SET time_zone = '+08:00';");
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

// Validate teacherSectionID and quizID
if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT) ||
    !isset($_GET['quizID']) || !filter_var($_GET['quizID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section or quiz ID.";
    header("Location: professorDash.php");
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];
$quizID = (int)$_GET['quizID'];

// Verify teacherSectionID and quizID
try {
    $checkStmt = $dbConnection->prepare("
        SELECT ts.sectionID, s.sectionName, s.sectionCode, sub.subjectName, sub.subjectCode, u.firstName, u.lastName,
               q.quizID, q.title, q.description, q.quizType, q.dueDate, q.releaseDate
        FROM teacher_section ts
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        JOIN users u ON ts.teacherID = u.userID
        JOIN quizzes q ON q.teacherSectionID = ts.teacherSectionID
        WHERE ts.teacherSectionID = :teacherSectionID AND ts.teacherID = :userID AND q.quizID = :quizID
        AND ts.archived = 0 AND u.archived = 0 AND s.archived = 0 AND sub.archived = 0 AND q.archived = 0
    ");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
    $checkStmt->execute();
    $section = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        $_SESSION['error_message'] = "Invalid section or quiz assignment.";
        header("Location: professorDash.php");
        exit;
    }
    $professorName = $section['firstName'] . ' ' . $section['lastName'];
    $subjectName = $section['subjectName'];
    $quizTitle = $section['title'];
    $quizDescription = $section['description'];
    $quizType = $section['quizType'];
    $dueDate = $section['dueDate'];
    $releaseDate = $section['releaseDate'];

    // Fetch questions
    $questionStmt = $dbConnection->prepare("
        SELECT questionID, questionText, option1, option2, option3, option4, correctOption, points
        FROM questions
        WHERE quizID = :quizID AND archived = 0
        ORDER BY questionID
    ");
    $questionStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
    $questionStmt->execute();
    $questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle quiz update
    if (isset($_POST['updateQuiz']) && isset($_POST['title']) && isset($_POST['quizType'])) {
        $dbConnection->beginTransaction();
        try {
            $title = trim($_POST['title']);
            $description = isset($_POST['description']) ? trim($_POST['description']) : null;
            $quizType = $_POST['quizType'];
            $dueDateInput = isset($_POST['dueDate']) ? trim($_POST['dueDate']) : null;
            $releaseDateInput = isset($_POST['releaseDate']) ? trim($_POST['releaseDate']) : null;
            $submittedQuestions = isset($_POST['questions']) ? $_POST['questions'] : [];

            // Validate inputs
            if (empty($title) || !in_array($quizType, ['Multiple Choice', 'True/False', 'Essay'])) {
                throw new Exception("Invalid quiz title or type.");
            }
            if (empty($submittedQuestions)) {
                throw new Exception("At least one valid question is required.");
            }

            // Validate and CONVERT dates to TRUE PH TIME
            if (empty($dueDateInput)) {
                throw new Exception("Due date is required.");
            }
            $dueDateTime = new DateTime($dueDateInput, new DateTimeZone('Asia/Manila'));
            $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
            if ($dueDateTime <= $now) {
                throw new Exception("Due date must be in the future.");
            }

            if (empty($releaseDateInput)) {
                throw new Exception("Release date is required.");
            }
            $releaseDateTime = new DateTime($releaseDateInput, new DateTimeZone('Asia/Manila'));
            if ($releaseDateTime <= $now) {
                throw new Exception("Release date must be in the future.");
            }
            if ($releaseDateTime >= $dueDateTime) {
                throw new Exception("Release date must be before due date.");
            }

            // FORMAT AS PH TIME FOR DATABASE (Y-m-d H:i:s)
            $dueDatePH = $dueDateTime->format('Y-m-d H:i:s');
            $releaseDatePH = $releaseDateTime->format('Y-m-d H:i:s');

            // Update quiz with TRUE PH TIME
            $quizStmt = $dbConnection->prepare("
                UPDATE quizzes
                SET title = :title, description = :description, quizType = :quizType, 
                    dueDate = :dueDate, releaseDate = :releaseDate
                WHERE quizID = :quizID AND teacherSectionID = :teacherSectionID
            ");
            $quizStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
            $quizStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
            $quizStmt->bindParam(':title', $title);
            $quizStmt->bindParam(':description', $description);
            $quizStmt->bindParam(':quizType', $quizType);
            $quizStmt->bindParam(':dueDate', $dueDatePH);
            $quizStmt->bindParam(':releaseDate', $releaseDatePH);
            if (!$quizStmt->execute()) {
                throw new Exception("Failed to update quiz.");
            }

            // Process questions
            $existingQuestionIDs = array_column($questions, 'questionID');
            $submittedQuestionIDs = [];
            foreach ($submittedQuestions as $index => $questionData) {
                $questionID = isset($questionData['questionID']) ? (int)$questionData['questionID'] : null;
                $questionText = trim($questionData['text'] ?? '');
                $points = isset($questionData['points']) ? (int)$questionData['points'] : 1;
                if (empty($questionText) || $points < 1) {
                    throw new Exception("Invalid question at index $index.");
                }
                $option1 = $option2 = $option3 = $option4 = $correctOption = null;
                if ($quizType !== 'Essay') {
                    $option1 = isset($questionData['option1']) ? trim($questionData['option1']) : '';
                    $option2 = isset($questionData['option2']) ? trim($questionData['option2']) : '';
                    $correctOption = isset($questionData['correctOption']) ? (int)$questionData['correctOption'] : null;
                    if ($quizType === 'Multiple Choice') {
                        $option3 = isset($questionData['option3']) ? trim($questionData['option3']) : '';
                        $option4 = isset($questionData['option4']) ? trim($questionData['option4']) : '';
                        if (empty($option1) || empty($option2) || empty($option3) || empty($option4) || !in_array($correctOption, [1, 2, 3, 4])) {
                            throw new Exception("Invalid Multiple Choice question at index $index.");
                        }
                    } elseif ($quizType === 'True/False') {
                        if (empty($option1) || empty($option2) || !in_array($correctOption, [1, 2])) {
                            throw new Exception("Invalid True/False question at index $index.");
                        }
                    }
                }
                if ($questionID) {
                    $questionStmt = $dbConnection->prepare("
                        UPDATE questions
                        SET questionText = :questionText, option1 = :option1, option2 = :option2,
                            option3 = :option3, option4 = :option4, correctOption = :correctOption, points = :points
                        WHERE questionID = :questionID AND quizID = :quizID
                    ");
                    $questionStmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                } else {
                    $questionStmt = $dbConnection->prepare("
                        INSERT INTO questions (quizID, questionText, option1, option2, option3, option4, correctOption, points)
                        VALUES (:quizID, :questionText, :option1, :option2, :option3, :option4, :correctOption, :points)
                    ");
                }
                $questionStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                $questionStmt->bindParam(':questionText', $questionText);
                $questionStmt->bindParam(':option1', $option1);
                $questionStmt->bindParam(':option2', $option2);
                $questionStmt->bindParam(':option3', $option3);
                $questionStmt->bindParam(':option4', $option4);
                $questionStmt->bindParam(':correctOption', $correctOption, PDO::PARAM_INT);
                $questionStmt->bindParam(':points', $points, PDO::PARAM_INT);
                if (!$questionStmt->execute()) {
                    throw new Exception("Failed to process question at index $index.");
                }
                if (!$questionID) {
                    $questionID = $dbConnection->lastInsertId();
                }
                $submittedQuestionIDs[] = $questionID;
            }

            // Archive deleted questions
            $questionsToDelete = array_diff($existingQuestionIDs, $submittedQuestionIDs);
            if (!empty($questionsToDelete)) {
                $deleteStmt = $dbConnection->prepare("UPDATE questions SET archived = 1 WHERE questionID = :questionID");
                foreach ($questionsToDelete as $questionID) {
                    $deleteStmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                    if (!$deleteStmt->execute()) {
                        throw new Exception("Failed to archive question ID $questionID.");
                    }
                }
            }

            $dbConnection->commit();
            $_SESSION['success_message'] = "Quiz updated successfully.";
            header("Location: dashQuiz.php?teacherSectionID=$teacherSectionID");
            exit;
        } catch (Exception $e) {
            $dbConnection->rollBack();
            error_log("Error updating quiz: " . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: edit_quiz.php?teacherSectionID=$teacherSectionID&quizID=$quizID");
            exit;
        }
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error: Unable to process request.";
    header("Location: professorDash.php");
    exit;
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
    <title>Learnify - Edit Quiz</title>
    <style>
        :root {
            --text-secondary: #4A5568;
            --blue: #3B82F6;
            --dark: #0F172A;
            --green: #10B981;
            --red: #EF4444;
            --purple: #8B5CF6;
            --yellow: #F59E0B;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .quiz-container {
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            padding: 1rem;
            border-radius: 16px;
            box-shadow: var(--light, 0 4px 20px rgba(0, 0, 0, 0.15));
            margin-bottom: 2rem;
            margin-top: 1rem;
        }

        .quiz-container h2 {
            text-align: center;
            color: var(--dark);
            padding: 0.75rem;
            font-size: clamp(1.2rem, 3vw, 2rem);
            margin-bottom: 0.25rem;
        }

        .quiz-header {
            background: var(--light);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .quiz-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient);
        }

        .quiz-title-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .quiz-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: clamp(1rem, 3vw, 1.5rem);
        }

        .quiz-title-input {
            flex: 1;
            font-size: clamp(1rem, 3vw, 2rem);
            font-weight: 400;
            color: var(--dark);
            border: none;
            border-bottom: 1px solid #ccc !important;
            background: transparent;
            outline: none;
            padding: 0;
            margin: 0;
        }

        .quiz-title-input:focus {
            border-bottom: 2px solid var(--purple) !important;
            padding-bottom: 0.25rem;
        }

        .progress-container {
            margin: 2rem 0;
            display: none;
        }

        .progress-container.show {
            display: block;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #E2E8F0;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: var(--gradient);
            border-radius: 4px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--dark-grey);
        }

        .step-indicator-modern {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            margin: 2rem 0;
            font-weight: 500;
            color: var(--dark);
            text-align: center;
        }

        .step-dots-container {
            display: flex;
            align-items: center;
            gap: 2rem;
            position: relative;
        }

        .step-dots {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            position: relative;
        }

        .step-dots::before {
            content: '';
            position: absolute;
            top: 50%;
            left: -1rem;
            right: -1rem;
            height: 2px;
            background: #E2E8F0;
            z-index: 0;
        }

        .step-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: clamp(1rem, 3vw, 1.2rem);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            background: white;
        }

        .step-dot.completed {
            background: var(--green);
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .step-dot.active {
            background: var(--blue);
            color: white;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        .step-dot.inactive {
            background: var(--grey);
            color: var(--dark);
            border: 2px solid #E2E8F0;
        }

        .step-labels {
            display: flex;
            gap: 2rem;
            margin-top: 0.75rem;
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--dark-grey);
            font-weight: 500;
        }

        .step-label {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            min-width: 60px;
        }

        .step-label-arrow {
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--dark-grey);
        }

        .step-content {
            background: var(--light);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .step-content {
            position: relative;
            overflow: hidden;
        }

        .step-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient);
        }

        .step-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #ccc;
        }

        .section-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: clamp(1rem, 3vw, 2rem);
        }

        .section-title {
            font-size: clamp(1.2rem, 3vw, 1.8rem);
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .required {
            color: var(--red);
        }

        #dueDate,
        #releaseDate,
        .form-textarea,
        .form-select,
        #dueDateDisplay {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #ccc;
            border-radius: 8px;
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--dark);
            background: var(--grey);
            transition: all 0.2s ease;
            font-family: inherit;
        }

        #dueDate,
        #releaseDate,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .quiz-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .type-card {
            border: 2px solid #ccc;
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--grey);
            position: relative;
            overflow: hidden;
        }

        .type-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: transparent;
            transition: background 0.3s ease;
        }

        .type-card:hover {
            border-color: var(--purple);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .type-card.selected {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .type-card.selected::before {
            background: var(--purple);
        }

        .type-card-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: clamp(1rem, 3vw, 2rem);
            color: white;
        }

        .type-card-title {
            font-size: clamp(1rem, 3vw, 1.7rem);
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .type-card-description {
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 0;
            font-size: clamp(1rem, 3vw, 1.4rem);
        }

        .questions-container {
            margin-top: 1.5rem;
            overflow-x: auto;
        }

        fieldset {
            border: 1px solid #ccc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: var(--light);
            position: relative;
            transition: all 0.2s ease;
        }

        fieldset:hover {
            border-color: var(--purple);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
        }

        fieldset legend {
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 600;
            color: var(--purple);
            padding: 0 0.75rem;
            background: transparent;
        }

        .question-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #ccc;
        }

        .question-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: clamp(1rem, 3vw, 1.3rem);
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #4b5357);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: #FECACA;
            color: red;
            border: 1px solid var(--red);
        }

        .btn-danger:hover {
            transform: translateY(-1px);
        }

        .tooltip-wrapper {
            position: relative;
            display: inline-block; 
        }

        [data-tooltip]::after {
            content: attr(data-tooltip); 
            position: absolute;
            top: 50%;
            left: calc(100% + 10px); 
            transform: translateY(-50%); 
            background: #FECACA; 
            color: red; 
            padding: 5px 10px;
            border: 1px solid red;
            border-radius: 4px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            white-space: nowrap; 
            opacity: 1; 
            visibility: visible; 
            z-index: 10; 
        }

        [data-tooltip]::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%; 
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-right-color: red;
            z-index: 10;
        }

        button[data-tooltip]::after {
            top: 50%;
            left: calc(100% + 10px);
            transform: translateY(-50%);
        }

        button[data-tooltip]::before {
            top: 50%;
            left: 100%;
            transform: translateY(-50%);
        }

        .btn-small {
            padding: 5px 5px !important;
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .points-input, .btn-small {
            position: relative;
            z-index: 11; 
        }

        .question-content {
            margin-top: 1rem;
        }

        .question-text-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #ccc;
            border-radius: 8px;
            resize: vertical;
            color: var(--dark);
            min-height: 80px;
            margin-bottom: 1rem;
            background-color: var(--grey);
            font-family: inherit;
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .question-text-input:focus,
        .points-input:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .points-input {
            width: 80px;
            padding: 0.625rem;
            border: 2px solid #ccc;
            border-radius: 6px;
            background-color: var(--grey);
            text-align: center;
            font-weight: 500;
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .option-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 8px;
            background: var(--light);
            transition: all 0.2s ease;
        }

        .option-label {
            font-weight: 600;
            border-radius: 50%;
            padding: 1rem;
            background-color: #1D4ED8;
            color: white;
            font-size: clamp(1rem, 3vw, 1.3rem);
            min-width: 20px;
        }

        .option-input {
            flex: 1;
            padding: 1rem;
            border: 1px solid #ccc;
            background-color: var(--grey);
            color: var(--dark);
            border-radius: 4px;
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .correct-toggle {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-left: auto;
            padding: 0.75rem;
            background: var(--grey);
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--green);
            font-weight: 500;
        }

        .correct-toggle input[type="checkbox"] {
            width: 14px;
            height: 14px;
            accent-color: var(--green);
        }

        .true-false-options {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .true-false-option {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border: 2px solid #E2E8F0;
            border-radius: 8px;
            background: var(--grey);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .true-false-option:hover {
            border-color: var(--purple);
        }

        .true-false-option.selected {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .true-false-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--green);
            margin: 0;
        }

        .true-false-label {
            font-weight: 500;
            color: var(--dark);
            cursor: pointer;
        }

        .navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #F1F5F9;
        }

        .nav-left {
            display: flex;
            gap: 1rem;
        }

        .nav-right {
            display: flex;
            gap: 1rem;
        }

        .btn-nav {
            min-width: 120px;
        }

        .completion-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #ECFDF5;
            color: var(--green);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 500;
            margin-left: 1rem;
        }

        .completion-badge i {
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.2s ease;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-content-modern {
            background: var(--light);
            border-radius: 16px;
            padding: 2rem;
            max-width: 700px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }

        .modal-title {
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            font-size: clamp(1.2rem, 3vw, 2rem);
        }

        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--dark);
            padding: 0.25rem;
            border-radius: 4px;
            font-size: clamp(1.2rem, 3vw, 2rem);
            transition: color 0.2s ease, background 0.2s ease;
        }

        .modal-close:hover {
            color: red;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .summary-table td {
            padding: 0.75rem 1rem;
            vertical-align: top;
            border: 1px solid #ccc;
        }

        .summary-label {
            font-weight: 500;
            color: var(--dark);
            width: 30%;
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .summary-value {
            font-weight: 600;
            color: var(--text-secondary);
            width: 70%;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .questions-summary {
            background: var(--grey);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            overflow-x: auto;
        }

        .questions-preview-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: clamp(1rem, 3vw, 1.5rem);
        }

        .questions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .questions-table th {
            font-weight: 600;
            color: white;
            text-align: left;
            padding: 0.5rem 1rem;
            background: #764ba2;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .questions-table td {
            padding: 0.5rem 1rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--text-secondary);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .error-message {
            color: var(--red);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            font-size: clamp(1rem, 3vw, 1.3rem);
            gap: 0.5rem;
        }

        .success-message {
            background: #F0FDF4;
            color: var(--green);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--green);
            font-size: clamp(1rem, 3vw, 1.3rem);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media screen and (max-width: 768px) {
            .quiz-container {
                padding: 0.5rem;
                margin: 0 1rem;
            }

            .quiz-header {
                padding: 1.5rem;
            }

            .step-content {
                padding: 1.5rem;
            }

            .step-dots {
                gap: 0.5rem;
            }

            .step-dots::before {
                left: 0;
                right: 0;
            }

            .step-labels {
                display: none;
            }

            .navigation {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-left, .nav-right {
                width: 100%;
                justify-content: center;
            }

            .btn-nav {
                width: 100%;
                justify-content: center;
            }

            .options-grid {
                grid-template-columns: 1fr;
            }

            .true-false-options {
                flex-direction: column;
            }

            .modal-actions {
                flex-direction: column;
            }
        }

        @media screen and (max-width: 480px) {
            .type-card {
                padding: 1rem;
            }

            .step-dots {
                gap: 0.75rem;
            }

            .step-dot {
                width: 32px;
                height: 32px;
            }

            .quiz-container {
                padding: 0;
                margin: 0;
            }
            .option-item {
                display: flex;
                flex-direction: column;
            }
            .nav-right {
                display: flex;
                flex-direction: column;
            }
        }

        .step-content::-webkit-scrollbar {
            width: 6px;
        }

        .step-content::-webkit-scrollbar-track {
            background: #F1F5F9;
            border-radius: 3px;
        }

        .step-content::-webkit-scrollbar-thumb {
            background: var(--blue);
            border-radius: 3px;
        }

        .step-content::-webkit-scrollbar-thumb:hover {
            background: #2563EB;
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
            <li><a href="./professor_main_dash.php"><i class='bx bxs-dashboard'></i><span class="text">Dashboard</span></a></li>
            <li><a href="./modules.php"><i class='bx bxs-bookmark'></i><span class="text">Modules</span></a></li>
            <li><a href="./calendar.php"><i class='bx bxs-calendar'></i><span class="text">Calendar</span></a></li>
            <li><a href="./message_admin.php"><i class='bx bxs-message'></i><span class="text">Message Admin</span></a></li>
            <li><a href="./game_controller.php"><i class='bx bxs-game'></i><span class="text">Game</span></a></li>
        </ul>
        <ul class="side-menu">
            <li><a href="./settings.php"><i class='bx bxs-cog'></i><span class="text">Settings</span></a></li>
            <li><a href="javascript:void(0);" class="logout" onclick="showLogoutModal()"><i class='bx bxs-log-out-circle'></i><span class="text">Logout</span></a></li>
        </ul>
    </section>

    <?php require_once './view/modal.php' ?>

    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-content-modern">
            <div class="modal-header">
                <h2 class="modal-title" style="font-size: 2.5rem;">Confirm Quiz Update</h2>
                <button class="modal-close" style="font-size: 2.5rem;" onclick="closeConfirmation()">&times;</button>
            </div>
            <div id="summaryContent"></div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeConfirmation()">Cancel</button>
                <button class="btn btn-primary" onclick="submitForm()">Update Quiz</button>
            </div>
        </div>
    </div>

    <section id="content">
        <nav>
            <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users..." required aria-label="Search">
                    <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile" aria-label="Profile">
                <img src="<?php echo htmlspecialchars($_SESSION['image']); ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small>Teacher</small>
                </div>
            </a>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Edit Quiz</h1>
                    <ul class="breadcrumb">
                        <li><a href="./professorDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>">Quizzes</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Edit</a></li>
                    </ul>
                </div>
                <a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Quiz Dashboard
                </a>
            </div>

            <div class="quiz-container">
                <h2><?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h2>
                <div class="quiz-header">
                    <div class="quiz-title-section">
                        <div class="quiz-icon">
                            <i class='bx bx-edit-alt'></i>
                        </div>
                        <input type="text" class="quiz-title-input" id="dynamicTitle" value="<?php echo htmlspecialchars($quizTitle); ?>" maxlength="255" required>
                    </div>
                </div>

                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-text">
                        <span id="progressLabel">0 of 0 questions completed</span>
                        <span id="progressPercentage">0%</span>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-message" style="margin: 0 2rem;">
                        <i class='bx bx-check-circle'></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="error-message" style="margin: 0 2rem;">
                        <i class='bx bx-error'></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <form id="quizForm" method="POST" action="edit_quiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>&quizID=<?php echo $quizID; ?>">
                    <!-- Step 1: Quiz Type -->
                    <div class="step-content active" id="step1">
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon" style="background: var(--gradient);">
                                    <i class='bx bx-list-check'></i>
                                </div>
                                <h3 class="section-title">Quiz Type</h3>
                            </div>
                            <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 1.3rem;">
                                The quiz type is set and cannot be changed.
                            </p>
                            <div class="quiz-type-grid">
                                <div class="type-card selected" data-type="<?php echo htmlspecialchars($quizType); ?>">
                                    <div class="type-card-icon" style="background: <?php echo $quizType === 'Multiple Choice' ? 'linear-gradient(135deg, #3B82F6, #1D4ED8)' : ($quizType === 'True/False' ? 'linear-gradient(135deg, #10B981, #059669)' : 'linear-gradient(135deg, #F59E0B, #D97706)'); ?>;">
                                        <i class='bx <?php echo $quizType === 'Multiple Choice' ? 'bxs-select-multiple' : ($quizType === 'True/False' ? 'bx-toggle-left' : 'bxs-pencil'); ?>'></i>
                                    </div>
                                    <h4 class="type-card-title"><?php echo htmlspecialchars($quizType); ?></h4>
                                    <p class="type-card-description">
                                        <?php echo $quizType === 'Multiple Choice' ? 'Students select one correct answer from four options (A-D).' : ($quizType === 'True/False' ? 'Students choose between True or False for each question.' : 'Students provide written responses to open-ended questions.'); ?>
                                    </p>
                                </div>
                            </div>
                            <input type="hidden" name="quizType" id="quizType" value="<?php echo htmlspecialchars($quizType); ?>" required>
                        </div>
                        <div class="navigation">
                            <div class="nav-left"></div>
                            <div class="nav-right">
                                <button type="button" class="btn btn-nav btn-primary" id="step1Continue">
                                    <i class='bx bx-right-arrow-alt'></i>
                                    <span>Continue</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Quiz Details -->
                    <div class="step-content" id="step2">
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon" style="background: var(--gradient);">
                                    <i class='bx bx-info-circle'></i>
                                </div>
                                <h3 class="section-title">Quiz Details</h3>
                            </div>
                            <div class="form-group">
                                <label for="description" class="form-label">Quiz Description</label>
                                <textarea id="description" class="form-textarea" name="description" placeholder="Add a description or instructions for your students (optional)" aria-label="Quiz Description"><?php echo htmlspecialchars($quizDescription ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="dueDate" class="form-label">Due Date <span class="required">*</span></label>
                                <input id="dueDate" type="datetime-local" class="form-input" name="dueDate" value="<?php echo htmlspecialchars($dueDate ?? ''); ?>" required aria-label="Due Date">
                                <span class="error-message" id="dueDate-error"></span>
                            </div>
                        </div>
                        <div class="navigation">
                            <div class="nav-left">
                                <button type="button" class="btn btn-secondary btn-nav" id="step2Back">
                                    <i class='bx bx-left-arrow-alt'></i>
                                    <span>Back</span>
                                </button>
                            </div>
                            <div class="nav-right">
                                <button type="button" class="btn btn-nav btn-primary" id="step2Continue">
                                    <i class='bx bx-right-arrow-alt'></i>
                                    <span>Continue to Questions</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Questions -->
                    <div class="step-content" id="step3">
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon" style="background: var(--gradient);">
                                    <i class='bx bx-question-mark'></i>
                                </div>
                                <h3 class="section-title">Edit Questions</h3>
                            </div>
                            <p style="color: var(--dark-grey); margin-bottom: 1.5rem; font-size: clamp(1rem, 3vw, 1.3rem);">
                                Modify existing questions or add new ones. Ensure all questions are complete before updating.
                            </p>
                            <div class="questions-container" id="questionsContainer"></div>
                            <button type="button" class="btn btn-primary" onclick="addQuestion()" style="margin-top: 1rem;">
                                <i class='bx bx-plus'></i>
                                <span>Add Question</span>
                            </button>
                        </div>
                        <div class="navigation">
                            <div class="nav-left">
                                <button type="button" class="btn btn-secondary btn-nav" id="step3Back">
                                    <i class='bx bx-left-arrow-alt'></i>
                                    <span>Back to Details</span>
                                </button>
                            </div>
                            <div class="nav-right">
                                <button type="button" class="btn btn-nav btn-primary" id="step3Continue">
                                    <i class='bx bx-right-arrow-alt'></i>
                                    <span>Continue to Settings</span>
                                </button>
                                <div class="completion-badge" id="completionBadge" style="display: none;">
                                    <i class='bx bx-check'></i>
                                    <span id="completionText"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Quiz Settings -->
                    <div class="step-content" id="step4">
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon" style="background: var(--gradient);">
                                    <i class='bx bx-cog'></i>
                                </div>
                                <h3 class="section-title">Quiz Settings</h3>
                            </div>
                            <p style="color: var(--dark-grey); margin-bottom: 1.5rem; font-size: clamp(1rem, 3vw, 1.3rem);">
                                Configure when the quiz will be available. The release date must be before the due date.
                            </p>
                            <div class="form-group">
                                <label class="form-label">Due Date (Set in Step 2)</label>
                                <input id="dueDateDisplay" type="text" class="form-input" value="<?php echo htmlspecialchars($dueDate ? date('F d, Y - h:i A', strtotime($dueDate)) : ''); ?>" readonly aria-label="Due Date (Read-only)">
                            </div>
                            <div class="form-group">
                                <label for="releaseDate" class="form-label">Release Date <span class="required">*</span></label>
                                <input id="releaseDate" type="datetime-local" name="releaseDate" value="<?php echo htmlspecialchars($releaseDate ?? ''); ?>" required aria-label="Release Date">
                                <span class="error-message" id="releaseDate-error"></span>
                            </div>
                        </div>
                        <div class="navigation">
                            <div class="nav-left">
                                <button type="button" class="btn btn-secondary btn-nav" id="step4Back">
                                    <i class='bx bx-left-arrow-alt'></i>
                                    <span>Back to Questions</span>
                                </button>
                            </div>
                            <div class="nav-right">
                                <button type="button" class="btn btn-nav btn-primary" id="step4Continue">
                                    <i class='bx bx-save'></i>
                                    <span>Review & Update</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="updateQuiz" value="1">
                    <input type="hidden" name="title" id="hiddenTitle" value="<?php echo htmlspecialchars($quizTitle); ?>">
                </form>

                <div class="step-indicator-modern" id="stepIndicator">
                    <div class="step-dots-container">
                        <div class="step-dots">
                            <div class="step-dot active" data-step="1">1</div>
                            <div class="step-dot inactive" data-step="2">2</div>
                            <div class="step-dot inactive" data-step="3">3</div>
                            <div class="step-dot inactive" data-step="4">4</div>
                        </div>
                    </div>
                    <div class="step-labels">
                        <span class="step-label"><i class='bx bx-right-arrow-alt step-label-arrow'></i> Type</span>
                        <span class="step-label"><i class='bx bx-right-arrow-alt step-label-arrow'></i> Details</span>
                        <span class="step-label"><i class='bx bx-right-arrow-alt step-label-arrow'></i> Questions</span>
                        <span class="step-label"><i class='bx bx-right-arrow-alt step-label-arrow'></i> Settings</span>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>

    <script>
    // Global variables
    let currentStep = 1;
    let quizType = '<?php echo htmlspecialchars($quizType); ?>';
    let questions = [];
    const quizForm = document.getElementById('quizForm');
    const dynamicTitle = document.getElementById('dynamicTitle');
    const hiddenTitle = document.getElementById('hiddenTitle');

    // Format date as "Month DD, YYYY - HH:MM AM/PM"
    function formatDate(date) {
        if (!date || isNaN(date.getTime())) return 'Invalid Date';
        const options = {
            month: 'long',
            day: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        };
        return date.toLocaleString('en-US', options).replace(',', ' -').replace(/,/, '');
    }

    // Initialize title sync
    dynamicTitle.addEventListener('input', function() {
        hiddenTitle.value = this.value || '<?php echo htmlspecialchars($quizTitle); ?>';
    });

    // Sync due date display in Step 4
    document.getElementById('dueDate').addEventListener('change', function() {
        const dueDateDisplay = document.getElementById('dueDateDisplay');
        if (dueDateDisplay && this.value) {
            dueDateDisplay.value = formatDate(new Date(this.value));
        }
    });

    // HTML special characters escape
    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;');
    }

    // Update step indicators
    function updateStepIndicators() {
        const dots = document.querySelectorAll('.step-dot');
        dots.forEach((dot, index) => {
            if (index < currentStep - 1) {
                dot.className = 'step-dot completed';
            } else if (index === currentStep - 1) {
                dot.className = 'step-dot active';
            } else {
                dot.className = 'step-dot inactive';
            }
        });
    }

    // Navigate to next step
    function nextStep(step) {
        document.querySelectorAll('.step-content').forEach(content => content.classList.remove('active'));
        document.getElementById(`step${step}`).classList.add('active');
        currentStep = step;
        updateStepIndicators();
        updateProgressVisibility();
    }

    // Navigate to previous step
    function prevStep(step) {
        document.querySelectorAll('.step-content').forEach(content => content.classList.remove('active'));
        document.getElementById(`step${step}`).classList.add('active');
        currentStep = step;
        updateStepIndicators();
        updateProgressVisibility();
    }

    // Validate Step 2 and proceed
    function validateStep2AndNext(step) {
        const dueDate = document.getElementById('dueDate');
        let isValid = true;

        clearErrors();

        if (!hiddenTitle.value.trim()) {
            showToast('Quiz title is required', 'error');
            isValid = false;
        }

        if (!dueDate.value) {
            showFieldError('dueDate', 'Due date is required');
            dueDate.classList.add('input-error');
            isValid = false;
        } else {
            const dueDateTime = new Date(dueDate.value);
            const now = new Date();
            if (dueDateTime <= now) {
                showFieldError('dueDate', 'Due date must be in the future');
                dueDate.classList.add('input-error');
                isValid = false;
            }
        }

        if (isValid) {
            // Update due date display in Step 4
            const dueDateDisplay = document.getElementById('dueDateDisplay');
            if (dueDateDisplay) {
                dueDateDisplay.value = dueDate.value ? formatDate(new Date(dueDate.value)) : '';
            }
            nextStep(step);
        }
    }

    // Validate Step 3 and proceed
    function validateStep3AndNext(step) {
        if (questions.length === 0) {
            showToast('Please add at least one question.', 'error');
            return;
        }

        let isValid = true;
        questions.forEach((q, index) => {
            if (!q.completed) {
                showToast(`Question ${index + 1} is incomplete.`, 'error');
                isValid = false;
            }
        });

        if (isValid) {
            nextStep(step);
        }
    }

    // Validate Step 4 and show confirmation
    function validateStep4AndNext() {
        const releaseDate = document.getElementById('releaseDate');
        const dueDate = document.getElementById('dueDate');
        let isValid = true;

        clearErrors();

        if (!releaseDate.value) {
            showFieldError('releaseDate', 'Release date is required');
            releaseDate.classList.add('input-error');
            isValid = false;
        } else {
            const releaseDateTime = new Date(releaseDate.value);
            const dueDateTime = new Date(dueDate.value);
            const now = new Date();
            if (releaseDateTime <= now) {
                showFieldError('releaseDate', 'Release date must be in the future');
                releaseDate.classList.add('input-error');
                isValid = false;
            } else if (releaseDateTime >= dueDateTime) {
                showFieldError('releaseDate', 'Release date must be before the due date (' + formatDate(dueDateTime) + ')');
                releaseDate.classList.add('input-error');
                isValid = false;
            }
        }

        if (isValid) {
            showConfirmation();
        }
    }

    // Clear error messages
    function clearErrors() {
        document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    }

    // Show field-specific error
    function showFieldError(fieldId, message) {
        const errorElement = document.getElementById(fieldId + '-error');
        if (errorElement) {
            errorElement.textContent = message;
        }
    }

    // Add a new question
    function addQuestion(questionData = null) {
        const container = document.getElementById('questionsContainer');
        const index = questions.length;
        const questionID = questionData ? questionData.questionID : null;
        const questionText = questionData ? questionData.questionText : '';
        const option1 = questionData ? questionData.option1 || '' : '';
        const option2 = questionData ? questionData.option2 || '' : '';
        const option3 = questionData ? questionData.option3 || '' : '';
        const option4 = questionData ? questionData.option4 || '' : '';
        const correctOption = questionData ? questionData.correctOption : null;
        const points = questionData ? questionData.points : 1;

        const questionFieldset = document.createElement('fieldset');
        questionFieldset.id = `question-${index}`;

        let optionsHTML = '';
        if (quizType === 'Multiple Choice') {
            optionsHTML = `
                <div class="options-grid">
                    <div class="option-item">
                        <span class="option-label">A</span>
                        <input type="text" class="option-input" name="questions[${index}][option1]" value="${htmlspecialchars(option1)}" placeholder="Option A" required aria-label="Option A">
                        <div class="correct-toggle">
                            <input type="checkbox" class="correct-checkbox" name="questions[${index}][correctOption]" value="1" ${correctOption == 1 ? 'checked' : ''}>
                            <span>Correct</span>
                        </div>
                    </div>
                    <div class="option-item">
                        <span class="option-label">B</span>
                        <input type="text" class="option-input" name="questions[${index}][option2]" value="${htmlspecialchars(option2)}" placeholder="Option B" required aria-label="Option B">
                        <div class="correct-toggle">
                            <input type="checkbox" class="correct-checkbox" name="questions[${index}][correctOption]" value="2" ${correctOption == 2 ? 'checked' : ''}>
                            <span>Correct</span>
                        </div>
                    </div>
                    <div class="option-item">
                        <span class="option-label">C</span>
                        <input type="text" class="option-input" name="questions[${index}][option3]" value="${htmlspecialchars(option3)}" placeholder="Option C" required aria-label="Option C">
                        <div class="correct-toggle">
                            <input type="checkbox" class="correct-checkbox" name="questions[${index}][correctOption]" value="3" ${correctOption == 3 ? 'checked' : ''}>
                            <span>Correct</span>
                        </div>
                    </div>
                    <div class="option-item">
                        <span class="option-label">D</span>
                        <input type="text" class="option-input" name="questions[${index}][option4]" value="${htmlspecialchars(option4)}" placeholder="Option D" required aria-label="Option D">
                        <div class="correct-toggle">
                            <input type="checkbox" class="correct-checkbox" name="questions[${index}][correctOption]" value="4" ${correctOption == 4 ? 'checked' : ''}>
                            <span>Correct</span>
                        </div>
                    </div>
                </div>
            `;
        } else if (quizType === 'True/False') {
            optionsHTML = `
                <div class="true-false-options">
                    <label class="true-false-option ${correctOption == 1 ? 'selected' : ''}" onclick="selectTrueFalse(this, ${index})">
                        <input type="radio" class="correct-radio" name="questions[${index}][correctOption]" value="1" ${correctOption == 1 ? 'checked' : ''} required>
                        <span class="true-false-label">True</span>
                    </label>
                    <label class="true-false-option ${correctOption == 2 ? 'selected' : ''}" onclick="selectTrueFalse(this, ${index})">
                        <input type="radio" class="correct-radio" name="questions[${index}][correctOption]" value="2" ${correctOption == 2 ? 'checked' : ''} required>
                        <span class="true-false-label">False</span>
                    </label>
                </div>
                <input type="hidden" name="questions[${index}][option1]" value="True">
                <input type="hidden" name="questions[${index}][option2]" value="False">
            `;
        }

        questionFieldset.innerHTML = `
            <legend>
                <i class='bx bx-hash'></i>
                Question ${index + 1}
            </legend>
            <div class="question-header">
                <div class="question-actions">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: clamp(1rem, 3vw, 1.3rem); color: var(--text-secondary);">
                        <div class="tooltip-wrapper">
                            <input 
                                type="number" 
                                class="points-input" 
                                name="questions[${index}][points]" 
                                value="${points}" 
                                min="1" 
                                max="10" 
                                placeholder="Pts" 
                                required 
                                data-tooltip="Set points (1-10) for this question"
                            >
                        </div>
                        <span>points</span>
                    </div>
                    <div class="tooltip-wrapper">
                        <button 
                            type="button" 
                            class="btn btn-danger btn-small" 
                            onclick="removeQuestion(${index})" 
                            title="Remove question" 
                            data-tooltip="Delete this question"
                        >
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="question-content">
                <textarea class="question-text-input" name="questions[${index}][text]" placeholder="Enter your question here..." required aria-label="Question ${index + 1}">${htmlspecialchars(questionText)}</textarea>
                ${optionsHTML}
                ${questionID ? `<input type="hidden" name="questions[${index}][questionID]" value="${questionID}">` : ''}
            </div>
        `;

        container.appendChild(questionFieldset);

        // Add event listeners for inputs
        const inputs = questionFieldset.querySelectorAll('textarea, input[type="text"], input[type="number"]');
        inputs.forEach(input => {
            input.addEventListener('input', () => updateQuestionCompletion(index));
            input.removeAttribute('disabled'); // Ensure inputs are editable
        });

        if (quizType === 'True/False') {
            const radioButtons = questionFieldset.querySelectorAll('.correct-radio');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', () => updateQuestionCompletion(index));
                radio.removeAttribute('disabled');
            });
        } else if (quizType === 'Multiple Choice') {
            const checkboxes = questionFieldset.querySelectorAll('.correct-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        checkboxes.forEach(cb => {
                            if (cb !== this) cb.checked = false;
                        });
                    }
                    updateQuestionCompletion(index);
                });
                checkbox.removeAttribute('disabled');
            });
        }

        questions.push({ index, completed: false });
        updateQuestionCompletion(index);
    }

    // Select True/False option
    function selectTrueFalse(element, questionIndex) {
        const fieldset = document.getElementById(`question-${questionIndex}`);
        const options = fieldset.querySelectorAll('.true-false-option');
        options.forEach(opt => opt.classList.remove('selected'));
        element.classList.add('selected');
        updateQuestionCompletion(questionIndex);
    }

    // Remove a question
    function removeQuestion(index) {
        const questionFieldset = document.getElementById(`question-${index}`);
        if (questionFieldset) {
            questionFieldset.remove();
        }
        questions = questions.filter(q => q.index !== index);
        updateQuestionNumbers();
        updateProgress();
    }

    // Update question numbers after removal
    function updateQuestionNumbers() {
        const questionFieldsets = document.querySelectorAll('fieldset[id^="question-"]');
        questionFieldsets.forEach((fieldset, newIndex) => {
            const legend = fieldset.querySelector('legend');
            if (legend) {
                legend.innerHTML = `<i class='bx bx-hash'></i> Question ${newIndex + 1}`;
            }

            const inputs = fieldset.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                if (input.name && input.name.includes('questions')) {
                    const oldIndexMatch = input.name.match(/questions\[(\d+)\]/);
                    if (oldIndexMatch) {
                        const oldIndex = parseInt(oldIndexMatch[1]);
                        input.name = input.name.replace(`questions[${oldIndex}]`, `questions[${newIndex}]`);
                    }
                }
            });

            questions[newIndex] = { index: newIndex, completed: questions[newIndex]?.completed || false };
        });
    }

    // Update question completion status
    function updateQuestionCompletion(questionIndex) {
        const questionFieldset = document.getElementById(`question-${questionIndex}`);
        if (!questionFieldset) return;

        const textArea = questionFieldset.querySelector(`textarea[name="questions[${questionIndex}][text]"]`);
        const pointsInput = questionFieldset.querySelector(`input[name="questions[${questionIndex}][points]"]`);
        const isValidText = textArea && textArea.value.trim().length > 0;
        const isValidPoints = pointsInput && parseInt(pointsInput.value) >= 1;

        let isValidOptions = true;
        if (quizType !== 'Essay') {
            if (quizType === 'True/False') {
                const selectedRadio = questionFieldset.querySelector(`input[name="questions[${questionIndex}][correctOption]"]:checked`);
                isValidOptions = !!selectedRadio;
            } else if (quizType === 'Multiple Choice') {
                const option1 = questionFieldset.querySelector(`input[name="questions[${questionIndex}][option1]"]`);
                const option2 = questionFieldset.querySelector(`input[name="questions[${questionIndex}][option2]"]`);
                const option3 = questionFieldset.querySelector(`input[name="questions[${questionIndex}][option3]"]`);
                const option4 = questionFieldset.querySelector(`input[name="questions[${questionIndex}][option4]"]`);
                const checkedBoxes = questionFieldset.querySelectorAll('.correct-checkbox:checked');
                isValidOptions = option1?.value.trim().length > 0 &&
                                option2?.value.trim().length > 0 &&
                                option3?.value.trim().length > 0 &&
                                option4?.value.trim().length > 0 &&
                                checkedBoxes.length === 1;
            }
        }

        const isCompleted = isValidText && isValidPoints && isValidOptions;
        questions[questionIndex].completed = isCompleted;
        updateProgress();
    }

    // Update progress bar and badge
    function updateProgress() {
        if (currentStep !== 3) return;

        const totalQuestions = questions.length;
        const completedQuestions = questions.filter(q => q.completed).length;
        const percentage = totalQuestions > 0 ? Math.round((completedQuestions / totalQuestions) * 100) : 0;

        document.getElementById('progressFill').style.width = percentage + '%';
        document.getElementById('progressLabel').textContent = `${completedQuestions} of ${totalQuestions} questions completed`;
        document.getElementById('progressPercentage').textContent
        = percentage + '%';

        const completionBadge = document.getElementById('completionBadge');
        const completionText = document.getElementById('completionText');
        if (totalQuestions > 0 && completedQuestions === totalQuestions) {
            completionBadge.style.display = 'flex';
            completionText.textContent = 'All questions completed';
        } else {
            completionBadge.style.display = 'none';
        }
    }

    // Show/hide progress container based on step
    function updateProgressVisibility() {
        const progressContainer = document.getElementById('progressContainer');
        if (currentStep === 3) {
            progressContainer.classList.add('show');
            updateProgress();
        } else {
            progressContainer.classList.remove('show');
        }
    }

    // Show toast notification
    function showToast(message, type = 'error') {
        const existingToast = document.querySelector('.toast-notification');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}-message`;
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '100000';
        toast.style.display = 'flex';
        toast.style.alignItems = 'center';
        toast.style.gap = '0.5rem';
        toast.innerHTML = `<i class='bx ${type === 'error' ? 'bx-error' : 'bx-check-circle'}'></i> ${htmlspecialchars(message)}`;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Show confirmation modal
    function showConfirmation() {
        const title = hiddenTitle.value;
        const description = document.getElementById('description').value || 'No description provided';
        const dueDate = document.getElementById('dueDate').value;
        const releaseDate = document.getElementById('releaseDate').value;
        let questionsHTML = '';

        questions.forEach((q, index) => {
            const questionFieldset = document.getElementById(`question-${index}`);
            if (!questionFieldset) return;
            const text = questionFieldset.querySelector(`textarea[name="questions[${index}][text]"]`).value;
            const points = questionFieldset.querySelector(`input[name="questions[${index}][points]"]`).value;
            let optionsHTML = '';
            let correctOption = '';

            if (quizType === 'Multiple Choice') {
                const options = ['A', 'B', 'C', 'D'].map((label, i) => {
                    const optionValue = questionFieldset.querySelector(`input[name="questions[${index}][option${i + 1}]"]`).value;
                    const isCorrect = questionFieldset.querySelector(`input[name="questions[${index}][correctOption]"][value="${i + 1}"]:checked`);
                    if (isCorrect) correctOption = `${label}: ${optionValue}`;
                    return `<tr><td>${label}</td><td>${htmlspecialchars(optionValue)}</td></tr>`;
                });
                optionsHTML = `
                    <table class="questions-table">
                        <thead><tr><th>Option</th><th>Text</th></tr></thead>
                        <tbody>${options.join('')}</tbody>
                    </table>
                `;
            } else if (quizType === 'True/False') {
                const selectedRadio = questionFieldset.querySelector(`input[name="questions[${index}][correctOption]"]:checked`);
                correctOption = selectedRadio ? (selectedRadio.value == 1 ? 'True' : 'False') : 'Not selected';
            }

            questionsHTML += `
                <div class="questions-summary">
                    <p class="questions-preview-title">Question ${index + 1} (${points} points)</p>
                    <p>${htmlspecialchars(text)}</p>
                    ${quizType !== 'Essay' ? `
                        <p><strong>Options:</strong></p>
                        ${optionsHTML}
                        <p><strong>Correct Answer:</strong> ${htmlspecialchars(correctOption)}</p>
                    ` : ''}
                </div>
            `;
        });

        const summaryContent = `
            <table class="summary-table">
                <tr>
                    <td class="summary-label">Quiz Title</td>
                    <td class="summary-value">${htmlspecialchars(title)}</td>
                </tr>
                <tr>
                    <td class="summary-label">Description</td>
                    <td class="summary-value">${htmlspecialchars(description)}</td>
                </tr>
                <tr>
                    <td class="summary-label">Quiz Type</td>
                    <td class="summary-value">${htmlspecialchars(quizType)}</td>
                </tr>
                <tr>
                    <td class="summary-label">Due Date</td>
                    <td class="summary-value">${dueDate ? formatDate(new Date(dueDate)) : 'Not set'}</td>
                </tr>
                <tr>
                    <td class="summary-label">Release Date</td>
                    <td class="summary-value">${releaseDate ? formatDate(new Date(releaseDate)) : 'Not set'}</td>
                </tr>
            </table>
            <h3 style="font-weight: 600; color: var(--dark); margin-bottom: 1rem;">Questions</h3>
            ${questionsHTML}
        `;

        document.getElementById('summaryContent').innerHTML = summaryContent;
        document.getElementById('confirmationModal').classList.add('show');
    }

    // Close confirmation modal
    function closeConfirmation() {
        document.getElementById('confirmationModal').classList.remove('show');
    }

    // Submit form
    function submitForm() {
        quizForm.submit();
    }

    // Initialize questions from database
    function initializeQuestions() {
        const initialQuestions = <?php echo json_encode($questions); ?>;
        initialQuestions.forEach(question => {
            addQuestion({
                questionID: question.questionID,
                questionText: question.questionText,
                option1: question.option1,
                option2: question.option2,
                option3: question.option3,
                option4: question.option4,
                correctOption: question.correctOption,
                points: question.points
            });
        });
    }

    // Event listeners for navigation buttons
    document.getElementById('step1Continue').addEventListener('click', () => nextStep(2));
    document.getElementById('step2Back').addEventListener('click', () => prevStep(1));
    document.getElementById('step2Continue').addEventListener('click', () => validateStep2AndNext(3));
    document.getElementById('step3Back').addEventListener('click', () => prevStep(2));
    document.getElementById('step3Continue').addEventListener('click', () => validateStep3AndNext(4));
    document.getElementById('step4Back').addEventListener('click', () => prevStep(3));
    document.getElementById('step4Continue').addEventListener('click', validateStep4AndNext);

    // Initialize form
    window.addEventListener('DOMContentLoaded', () => {
        initializeQuestions();
        updateStepIndicators();
        updateProgressVisibility();

        // Set initial due date display
        const dueDate = document.getElementById('dueDate');
        const dueDateDisplay = document.getElementById('dueDateDisplay');
        if (dueDate.value && dueDateDisplay) {
            dueDateDisplay.value = formatDate(new Date(dueDate.value));
        }
    });

    </script>
</body>
</html>