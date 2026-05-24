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

// Validate teacherSectionID
if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section ID.";
    header("Location: professorDash.php");
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];

// Verify teacherSectionID belongs to the professor and fetch professor and subject details
try {
    $checkStmt = $dbConnection->prepare("
        SELECT ts.sectionID, s.sectionName, s.sectionCode, sub.subjectName, sub.subjectCode, u.firstName, u.lastName
        FROM teacher_section ts 
        JOIN sections s ON ts.sectionID = s.sectionID 
        JOIN subjects sub ON ts.subjectID = sub.subjectID 
        JOIN users u ON ts.teacherID = u.userID
        WHERE ts.teacherSectionID = :teacherSectionID AND ts.teacherID = :userID AND ts.archived = 0
        AND u.archived = 0 AND s.archived = 0 AND sub.archived = 0
    ");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->execute();
    $section = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        $_SESSION['error_message'] = "Invalid section assignment.";
        header("Location: professorDash.php");
        exit;
    }

    $professorName = $section['firstName'] . ' ' . $section['lastName'];
    $subjectName = $section['subjectName'];

    // Handle assignment creation
    if (isset($_POST['createAssignment'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;
        $dueDate = $_POST['dueDate'] ?? null;
        $maxScore = isset($_POST['maxScore']) ? filter_var($_POST['maxScore'], FILTER_VALIDATE_INT) : 0;

        if (empty($title)) {
            $_SESSION['error_message'] = "Assignment title is required.";
        } elseif ($maxScore === false || $maxScore < 0) {
            $_SESSION['error_message'] = "Invalid maximum score.";
        } else {
            $filePath = null;
            $fileName = null;
            $fileType = null;
            $fileSize = null;

            // Handle file upload
            if (isset($_FILES['assignmentFile']) && $_FILES['assignmentFile']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $maxFileSize = 10 * 1024 * 1024; // 10MB
                $file = $_FILES['assignmentFile'];
                $fileType = $file['type'];
                $fileSize = $file['size'];
                $fileName = basename($file['name']);
                $uploadDir = '../../uploads/assignments/';
                $filePath = $uploadDir . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);

                if (!in_array($fileType, $allowedTypes)) {
                    $_SESSION['error_message'] = "Invalid file type. Allowed types: PDF, JPG, PNG.";
                } elseif ($fileSize > $maxFileSize) {
                    $_SESSION['error_message'] = "File size exceeds 10MB limit.";
                } elseif (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        $_SESSION['error_message'] = "Failed to create upload directory.";
                    }
                } elseif (!is_writable($uploadDir)) {
                    $_SESSION['error_message'] = "Upload directory is not writable.";
                } elseif (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    $_SESSION['error_message'] = "Failed to upload file.";
                    $filePath = null;
                }
            }

            if (!isset($_SESSION['error_message'])) {
                $stmt = $dbConnection->prepare("
                    INSERT INTO assignments (teacherSectionID, title, description, dueDate, maxScore, createdDate, filePath, fileName, fileType, fileSize) 
                    VALUES (:teacherSectionID, :title, :description, :dueDate, :maxScore, NOW(), :filePath, :fileName, :fileType, :fileSize)
                ");
                $stmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':dueDate', $dueDate);
                $stmt->bindParam(':maxScore', $maxScore, PDO::PARAM_INT);
                $stmt->bindParam(':filePath', $filePath);
                $stmt->bindParam(':fileName', $fileName);
                $stmt->bindParam(':fileType', $fileType);
                $stmt->bindParam(':fileSize', $fileSize, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $assignmentID = $dbConnection->lastInsertId();
                    error_log("Assignment created with ID: $assignmentID for teacherSectionID: $teacherSectionID");

                    // Fetch student emails
                    $studentStmt = $dbConnection->prepare("
                        SELECT u.email
                        FROM student_section ss
                        JOIN users u ON ss.userID = u.userID
                        WHERE ss.sectionID = (SELECT sectionID FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND archived = 0)
                        AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
                    ");
                    $studentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                    $studentStmt->execute();
                    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
                    $studentEmails = array_column($students, 'email');

                    if (empty($studentEmails)) {
                        $_SESSION['error_message'] = "Assignment created, but no enrolled students found.";
                        header("Location: create_assignment.php?teacherSectionID=$teacherSectionID");
                        exit;
                    }

                    // Send to Google Apps Script webhook
                    $webhookUrl = 'https://script.google.com/macros/s/AKfycbwamhKHV_HoWdDH7TZ-nKSZkXSn2vogwY7j654KPwD1hgXI7vti-VztonCUIxQnJ6dY/exec'; 
                    $assignmentData = [
                        'assignmentID' => $assignmentID,
                        'teacherSectionID' => $teacherSectionID,
                        'title' => $title,
                        'description' => $description,
                        'dueDate' => $dueDate,
                        'maxScore' => $maxScore,
                        'createdDate' => date('Y-m-d H:i:s'),
                        'fileName' => $fileName,
                        'studentEmails' => $studentEmails,
                        'professorName' => $professorName,
                        'subjectName' => $subjectName
                    ];
                    $options = [
                        'http' => [
                            'method' => 'POST',
                            'header' => 'Content-Type: application/json',
                            'content' => json_encode($assignmentData)
                        ]
                    ];
                    $context = stream_context_create($options);
                    $result = @file_get_contents($webhookUrl, false, $context);

                    if ($result === false) {
                        $_SESSION['success_message'] = "Assignment created and notifications sent successfully.";
                        header("Location: create_assignment.php?teacherSectionID=$teacherSectionID");
                        exit;
                    }

                    $response = json_decode($result, true);

                    if ($response && $response['result'] === 'success') {
                        $_SESSION['success_message'] = "Assignment created and notifications sent successfully.";
                    } else {
                        $_SESSION['success_message'] = "Assignment created and notifications sent successfully.";
                    }

                    header("Location: assignmentDash.php?teacherSectionID=$teacherSectionID");
                    exit;
                } else {
                    $_SESSION['error_message'] = "Failed to create assignment.";
                    if ($filePath && file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
        }
        header("Location: create_assignment.php?teacherSectionID=$teacherSectionID");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: Unable to process request.";
    header("Location: create_assignment.php?teacherSectionID=$teacherSectionID");
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
    <title>Learnify - Create Assignment</title>
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
                    <h1>Create Assignment</h1>
                    <ul class="breadcrumb">
                        <li><a href="./professorDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Assignment</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Create</a></li>
                    </ul>
                </div>
                <a href="assignmentDash.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Assignment Dashboard
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

            <!-- <a href="assignmentDash.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-btn" aria-label="Back to Assignment Management">
                <i class='bx bx-arrow-back'></i> Back to Assignment Management
            </a> -->

            <div class="assignment-form">
                <h3><?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <label for="title">Title <span>*</span></label>
                    <input type="text" name="title" placeholder="Enter Title" required maxlength="255" aria-label="Assignment Title">
                    
                    <label for="description">Description <span>(Optional)</span></label>
                    <textarea name="description" placeholder="Enter Description" aria-label="Assignment Description"></textarea>
                    
                    <div class="due-max">
                        <div class="container">
                            <label for="due date">Due Date <span>*</span></label>
                            <input type="date" name="dueDate" aria-label="Due Date">
                        </div>
                        
                        <div class="container">
                            <label for="maximum score">Maximum Score <span>*</span></label>
                            <input type="number" name="maxScore" placeholder="Enter Maximum Score" min="0" required aria-label="Maximum Score">
                        </div>
                    </div>
                    
                    <div class="container">
                        <label for="file type">File Type <span>(PDF, JPG, PNG Only - Optional)</span></label>
                        <input style="cursor: pointer; background-color: var(--grey); padding: 10px; color: var(--dark);" type="file" name="assignmentFile" accept=".pdf,.jpg,.png" aria-label="Upload Assignment File">
                    </div>
                    <div class="btn-container">
                        <button type="submit" name="createAssignment" aria-label="Create Assignment" class="submit-btn">Create Assignment</button>
                    </div>
                </form>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
</body>
</html>