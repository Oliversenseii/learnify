<?php
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

try {
    // Get user details and enrollment status
    $stmt = $dbConnection->prepare("
        SELECT u.firstName, u.userType, u.image, u.dashboard_view, ss.status 
        FROM users u
        LEFT JOIN student_section ss ON u.userID = ss.userID
        WHERE u.userID = :userID
        LIMIT 1
    ");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
        $_SESSION['dashboard_view'] = isset($user['dashboard_view']) && in_array($user['dashboard_view'], ['table', 'card']) ? $user['dashboard_view'] : null;
        
        // Check enrollment status
        $status = $user['status'];
        if ($status === 'Pending' || $status === 'Dropped' || $status === 'Completed') {
            header("Location: ./status.php");
            exit;
        }
    } else {
        header("Location: ../../index.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
}
?>