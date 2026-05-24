<?php
try {
    $stmt = $dbConnection->prepare("SELECT status FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['status'] !== 'Active') {
        header("Location: ./inactive_admin.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
    header("Location: ./inactive_admin.php");
    exit;
}
?>