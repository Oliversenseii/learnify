<?php
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}

require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

$errors = [];

if (isset($_GET['id'])) {
    $subjectID = $_GET['id'];

    // Fetch the subject details based on subjectID
    $sql = "SELECT s.subjectID, s.subjectCode, s.subjectName, s.subjectType, s.yearLevel, s.semester, s.strandID, ts.trackName, ts.strandName 
            FROM subjects s 
            LEFT JOIN track_strands ts ON s.strandID = ts.strandID 
            WHERE s.subjectID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $subjectID, PDO::PARAM_INT);
    $stmt->execute();
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {
        $_SESSION['error_message'] = "Subject not found.";
        header("Location: subjects.php");
        exit;
    }
}

// Fetch track-strands for dropdown
$strandSQL = "SELECT strandID, trackName, strandName FROM track_strands WHERE archived = 0 ORDER BY trackName, strandName";
$strandStmt = $dbConnection->prepare($strandSQL);
$strandStmt->execute();
$strands = $strandStmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['updateSubject'])) {
    $subjectCode = trim($_POST['subjectCode'] ?? '');
    $subjectName = trim($_POST['subjectName'] ?? '');
    $subjectType = $_POST['subjectType'] ?? '';
    $yearLevel = $_POST['yearLevel'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $strandID = $_POST['strandID'] ?? '';

    // Validation
    if (empty($subjectCode)) {
        $errors['subjectCode'] = "⚠️ Subject Code is required.";
    }
    if (empty($subjectName)) {
        $errors['subjectName'] = "⚠️ Subject Name is required.";
    }
    if (empty($subjectType)) {
        $errors['subjectType'] = "⚠️ Subject Type is required.";
    }
    if (empty($yearLevel)) {
        $errors['yearLevel'] = "⚠️ Year Level is required.";
    }
    if (empty($semester)) {
        $errors['semester'] = "⚠️ Semester is required.";
    }
    if (empty($strandID)) {
        $errors['strandID'] = "⚠️ Track and Strand are required.";
    }

    // Check for duplicate subject code (excluding the current subject)
    if (empty($errors)) {
        $checkSQL = "SELECT * FROM subjects WHERE subjectCode = ? AND subjectID != ? AND archived = 0";
        $checkStmt = $dbConnection->prepare($checkSQL);
        $checkStmt->bindParam(1, $subjectCode);
        $checkStmt->bindParam(2, $subjectID, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->rowCount() > 0) {
            $errors['subjectCode'] = "⚠️ Subject Code already exists.";
        }
    }

    if (empty($errors)) {
        // Update the subject in the database
        $sql = "UPDATE subjects SET subjectCode = ?, subjectName = ?, subjectType = ?, yearLevel = ?, semester = ?, strandID = ? WHERE subjectID = ?";
        $stmt = $dbConnection->prepare($sql);
        $stmt->bindParam(1, $subjectCode);
        $stmt->bindParam(2, $subjectName);
        $stmt->bindParam(3, $subjectType);
        $stmt->bindParam(4, $yearLevel);
        $stmt->bindParam(5, $semester);
        $stmt->bindParam(6, $strandID, PDO::PARAM_INT);
        $stmt->bindParam(7, $subjectID, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Subject updated successfully!";
            // Refresh subject data to display updated values
            $sql = "SELECT s.subjectID, s.subjectCode, s.subjectName, s.subjectType, s.yearLevel, s.semester, s.strandID, ts.trackName, ts.strandName 
                    FROM subjects s 
                    LEFT JOIN track_strands ts ON s.strandID = ts.strandID 
                    WHERE s.subjectID = ?";
            $stmt = $dbConnection->prepare($sql);
            $stmt->bindParam(1, $subjectID, PDO::PARAM_INT);
            $stmt->execute();
            $subject = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $_SESSION['error_message'] = "Error updating subject. Please try again.";
        }
    }
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
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify</title>
    <style>
        .main-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .main-container h2 {
            margin-bottom: 20px;
			border-bottom: 1px solid #ccc;
			padding-bottom: 10px;
            font-size: clamp(1.9rem, 3vw, 2rem);
            color: var(--dark);
            text-align: left;
        }

        .main-container form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .form-group label {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            margin-bottom: 5px;
        }

        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: var(--grey);
            color: var(--dark);
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
            outline: none;
        }

        label {
            font-weight: 600;
        }

        .submit-btn {
            padding: 10px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: none;
            border-radius: 5px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .success-notification,
        .error-notification {
            max-width: 500px;
            margin: 10px auto;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .error-text {
            color: #dc3545;
            font-size: 0.9rem;
            text-align: left;
            margin-top: -10px;
            margin-bottom: 10px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                max-width: 90%;
                margin: 10px auto;
                padding: 15px;
            }

            .main-container h2 {
                font-size: 1.3rem;
            }

            .form-group input,
            .form-group select,
            .submit-btn {
                font-size: 0.9rem;
                padding: 8px;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                max-width: 95%;
                padding: 10px;
            }

            .main-container h2 {
                font-size: 1.2rem;
            }

            .form-group label {
                font-size: 0.9rem;
            }

            .form-group input,
            .form-group select,
            .submit-btn {
                font-size: 0.8rem;
                padding: 7px;
            }

            .success-notification,
            .error-notification {
                max-width: 95%;
                padding: 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./adminDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./message_professor.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Teachers</span>
                </a>
            </li>
            <li>
                <a href="./registration.php">
                    <i class='bx bx-user-plus'></i>
                    <span class="text">Registration</span>
                </a>
            </li>
            <li>
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li>
                <a href="./enroll_student_section.php">
                    <i class='bx bxs-user-check'></i>
                    <span class="text">Enroll Student Section</span>
                </a>
            </li>
            <li>
                <a href="./enroll_teacher_section.php">
                    <i class='bx bxs-user-check'></i>
                    <span class="text">Assign Teacher Schedule</span>
                </a>
            </li>
            <li>
                <a href="./game.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Games</span>
                </a>
            </li>
            <li>
                <a href="./admin_calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Academic Calendar</span>
                </a>
            </li>
            <li>
                <a href="./grading.php">
                    <i class='bx bxs-book-content'></i>
                    <span class="text">Student Grades</span>
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
    <!-- SIDEBAR -->

    <?php require_once './view/modal.php' ?>    

    <!-- CONTENT -->
    <section id="content">
        <!-- NAVBAR -->
        <nav>
            <i class='bx bx-menu' ></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
                <img src="<?php echo isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <p><?php echo $_SESSION['firstName']; ?></p>
                    <small><?php echo $_SESSION['userType']; ?></small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Subjects</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./subjects.php">Data</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Edit</a></li>
                    </ul>
                </div>
                <a href="data_subjects.php" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
                    <span class="text">Back</span>
                </a>
            </div>

            <!-- Success or Error Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']); 
                    ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']); 
                    ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="error-notification">
                    <?php foreach ($errors as $error): ?>
                        <div class="error-text"><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="main-container">
                <h2>Edit Subjects</h2>
                <form action="edit_subjects.php?id=<?php echo $subject['subjectID']; ?>" method="POST">
                    <div class="form-group">
                        <label for="subjectCode">Subject Code</label>
                        <input type="text" name="subjectCode" id="subjectCode" value="<?php echo htmlspecialchars($subjectCode ?? $subject['subjectCode']); ?>" required>
                        <?php if (isset($errors['subjectCode'])): ?>
                            <div class="error-text"><?php echo $errors['subjectCode']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="subjectName">Subject Name</label>
                        <input type="text" name="subjectName" id="subjectName" value="<?php echo htmlspecialchars($subjectName ?? $subject['subjectName']); ?>" required>
                        <?php if (isset($errors['subjectName'])): ?>
                            <div class="error-text"><?php echo $errors['subjectName']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="subjectType">Subject Type</label>
                        <select name="subjectType" id="subjectType" required>
                            <option value="" disabled <?php echo !isset($subjectType) && $subject['subjectType'] == '' ? 'selected' : ''; ?>>- Select Subject Type -</option>
                            <option value="Core Subject" <?php echo (isset($subjectType) ? $subjectType : $subject['subjectType']) == 'Core Subject' ? 'selected' : ''; ?>>Core Subject</option>
                            <option value="Applied Subject" <?php echo (isset($subjectType) ? $subjectType : $subject['subjectType']) == 'Applied Subject' ? 'selected' : ''; ?>>Applied Subject</option>
                            <option value="Specialized Subject" <?php echo (isset($subjectType) ? $subjectType : $subject['subjectType']) == 'Specialized Subject' ? 'selected' : ''; ?>>Specialized Subject</option>
                        </select>
                        <?php if (isset($errors['subjectType'])): ?>
                            <div class="error-text"><?php echo $errors['subjectType']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="yearLevel">Year Level</label>
                        <select name="yearLevel" id="yearLevel" required>
                            <option value="" disabled <?php echo !isset($yearLevel) && $subject['yearLevel'] == '' ? 'selected' : ''; ?>>- Select Year Level -</option>
                            <option value="Grade 11" <?php echo (isset($yearLevel) ? $yearLevel : $subject['yearLevel']) == 'Grade 11' ? 'selected' : ''; ?>>Grade 11</option>
                            <option value="Grade 12" <?php echo (isset($yearLevel) ? $yearLevel : $subject['yearLevel']) == 'Grade 12' ? 'selected' : ''; ?>>Grade 12</option>
                        </select>
                        <?php if (isset($errors['yearLevel'])): ?>
                            <div class="error-text"><?php echo $errors['yearLevel']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select name="semester" id="semester" required>
                            <option value="" disabled <?php echo !isset($semester) && $subject['semester'] == '' ? 'selected' : ''; ?>>- Select Semester -</option>
                            <option value="1st Sem" <?php echo (isset($semester) ? $semester : $subject['semester']) == '1st Sem' ? 'selected' : ''; ?>>1st Sem</option>
                            <option value="2nd Sem" <?php echo (isset($semester) ? $semester : $subject['semester']) == '2nd Sem' ? 'selected' : ''; ?>>2nd Sem</option>
                        </select>
                        <?php if (isset($errors['semester'])): ?>
                            <div class="error-text"><?php echo $errors['semester']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="strandID">Track and Strand</label>
                        <select name="strandID" id="strandID" required>
                            <option value="" disabled <?php echo !isset($strandID) && empty($subject['strandID']) ? 'selected' : ''; ?>>- Select Track and Strand -</option>
                            <?php foreach ($strands as $strand): ?>
                                <option value="<?php echo $strand['strandID']; ?>" <?php echo (isset($strandID) ? $strandID : $subject['strandID']) == $strand['strandID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($strand['trackName'] . ' - ' . $strand['strandName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['strandID'])): ?>
                            <div class="error-text"><?php echo $errors['strandID']; ?></div>
                        <?php endif; ?>
                        <?php if (empty($strands)): ?>
                            <div class="error-text">⚠️ No tracks and strands available. Please add them first.</div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="updateSubject" class="submit-btn">Save</button>
                </form>
            </div>
        </main>
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>