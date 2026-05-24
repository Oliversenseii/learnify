<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';

$validHash = 'lp{d1]\awdlll,>>121_!333';
if (!isset($_GET['hash']) || $_GET['hash'] !== $validHash) {
    die('Unauthorized access.');
}

try {

    $backupFileName = 'db_lms_backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $backupFileName . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $sql = "-- Learnify Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: db_lms\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $stmt = $dbConnection->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {

        $stmt = $dbConnection->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql .= "-- Table structure for `$table`\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createTable['Create Table'] . ";\n\n";

        $stmt = $dbConnection->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $sql .= "-- Data for `$table`\n";
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`,`', $columns) . '`';

            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $values[] = "'" . $dbConnection->quote($value) . "'";
                    }
                }
                $sql .= "INSERT INTO `$table` ($columnList) VALUES (" . implode(',', $values) . ");\n";
            }
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    echo $sql;

} catch (PDOException $e) {
    die('Error generating backup: ' . $e->getMessage());
}

exit;
?>