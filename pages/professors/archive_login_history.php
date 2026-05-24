<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: settings.php");
    exit;
}

if (!isset($_SESSION['userID'])) {
    $_SESSION['error_message'] = "You must be logged in to archive login history.";
    header("Location: ../../index.php");
    exit;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false || $userID <= 0) {
    $_SESSION['error_message'] = "Invalid user ID.";
    header("Location: settings.php");
    exit;
}

try {
    $successCount = 0;
    $errorCount = 0;

    // Handle bulk archiving (history_ids[])
    if (isset($_POST['history_ids']) && is_array($_POST['history_ids']) && !empty($_POST['history_ids'])) {
        $history_ids = array_filter($_POST['history_ids'], function($id) {
            return filter_var($id, FILTER_VALIDATE_INT) !== false && $id > 0;
        });

        if (empty($history_ids)) {
            $_SESSION['error_message'] = "No valid history IDs provided.";
            header("Location: settings.php");
            exit;
        }

        foreach ($history_ids as $history_id) {
            // Verify each entry belongs to the user and is not archived
            $stmt = $dbConnection->prepare("SELECT userID FROM login_history WHERE history_id = :history_id AND archived = 0");
            $stmt->bindParam(':history_id', $history_id, PDO::PARAM_INT);
            $stmt->execute();
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($entry && $entry['userID'] == $userID) {
                $updateStmt = $dbConnection->prepare("UPDATE login_history SET archived = 1 WHERE history_id = :history_id");
                $updateStmt->bindParam(':history_id', $history_id, PDO::PARAM_INT);
                $updateStmt->execute();
                $successCount++;
            } else {
                $errorCount++;
            }
        }
    } else {
        $_SESSION['error_message'] = "No history entries selected for archiving.";
        header("Location: settings.php");
        exit;
    }

    // Set feedback message
    if ($successCount > 0 && $errorCount == 0) {
        $_SESSION['success_message'] = "$successCount login history " . ($successCount > 1 ? "entries" : "entry") . " archived successfully.";
    } elseif ($successCount > 0) {
        $_SESSION['success_message'] = "$successCount login history " . ($successCount > 1 ? "entries" : "entry") . " archived successfully, but $errorCount failed.";
    } else {
        $_SESSION['error_message'] = "Failed to archive any entries.";
    }
    header("Location: settings.php");
    exit;

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: Unable to archive entries.";
    header("Location: settings.php");
    exit;
}
?>