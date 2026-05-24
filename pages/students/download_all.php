<?php
require_once '../../config/db_connection.php';

// Validate token
if (!isset($_GET['token'])) {
    die("Error: token parameter is missing in the URL.");
}
$token = trim($_GET['token']);
if (empty($token) || !preg_match('/^[a-zA-Z0-9]{1,32}$/', $token)) {
    die("Error: token must be a non-empty string of up to 32 alphanumeric characters.");
}

try {
    // Fetch section details
    $sectionStmt = $dbConnection->prepare("
        SELECT s.sectionName, sub.subjectName, ts.teacherSectionID
        FROM teacher_section ts 
        JOIN sections s ON ts.sectionID = s.sectionID 
        JOIN subjects sub ON ts.subjectID = sub.subjectID 
        WHERE ts.token = :token 
        AND ts.archived = 0 AND s.archived = 0 AND sub.archived = 0
    ");
    $sectionStmt->bindParam(':token', $token, PDO::PARAM_STR);
    $sectionStmt->execute();
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        die("Error: No active section found for token = " . htmlspecialchars($token) . ".");
    }

    $teacherSectionID = (int)$section['teacherSectionID'];

    // Fetch modules
    $moduleStmt = $dbConnection->prepare("
        SELECT fileName, filePath 
        FROM modules 
        WHERE teacherSectionID = :teacherSectionID AND archived = 0
    ");
    $moduleStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $moduleStmt->execute();
    $modules = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($modules)) {
        die("Error: No modules available to download.");
    }

    // Create ZIP file
    $zip = new ZipArchive();
    $zipName = 'modules_' . $section['subjectName'] . '_' . $section['sectionName'] . '_' . time() . '.zip';
    $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Error: Could not create ZIP file.");
    }

    // Add files to ZIP
    foreach ($modules as $module) {
        $filePath = $module['filePath'];
        $fileName = $module['fileName'];
        if (file_exists($filePath)) {
            $zip->addFile($filePath, $fileName);
        }
    }

    $zip->close();

    // Download the ZIP
    if (file_exists($zipPath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        unlink($zipPath); // Delete temp file
        exit;
    } else {
        die("Error: ZIP file creation failed.");
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    die("Error: Database connection failed or query error. Details: " . htmlspecialchars($e->getMessage()));
}
?>