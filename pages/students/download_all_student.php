<?php
require_once '../../config/db_connection.php';
require_once './sessions/session_student.php';

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
    die("Error: Invalid section ID.");
}
$teacherSectionID = (int)$_GET['teacherSectionID'];

try {
    // Verify student is enrolled in the section
    $checkStmt = $dbConnection->prepare("
        SELECT ts.sectionID, s.sectionName, sub.subjectName
        FROM teacher_section ts 
        JOIN sections s ON ts.sectionID = s.sectionID 
        JOIN subjects sub ON ts.subjectID = sub.subjectID 
        JOIN student_section ss ON ts.sectionID = ss.sectionID 
        WHERE ts.teacherSectionID = :teacherSectionID AND ss.userID = :userID 
        AND ts.archived = 0 AND ss.archived = 0 AND s.archived = 0 AND sub.archived = 0 AND ss.status = 'Enrolled'
    ");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->execute();
    $section = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        die("Error: You are not enrolled in this section.");
    }

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
    $zipName = 'modules_' . str_replace(' ', '_', $section['subjectName']) . '_' . str_replace(' ', '_', $section['sectionName']) . '_' . time() . '.zip';
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