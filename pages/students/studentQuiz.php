<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';
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

// Validate teacherSectionID
if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section ID.";
    header("Location: studentDash.php");
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];

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

    // Verify student is enrolled
    $checkStmt = $dbConnection->prepare("SELECT ts.sectionID, s.sectionName, s.sectionCode, sub.subjectName, sub.subjectCode 
                                         FROM teacher_section ts 
                                         JOIN sections s ON ts.sectionID = s.sectionID 
                                         JOIN subjects sub ON ts.subjectID = sub.subjectID 
                                         JOIN student_section ss ON ts.sectionID = ss.sectionID 
                                         WHERE ts.teacherSectionID = :teacherSectionID AND ss.userID = :userID 
                                         AND ts.archived = 0 AND ss.archived = 0 AND s.archived = 0 AND sub.archived = 0 AND ss.status = 'Enrolled'");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->execute();
    $section = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        $_SESSION['error_message'] = "You are not enrolled in this section.";
        header("Location: studentDash.php");
        exit;
    }

    // Handle quiz submission
    if (isset($_POST['submitQuiz']) && isset($_POST['quizID'])) {
        $quizID = filter_var($_POST['quizID'], FILTER_VALIDATE_INT);
        $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : [];
        $currentDateTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');

        $dbConnection->beginTransaction();

        try {
            $checkQuizStmt = $dbConnection->prepare("SELECT quizID, quizType, dueDate, title, releaseDate 
                                                    FROM quizzes 
                                                    WHERE quizID = :quizID AND teacherSectionID = :teacherSectionID AND archived = 0 
                                                    AND (releaseDate IS NULL OR releaseDate <= :currentDateTime)");
            $checkQuizStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
            $checkQuizStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
            $checkQuizStmt->bindParam(':currentDateTime', $formattedDateTime, PDO::PARAM_STR);
            $checkQuizStmt->execute();
            $quiz = $checkQuizStmt->fetch(PDO::FETCH_ASSOC);

            if (!$quiz) {
                throw new Exception("Invalid quiz, quiz not found, or quiz not yet released.");
            }

            $checkSubmissionStmt = $dbConnection->prepare("SELECT answerID FROM quiz_answers WHERE quizID = :quizID AND studentID = :studentID AND archived = 0 LIMIT 1");
            $checkSubmissionStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
            $checkSubmissionStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
            $checkSubmissionStmt->execute();
            if ($checkSubmissionStmt->rowCount() > 0) {
                throw new Exception("You have already submitted this quiz.");
            }

            if ($quiz['dueDate']) {
                $currentDateTimeLA = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
                $dueDateTime = new DateTime($quiz['dueDate'], new DateTimeZone('America/Los_Angeles'));
                if ($currentDateTimeLA > $dueDateTime) {
                    throw new Exception("This quiz is past its due date (" . $dueDateTime->format('M j, Y g:i A') . ") and cannot be submitted.");
                }
            }

            $totalScore = 0;
            $maxScore = 0;

            if ($quiz['quizType'] !== 'Essay') {
                $questionStmt = $dbConnection->prepare("SELECT questionID, correctOption, points FROM questions WHERE quizID = :quizID AND archived = 0");
                $questionStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                $questionStmt->execute();
                $questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($questions)) {
                    throw new Exception("No questions found for this quiz.");
                }

                $optionOrders = isset($_SESSION['quiz_option_order'][$quizID]) ? $_SESSION['quiz_option_order'][$quizID] : [];

                foreach ($questions as $question) {
                    $questionID = $question['questionID'];
                    $answerText = isset($answers[$questionID]) ? trim($answers[$questionID]) : null;
                    $isCorrect = 0;

                    if ($answerText !== null && isset($optionOrders[$questionID])) {
                        $shuffledIndex = (int)$answerText;
                        $originalIndex = $optionOrders[$questionID][$shuffledIndex - 1];
                        $isCorrect = ($originalIndex + 1 == $question['correctOption']) ? 1 : 0;
                    }

                    $points = (int)$question['points'];
                    $maxScore += $points;
                    if ($isCorrect) $totalScore += $points;

                    $answerStmt = $dbConnection->prepare("INSERT INTO quiz_answers (quizID, studentID, questionID, answerText, isCorrect, submittedDate) 
                                                         VALUES (:quizID, :studentID, :questionID, :answerText, :isCorrect, NOW())");
                    $answerStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                    $answerStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
                    $answerStmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                    $answerStmt->bindParam(':answerText', $answerText);
                    $answerStmt->bindParam(':isCorrect', $isCorrect, PDO::PARAM_INT);
                    $answerStmt->execute();
                }

                $scoreStmt = $dbConnection->prepare("INSERT INTO quiz_scores (quizID, studentID, totalScore, maxScore, recordedDate, approved) 
                                                    VALUES (:quizID, :studentID, :totalScore, :maxScore, NOW(), 0)");
                $scoreStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                $scoreStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
                $scoreStmt->bindParam(':totalScore', $totalScore, PDO::PARAM_INT);
                $scoreStmt->bindParam(':maxScore', $maxScore, PDO::PARAM_INT);
                $scoreStmt->execute();

                $_SESSION['success_message'] = "Quiz '{$quiz['title']}' submitted successfully! Awaiting approval.";
            } else {
                $questionStmt = $dbConnection->prepare("SELECT questionID, points FROM questions WHERE quizID = :quizID AND archived = 0");
                $questionStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                $questionStmt->execute();
                $questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($questions as $question) {
                    $questionID = $question['questionID'];
                    $answerText = isset($answers[$questionID]) ? trim($answers[$questionID]) : '';
                    $maxScore += (int)$question['points'];

                    if ($answerText === '') {
                        throw new Exception("Answer for question ID: $questionID is empty.");
                    }

                    $answerStmt = $dbConnection->prepare("INSERT INTO quiz_answers (quizID, studentID, questionID, answerText, isCorrect, submittedDate) 
                                                         VALUES (:quizID, :studentID, :questionID, :answerText, NULL, NOW())");
                    $answerStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                    $answerStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
                    $answerStmt->bindParam(':questionID', $questionID, PDO::PARAM_INT);
                    $answerStmt->bindParam(':answerText', $answerText);
                    $answerStmt->execute();
                }

                $scoreStmt = $dbConnection->prepare("INSERT INTO quiz_scores (quizID, studentID, totalScore, maxScore, recordedDate, approved) 
                                                    VALUES (:quizID, :studentID, 0, :maxScore, NOW(), 0)");
                $scoreStmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
                $scoreStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
                $scoreStmt->bindParam(':maxScore', $maxScore, PDO::PARAM_INT);
                $scoreStmt->execute();

                $_SESSION['success_message'] = "Essay quiz '{$quiz['title']}' submitted successfully. Awaiting grading.";
            }

            $dbConnection->commit();
            unset($_SESSION['quiz_question_order'][$quizID]);
            unset($_SESSION['quiz_option_order'][$quizID]);
        } catch (Exception $e) {
            $dbConnection->rollBack();
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: studentQuiz.php?teacherSectionID=$teacherSectionID");
            exit;
        }

        header("Location: studentQuiz.php?teacherSectionID=$teacherSectionID");
        exit;
    }

    // Clear old shuffle data
    if (isset($_SESSION['quiz_question_order'])) unset($_SESSION['quiz_question_order']);
    if (isset($_SESSION['quiz_option_order'])) unset($_SESSION['quiz_option_order']);

    // === GET CURRENT PH TIME ===
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $phTime = $now->format('Y-m-d H:i:s');

    // === FETCH RELEASED QUIZZES ONLY ===
    $quizStmt = $dbConnection->prepare("
        SELECT q.quizID, q.title, q.description, q.quizType, q.createdDate, q.dueDate, q.releaseDate, 
               qs.totalScore, qs.maxScore, qs.recordedDate, qs.approved,
               (SELECT COUNT(*) FROM questions WHERE quizID = q.quizID AND archived = 0) as questionCount
        FROM quizzes q 
        LEFT JOIN quiz_scores qs ON q.quizID = qs.quizID AND qs.studentID = :userID AND qs.archived = 0 
        WHERE q.teacherSectionID = :teacherSectionID 
          AND q.archived = 0 
          AND (q.releaseDate IS NULL OR q.releaseDate <= :currentTime)
        ORDER BY q.createdDate DESC
    ");
    $quizStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $quizStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $quizStmt->bindParam(':currentTime', $phTime, PDO::PARAM_STR);
    $quizStmt->execute();


    $quizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);

    // === FETCH UPCOMING QUIZZES ===
    $upcomingStmt = $dbConnection->prepare("
        SELECT title, releaseDate 
        FROM quizzes 
        WHERE teacherSectionID = :teacherSectionID 
          AND archived = 0 
          AND releaseDate IS NOT NULL 
          AND releaseDate > :currentTime
        ORDER BY releaseDate ASC
    ");
    $upcomingStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $upcomingStmt->bindParam(':currentTime', $phTime, PDO::PARAM_STR);
    $upcomingStmt->execute();
    $upcomingQuizzes = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error. Please try again.";
}

require_once './get_notification_count.php';
$unreadCount = $userID ? getUnreadAnnouncementCount($dbConnection, $userID) : 0;
if (!isset($_SESSION['announcements_viewed'])) {
    $_SESSION['announcements_viewed'] = false;
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
    <link rel="stylesheet" href="./css/scrollbar.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <script src="./logout.js"></script>
    <title>Learnify - Student Quiz</title>
    <style>
        :root {
            --white: #ffffff;
            --light-grey: #f5f5f5;
            --grey: #dadce0;
            --dark-grey: #5f6368;
            --blue: #1a73e8;
            --dark: #202124;
            --green: #34a853;
            --red: #d93025;

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
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .admin-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            min-width: 700px;
        }

        .admin-table th,
        .admin-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--grey);
        }

        .admin-table th {
            background: var(--blue);
            color: var(--white);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .admin-table td {
            color: var(--dark);
            vertical-align: middle;
        }

        .admin-table tr.quiz-row {
            cursor: pointer;
            transition: background-color 0.2s ease;
            position: relative;
        }

        .admin-table tr.quiz-row:nth-child(odd) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        } 

        .admin-table tr.quiz-row:nth-child(odd):hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .admin-table tr.quiz-row:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
            transition: background 0.3s ease; 
        }

        .admin-table tr.quiz-row.highlighted {
           background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .quiz-row::after {
            content: '\e994';
            font-family: 'boxicons' !important;
            font-size: 1rem;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s ease;
            color: var(--dark-grey);
        }

        .quiz-row.highlighted::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: clamp(0.9rem, 3vw, 1rem);
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
        }

        .status-submitted {
            background: var(--green);
            color: var(--white);
        }

        .status-not-complete {
            background: var(--red);
            color: var(--white);
        }

        .status-past-due {
            background: var(--red);
            color: var(--white);
        }

        .take-btn {
            background: var(--blue);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            text-transform: uppercase;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s ease;
            font-size: clamp(0.9rem, 3rem, 1rem);
        }

        .take-btn:hover {
            background: #1557b0;
        }

        .take-btn:disabled {
            background: var(--grey);
            cursor: not-allowed;
            display: none;
        }

        #modal-content {
            background: var(--light);
            width: 100%;
            height: 100%;
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            background: #1a73e8;
            padding: 16px;
            border-bottom: 1px solid var(--grey);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1001;
        }

        .modal-header h3 {
            margin: 0;
            font-size: clamp(1.5rem, 3rem, 2rem);
            font-weight: 500;
            color: white;
        }

        .modal-header .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: clamp(1.5rem, 3rem, 2rem);
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .modal-header .close-btn:hover {
            color: var(--red);
        }

        .modal-body {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            align-items: center;
        }

        .quiz-info {
            width: 100%;
            max-width: 800px;
            padding: 16px;
            background: var(--grey);
            border-radius: 4px;
        }

        .quiz-info p {
            margin: 0;
            font-size: clamp(1.1rem, 3rem, 1.2rem);
            color: var(--dark-grey);
        }

        .quiz-content {
            width: 100%;
            max-width: 1000px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .progress-bar {
            width: 100%;
            background: whitesmoke;
            border-radius: 8px;
            height: 12px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            position: relative;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .progress-bar .progress {
            background: linear-gradient(90deg, var(--blue), #4a91ff);
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
            position: relative;
        }

        .progress-bar .progress::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 10px;
            height: 100%;
            background: linear-gradient(to right, rgba(255, 255, 255, 0.2), transparent);
        }

        .progress-bar .progress-text {
            position: absolute;
            top: -20px;
            right: 0;
            font-size: clamp(1.1rem, 3rem, 1.2rem);
            color: var(--dark-grey);
            font-weight: 500;
        }

        .quiz-form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .question-block {
            display: none;
            width: 100%;
            padding: 16px;
            background: var(--light);
            border-radius: 4px;
        }

        .question-block.active {
            display: block;
        }

        .question-block fieldset {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 16px;
            margin: 0;
        }

        .question-block legend {
            font-size: clamp(1.1rem, 3rem, 1.2rem);
            font-weight: 500;
            color: var(--blue);
            padding: 0 8px;
        }

        .question-block h4 {
            margin: 0 0 1rem;
            font-size: clamp(1.1rem, 3rem, 2rem);
            font-weight: 500;
            color: var(--dark);
            text-align: center;
        }

        .question-block h4 em {
            color: green;
            font-weight: 700;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .option-box {
            background: var(--grey);
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 80px;
            font-size: clamp(1.3rem, 3rem, 1.6rem);
            color: var(--dark);
        }

        .option-box:hover {
            border-color: var(--blue);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .option-box.selected {
            border-color: var(--blue);
            background-color: var(--blue);
            color: white;
        }

        .option-box input[type="radio"] {
            display: none;
        }

        .error {
            color: var(--red);
            font-size: 0.875rem;
            text-align: center;
            margin-top: 0.5rem;
            display: none;
        }

        .modal-footer {
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--grey);
            position: sticky;
            bottom: 0;
            z-index: 1001;
        }

        .tab-nav {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            background: var(--light);
            color: var(--dark);
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-size: clamp(1.1rem, 3rem, 1.2rem);
            min-width: 40px;
            text-align: center;
        }

        .tab-btn.active {
            background: var(--blue);
            color: var(--white);
        }

        .tab-btn.answered {
            background: var(--green);
            color: var(--white);
        }

        .tab-btn:hover:not(.active):not(.answered) {
            background-color: white;
            color: #1a73e8;
            border: 1px solid #1a73e8;
        }

        .modal-footer button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: clamp(0.9rem, 3rem, 1rem);
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .submit-btn {
            background: var(--blue);
            color: var(--white);
            display: none;
            margin-left: 3px;
        }

        .submit-btn.visible {
            display: inline-block;
        }

        .submit-btn:hover {
            background: #1557b0;
        }

        .cancel-btn {
            background: red;
            color: white;
            margin-left: 5px;
            margin-bottom: 5px;
        }

        .cancel-btn:hover {
            background: white;
            color: red;
            border: 1px solid red;
        }

        .confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 100000000000000000002;
            justify-content: center;
            align-items: center;
        }

        .confirm-modal-content {
            background: var(--light);
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
        }

        .confirm-modal-header {
            padding: 16px;
            border-bottom: 1px solid #ccc;
        }

        .confirm-modal-header h3 {
            margin: 0;
            font-size: clamp(1.25rem, 3vw, 2rem);
            font-weight: 500;
            color: var(--dark);
        }

        .confirm-modal-body {
            padding: 16px;
            color: var(--dark-grey);
            font-size: clamp(1.2rem, 3vw, 1.6rem);
        }

        .confirm-modal-footer {
            padding: 16px;
            border-top: 1px solid #ccc;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .confirm-modal-footer button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: clamp(1.1rem, 3rem, 1.2rem);
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .confirm-modal-footer .cancel-btn {
            background: var(--white);
            color: var(--blue);
            border: 1px solid var(--blue);
        }

        .confirm-modal-footer .cancel-btn:hover {
            background: var(--light-grey);
        }

        .confirm-modal-footer .turn-in-btn {
            background: var(--green);
            color: var(--white);
        }

        .confirm-modal-footer .turn-in-btn:hover {
            background: #2d8c46;
        }

        .success-notification, .error-notification {
            background: var(--green);
            color: var(--white);
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 4px;
            text-align: center;
            font-weight: 500;
            width: 100%;
            margin: 0 auto;
        }

        .error-notification {
            background: var(--red);
        }

        .collapsible-content {
            display: none;
            background: var(--grey);
            padding: 1rem;
            border-radius: 4px;
            margin: 0.5rem 1rem;
            transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
            overflow: hidden;
            opacity: 0;
            border: 1px solid var(--grey);
        }

        .collapsible-content.active {
            display: block;
            opacity: 1;
        }

        .collapsible-content h4 {
            margin: 0 0 1rem;
            color: var(--dark);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 500;
        }

        .collapsible-content p {
            margin: 0.5rem 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark-grey);
        }

        .view-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            margin-top: 1rem;
        }

        .view-table th,
        .view-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--grey);
            text-align: left;
        }

        .view-table th {
            background: var(--blue);
            color: var(--white);
            font-weight: 500;
        }

        .view-table td {
            color: var(--dark);
        }

        .back-btn {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--dark);
            font-size: clamp(1.1rem, 3rem, 1.2rem);
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: var(--light-grey);
            color: var(--blue);
        }

        .notification-badge {
            background: var(--red);
            color: var(--white);
            border-radius: 50%;
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        @media screen and (max-width: 768px) {
            .admin-table {
                margin: 0;
                padding: 0;
                margin-top: 10px;
            }

            .options-grid {
                grid-template-columns: 1fr;
            }

            .confirm-modal-content {
                max-width: 90%;
            }
            .question-block h4 {
                font-size: clamp(1.2rem, 3rem, 1.5rem);
            }
            .option-box {
                font-size: clamp(1rem, 3rem, 1.2rem);
            }
        }

        @media screen and (max-width: 480px) {
     
        }

        /* Pending Tasks Badge in Header */
        .dashboard-header-badge {
            background: #ED8936;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.55rem;
            font-size: 0.9rem;
            font-weight: 600;
            min-width: 15px;
            text-align: center;
            margin-left: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        textarea {
            background-color: var(--grey);
            font-size: clamp(1.1rem, 3vw, 1.4rem);
            color: var(--dark);
            height: auto;
        }

        .back-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
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
            font-size: clamp(1.1rem, 3vw, 1.3rem);
        }

        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
             --transition: all 0.3s ease;
             --white: #FFFFFF;
             --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* PROJECT MANAGEMENT UPCOMING QUIZZES */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }

        .kanban-card {
            background: var(--light);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            border: none;
        }

        .kanban-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 35px rgba(0,0,0,0.18);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 1.2rem 1.5rem;
            color: white;
        }

        .card-header h4 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        .release-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1a73e8;
            font-weight: 600;
            font-size: 1rem;
        }

        .countdown-badge {
            display: inline-block;
            background: #d93025;
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 700;
            margin-top: 0.8rem;
        }

        .upcoming-quizzes h3 {
            color: var(--dark);
        }
        .upcoming-quizzes p {
            color: #666;
        }

        /* FULLSCREEN MODAL */
        .fullscreen-modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100vw;
            height: 100vh;
            background: #f8faff;
            z-index: 999999;
            flex-direction: column;
        }

        .fullscreen-modal .modal-content {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .fullscreen-modal .modal-header {
            background: #1a73e8;
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .fullscreen-modal .modal-body {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: #f8faff;
        }

        .fullscreen-modal .modal-footer {
            padding: 1.5rem 2rem;
            background: white;
            border-top: 1px solid #dadce0;
            position: sticky;
            bottom: 0;
            z-index: 1001;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .kanban-board { grid-template-columns: 1fr; }
            .fullscreen-modal .modal-header,
            .fullscreen-modal .modal-footer { padding: 1rem; }
        }
        
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <?php require_once './dashboard_nav_item.php' ?>
            <li><a href="./announcements.php?viewed=true"><i class='bx bxs-bell'></i><span class="text">Announcements</span></a></li>
            <li><a href="./calendar.php"><i class='bx bxs-calendar'></i><span class="text">Calendar</span></a></li>
            <li><a href="./game.php"><i class='bx bxs-game'></i><span class="text">Games</span></a></li>
        </ul>
        <ul class="side-menu">
            <li><a href="./settings.php"><i class='bx bxs-cog'></i><span class="text">Settings</span></a></li>
            <li><a href="javascript:void(0);" class="logout" onclick="showLogoutModal()"><i class='bx bxs-log-out-circle'></i><span class="text">Logout</span></a></li>
        </ul>
    </section>

    <?php require_once './view/modal.php' ?>

    <section id="content">
        <nav>
            <i class='bx bx-menu'></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search Teachers and Students" required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile">
                <div><p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p><small><?php echo htmlspecialchars($_SESSION['userType']); ?></small></div>
            </a>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Quizzes - <?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h1>
                    <ul class="breadcrumb">
                        <li><a href="./studentDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./studentDash.php">Class Details</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Quizzes</a></li>
                    </ul>
                </div>
                <a href="./studentDash.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <div class="admin-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Points</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($quizzes)): ?>
                            <?php foreach ($quizzes as $index => $quiz): ?>
                                <?php
                                $submissionStmt = $dbConnection->prepare("SELECT answerID FROM quiz_answers WHERE quizID = :quizID AND studentID = :studentID AND archived = 0");
                                $submissionStmt->bindParam(':quizID', $quiz['quizID'], PDO::PARAM_INT);
                                $submissionStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
                                $submissionStmt->execute();
                                $hasTaken = $submissionStmt->rowCount() > 0;

                                $currentDateTimeLA = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
                                $dueDate = $quiz['dueDate'] ? new DateTime($quiz['dueDate'], new DateTimeZone('America/Los_Angeles')) : null;
                                $isPastDue = $dueDate && $currentDateTimeLA > $dueDate;

                                $questionStmt = $dbConnection->prepare("SELECT questionID, questionText, option1, option2, option3, option4, points, correctOption 
                                                                       FROM questions WHERE quizID = :quizID AND archived = 0 ORDER BY questionID");
                                $questionStmt->bindParam(':quizID', $quiz['quizID'], PDO::PARAM_INT);
                                $questionStmt->execute();
                                $questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);

                                if (!$hasTaken && !$isPastDue) {
                                    $questionOrder = array_keys($questions);
                                    shuffle($questionOrder);
                                    $_SESSION['quiz_question_order'][$quiz['quizID']] = $questionOrder;
                                    $shuffledQuestions = [];
                                    foreach ($questionOrder as $qIndex) {
                                        $shuffledQuestions[] = $questions[$qIndex];
                                    }
                                    $questions = $shuffledQuestions;

                                    if ($quiz['quizType'] === 'Multiple Choice' || $quiz['quizType'] === 'True/False') {
                                        $_SESSION['quiz_option_order'][$quiz['quizID']] = [];
                                        foreach ($questions as &$question) {
                                            if ($quiz['quizType'] === 'Multiple Choice') {
                                                $options = [1 => $question['option1'], 2 => $question['option2'], 3 => $question['option3'], 4 => $question['option4']];
                                                $indices = [0,1,2,3];
                                                shuffle($indices);
                                                $_SESSION['quiz_option_order'][$quiz['quizID']][$question['questionID']] = $indices;
                                                $shuffled = [];
                                                foreach ($indices as $i => $orig) {
                                                    $shuffled[$i + 1] = $options[$orig + 1];
                                                }
                                                $question['option1'] = $shuffled[1];
                                                $question['option2'] = $shuffled[2];
                                                $question['option3'] = $shuffled[3];
                                                $question['option4'] = $shuffled[4];
                                            } elseif ($quiz['quizType'] === 'True/False') {
                                                $options = [1 => $question['option1'] ?: 'True', 2 => $question['option2'] ?: 'False'];
                                                $indices = [0,1];
                                                shuffle($indices);
                                                $_SESSION['quiz_option_order'][$quiz['quizID']][$question['questionID']] = $indices;
                                                $shuffled = [];
                                                foreach ($indices as $i => $orig) {
                                                    $shuffled[$i + 1] = $options[$orig + 1];
                                                }
                                                $question['option1'] = $shuffled[1];
                                                $question['option2'] = $shuffled[2];
                                            }
                                        }
                                        unset($question);
                                    }
                                }

                                $answerStmt = $dbConnection->prepare("SELECT questionID, answerText, isCorrect FROM quiz_answers WHERE quizID = :quizID AND studentID = :studentID AND archived = 0");
                                $answerStmt->bindParam(':quizID', $quiz['quizID'], PDO::PARAM_INT);
                                $answerStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
                                $answerStmt->execute();
                                $answers = $answerStmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <tr class="quiz-row" onclick="toggleCollapse('collapsible-<?php echo $quiz['quizID']; ?>', this)">
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                    <td><?php echo htmlspecialchars($quiz['quizType']); ?></td>
                                    <td>
                                        <?php 
                                        if ($hasTaken) {
                                            echo $quiz['approved'] == 1 && $quiz['totalScore'] !== null ? htmlspecialchars($quiz['totalScore'] . '/' . $quiz['maxScore']) : 
                                                 ($quiz['quizType'] === 'Essay' ? 'Pending Grading' : 'Pending Approval');
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $hasTaken ? 'status-submitted' : ($isPastDue ? 'status-past-due' : 'status-not-complete'); ?>">
                                            <?php echo $hasTaken ? 'Submitted' : ($isPastDue ? 'Past Due' : 'Not Complete'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!$hasTaken && !$isPastDue): ?>
                                            <button class="take-btn" onclick="openModal('take-quiz-modal-<?php echo $quiz['quizID']; ?>'); event.stopPropagation();">
                                                Take Quiz
                                            </button>
                                        <?php elseif ($isPastDue): ?>
                                            <button class="take-btn" disabled>Past Due</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="9">
                                        <div id="collapsible-<?php echo $quiz['quizID']; ?>" class="collapsible-content">
                                            <h4><?php echo htmlspecialchars($quiz['title']); ?></h4>
                                            <p><strong>Description:</strong> <?php echo $quiz['description'] ? htmlspecialchars($quiz['description']) : 'No description'; ?></p>
                                            <p><strong>Type:</strong> <?php echo htmlspecialchars($quiz['quizType']); ?></p>
                                            <p><strong>Created:</strong> <?php echo date('F j, Y', strtotime($quiz['createdDate'])); ?></p>
                                            <p><strong>Release Date:</strong> <?php echo $quiz['releaseDate'] ? date('F j, Y, g:i A', strtotime($quiz['releaseDate'])) : 'Immediate'; ?></p>
                                            <p><strong>Due Date:</strong> <?php echo $quiz['dueDate'] ? date('F j, Y, g:i A', strtotime($quiz['dueDate'])) : 'Not set'; ?></p>
                                            <?php if ($hasTaken && $quiz['approved'] == 1 && !empty($questions)): ?>
                                                <h4>Your Answers</h4>
                                                <table class="view-table">
                                                    <thead><tr><th>#</th><th>Question</th><th>Your Answer</th><?php if ($quiz['quizType'] !== 'Essay'): ?><th>Correct</th><?php endif; ?><th>Points</th></tr></thead>
                                                    <tbody>
                                                        <?php foreach ($questions as $qIdx => $q): 
                                                            $ans = array_filter($answers, fn($a) => $a['questionID'] == $q['questionID']);
                                                            $ans = reset($ans);
                                                        ?>
                                                            <tr>
                                                                <td><?php echo $qIdx + 1; ?></td>
                                                                <td><?php echo htmlspecialchars($q['questionText']); ?></td>
                                                                <td>
                                                                    <?php 
                                                                    if ($quiz['quizType'] === 'Multiple Choice') {
                                                                        $opts = [$q['option1'], $q['option2'], $q['option3'], $q['option4']];
                                                                        echo $ans['answerText'] ? htmlspecialchars($opts[$ans['answerText'] - 1]) : 'No answer';
                                                                    } elseif ($quiz['quizType'] === 'True/False') {
                                                                        $opts = [$q['option1'] ?: 'True', $q['option2'] ?: 'False'];
                                                                        echo $ans['answerText'] ? htmlspecialchars($opts[$ans['answerText'] - 1]) : 'No answer';
                                                                    } else {
                                                                        echo $ans['answerText'] ? htmlspecialchars($ans['answerText']) : 'No answer';
                                                                    }
                                                                    ?>
                                                                </td>
                                                                <?php if ($quiz['quizType'] !== 'Essay'): ?>
                                                                    <td><?php echo $ans['isCorrect'] ? 'Correct' : 'Incorrect'; ?></td>
                                                                <?php endif; ?>
                                                                <td><?php echo $q['points']; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php else: ?>
                                                <p><?php echo $hasTaken ? 'Pending approval...' : 'Not taken yet.'; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- TAKE QUIZ MODAL -->
                                <?php if (!$hasTaken && !$isPastDue): ?>
                                <div id="take-quiz-modal-<?php echo $quiz['quizID']; ?>" class="modal fullscreen-modal">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                                            <button class="close-btn" onclick="closeModal('take-quiz-modal-<?php echo $quiz['quizID']; ?>')">
                                                <i class='bx bx-x'></i>
                                            </button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="quiz-info">
                                                <p><?php echo $quiz['description'] ? htmlspecialchars($quiz['description']) : 'No description provided.'; ?></p>
                                            </div>

                                            <div class="progress-bar">
                                                <div class="progress"></div>
                                                <span class="progress-text">0%</span>
                                            </div>

                                            <form class="quiz-form" method="POST" action="">
                                                <input type="hidden" name="quizID" value="<?php echo $quiz['quizID']; ?>">
                                                <input type="hidden" name="submitQuiz" value="1">

                                                <?php foreach ($questions as $qIdx => $q): ?>
                                                <div class="question-block <?php echo $qIdx === 0 ? 'active' : ''; ?>" data-question-id="<?php echo $q['questionID']; ?>">
                                                    <fieldset>
                                                        <legend>Question <?php echo $qIdx + 1; ?> of <?php echo count($questions); ?></legend>
                                                        <h4><?php echo htmlspecialchars($q['questionText']); ?> <em>(<?php echo $q['points']; ?> pts)</em></h4>

                                                        <?php if ($quiz['quizType'] === 'Multiple Choice'): ?>
                                                            <div class="options-grid">
                                                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                                                    <div class="option-box" onclick="selectOption(this, '<?php echo $q['questionID']; ?>', '<?php echo $i; ?>')">
                                                                        <input type="radio" name="answers[<?php echo $q['questionID']; ?>]" value="<?php echo $i; ?>">
                                                                        <span><?php echo htmlspecialchars($q["option$i"]); ?></span>
                                                                    </div>
                                                                <?php endfor; ?>
                                                            </div>
                                                        <?php elseif ($quiz['quizType'] === 'True/False'): ?>
                                                            <div class="options-grid">
                                                                <div class="option-box" onclick="selectOption(this, '<?php echo $q['questionID']; ?>', '1')">
                                                                    <input type="radio" name="answers[<?php echo $q['questionID']; ?>]" value="1">
                                                                    <span><?php echo htmlspecialchars($q['option1'] ?: 'True'); ?></span>
                                                                </div>
                                                                <div class="option-box" onclick="selectOption(this, '<?php echo $q['questionID']; ?>', '2')">
                                                                    <input type="radio" name="answers[<?php echo $q['questionID']; ?>]" value="2">
                                                                    <span><?php echo htmlspecialchars($q['option2'] ?: 'False'); ?></span>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <textarea name="answers[<?php echo $q['questionID']; ?>]" rows="6" placeholder="Type your answer here..." required></textarea>
                                                        <?php endif; ?>

                                                        <p class="error">Please answer this question.</p>
                                                    </fieldset>
                                                </div>
                                                <?php endforeach; ?>
                                            </form>
                                        </div>

                                        <div class="modal-footer">
                                            <div class="tab-nav">
                                                <?php foreach ($questions as $i => $q): ?>
                                                    <button type="button" class="tab-btn <?php echo $i === 0 ? 'active' : ''; ?>" 
                                                            onclick="switchTab(<?php echo $i; ?>, 'take-quiz-modal-<?php echo $quiz['quizID']; ?>')">
                                                        <?php echo $i + 1; ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <div>
                                                <button type="button" class="cancel-btn" onclick="closeModal('take-quiz-modal-<?php echo $quiz['quizID']; ?>')">
                                                    Cancel
                                                </button>
                                                <button type="button" class="submit-btn" onclick="showConfirmModal()">
                                                    Submit Quiz
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9">No quizzes available at this time.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- UPCOMING QUIZZES -->
            <?php if (!empty($upcomingQuizzes)): ?>
            <div class="upcoming-quizzes">
                <h3><i class='bx bx-task'></i> Upcoming Quizzes</h3>
                <p>These will appear automatically when released (Philippine Time):</p>
                
                <div class="kanban-board">
                    <?php foreach ($upcomingQuizzes as $u): 
                        $release = new DateTime($u['releaseDate'], new DateTimeZone('Asia/Manila'));
                        $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
                        $interval = $now->diff($release);
                        $days = $interval->days;
                        $hours = $interval->h;
                        $countdown = $days > 0 ? "$days days" : ($hours > 0 ? "$hours hours" : "less than an hour");
                    ?>
                    <div class="kanban-card">
                        <div class="card-header">
                            <h4><?php echo htmlspecialchars($u['title']); ?></h4>
                        </div>
                        <div class="card-body">
                            <div class="release-info">
                                <i class='bx bx-calendar-event'></i>
                                <?php echo $release->format('F j, Y \a\t g:i A'); ?>
                            </div>
                            <div class="countdown-badge"><?php echo $countdown; ?> left</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <p style="margin-top: 1.5rem; font-style: italic; color: #666;">
                    <i class='bx bx-sync'></i> Page refreshes every minute
                </p>
            </div>
            <?php endif; ?>

            <div id="confirm-submit-modal" class="confirm-modal">
                <div class="confirm-modal-content">
                    <div class="confirm-modal-header"><h3>Turn in your work?</h3></div>
                    <div class="confirm-modal-body"><p>Are you sure? You cannot edit after submission.</p></div>
                    <div class="confirm-modal-footer">
                        <button class="cancel-btn" onclick="closeConfirmModal()">Cancel</button>
                        <button class="turn-in-btn" onclick="document.querySelector('.quiz-form').submit()">Turn in</button>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script>
        let currentForm = null;
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
            currentForm = document.getElementById(id).querySelector('.quiz-form');
            updateProgress(id);
            switchTab(0, id);
        }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function switchTab(i, modalId) {
            const modal = document.getElementById(modalId);
            modal.querySelectorAll('.question-block').forEach((q, idx) => q.classList.toggle('active', idx === i));
            modal.querySelectorAll('.tab-btn').forEach((t, idx) => t.classList.toggle('active', idx === i));
            updateProgress(modalId);
        }
        function selectOption(el, qid, val) {
            el.closest('.question-block').querySelectorAll('.option-box').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            el.querySelector('input').checked = true;
            updateProgress(el.closest('.modal').id);
        }
        function updateProgress(modalId) {
            const modal = document.getElementById(modalId);
            const blocks = modal.querySelectorAll('.question-block');
            const progress = modal.querySelector('.progress');
            const text = modal.querySelector('.progress-text');
            const submit = modal.querySelector('.submit-btn');
            let done = 0;
            blocks.forEach(b => {
                const answered = b.querySelector('input[type=radio]:checked') || (b.querySelector('textarea')?.value.trim());
                if (answered) done++;
                b.querySelector('.tab-btn')?.classList.toggle('answered', !!answered);
            });
            const pct = blocks.length ? (done / blocks.length) * 100 : 0;
            progress.style.width = pct + '%';
            text.textContent = Math.round(pct) + '%';
            submit.classList.toggle('visible', pct === 100);
        }
        function showConfirmModal() {
            let all = true;
            document.querySelectorAll('.question-block').forEach(q => {
                const ok = q.querySelector('input[type=radio]:checked') || q.querySelector('textarea')?.value.trim();
                q.querySelector('.error').style.display = ok ? 'none' : 'block';
                if (!ok) all = false;
            });
            if (!all) { alert('Please answer all questions.'); return; }
            document.getElementById('confirm-submit-modal').style.display = 'flex';
        }
        function closeConfirmModal() { document.getElementById('confirm-submit-modal').style.display = 'none'; }
        function toggleCollapse(id, row) {
            const content = document.getElementById(id);
            document.querySelectorAll('.collapsible-content').forEach(c => { if (c.id !== id) c.classList.remove('active'); });
            document.querySelectorAll('.quiz-row').forEach(r => r.classList.remove('highlighted'));
            content.classList.toggle('active');
            row.classList.toggle('highlighted');
            content.style.maxHeight = content.classList.contains('active') ? content.scrollHeight + 'px' : null;
        }
        window.onclick = e => {
            if (e.target.classList.contains('modal') || e.target.classList.contains('confirm-modal')) {
                e.target.style.display = 'none';
            }
        };
        document.addEventListener('input', e => {
            if (e.target.tagName === 'TEXTAREA') {
                const modal = e.target.closest('.modal');
                if (modal) updateProgress(modal.id);
            }
        });
        // Auto-refresh every 60 seconds
        setTimeout(() => location.reload(), 60000);
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>