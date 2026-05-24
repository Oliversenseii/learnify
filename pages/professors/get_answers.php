<?php
require_once '../../config/db_connection.php';

// Set headers to ensure JSON response
header('Content-Type: application/json; charset=UTF-8');

// Validate input parameters
if (!isset($_POST['quizID']) || !filter_var($_POST['quizID'], FILTER_VALIDATE_INT) ||
    !isset($_POST['studentID']) || !filter_var($_POST['studentID'], FILTER_VALIDATE_INT)) {
    echo json_encode(['error' => 'Invalid quiz or student ID']);
    exit;
}

$quizID = (int)$_POST['quizID'];
$studentID = (int)$_POST['studentID'];

try {
    // Fetch answers with question text, options, and correctness
    $stmt = $dbConnection->prepare("
        SELECT q.questionText, qa.answerText, qa.isCorrect, q.option1, q.option2, q.option3, q.option4, q.correctOption
        FROM quiz_answers qa
        JOIN questions q ON qa.questionID = q.questionID
        WHERE qa.quizID = :quizID 
        AND qa.studentID = :studentID 
        AND qa.archived = 0 
        AND q.archived = 0
        ORDER BY q.questionID
    ");
    $stmt->bindParam(':quizID', $quizID, PDO::PARAM_INT);
    $stmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
    $stmt->execute();
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process answers to include letter and option text
    $result = [];
    $optionMap = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D'];
    foreach ($answers as $answer) {
        $answerNum = (int)$answer['answerText'];
        $answerLetter = isset($optionMap[$answerNum]) ? $optionMap[$answerNum] : 'N/A';
        $answerText = $answerNum >= 1 && $answerNum <= 4 ? $answer["option$answerNum"] : ($answer['answerText'] ?: 'No response');
        $isCorrect = $answer['isCorrect'] !== null ? $answer['isCorrect'] : ($answerNum == $answer['correctOption'] ? 1 : 0);
        $result[] = [
            'questionText' => $answer['questionText'],
            'answerLetter' => $answerLetter,
            'answerText' => $answerText,
            'isCorrect' => $isCorrect
        ];
    }

    // Return answers as JSON
    if (empty($result)) {
        echo json_encode(['error' => 'No answers submitted for this student']);
    } else {
        echo json_encode($result);
    }
} catch (PDOException $e) {
    error_log("Database error in get_answers.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in get_answers.php: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred']);
}
?>