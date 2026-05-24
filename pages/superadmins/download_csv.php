<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';

if (!isset($_GET['log_id']) || !is_numeric($_GET['log_id'])) {
    die("Invalid request.");
}

$log_id = (int)$_GET['log_id'];

try {
    $stmt = $dbConnection->prepare("SELECT file_path, file_name FROM csv_upload_logs WHERE log_id = :log_id");
    $stmt->bindParam(':log_id', $log_id, PDO::PARAM_INT);
    $stmt->execute();
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log || empty($log['file_path']) || !file_exists($log['file_path'])) {
        die("File not found.");
    }

    $filePath = $log['file_path'];
    $fileName = $log['file_name'];

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($filePath);
    exit;
} catch (PDOException $e) {
    die("Error accessing file: " . $e->getMessage());
}
?>