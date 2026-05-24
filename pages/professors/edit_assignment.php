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

// Validate teacherSectionID and assignmentID
if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT) ||
    !isset($_GET['assignmentID']) || !filter_var($_GET['assignmentID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section or assignment ID.";
    header("Location: assignmentDash.php?teacherSectionID=" . (isset($_GET['teacherSectionID']) ? $_GET['teacherSectionID'] : ''));
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];
$assignmentID = (int)$_GET['assignmentID'];

// Verify teacherSectionID belongs to the professor
$checkStmt = $dbConnection->prepare("SELECT ts.sectionID, s.sectionName, s.sectionCode, sub.subjectName, sub.subjectCode
                                     FROM teacher_section ts
                                     JOIN sections s ON ts.sectionID = s.sectionID
                                     JOIN subjects sub ON ts.subjectID = sub.subjectID
                                     WHERE ts.teacherSectionID = :teacherSectionID AND ts.teacherID = :userID AND ts.archived = 0");
$checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
$checkStmt->execute();
$section = $checkStmt->fetch(PDO::FETCH_ASSOC);
if (!$section) {
    $_SESSION['error_message'] = "Invalid section assignment.";
    header("Location: professorDash.php");
    exit;
}

// Fetch assignment details
$assignmentStmt = $dbConnection->prepare("SELECT assignmentID, title, description, dueDate, maxScore, filePath, fileName
                                         FROM assignments
                                         WHERE assignmentID = :assignmentID AND teacherSectionID = :teacherSectionID AND archived = 0");
$assignmentStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
$assignmentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$assignmentStmt->execute();
$assignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC);
if (!$assignment) {
    $_SESSION['error_message'] = "Invalid or archived assignment.";
    header("Location: assignmentDash.php?teacherSectionID=$teacherSectionID");
    exit;
}

// Handle assignment editing
if (isset($_POST['editAssignment']) && isset($_POST['assignmentID']) && isset($_POST['title'])) {
    $assignmentID = filter_var($_POST['assignmentID'], FILTER_VALIDATE_INT);
    $title = trim($_POST['title']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $dueDate = !empty($_POST['dueDate']) ? $_POST['dueDate'] : null;
    $maxScore = isset($_POST['maxScore']) ? (int)$_POST['maxScore'] : 0;
    $removeFile = isset($_POST['removeFile']) && $_POST['removeFile'] === '1';

    if ($assignmentID && !empty($title) && $maxScore >= 0) {
        $checkAssignmentStmt = $dbConnection->prepare("SELECT assignmentID, filePath FROM assignments WHERE assignmentID = :assignmentID AND teacherSectionID = :teacherSectionID AND archived = 0");
        $checkAssignmentStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
        $checkAssignmentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $checkAssignmentStmt->execute();
        if ($checkAssignmentStmt->rowCount() > 0) {
            $existingAssignment = $checkAssignmentStmt->fetch(PDO::FETCH_ASSOC);
            $filePath = $existingAssignment['filePath'];
            $fileName = null;
            $fileType = null;
            $fileSize = null;

            // Handle file upload
            if (!$removeFile && isset($_FILES['assignmentFile']) && $_FILES['assignmentFile']['size'] > 0) {
                $uploadDir = '../../uploads/assignments/';
                $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                $maxFileSize = 10 * 1024 * 1024; // 10MB
                $file = $_FILES['assignmentFile'];
                if ($file['size'] <= $maxFileSize && in_array($file['type'], $allowedTypes)) {
                    $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($file['name']));
                    $targetPath = $uploadDir . $fileName;
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            $_SESSION['error_message'] = "Failed to create upload directory.";
                            header("Location: edit_assignment.php?teacherSectionID=$teacherSectionID&assignmentID=$assignmentID");
                            exit;
                        }
                    }
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        // Delete old file if exists
                        if ($filePath && file_exists($filePath)) {
                            unlink($filePath);
                        }
                        $filePath = $targetPath;
                        $fileType = $file['type'];
                        $fileSize = $file['size'];
                    } else {
                        $_SESSION['error_message'] = "Failed to upload file.";
                        header("Location: edit_assignment.php?teacherSectionID=$teacherSectionID&assignmentID=$assignmentID");
                        exit;
                    }
                } else {
                    $_SESSION['error_message'] = "Invalid file type or size. Allowed types: PDF, JPG, PNG. Max size: 10MB.";
                    header("Location: edit_assignment.php?teacherSectionID=$teacherSectionID&assignmentID=$assignmentID");
                    exit;
                }
            } elseif ($removeFile && $filePath) {
                // Remove existing file
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $filePath = null;
                $fileName = null;
                $fileType = null;
                $fileSize = null;
            }

            $assignmentStmt = $dbConnection->prepare("UPDATE assignments
                                                     SET title = :title, description = :description, dueDate = :dueDate,
                                                         maxScore = :maxScore, filePath = :filePath, fileName = :fileName,
                                                         fileType = :fileType, fileSize = :fileSize
                                                     WHERE assignmentID = :assignmentID");
            $assignmentStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
            $assignmentStmt->bindParam(':title', $title);
            $assignmentStmt->bindParam(':description', $description);
            $assignmentStmt->bindParam(':dueDate', $dueDate);
            $assignmentStmt->bindParam(':maxScore', $maxScore, PDO::PARAM_INT);
            $assignmentStmt->bindParam(':filePath', $filePath);
            $assignmentStmt->bindParam(':fileName', $fileName);
            $assignmentStmt->bindParam(':fileType', $fileType);
            $assignmentStmt->bindParam(':fileSize', $fileSize, PDO::PARAM_INT);
            if ($assignmentStmt->execute()) {
                $_SESSION['success_message'] = "Assignment updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update assignment.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid assignment.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid assignment data.";
    }
    header("Location: assignmentDash.php?teacherSectionID=$teacherSectionID");
    exit;
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
    <link rel="stylesheet" href="./utils/subjects_sidebar.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <script src="./utils/logout.js"></script>
    <title>Learnify - Edit Assignment</title>
    <style>
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --grey: #f3f4f6;
            --dark-grey: #9ca3af;
            --dark: #1f2937;
            --green: #10b981;
            --red: #ef4444;
            --purple: #8b5cf6;
        }

        .assignment-form {
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            margin: 10px auto;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .assignment-form h3 {
            margin: 0 0 1rem;
            color: var(--dark);
            font-size: clamp(1.2rem, 3vw, 2rem);
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            font-weight: 600;
        }

        label {
            font-weight: 600;
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.2rem);
            margin-bottom: 2px;
        }

        label span {
            color: var(--red);
        }

        .due-max {
            display: flex;
            flex-direction: row;
            gap: 10px;
        }

        .due-max .container,
        .assignment-form .container {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .assignment-form input[type="text"],
        .assignment-form input[type="date"],
        .assignment-form input[type="number"],
        .assignment-form textarea {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid var(--dark-grey);
            background-color: var(--grey);
            border-radius: 0.375rem;
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.2rem);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .assignment-form input[type="file"] {
            padding: 0.5rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            border: 1px solid var(--dark-grey);
        }

        .assignment-form input[type="file"]::file-selector-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            cursor: pointer;
        }

        .assignment-form input[type="file"]::file-selector-button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .assignment-form input[type="date"]::-webkit-calendar-picker-indicator {
            background-color: #FFCE26;
            padding: 2px;
            border-radius: 50%;
            cursor: pointer;
        }
        .assignment-form input[type="date"]::-webkit-calendar-picker-indicator:hover {
            background-color: #FFA500;
         }

        .assignment-form textarea {
            resize: vertical;
            min-height: 100px;
        }

        .assignment-form button {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.1rem);
            font-weight: 600;
        }

        .assignment-form button:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .btn-container {
            display: flex;
            justify-content: flex-end;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }

        .back-btn {
            background-color: var(--purple);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            text-decoration: none;
            float: right;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            margin: 2.5rem;
            transition: background-color 0.3s ease;
        }

        .back-btn:hover {
            background-color: #7c3aed;
        }

        .success-notification, .error-notification {
            background: #10b981;
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 0.375rem;
            text-align: center;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 600;
        }

        .error-notification {
            background-color: var(--red);
        }

        .modal {
            z-index: 1000000;
        }

        @media screen and  (max-width: 768px) {
            .assignment-form {
                margin: 1rem 0rem;
                padding: 1rem;
            }
        }

        @media  screen and (max-width: 480px) {
          .due-max {
            display: flex;
            flex-direction: column;
          }
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
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
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
                <img src="<?php echo htmlspecialchars($_SESSION['image']); ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small>Teacher</small>
                </div>
            </a>
        </nav>
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Edit Assignment</h1>
                    <ul class="breadcrumb">
                        <li><a href="./professorDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="assignmentDash.php?teacherSectionID=<?php echo $teacherSectionID; ?>">Assignment</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Edit</a></li>
                    </ul>
                </div>
                <a href="assignmentDash.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Assignment Management
                </a>
            </div>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
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
            <div class="assignment-form">
                <h3><?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="assignmentID" value="<?php echo $assignment['assignmentID']; ?>">
                    <label for="title">Title <span>*</span></label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($assignment['title']); ?>" placeholder="Enter Title" required maxlength="255" aria-label="Assignment Title">
                    <label for="description">Description <span>(Optional)</span></label>
                    <textarea name="description" placeholder="Enter Description" aria-label="Assignment Description"><?php echo htmlspecialchars($assignment['description'] ?: ''); ?></textarea>
                    <div class="due-max">
                        <div class="container">
                            <label for="dueDate">Due Date</label>
                            <input type="date" name="dueDate" value="<?php echo htmlspecialchars($assignment['dueDate'] ?: ''); ?>" aria-label="Due Date">
                        </div>
                        <div class="container">
                            <label for="maxScore">Maximum Score <span>*</span></label>
                            <input type="number" name="maxScore" value="<?php echo htmlspecialchars($assignment['maxScore']); ?>" placeholder="Enter Maximum Score" min="0" required aria-label="Maximum Score">
                        </div>
                    </div>
                    <div class="container">
                        <label for="assignmentFile">File Type</label>
                        <input style="cursor: pointer; background-color: var(--grey); padding: 10px; color: var(--dark);" type="file" name="assignmentFile" accept=".pdf,.jpg,.png" aria-label="Upload Assignment File">
                    </div>
                    <?php if ($assignment['fileName']): ?>
                        <div class="checkbox-container">
                            <input type="checkbox" name="removeFile" id="removeFile" value="1" aria-label="Remove current file">
                            <label for="removeFile">Remove current file: <?php echo htmlspecialchars($assignment['fileName']); ?></label>
                        </div>
                    <?php endif; ?>
                    <div class="btn-container">
                        <button type="submit" name="editAssignment" aria-label="Update Assignment">Update Assignment</button>
                    </div>
                </form>
            </div>
        </main>
    </section>
    <script src="./utils/script.js"></script>
</body>
</html>