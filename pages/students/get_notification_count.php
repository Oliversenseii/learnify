<?php
require_once '../../config/db_connection.php';

function getUnreadAnnouncementCount($dbConnection, $userID, $teacherSectionID = null) {
    try {
        // Base query for unread announcements (within 7 days, not archived)
        $sql = "
            SELECT COUNT(*) as unreadCount
            FROM announcements a
            JOIN teacher_section ts ON a.teacherSectionID = ts.teacherSectionID
            JOIN student_section ss ON ts.sectionID = ss.sectionID
            WHERE ss.userID = :userID 
            AND ss.status = 'Enrolled' 
            AND a.archived = 0 
            AND ts.archived = 0 
            AND ss.archived = 0
            AND a.createdDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";

        // Add teacherSectionID filter if provided
        if ($teacherSectionID !== null) {
            $sql .= " AND a.teacherSectionID = :teacherSectionID";
        }

        $stmt = $dbConnection->prepare($sql);
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        if ($teacherSectionID !== null) {
            $stmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['unreadCount'];
    } catch (PDOException $e) {
        error_log("Database Error in get_notification_count.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        return 0; // Return 0 on error to prevent breaking the UI
    }
}
?>