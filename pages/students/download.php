<?php
require_once '../../config/db_connection.php';

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    die("Error: Invalid module ID.");
}

$moduleID = (int)$_GET['id'];

try {
    $stmt = $dbConnection->prepare("SELECT fileName, filePath, fileType FROM modules WHERE moduleID = :moduleID AND archived = 0");
    $stmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
    $stmt->execute();
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($module) {
        $filePath = $module['filePath'];
        $fileName = $module['fileName'];
        $fileType = $module['fileType'];

        // Validate file existence and readability
        if (!file_exists($filePath) || !is_readable($filePath)) {
            http_response_code(404);
            error_log("File not found or not readable: $filePath");
            die("Error: File not found or inaccessible.");
        }

        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $fileType);
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        header('Content-Transfer-Encoding: binary');

        // Clear output buffer to prevent corruption
        ob_clean();
        flush();

        // Serve the file
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        error_log("Module not found for ID: $moduleID");
        die("Error: Module not found.");
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    die("Error: A database error occurred.");
}
?>