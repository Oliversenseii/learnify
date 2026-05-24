<?php
/**
 * Dashboard Navigation Item with Pending Count (PHILIPPINE TIME ACCURATE)
 * Uses CONVERT_TZ + NOW() → excludes past-due items (even if due today)
 */

if (!isset($_SESSION['userID'])) {
    return;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false) {
    return;
}

if (!isset($dbConnection)) {
    require_once '../../config/db_connection.php';
}

$dbConnection->exec("SET time_zone = '+08:00';");

if (!isset($totalPendingCount)) {
    try {
        $pendingCountStmt = $dbConnection->prepare("
            SELECT 
                COALESCE(SUM(pending_quiz_count), 0) + COALESCE(SUM(pending_assignment_count), 0) as total_pending
            FROM (
                SELECT 
                    (
                        SELECT COUNT(*) 
                        FROM quizzes q 
                        WHERE q.teacherSectionID = ts.teacherSectionID 
                          AND q.archived = 0 
                          AND (q.releaseDate IS NULL OR q.releaseDate <= CONVERT_TZ(NOW(), @@session.time_zone, '+08:00'))
                          AND (q.dueDate IS NULL OR CONVERT_TZ(q.dueDate, @@session.time_zone, '+08:00') >= CONVERT_TZ(NOW(), @@session.time_zone, '+08:00'))
                          AND NOT EXISTS (
                              SELECT 1 FROM quiz_answers qa 
                              WHERE qa.quizID = q.quizID 
                                AND qa.studentID = :userID 
                                AND qa.archived = 0
                          )
                    ) as pending_quiz_count,
                    (
                        SELECT COUNT(*) 
                        FROM assignments a 
                        WHERE a.teacherSectionID = ts.teacherSectionID 
                          AND a.archived = 0 
                          AND (a.dueDate IS NULL OR CONVERT_TZ(a.dueDate, @@session.time_zone, '+08:00') >= CONVERT_TZ(NOW(), @@session.time_zone, '+08:00'))
                          AND NOT EXISTS (
                              SELECT 1 FROM assignment_submissions asm 
                              WHERE asm.assignmentID = a.assignmentID 
                                AND asm.studentID = :userID 
                                AND asm.archived = 0
                          )
                    ) as pending_assignment_count
                FROM teacher_section ts
                JOIN student_section ss ON ts.sectionID = ss.sectionID
                WHERE ss.userID = :userID 
                  AND ss.status = 'Enrolled' 
                  AND ts.archived = 0 
                  AND ss.archived = 0
                GROUP BY ts.teacherSectionID
            ) as pending_summary
        ");
        $pendingCountStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $pendingCountStmt->execute();
        $result = $pendingCountStmt->fetch(PDO::FETCH_ASSOC);
        $totalPendingCount = $result['total_pending'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error calculating pending count (PH Time): " . $e->getMessage());
        $totalPendingCount = 0;
    }
}
?>

<li class="<?php echo basename($_SERVER['PHP_SELF']) === 'studentDash.php' ? 'active' : ''; ?>">
    <a href="./studentDash.php">
        <i class='bx bxs-dashboard'></i>
        <span class="text">Dashboard<?php if ($totalPendingCount > 0): ?>
            <span class="dashboard-header-badge"><?php echo $totalPendingCount; ?></span>
        <?php endif; ?></span>
    </a>
</li>