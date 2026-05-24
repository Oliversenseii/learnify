<?php
   require_once './sessions/session_professor.php';
   require_once '../../config/db_connection.php';
   require_once './auto_logout.php';
   require_once './check_status.php';
   require_once './academic_events.php';
   
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
      // Get user details
      $stmt = $dbConnection->prepare("SELECT firstName, userType, image FROM users WHERE userID = :userID");
      $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
      $stmt->execute();
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($user) {
         $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
         $_SESSION['userType'] = htmlspecialchars($user['userType']);
         $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
      } else {
         header("Location: ../../index.php");
         exit;
      }

      if (isset($_POST['viewStudent']) && isset($_POST['studentID'])) {
         $studentID = filter_var($_POST['studentID'], FILTER_VALIDATE_INT);
         if ($studentID === false) {
            $_SESSION['error_message'] = "Invalid student ID.";
            $subjectID = isset($_GET['subjectID']) ? filter_var($_GET['subjectID'], FILTER_VALIDATE_INT) : 0;
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
         }
         $stmt = $dbConnection->prepare("SELECT * FROM users WHERE userID = :studentID AND archived = 0");
         $stmt->bindParam(':studentID', $studentID, PDO::PARAM_INT);
         $stmt->execute();
         if ($stmt->rowCount() === 0) {
            $_SESSION['error_message'] = "Student not found.";
            $subjectID = isset($_GET['subjectID']) ? filter_var($_GET['subjectID'], FILTER_VALIDATE_INT) : 0;
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
         }
         $_SESSION['success_message'] = "Student data retrieved.";
         $subjectID = isset($_GET['subjectID']) ? filter_var($_GET['subjectID'], FILTER_VALIDATE_INT) : 0;
         header("Location: professorDash.php?subjectID=$subjectID");
         exit;
      }
      if (isset($_POST['createAnnouncement']) && isset($_POST['teacherSectionID'])) {
         $teacherSectionID = filter_var($_POST['teacherSectionID'], FILTER_VALIDATE_INT);
         $title = isset($_POST['title']) ? trim($_POST['title']) : '';
         $content = isset($_POST['content']) ? trim($_POST['content']) : '';
         $fileName = null;
         $filePath = null;
         $fileType = null;
         $fileSize = null;
         if ($teacherSectionID && $title && $content) {
            $checkStmt = $dbConnection->prepare("SELECT * FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND teacherID = :userID AND archived = 0");
            $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
            $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->rowCount() > 0) {
               if (isset($_FILES['announcementFile']) && $_FILES['announcementFile']['error'] === UPLOAD_ERR_OK) {
                  $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                  $maxFileSize = 10 * 1024 * 1024;
                  $file = $_FILES['announcementFile'];
                  $fileType = $file['type'];
                  $fileSize = $file['size'];
                  $fileName = basename($file['name']);
                  $uploadDir = '../../uploads/announcements/';
                  $filePath = $uploadDir . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
                  if (!in_array($fileType, $allowedTypes)) {
                     $_SESSION['error_message'] = "Invalid file type. Allowed types: PDF, JPG, PNG.";
                  } elseif ($fileSize > $maxFileSize) {
                     $_SESSION['error_message'] = "File size exceeds 10MB limit.";
                  } elseif (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
                     $_SESSION['error_message'] = "Failed to create upload directory.";
                  } elseif (!move_uploaded_file($file['tmp_name'], $filePath)) {
                     $_SESSION['error_message'] = "Failed to upload file.";
                  }
               }
               if (!isset($_SESSION['error_message'])) {
                  $insertStmt = $dbConnection->prepare("INSERT INTO announcements (teacherSectionID, title, content, fileName, filePath, fileType, fileSize, createdDate) VALUES (:teacherSectionID, :title, :content, :fileName, :filePath, :fileType, :fileSize, NOW())");
                  $insertStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                  $insertStmt->bindParam(':title', $title, PDO::PARAM_STR);
                  $insertStmt->bindParam(':content', $content, PDO::PARAM_STR);
                  $insertStmt->bindValue(':fileName', $fileName, $fileName === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                  $insertStmt->bindValue(':filePath', $filePath, $filePath === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                  $insertStmt->bindValue(':fileType', $fileType, $fileType === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                  $insertStmt->bindValue(':fileSize', $fileSize, $fileSize === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                  if ($insertStmt->execute()) {
                     $_SESSION['success_message'] = "Announcement created successfully.";
                  } else {
                     $_SESSION['error_message'] = "Failed to create announcement.";
                     if ($filePath && file_exists($filePath)) {
                        unlink($filePath);
                     }
                  }
               }
            } else {
               $_SESSION['error_message'] = "Invalid assignment.";
            }
         } else {
            $_SESSION['error_message'] = "Title and content are required.";
         }
         header("Location: professorDash.php");
         exit;
      }
      if (isset($_POST['updateAnnouncement']) && isset($_POST['announcementID'])) {
         $announcementID = filter_var($_POST['announcementID'], FILTER_VALIDATE_INT);
         $title = isset($_POST['title']) ? trim($_POST['title']) : '';
         $content = isset($_POST['content']) ? trim($_POST['content']) : '';
         $newFileUploaded = isset($_FILES['announcementFile']) && $_FILES['announcementFile']['error'] === UPLOAD_ERR_OK;
         if ($announcementID && $title && $content) {
            $stmt = $dbConnection->prepare("SELECT filePath, teacherSectionID FROM announcements WHERE announcementID = :announcementID AND archived = 0");
            $stmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
            $stmt->execute();
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($announcement) {
               $checkStmt = $dbConnection->prepare("SELECT * FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND teacherID = :userID AND archived = 0");
               $checkStmt->bindParam(':teacherSectionID', $announcement['teacherSectionID'], PDO::PARAM_INT);
               $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
               $checkStmt->execute();
               if ($checkStmt->rowCount() > 0) {
                  $fileName = null;
                  $filePath = null;
                  $fileType = null;
                  $fileSize = null;
                  if ($newFileUploaded) {
                     $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                     $maxFileSize = 10 * 1024 * 1024;
                     $file = $_FILES['announcementFile'];
                     $fileType = $file['type'];
                     $fileSize = $file['size'];
                     $fileName = basename($file['name']);
                     $uploadDir = '../../uploads/announcements/';
                     $filePath = $uploadDir . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
                     if (!in_array($fileType, $allowedTypes)) {
                        $_SESSION['error_message'] = "Invalid file type. Allowed types: PDF, JPG, PNG.";
                     } elseif ($fileSize > $maxFileSize) {
                        $_SESSION['error_message'] = "File size exceeds 10MB limit.";
                     } elseif (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
                        $_SESSION['error_message'] = "Failed to create upload directory.";
                     } elseif (!move_uploaded_file($file['tmp_name'], $filePath)) {
                        $_SESSION['error_message'] = "Failed to upload new file.";
                     }
                  }
                  if (!isset($_SESSION['error_message'])) {
                     $sql = "UPDATE announcements SET title = :title, content = :content" . ($newFileUploaded ? ", fileName = :fileName, filePath = :filePath, fileType = :fileType, fileSize = :fileSize" : "") . " WHERE announcementID = :announcementID AND archived = 0";
                     $updateStmt = $dbConnection->prepare($sql);
                     $updateStmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
                     $updateStmt->bindParam(':title', $title, PDO::PARAM_STR);
                     $updateStmt->bindParam(':content', $content, PDO::PARAM_STR);
                     if ($newFileUploaded) {
                        $updateStmt->bindValue(':fileName', $fileName, PDO::PARAM_STR);
                        $updateStmt->bindValue(':filePath', $filePath, PDO::PARAM_STR);
                        $updateStmt->bindValue(':fileType', $fileType, PDO::PARAM_STR);
                        $updateStmt->bindValue(':fileSize', $fileSize, PDO::PARAM_INT);
                     }
                     if ($updateStmt->execute()) {
                        if ($newFileUploaded && $announcement['filePath'] && file_exists($announcement['filePath'])) {
                           unlink($announcement['filePath']);
                        }
                        $_SESSION['success_message'] = "Announcement updated successfully.";
                     } else {
                        $_SESSION['error_message'] = "Failed to update announcement.";
                        if ($newFileUploaded && $filePath && file_exists($filePath)) {
                           unlink($filePath);
                        }
                     }
                  }
               } else {
                  $_SESSION['error_message'] = "Invalid assignment.";
               }
            } else {
               $_SESSION['error_message'] = "Announcement not found.";
            }
         } else {
            $_SESSION['error_message'] = "Title and content are required.";
         }
         header("Location: professorDash.php");
         exit;
      }
      if (isset($_POST['archiveAnnouncement']) && isset($_POST['announcementID'])) {
         $announcementID = filter_var($_POST['announcementID'], FILTER_VALIDATE_INT);
         if ($announcementID) {
            $stmt = $dbConnection->prepare("SELECT filePath FROM announcements WHERE announcementID = :announcementID AND archived = 0");
            $stmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
            $stmt->execute();
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($announcement) {
               $updateStmt = $dbConnection->prepare("UPDATE announcements SET archived = 1 WHERE announcementID = :announcementID");
               $updateStmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
               if ($updateStmt->execute()) {
                  if ($announcement['filePath'] && file_exists($announcement['filePath'])) {
                     unlink($announcement['filePath']);
                  }
                  $_SESSION['success_message'] = "Announcement archived successfully.";
               } else {
                  $_SESSION['error_message'] = "Failed to archive announcement.";
               }
            } else {
               $_SESSION['error_message'] = "Announcement not found.";
            }
         }
         header("Location: professorDash.php");
         exit;
      }
      $sectionStmt = $dbConnection->prepare("SELECT DISTINCT s.sectionID, s.sectionName
                                            FROM sections s
                                            JOIN teacher_section ts ON s.sectionID = ts.sectionID
                                            WHERE ts.teacherID = :userID AND s.archived = 0 AND ts.archived = 0
                                            ORDER BY s.sectionName");
      $sectionStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
      $sectionStmt->execute();
      $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
      $subjectStmt = $dbConnection->prepare("SELECT DISTINCT sub.subjectID, sub.subjectName
                                           FROM subjects sub
                                           JOIN teacher_section ts ON sub.subjectID = ts.subjectID
                                           WHERE ts.teacherID = :userID AND sub.archived = 0 AND ts.archived = 0
                                           ORDER BY sub.subjectName");
      $subjectStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
      $subjectStmt->execute();
      $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);
      $search = isset($_GET['search']) ? trim($_GET['search']) : '';
      $sectionFilter = isset($_GET['sectionID']) && filter_var($_GET['sectionID'], FILTER_VALIDATE_INT) ? $_GET['sectionID'] : (!empty($sections) ? $sections[0]['sectionID'] : '');
      $subjectFilter = isset($_GET['subjectID']) && filter_var($_GET['subjectID'], FILTER_VALIDATE_INT) ? $_GET['subjectID'] : (!empty($subjects) ? $subjects[0]['subjectID'] : '');
      $sortOrder = isset($_GET['sort']) && in_array($_GET['sort'], ['asc', 'desc']) ? $_GET['sort'] : 'asc';
      $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
      $recordsPerPage = 15;
      $offset = ($currentPage - 1) * $recordsPerPage;
      $countSQL = "SELECT COUNT(DISTINCT ts.teacherSectionID) as total
                  FROM teacher_section ts
                  JOIN users u ON ts.teacherID = u.userID
                  JOIN sections s ON ts.sectionID = s.sectionID
                  JOIN subjects sub ON ts.subjectID = sub.subjectID
                  WHERE ts.teacherID = :userID AND ts.archived = 0 AND u.archived = 0 AND s.archived = 0 AND sub.archived = 0";
      $params = [':userID' => $userID];
      if ($search !== '') {
         $countSQL .= " AND (u.firstName LIKE :search OR u.lastName LIKE :search OR s.sectionName LIKE :search OR sub.subjectName LIKE :search)";
         $params[':search'] = "%$search%";
      }
      if ($sectionFilter !== '') {
         $countSQL .= " AND ts.sectionID = :sectionID";
         $params[':sectionID'] = $sectionFilter;
      }
      if ($subjectFilter !== '') {
         $countSQL .= " AND ts.subjectID = :subjectID";
         $params[':subjectID'] = $subjectFilter;
      }
      $countStmt = $dbConnection->prepare($countSQL);
      foreach ($params as $key => $value) {
         $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
      }
      $countStmt->execute();
      $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
      $totalPages = ceil($totalRecords / $recordsPerPage);
      $sql = "SELECT
             ts.teacherSectionID,
             ts.sectionID,
             s.sectionName,
             ts.subjectID,
             sub.subjectName,
             ts.teacherID,
             u.firstName AS teacherFirstName,
             u.lastName AS teacherLastName,
             ts.startTime,
             ts.endTime,
             ts.day,
             ts.token,
             CASE
                WHEN ts.advisory = 1 THEN 'Yes'
                ELSE 'No'
             END AS is_advisory,
             GROUP_CONCAT(DISTINCT CONCAT(COALESCE(u2.image, './img/noprofile.png'), '|', us.firstName, ' ', us.lastName) ORDER BY us.firstName SEPARATOR ',') AS student_list
         FROM teacher_section ts
         JOIN users u ON ts.teacherID = u.userID
         JOIN sections s ON ts.sectionID = s.sectionID
         JOIN subjects sub ON ts.subjectID = sub.subjectID
         LEFT JOIN student_section ss ON ts.sectionID = ss.sectionID AND ss.status = 'Enrolled' AND ss.archived = 0
         LEFT JOIN users us ON ss.userID = us.userID
         LEFT JOIN users u2 ON us.userID = u2.userID
         WHERE ts.teacherID = :userID AND ts.archived = 0 AND u.archived = 0 AND s.archived = 0 AND sub.archived = 0";
      if ($search !== '') {
         $sql .= " AND (u.firstName LIKE :search OR u.lastName LIKE :search OR s.sectionName LIKE :search OR sub.subjectName LIKE :search)";
      }
      if ($sectionFilter !== '') {
         $sql .= " AND ts.sectionID = :sectionID";
      }
      if ($subjectFilter !== '') {
         $sql .= " AND ts.subjectID = :subjectID";
      }
      $sql .= " GROUP BY ts.teacherSectionID
               ORDER BY ts.teacherSectionID
               LIMIT :offset, :recordsPerPage";
      $stmt = $dbConnection->prepare($sql);
      foreach ($params as $key => $value) {
         $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
      }
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      $stmt->bindValue(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
      $stmt->execute();
      $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach ($assignments as &$assignment) {
         if ($assignment['student_list']) {
            $studentData = explode(',', $assignment['student_list']);
            $sortedStudents = [];
            foreach ($studentData as $data) {
               list($image, $name) = explode('|', $data, 2);
               $sortedStudents[] = ['image' => $image, 'name' => ucwords(trim($name))];
            }
            usort($sortedStudents, function($a, $b) use ($sortOrder) {
               return $sortOrder === 'asc' ? strcmp($a['name'], $b['name']) : strcmp($b['name'], $a['name']);
            });
            $assignment['students'] = $sortedStudents;
         }
         $announcementStmt = $dbConnection->prepare("SELECT announcementID, title, content, fileName, filePath, fileType, fileSize, createdDate FROM announcements WHERE teacherSectionID = :teacherSectionID AND archived = 0 ORDER BY createdDate DESC");
         $announcementStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
         $announcementStmt->execute();
         $assignment['announcements'] = $announcementStmt->fetchAll(PDO::FETCH_ASSOC);
      }
      $countsStmt = $dbConnection->prepare("
         SELECT
            (SELECT COUNT(DISTINCT ss.userID)
             FROM student_section ss
             WHERE ss.sectionID = ts.sectionID AND ss.status = 'Enrolled' AND ss.archived = 0) AS total_students,
            (SELECT COUNT(*)
             FROM quizzes q
             WHERE q.teacherSectionID = ts.teacherSectionID AND q.archived = 0) AS total_quizzes,
            (SELECT COUNT(*)
             FROM assignments a
             WHERE a.teacherSectionID = ts.teacherSectionID AND a.archived = 0) AS total_assignments,
            (SELECT COUNT(*)
             FROM modules m
             WHERE m.teacherSectionID = ts.teacherSectionID AND m.archived = 0) AS total_modules
         FROM teacher_section ts
         WHERE ts.teacherSectionID = :teacherSectionID
      ");
      $countsStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
      $countsStmt->execute();
      $counts = $countsStmt->fetch(PDO::FETCH_ASSOC);
      $totalStudentsStmt = $dbConnection->prepare("
         SELECT COUNT(DISTINCT ss.userID) AS total_students
         FROM student_section ss
         JOIN teacher_section ts ON ss.sectionID = ts.sectionID
         WHERE ts.teacherSectionID = :teacherSectionID AND ss.status = 'Enrolled' AND ss.archived = 0
      ");
      $totalStudentsStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
      $totalStudentsStmt->execute();
      $counts['total_students'] = $totalStudentsStmt->fetch(PDO::FETCH_ASSOC)['total_students'] ?: 0;
      $quizEngagementStmt = $dbConnection->prepare("
         SELECT
            COUNT(DISTINCT qs.scoreID) AS total_submissions,
            AVG(qs.totalScore / qs.maxScore * 100) AS avg_score_percentage,
            COUNT(DISTINCT qs.studentID) AS unique_participants,
            (SELECT COUNT(*) FROM quizzes q WHERE q.teacherSectionID = :teacherSectionID AND q.archived = 0) AS total_quizzes
         FROM quiz_scores qs
         JOIN quizzes q ON qs.quizID = q.quizID
         WHERE q.teacherSectionID = :teacherSectionID AND qs.archived = 0
      ");
      $quizEngagementStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
      $quizEngagementStmt->execute();
      $quizEngagement = $quizEngagementStmt->fetch(PDO::FETCH_ASSOC);
      $assignmentEngagementStmt = $dbConnection->prepare("
         SELECT
            COUNT(DISTINCT ass.submissionID) AS total_submissions,
            COUNT(DISTINCT ass.studentID) AS unique_participants,
            (SELECT COUNT(*) FROM assignments a WHERE a.teacherSectionID = :teacherSectionID AND a.archived = 0) AS total_assignments
         FROM assignment_submissions ass
         JOIN assignments a ON ass.assignmentID = a.assignmentID
         WHERE a.teacherSectionID = :teacherSectionID AND ass.archived = 0
      ");
      $assignmentEngagementStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
      $assignmentEngagementStmt->execute();
      $assignmentEngagement = $assignmentEngagementStmt->fetch(PDO::FETCH_ASSOC);
      $quizCompletionRate = $quizEngagement['total_quizzes'] > 0
         ? round(($quizEngagement['unique_participants'] / ($counts['total_students'] ?: 1)) * 100, 1)
         : 0;
      $assignmentSubmissionRate = $assignmentEngagement['total_assignments'] > 0
         ? round(($assignmentEngagement['unique_participants'] / ($counts['total_students'] ?: 1)) * 100, 1)
         : 0;
      $quizBarDataStmt = $dbConnection->prepare("
         SELECT
            q.title,
            AVG(qs.totalScore / qs.maxScore * 100) AS avg_score_percentage
         FROM quiz_scores qs
         JOIN quizzes q ON qs.quizID = q.quizID
         WHERE q.teacherSectionID = :teacherSectionID AND qs.archived = 0
         GROUP BY q.quizID, q.title
      ");
      $quizBarDataStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
      $quizBarDataStmt->execute();
      $quizBarData = $quizBarDataStmt->fetchAll(PDO::FETCH_ASSOC);
      $assignmentBarDataStmt = $dbConnection->prepare("
         SELECT
            a.title,
            COUNT(ass.submissionID) AS submission_count
         FROM assignment_submissions ass
         JOIN assignments a ON ass.assignmentID = a.assignmentID
         WHERE a.teacherSectionID = :teacherSectionID AND ass.archived = 0
         GROUP BY a.assignmentID, a.title
      ");
      $assignmentBarDataStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
      $assignmentBarDataStmt->execute();
      $assignmentBarData = $assignmentBarDataStmt->fetchAll(PDO::FETCH_ASSOC);
      $quizLineDataStmt = $dbConnection->prepare("
         SELECT
            DATE_FORMAT(qs.recordedDate, '%Y-%m') AS month,
            AVG(qs.totalScore / qs.maxScore * 100) AS avg_score_percentage
         FROM quiz_scores qs
         JOIN quizzes q ON qs.quizID = q.quizID
         WHERE q.teacherSectionID = :teacherSectionID AND qs.archived = 0
         GROUP BY month
         ORDER BY month
      ");
      $quizLineDataStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
      $quizLineDataStmt->execute();
      $quizLineData = $quizLineDataStmt->fetchAll(PDO::FETCH_ASSOC);
      $assignmentLineDataStmt = $dbConnection->prepare("
         SELECT
            DATE_FORMAT(ass.submissionDate, '%Y-%m') AS month,
            COUNT(ass.submissionID) AS submission_count
         FROM assignment_submissions ass
         JOIN assignments a ON ass.assignmentID = a.assignmentID
         WHERE a.teacherSectionID = :teacherSectionID AND ass.archived = 0
         GROUP BY month
         ORDER BY month
      ");
      $assignmentLineDataStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
      $assignmentLineDataStmt->execute();
      $assignmentLineData = $assignmentLineDataStmt->fetchAll(PDO::FETCH_ASSOC);
      $quizPieDataStmt = $dbConnection->prepare("
         SELECT
            CASE
               WHEN (qs.totalScore / qs.maxScore * 100) <= 60 THEN '0-60%'
               WHEN (qs.totalScore / qs.maxScore * 100) <= 80 THEN '61-80%'
               ELSE '81-100%'
            END AS score_range,
            COUNT(*) AS count
         FROM quiz_scores qs
         JOIN quizzes q ON qs.quizID = q.quizID
         WHERE q.teacherSectionID = :teacherSectionID AND qs.archived = 0
         GROUP BY score_range
      ");
      $quizPieDataStmt->bindParam(':teacherSectionID', $assignment['teacherSectionID'], PDO::PARAM_INT);
      $quizPieDataStmt->execute();
      $quizPieData = $quizPieDataStmt->fetchAll(PDO::FETCH_ASSOC);
   } catch (PDOException $e) {
      error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
      $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
   }
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
   <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
   <link rel="stylesheet" href="./utils/style.css">
   <link rel="stylesheet" href="./utils/semi-dash.css">
   <link rel="stylesheet" href="./utils/logout.css">
   <link rel="stylesheet" href="./utils/animation_slide.css">
   <link rel="stylesheet" href="./utils/logo.css">
   <link rel="stylesheet" href="./css/scrollbar.css">
   <script src="./logout.js"></script>
   <title>Learnify - Professor Dashboard</title>
   <style>
      :root {
         --primary: #1a73e8;
         --primary-light: #e8f0fe;
         --secondary: #f1f3f4;
         --text: #202124;
         --text-light: #5f6368;
         --border: #dadce0;
         --background: #ffffff;
         --success: #34a853;
         --error: #d93025;
         --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
         --hover: #d5d5d5;
         --secondary-hover: #e6f0ff;
         --secondary-grey: #f8faff;
      }
      body.dark {
         --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }
      .success-notification, .error-notification {
         padding: 12px;
         margin: 10px 0;
         border-radius: 4px;
         color: white;
         font-size: clamp(1.1rem, 3vw, 1.3rem);
         z-index: 1000;
         transition: opacity 0.3s ease;
      }
      .success-notification {
         position: fixed;
         bottom: 20px;
         right: 20px;
         background: linear-gradient(135deg, #28a745, #218838);
      }
      .error-notification {
         background: var(--error);
         color: var(--background);
         text-align: center;
      }
      .card-container {
         display: grid;
         gap: 20px;
         margin-top: 20px;
      }
      .card {
         background: var(--light);
         border-radius: 8px;
         box-shadow: var(--card-shadow);
         overflow: hidden;
      }
      .card-header {
         padding: 16px;
         display: flex;
         justify-content: space-between;
         align-items: center;
         background: #1557b0;
      }
      .card-header h3 {
         margin: 0;
         font-size: clamp(1.2rem, 3vw, 2rem);
         font-weight: 600;
         color: white;
      }
      .section-id {
         display: none;
      }
      .card-body {
         padding: 1.4rem;
      }
      .details-table {
         width: 100%;
         border-collapse: collapse;
         font-size: clamp(1rem, 3vw, 1.3rem);
      }
      .details-table th, .details-table td {
         padding: 12px;
         text-align: left;
      }
      .details-table th {
         background: var(--primary);
         color: var(--background);
         font-weight: 600;
      }
      .details-table td {
         color: var(--dark);
         font-weight: 500;
         background-color: var(--grey);
         border-bottom: 1px solid var(--border);
      }
      .advisory-yes {
         color: var(--success) !important;
         font-weight: 500;
      }
      .advisory-no {
         color: var(--error) !important;
         font-weight: 500;
      }
      .card-actions {
         display: flex;
         flex-wrap: wrap;
         gap: 15px;
         padding: 16px;
         border-radius: 15px;
      }
      .action-group {
         max-width: 260px;
         width: 100%;
      }
      .action-box {
         display: flex;
         align-items: center;
         justify-content: space-between;
         gap: 12px;
         border-radius: 10px !important;
         background-color: var(--grey);
         padding: 12px 16px;
         box-shadow: 0px 3px 2px rgba(0, 0, 0, 0.5);
         border-top: 4px solid var(--primary);
         text-align: left;
         max-height: 20vh;
         height: 16vh;
      }
      .action-box:hover {
         transform: translateY(-5px);
      }
      .action-box i {
         font-size: clamp(1rem, 3vw, 5rem);
         flex-shrink: 0;
         color: var(--primary);
         padding: 12px 16px;
         border-radius: 10px;
      }
      .action-box .bx-upload {
         background-color: #CFE8FF;
         color: #3C91E6;
      }
      .action-box .bx-calendar-check {
         background-color: #A9D08D;
         color: #28A745;
      }
      .action-box .bx-book {
         color: #6F42C1;
         background-color: #D8A8E4;
      }
      .action-box .bx-task {
         background-color: #F9D1E3;
         color: #FF4F89;
      }
      .action-box .action-text {
         display: flex;
         flex-direction: column;
         align-items: flex-start;
         flex: 1;
      }
      .action-box .action-text span {
         font-size: clamp(1rem, 3vw, 1.6rem);
         font-weight: 600;
         color: var(--dark);
      }
      .action-box .action-text small {
         font-size: clamp(1rem, 3vw, 2.5rem);
         font-weight: 600;
         color: var(--dark);
      }
      .tab-container {
         padding: 16px;
      }
      .tab-buttons {
         display: flex;
         border-bottom: 2px solid var(--border);
         margin-bottom: 16px;
         overflow-x: auto;
         white-space: nowrap;
         background: var(--grey);
         padding: 8px 0;
      }
      .tab-button {
         padding: 12px 24px;
         cursor: pointer;
         background: transparent;
         border: none;
         border-bottom: 2px solid transparent;
         margin-right: 8px;
         font-size: clamp(1rem, 3vw, 1.3rem);
         font-weight: 500;
         color: var(--dark);
         transition: all 0.3s ease;
      }
      .tab-button.active {
         color: var(--primary);
         border-bottom: 2px solid var(--primary);
         font-weight: 600;
      }
      .tab-button:hover {
         color: var(--primary);
         border-bottom: 2px solid #1a73e8;
      }
      .tab-content {
         display: none;
         padding: 16px;
         background: var(--light);
         border-radius: 4px;
         box-shadow: var(--card-shadow);
      }
      .tab-content.active {
         display: block;
      }
      .student-table, .announcement-table {
         width: 100%;
         border-collapse: collapse;
         font-size: clamp(1rem, 3vw, 1.2rem);
      }
      .student-table th, .student-table td,
      .announcement-table th, .announcement-table td {
         border: none;
         padding: 12px;
         text-align: left;
      }
      .student-table tr:hover,
      .student-table tr:nth-child(even):hover {
         background: linear-gradient(135deg, var(--hover), var(--secondary-hover));
      }
      .student-table th, .announcement-table th {
         background: var(--primary);
         color: var(--background);
         font-weight: 600;
      }
      .student-table td, .announcement-table td {
         color: var(--dark);
      }
      .student-table tr:nth-child(even),
      .announcement-table tr:nth-child(even) {
         background: linear-gradient(135deg, var(--grey), var(--secondary-grey));
            transition: background 0.3s ease; 
      }
      .profile-img {
         width: 40px;
         height: 40px;
         border-radius: 50%;
         object-fit: cover;
         margin-right: 8px;
      }
      .btn-edit, .btn-archive, .close-btn, .btn-preview {
         padding: 8px 16px;
         border: none;
         border-radius: 4px;
         font-size: clamp(1rem, 3vw, 1.2rem);
         cursor: pointer;
         transition: background 0.3s;
         margin-right: 8px;
         display: inline-flex;
         align-items: center;
         gap: 4px;
      }
      .btn-edit {
         background: var(--primary);
         color: var(--background);
      }
      .btn-edit:hover {
         background: #1557b0;
      }
      .btn-preview,
      .btn-preview-preview {
         color: var(--dark);
         background-color: transparent;
         border: 1px solid #1a73e8;
      }
      .btn-preview:hover,
      .btn-preview-preview:hover {
         background-color: #1557b0;
         color: white;
      }
      .btn-archive {
         background: var(--error);
         color: var(--background);
      }
      .btn-archive:hover {
         background: #b71c1c;
      }
      .close-btn {
         background: var(--text-light);
         color: var(--background);
      }
      .close-btn:hover {
         background: #3c4043;
      }
      .announcement-form, .edit-announcement-form {
         margin-top: 16px;
         display: flex;
         flex-direction: column;
         gap: 16px;
         padding: 16px;
         background-color: var(--grey);
         border-radius: 8px;
      }
      .announcement-form input[type="text"],
      .announcement-form textarea,
      .edit-announcement-form input[type="text"],
      .edit-announcement-form textarea {
         padding: 1rem;
         border: 1px solid var(--border);
         border-radius: 4px;
         font-size: clamp(1rem, 3vw, 1.3rem);
         width: 100%;
         color: var(--dark);
         box-sizing: border-box;
         background-color: var(--light);
         transition: border-color 0.3s;
      }
      .announcement-form input[type="text"]:focus,
      .announcement-form textarea:focus,
      .edit-announcement-form input[type="text"]:focus,
      .edit-announcement-form textarea:focus,
      .tab-content .announcement-select:focus {
         border-color: var(--primary);
         outline: none;
      }
      .announcement-form textarea,
      .edit-announcement-form textarea {
         min-height: 120px;
         resize: vertical;
         padding: 1rem;
      }
      .announcement-form input[type="file"],
      .edit-announcement-form input[type="file"],
      .tab-content .announcement-select {
         padding: 1rem;
         border: 1px solid var(--border);
         border-radius: 4px;
         background-color: var(--light);
         font-size: clamp(1rem, 3vw, 1.3rem);
         color: var(--dark);
         cursor: pointer;
      }
      .announcement-form button,
      .edit-announcement-form button {
         background: var(--primary);
         color: var(--background);
         padding: 12px;
         border: none;
         border-radius: 4px;
         font-size: clamp(1rem, 3vw, 1.3rem);
         cursor: pointer;
         transition: background 0.3s;
         align-self: flex-start;
      }
      .announcement-form button:hover,
      .edit-announcement-form button:hover {
         background: #1557b0;
      }
      .announcement-item {
         border-radius: 8px;
         padding: 16px;
         margin-bottom: 16px;
         background-color: var(--grey);
         box-shadow: var(--card-shadow);
         max-width: 900px;
         width: 100%;
         border-left: 4px solid var(--primary);
      }
      .announcement-item h3 {
         font-size: clamp(1.2rem, 3vw, 1.8rem);
         color: var(--dark);
         font-weight: 600;
      }
      .announcement-header {
         display: flex;
         align-items: center;
         gap: 12px;
         margin-bottom: 12px;
      }
      .announcement-header img {
         width: 40px;
         height: 40px;
         border-radius: 50%;
         object-fit: cover;
      }
      .announcement-header h4 {
         margin: 0;
         font-size: clamp(1rem, 3vw, 1.3rem);
         color: var(--dark);
      }
      .announcement-meta {
         color: var(--text-light);
         font-size: clamp(1rem, 3vw, 1.1rem);
         margin-bottom: 8px;
      }
      .announcement-content {
         color: var(--text-light);
         font-size: clamp(1rem, 3vw, 1.3rem);
         line-height: 1.5;
         margin-bottom: 12px;
      }
      .announcement-file {
         display: flex;
         align-items: center;
         gap: 8px;
         margin-top: 8px;
      }
      .announcement-file a,
      .current-file a {
         color: var(--dark);
         text-decoration: none;
         font-size: clamp(1rem, 3vw, 1.2rem);
         background-color: var(--light);
         padding: 8px 12px;
         border-radius: 4px;
      }
      .announcement-file a:hover,
      .current-file a:hover {
        transform: translateY(-2px);
      }
      
      .modal-header {
         padding: 16px;
         border-bottom: 1px solid var(--border);
         display: flex;
         justify-content: space-between;
         align-items: center;
         background: transparent;
      }
      .modal-header h3 {
         margin: 0;
         font-size: clamp(1.2rem, 3vw, 1.8rem);
         font-weight: 500;
      }
      .modal-actions {
         display: flex;
         gap: 8px;
      }
      .modal-body {
         padding: 16px;
      }
      .modal-body p {
         margin: 0 0 16px;
         font-size: clamp(1rem, 3vw, 1.3rem);
         color: var(--dark);
      }
      .modal-body form {
         display: flex;
         flex-direction: column;
         gap: 12px;
      }
      .modal-body form button {
         background: var(--error);
         color: var(--background);
         padding: 12px;
         border: none;
         border-radius: 4px;
         font-size: clamp(1rem, 3vw, 1.3rem);
         cursor: pointer;
         transition: background 0.3s;
         align-self: flex-start;
      }
      .modal-body form button:hover {
         background: #b71c1c;
      }
      .pagination {
         display: flex;
         justify-content: center;
         gap: 8px;
         margin-top: 20px;
      }
      .pagination a {
         padding: 8px 12px;
         border: 1px solid var(--border);
         border-radius: 4px;
         text-decoration: none;
         color: var(--dark);
         font-size: clamp(1rem, 3vw, 1.2rem);
         transition: all 0.3s;
      }
      .pagination a.active {
         background: var(--primary);
         color: var(--background);
         border-color: var(--primary);
      }
      .pagination a:hover:not(.disabled) {
         background: var(--primary-light);
         color: var(--primary);
      }
      .pagination a.disabled {
         color: var(--text-light);
         cursor: not-allowed;
      }
      .announcement-label {
         font-size: clamp(1rem, 3vw, 1.3rem);
         color: var(--dark);
         font-weight: 600;
      }
      .announcement-label span {
         color: var(--error);
      }
       @media screen and (max-width: 768px) {
            #content {
                margin-left: 0;
            }
            .card-actions {
                flex-direction: column;
            }
            .action-box {
                min-width: 100%;
            }
            .tab-buttons {
                flex-direction: row;
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            .tab-button {
                margin-right: 4px;
                margin-bottom: 0;
            }
            .success-notification, .error-notification {
                bottom: 10px;
                right: 10px;
                padding: 0.5rem;
            }
            .profile-img {
                width: 35px;
                height: 35px;
            }
            .email-text {
                max-width: 150px;
            }
        }
        @media screen and (max-width: 480px) {
            .success-notification, .error-notification {
                bottom: 10px;
                right: 10px;
                padding: 0.5rem;
            }
            .profile-img {
                width: 30px;
                height: 30px;
            }
            .action-box .action-text small {
                text-align: center;
            }
            .announcement-file {
               display: flex;
               flex-direction: column;
            }
            .two-buttons {
               display: flex;
               flex-direction: column;
            }
            .card-actions {
               padding: 0;
            }
            .tab-buttons {
                flex-direction: column;
                justify-items: flex-start;
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            .pdf-button-name {
               white-space: nowrap; 
                overflow: hidden; 
                text-overflow: ellipsis;
                max-width: 80%; 
            }
        }

      .all-stat-container {
         border-top: 1px solid #ccc;
         padding-top: 10px;
      }

      .view-rates-btn {
         width: 100%;
         max-width: 300px;
         display: flex;
         align-items: center;
         justify-content: center;
         gap: 8px;
         padding: 10px;
         background: var(--primary);
         color: var(--background);
         text-decoration: none;
         border-radius: 4px;
         font-size: clamp(1rem, 3vw, 1.2rem);
         transition: background 0.3s;
         margin: 0 auto;
      }
      .view-rates-btn:hover {
         background: #1557b0;
      }

      .no-announcements {
         color: var(--dark);
         font-size: clamp(1rem, 3vw, 1.2rem);
         font-style: italic;
      }

      .back-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .back-button:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1A4971);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .back-button i {
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
             --transition: all 0.3s ease;
             --white: #FFFFFF;
             --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

      .modal-body .file-preview-container {
         overflow-y: auto;
         height: 65vh;
      }
   </style>
</head>
<body>
   <section id="sidebar">
      <?php require_once './brand.php' ?>
      <ul class="side-menu top">
         <li class="active">
            <a href="./professor_main_dash.php">
               <i class='bx bxs-dashboard'></i>
               <span class="text">Dashboard</span>
            </a>
         </li>
         <li>
            <a href="./modules.php">
               <i class='bx bxs-bookmark'></i>
               <span class="text">Modules</span>
            </a>
         </li>
         <li>
            <a href="./calendar.php">
               <i class='bx bxs-calendar'></i>
               <span class="text">Calendar</span>
            </a>
         </li>
         <li>
            <a href="./message_admin.php">
               <i class='bx bxs-message'></i>
               <span class="text">Message Admin</span>
            </a>
         </li>
         <li>
            <a href="./game_controller.php">
               <i class='bx bxs-game'></i>
               <span class="text">Game</span>
            </a>
         </li>
      </ul>
      <ul class="side-menu">
         <li>
            <a href="./settings.php">
               <i class='bx bxs-cog'></i>
               <span class="text">Settings</span>
            </a>
         </li>
         <li>
            <a href="javascript:void(0);" class="logout" onclick="showLogoutModal()">
               <i class='bx bxs-log-out-circle'></i>
               <span class="text">Logout</span>
            </a>
         </li>
      </ul>
   </section>
   <?php require_once './view/modal.php' ?>
   <section id="content">
      <nav>
         <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
         <form action="search.php" method="get">
            <div class="form-input">
               <input type="search" name="query" placeholder="Search users..." required aria-label="Search">
               <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
            </div>
         </form>
         <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
         <label for="switch-mode" class="switch-mode"></label>
         <a href="./main_acc.php" class="profile" aria-label="Profile">
            <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image">
            <div>
               <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
               <small>Teacher</small>
            </div>
         </a>
      </nav>
      
      <main>
         <div class="head-title">
            <div class="left">
               <h1>Teacher Management</h1>
               <ul class="breadcrumb">
                  <li><a href="#">Home</a></li>
                  <li><i class='bx bx-chevron-right'></i></li>
                  <li><a href="#">Dashboard</a></li>
                  <li><i class='bx bx-chevron-right'></i></li>
                  <li><a class="active" href="./professorDash.php">Manage</a></li>
               </ul>
            </div>
            <a href="./professor_main_dash.php" class="back-button">
               <i class='bx bx-arrow-back'></i> Back to Dashboard
            </a>
         </div>
         <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-notification" id="success-notification">
               <?php
                  echo htmlspecialchars($_SESSION['success_message']);
                  unset($_SESSION['success_message']);
               ?>
            </div>
         <?php endif; ?>
         <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-notification">
               <?php
                  echo htmlspecialchars($_SESSION['error_message']);
                  unset($_SESSION['error_message']);
               ?>
            </div>
         <?php endif; ?>

         <div class="card-container">
            <?php if (!empty($assignments)): ?>
               <?php foreach ($assignments as $index => $assignment): ?>
                  <?php
                  $startTime = date("g:ia", strtotime($assignment['startTime']));
                  $endTime = date("g:ia", strtotime($assignment['endTime']));
                  $formattedTime = "$startTime - $endTime";
                  ?>
                  <div class="card">
                     <div class="card-header">
                        <h3><?php echo htmlspecialchars($assignment['sectionName']); ?> - <?php echo htmlspecialchars($assignment['subjectName']); ?></h3>
                     </div>
                     <div class="tab-container">
                        <div class="tab-buttons">
                           <button class="tab-button active" data-tab="overview-<?php echo $index; ?>" aria-label="Overview Tab"><i class='bx bx-list-ul'></i> Overview</button>
                           <button class="tab-button" data-tab="students-<?php echo $index; ?>" aria-label="Students Tab"><i class='bx bxs-group'></i> Students</button>
                           <button class="tab-button" data-tab="announcements-<?php echo $index; ?>" aria-label="Announcements Tab"><i class='bx bxs-bell'></i> Announcements</button>
                        </div>
                        <div id="overview-<?php echo $index; ?>" class="tab-content active">
                           <!-- <div class="card-body">
                              <table class="details-table">
                                 <thead>
                                    <tr>
                                       <th>Day</th>
                                       <th>Time</th>
                                       <th>Advisory</th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    <tr>
                                       <td><?php echo htmlspecialchars($assignment['day']); ?></td>
                                       <td><?php echo htmlspecialchars($formattedTime); ?></td>
                                       <td class="<?php echo $assignment['is_advisory'] == 'Yes' ? 'advisory-yes' : 'advisory-no'; ?>">
                                          <?php echo htmlspecialchars($assignment['is_advisory']); ?>
                                       </td>
                                    </tr>
                                 </tbody>
                              </table>
                           </div> -->
                           <div class="card-actions">
                              <!-- Modules -->
                              <div class="action-group">
                                 <a href="upload_modules.php?teacherSectionID=<?php echo $assignment['teacherSectionID']; ?>&subjectID=<?php echo $assignment['subjectID']; ?>"
                                    class="action-box" aria-label="Manage Modules">
                                    <i class='bx bx-upload'></i>
                                    <div class="action-text">
                                       <span>Modules</span>
                                       <small><?php echo $counts['total_modules']; ?></small>
                                    </div>
                                 </a>
                              </div>
                              <!-- Attendance -->
                              <div class="action-group">
                                 <a href="attendance.php?teacherSectionID=<?php echo $assignment['teacherSectionID']; ?>&sectionID=<?php echo $assignment['sectionID']; ?>&subjectID=<?php echo $assignment['subjectID']; ?>&action=record" class="action-box" aria-label="Record Attendance">
                                    <i class='bx bx-calendar-check'></i>
                                    <div class="action-text">
                                       <span>Attendance</span>
                                       <small><?php echo $counts['total_students']; ?></small>
                                    </div>
                                 </a>
                              </div>
                              <!-- Quizzes -->
                              <div class="action-group">
                                 <a href="dashQuiz.php?teacherSectionID=<?php echo $assignment['teacherSectionID']; ?>&subjectID=<?php echo $assignment['subjectID']; ?>" class="action-box" aria-label="Manage Quizzes">
                                    <i class='bx bx-book'></i>
                                    <div class="action-text">
                                       <span>Quizzes</span>
                                       <small><?php echo $counts['total_quizzes']; ?></small>
                                    </div>
                                 </a>
                              </div>
                              <!-- Assignments -->
                              <div class="action-group">
                                 <a href="assignmentDash.php?teacherSectionID=<?php echo $assignment['teacherSectionID']; ?>&subjectID=<?php echo $assignment['subjectID']; ?>" class="action-box" aria-label="Manage Assignments">
                                    <i class='bx bx-task'></i>
                                    <div class="action-text">
                                       <span>Assignments</span>
                                       <small><?php echo $counts['total_assignments']; ?></small>
                                    </div>
                                 </a>
                              </div>
                           </div>
                           <div class="all-stat-container">
                              <?php if (!empty($assignments)): ?>
                                 <a href="all_rate.php?teacherSectionID=<?php echo htmlspecialchars($assignments[0]['teacherSectionID']); ?>&sectionID=<?php echo htmlspecialchars($assignments[0]['sectionID']); ?>&subjectID=<?php echo htmlspecialchars($assignments[0]['subjectID']); ?>&year=<?php echo date('Y'); ?>" class="view-rates-btn" aria-label="View All Rates">
                                    <i class='bx bx-stats'></i> View All Rates
                                 </a>
                              <?php endif; ?>
                           </div>
                        </div>
                        <div id="students-<?php echo $index; ?>" class="tab-content">
                           <?php if (!empty($assignment['students'])): ?>
                              <table class="student-table">
                                 <thead>
                                    <tr>
                                       <th>#</th>
                                       <th>Profile</th>
                                       <th>Student Name</th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    <?php foreach ($assignment['students'] as $studentIndex => $student): ?>
                                       <tr>
                                          <td><?php echo $studentIndex + 1; ?></td>
                                          <td><img src="<?php echo htmlspecialchars($student['image']); ?>" alt="Profile Image" class="profile-img"></td>
                                          <td><?php echo htmlspecialchars($student['name']); ?></td>
                                       </tr>
                                    <?php endforeach; ?>
                                 </tbody>
                              </table>
                           <?php else: ?>
                              <p>No students enrolled</p>
                           <?php endif; ?>
                        </div>
                        <div id="announcements-<?php echo $index; ?>" class="tab-content">
                           <div class="tab-buttons">
                              <button class="tab-button active" data-tab="announcements-records-<?php echo $index; ?>" aria-label="Records Tab"><i class='bx bxs-folder'></i> Records</button>
                              <button class="tab-button" data-tab="announcements-add-<?php echo $index; ?>" aria-label="Add Announcement Tab"><i class='bx bxs-plus-circle'></i> Add</button>
                              <button class="tab-button" data-tab="announcements-edit-<?php echo $index; ?>" aria-label="Edit Announcement Tab"><i class='bx bxs-edit'></i> Edit</button>
                           </div>
                           <div id="announcements-records-<?php echo $index; ?>" class="tab-content active">
                              <?php if (!empty($assignment['announcements'])): ?>
                                 <?php foreach ($assignment['announcements'] as $announcementIndex => $announcement): ?>
                                    <div class="announcement-item">
                                       <div class="announcement-header">
                                          <img src="<?php echo $_SESSION['image']; ?>" alt="Teacher Profile">
                                          <div>
                                             <h4><?php echo htmlspecialchars($_SESSION['firstName']); ?></h4>
                                             <div class="announcement-meta">
                                                Posted on <?php echo date('F j, Y, g:ia', strtotime($announcement['createdDate'])); ?>
                                             </div>
                                          </div>
                                       </div>
                                       <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                       <div class="announcement-content">
                                          <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                       </div>
                                       <?php if ($announcement['filePath']): ?>
                                          <div class="announcement-file">
                                             <?php
                                             $isImage = in_array($announcement['fileType'], ['image/jpeg', 'image/png']);
                                             $isPdf = $announcement['fileType'] === 'application/pdf';
                                             ?>
                                             <?php if ($isImage): ?>
                                                <img src="<?php echo htmlspecialchars($announcement['filePath']); ?>" alt="<?php echo htmlspecialchars($announcement['fileName']); ?>" style="max-width: 100px; max-height: 100px; cursor: pointer;" onclick="openFileViewer('<?php echo htmlspecialchars($announcement['filePath']); ?>', '<?php echo htmlspecialchars($announcement['fileName']); ?>', '<?php echo $announcement['fileType']; ?>')" aria-label="Preview Image">
                                                <br>
                                             <?php elseif ($isPdf): ?>
                                                <button class="btn-preview" onclick="openFileViewer('<?php echo htmlspecialchars($announcement['filePath']); ?>', '<?php echo htmlspecialchars($announcement['fileName']); ?>', '<?php echo $announcement['fileType']; ?>')" aria-label="Preview PDF">
                                                   <i class='bx bx-show'></i> Preview
                                                </button>
                                             <?php endif; ?>
                                             <!-- Validation for filtype icon -->
                                             <?php
                                                $filePath = $announcement['filePath'];
                                                $fileName = $announcement['fileName'];

                                                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                                                $icon = "<i class='bx bxs-file' style='color: #5f6368; font-size: clamp(1.1rem, 3vw, 3rem);'></i>";

                                                if ($extension === 'pdf') {
                                                    $icon = "<i class='bx bxs-file-pdf' style='color: #b71c1c; font-size: clamp(1.1rem, 3vw, 3rem);'></i>";
                                                } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                                                    $icon = "<i class='bx bxs-file-image' style='color: #1a73e8; font-size: clamp(1.1rem, 3vw, 3rem);'></i>";
                                                }
                                                ?>

                                                <a href="<?php echo htmlspecialchars($filePath); ?>" download aria-label="Download Announcement File" class="pdf-button-name">
                                                    <?php echo $icon; ?> <?php echo htmlspecialchars($fileName); ?>
                                                </a>
                                          </div>
                                       <?php endif; ?>
                                       <div style="margin-top: 16px; display: flex; gap: 8px;" class="two-buttons">
                                          <button class="btn-edit" onclick="selectEditAnnouncement(<?php echo $announcement['announcementID']; ?>, '<?php echo $index; ?>')" aria-label="Edit Announcement">
                                             <i class='bx bx-edit'></i> Edit
                                          </button>
                                          <button class="btn-archive" onclick="openModal('archive-announcement-modal-<?php echo $announcement['announcementID']; ?>')" aria-label="Archive Announcement">
                                             <i class='bx bx-archive'></i> Archive
                                          </button>
                                       </div>
                                       <div id="archive-announcement-modal-<?php echo $announcement['announcementID']; ?>" class="modal" role="dialog" aria-labelledby="archive-announcement-modal-title-<?php echo $announcement['announcementID']; ?>">
                                          <div class="modal-content">
                                             <div class="modal-header">
                                                <h3 id="archive-announcement-modal-title-<?php echo $announcement['announcementID']; ?>">Archive Announcement</h3>
                                                <div class="modal-actions">
                                                   <button class="close-btn" onclick="closeModal('archive-announcement-modal-<?php echo $announcement['announcementID']; ?>')" aria-label="Close">Close</button>
                                                </div>
                                             </div>
                                             <div class="modal-body">
                                                <p>Are you sure you want to archive this announcement? <br> This action <span style="color: #d93025;">cannot be undone</span>.</p>
                                                <form method="POST">
                                                   <input type="hidden" name="announcementID" value="<?php echo $announcement['announcementID']; ?>">
                                                   <button type="submit" name="archiveAnnouncement">Confirm Archive</button>
                                                </form>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 <?php endforeach; ?>
                              <?php else: ?>
                                 <p class="no-announcements">No announcements available</p>
                              <?php endif; ?>
                           </div>
                           <div id="announcements-add-<?php echo $index; ?>" class="tab-content">
                              <form class="announcement-form" method="POST" enctype="multipart/form-data">
                                 <input type="hidden" name="teacherSectionID" value="<?php echo $assignment['teacherSectionID']; ?>">
                                 <label for="title" class="announcement-label">Title <span>*</span></label>
                                 <input type="text" name="title" placeholder="Enter announcement title" maxlength="100" required aria-label="Announcement Title">
                                 <label for="content" class="announcement-label">Announcement Content <span>*</span></label>
                                 <textarea name="content" placeholder="Enter announcement content" required aria-label="Announcement Content"></textarea>
                                 <label for="announcementFile" class="announcement-label">File <span>(PDF/JPG/PNG)*</span></label>
                                 <input type="file" name="announcementFile" accept=".pdf,.jpg,.png" aria-label="Upload Announcement File">
                                 <button type="submit" name="createAnnouncement">Post Announcement</button>
                              </form>
                           </div>
                           <div id="announcements-edit-<?php echo $index; ?>" class="tab-content">
                              <select class="announcement-select" id="announcement-select-<?php echo $index; ?>" onchange="loadEditAnnouncement(this.value, '<?php echo $index; ?>')" aria-label="Select Announcement to Edit">
                                 <option value="">Select Edit</option>
                                 <?php foreach ($assignment['announcements'] as $announcement): ?>
                                    <option value="<?php echo $announcement['announcementID']; ?>">
                                       <?php echo htmlspecialchars($announcement['title']); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                              <div id="edit-announcement-form-<?php echo $index; ?>" class="edit-announcement-form" style="display: none;">
                                 <!-- Edit form will be populated via JavaScript -->
                              </div>
                           </div>
                        </div>
                     </div>
                     <div id="file-viewer-modal" class="modal" role="dialog" aria-labelledby="file-viewer-title" style="display: none;">
                        <div class="modal-content" style="max-width: 1200px; width: 90%; max-height: 90%;">
                           <div class="modal-header">
                              <h3 id="file-viewer-title">File Preview</h3>
                              <div class="modal-actions">
                                 <button class="close-btn" onclick="closeFileViewer()" aria-label="Close">Close</button>
                              </div>
                           </div>
                           <div class="modal-body" style="text-align: center;">
                              <div id="file-viewer-content" class="file-preview-container"></div>
                           </div>
                        </div>
                     </div>
                  </div>
               <?php endforeach; ?>
            <?php else: ?>
               <div class="card">
                  <div class="card-body">
                     <p>No assignments found.</p>
                  </div>
               </div>
            <?php endif; ?>
         </div>
         <?php if ($totalPages > 1): ?>
            <div class="pagination">
               <?php
               $baseUrl = "professorDash.php?sectionID=" . urlencode($sectionFilter) . "&subjectID=" . urlencode($subjectFilter) . "&sort=" . urlencode($sortOrder);
               if ($search !== '') {
                  $baseUrl .= "&search=" . urlencode($search);
               }
               if ($currentPage > 1): ?>
                  <a href="<?php echo $baseUrl; ?>&page=<?php echo $currentPage - 1; ?>" aria-label="Previous Page">«</a>
               <?php else: ?>
                  <a class="disabled" aria-disabled="true">«</a>
               <?php endif; ?>
               <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <a href="<?php echo $baseUrl; ?>&page=<?php echo $i; ?>" class="<?php echo $i == $currentPage ? 'active' : ''; ?>" aria-label="Page <?php echo $i; ?>">
                     <?php echo $i; ?>
                  </a>
               <?php endfor; ?>
               <?php if ($currentPage < $totalPages): ?>
                  <a href="<?php echo $baseUrl; ?>&page=<?php echo $currentPage + 1; ?>" aria-label="Next Page">»</a>
               <?php else: ?>
                  <a class="disabled" aria-disabled="true">»</a>
               <?php endif; ?>
            </div>
         <?php endif; ?>
      </main>
   </section>
   <script src="./utils/script.js"></script>
   <script>
   document.addEventListener('DOMContentLoaded', function() {
      function openFileViewer(fileSrc, fileName, fileType) {
         const modal = document.getElementById('file-viewer-modal');
         const content = document.getElementById('file-viewer-content');
         const title = document.getElementById('file-viewer-title');
         if (modal && content && title) {
            content.innerHTML = '';
            title.textContent = `${fileName}`;
            if (fileType === 'application/pdf') {
               content.innerHTML = `<embed src="${fileSrc}" type="application/pdf" width="100%" height="100%" />`;
            } else if (fileType === 'image/jpeg' || fileType === 'image/png') {
               content.innerHTML = `<img src="${fileSrc}" alt="${fileName}" style="max-width: 100%; height: auto;" />`;
            }
            modal.style.display = 'flex';
            modal.style.animation = 'modalPop 0.3s ease';
            document.body.style.overflow = 'hidden';
         }
      }
      function closeFileViewer() {
         const modal = document.getElementById('file-viewer-modal');
         if (modal) {
            modal.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
               modal.style.display = 'none';
               document.body.style.overflow = 'auto';
            }, 300);
         }
      }
      function openModal(modalId) {
         const modal = document.getElementById(modalId);
         if (modal) {
            modal.style.display = 'flex';
            modal.style.animation = 'modalPop 0.3s ease';
            document.body.style.overflow = 'hidden';
         }
      }
      function closeModal(modalId) {
         const modal = document.getElementById(modalId);
         if (modal) {
            modal.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
               modal.style.display = 'none';
               document.body.style.overflow = 'auto';
            }, 300);
         }
      }
      function toggleFullscreen(modalId) {
         const modalContent = document.querySelector(`#${modalId} .modal-content`);
         if (modalContent) {
            modalContent.classList.toggle('fullscreen');
         }
      }
      function switchTab(tabId, container) {
         const tabButtons = container.querySelectorAll('.tab-button');
         const tabContents = container.querySelectorAll('.tab-content');
         tabButtons.forEach(btn => btn.classList.remove('active'));
         tabContents.forEach(content => content.classList.remove('active'));
         container.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
         container.querySelector(`#${tabId}`).classList.add('active');
      }
      // Fade out success notification after 10 seconds
      const successNotification = document.getElementById('success-notification');
      if (successNotification) {
         setTimeout(() => {
            successNotification.style.opacity = '0';
            setTimeout(() => {
               successNotification.remove();
            }, 300);
         }, 10000);
      }
      // Tab switching logic for main tabs
      document.querySelectorAll('.tab-container > .tab-buttons .tab-button').forEach(button => {
         button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            const card = this.closest('.card');
            switchTab(tabId, card);
         });
      });
      // Tab switching logic for announcement sub-tabs
      document.querySelectorAll('.tab-content .tab-buttons .tab-button').forEach(button => {
         button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            const announcementTab = this.closest('.tab-content');
            switchTab(tabId, announcementTab);
         });
      });
      // Load announcement data for edit
      window.loadEditAnnouncement = function(announcementId, index) {
         if (!announcementId) {
            document.getElementById(`edit-announcement-form-${index}`).style.display = 'none';
            return;
         }
         const announcements = <?php echo json_encode(array_map(function($a) {
            return [
               'announcementID' => $a['announcementID'],
               'title' => $a['title'],
               'content' => $a['content'],
               'fileName' => $a['fileName'],
               'filePath' => $a['filePath'],
               'fileType' => $a['fileType']
            ];
         }, $assignment['announcements'])); ?>;
         const announcement = announcements.find(a => a.announcementID == announcementId);
         if (announcement) {
            const isImage = ['image/jpeg', 'image/png'].includes(announcement.fileType);
            const isPdf = announcement.fileType === 'application/pdf';
            const formHtml = `
               <form class="edit-announcement-form" method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="announcementID" value="${announcement.announcementID}">
                  <label for="title" class="announcement-label">Title <span>*</span></label>
                  <input type="text" name="title" value="${announcement.title.replace(/"/g, '&quot;')}" placeholder="Enter announcement title" maxlength="100" required aria-label="Announcement Title">
                  <label for="content" class="announcement-label">Announcement <span>*</span></label>
                  <textarea name="content" placeholder="Enter announcement content" required aria-label="Announcement Content">${announcement.content.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                  ${announcement.filePath ? `
                     <div class="current-file">
                        <label class="announcement-label">Current File</label>
                        ${isImage ? `
                           <img src="${announcement.filePath}" alt="${announcement.fileName}" style="max-width: 150px; max-height: 150px; cursor: pointer;" onclick="openFileViewer('${announcement.filePath}', '${announcement.fileName}', '${announcement.fileType}')" aria-label="Preview Current Image">
                           <br>
                        ` : isPdf ? `
                           <button class="btn-preview-preview" type="button" onclick="openFileViewer('${announcement.filePath}', '${announcement.fileName}', '${announcement.fileType}')" aria-label="Preview Current PDF">
                              Preview
                           </button>
                        ` : ''}
                        <br><br>
                        <a href="${announcement.filePath}" class="btn-download" download aria-label="Download Current File">
                           ${announcement.fileName}
                        </a>
                     </div>
                  ` : '<p>No file currently uploaded</p>'}
                  <label class="announcement-label" for="announcementFile-${announcement.announcementID}">Upload New File <span>(PDF/JPG/PNG)*</span></label>
                  <input id="announcementFile-${announcement.announcementID}" type="file" name="announcementFile" accept=".pdf,.jpg,.png" aria-label="Upload Announcement File">
                  <button type="submit" name="updateAnnouncement">Update Announcement</button>
               </form>
            `;
            const formContainer = document.getElementById(`edit-announcement-form-${index}`);
            formContainer.innerHTML = formHtml;
            formContainer.style.display = 'block';
         }
      };
      window.selectEditAnnouncement = function(announcementId, index) {
         const select = document.getElementById(`announcement-select-${index}`);
         if (select) {
            select.value = announcementId;
            loadEditAnnouncement(announcementId, index);
            switchTab(`announcements-edit-${index}`, document.getElementById(`announcements-${index}`));
         }
      };
      // Single event listener for closing modals when clicking outside
      document.querySelectorAll('.modal').forEach(modal => {
         modal.addEventListener('click', function(e) {
            if (e.target === this) {
               closeModal(this.id);
            }
         });
      });
      // Expose functions globally
      window.openFileViewer = openFileViewer;
      window.closeFileViewer = closeFileViewer;
      window.openModal = openModal;
      window.closeModal = closeModal;
      window.toggleFullscreen = toggleFullscreen;
   });
   </script>
   <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</body>
</html>
<?php
   // Close database connection
   $dbConnection = null;
?>