<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';

// Decryption function for userID
function decryptID($encryptedID) {
    $key = "b7e1a8f9c3d4e2b69a7f5d8e1c4b3a61";
    $decoded = base64_decode(urldecode($encryptedID));
    $decrypted = openssl_decrypt($decoded, 'AES-128-ECB', $key, 0, "");
    return $decrypted !== false && is_numeric($decrypted) ? (int)$decrypted : null;
}

if (!isset($_GET['userID']) || empty($_GET['userID'])) {
    $_SESSION['error_message'] = "Invalid or missing user ID.";
    header('Location: data_student.php');
    exit();
}

$encryptedID = $_GET['userID'];
$userID = decryptID($encryptedID);

if ($userID === null) {
    $_SESSION['error_message'] = "Invalid user ID format.";
    header('Location: data_student.php');
    exit();
}

try {
    $archiveSql = "UPDATE users SET archived = 1 WHERE userID = :userID";
    $archiveStmt = $dbConnection->prepare($archiveSql);
    $archiveStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    
    if ($archiveStmt->execute() && $archiveStmt->rowCount() > 0) {
        $_SESSION['success_message'] = "Student user archived successfully!";
    } else {
        $_SESSION['error_message'] = "No student found with the provided ID or already archived.";
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error archiving student user: " . htmlspecialchars($e->getMessage());
}

header('Location: data_student.php');
exit();
?>