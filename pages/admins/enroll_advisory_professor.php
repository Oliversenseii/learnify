<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

if (isset($_POST['enrollProfessor'])) {
    $professorID = $_POST['professorID'] ?? '';
    $sectionID = $_POST['sectionID'] ?? '';
    $subjectID = $_POST['subjectID'] ?? '';

    if (empty($professorID) || empty($sectionID) || empty($subjectID)) {
        $_SESSION['error_message'] = "Please select a teacher, section, and subject.";
        header("Location: enroll_advisory_professor.php");
        exit;
    }

    try {
        // Ensure the selected user is a professor
        $checkProfessor = "SELECT * FROM users WHERE userID = ? AND userType = 'Professor' AND archived = 0";
        $stmt = $dbConnection->prepare($checkProfessor);
        $stmt->bindParam(1, $professorID, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $_SESSION['error_message'] = "Selected user is not a teacher or is archived.";
            header("Location: enroll_advisory_professor.php");
            exit;
        }

        // Ensure the selected subject exists and is not archived
        $checkSubject = "SELECT * FROM subjects WHERE subjectID = ? AND archived = 0";
        $stmt = $dbConnection->prepare($checkSubject);
        $stmt->bindParam(1, $subjectID, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $_SESSION['error_message'] = "Selected subject is invalid or archived.";
            header("Location: enroll_advisory_professor.php");
            exit;
        }

        // Check if professor is already assigned to the section and subject
        $checkSql = "SELECT * FROM advisory_professor_section WHERE professorID = ? AND sectionID = ? AND subjectID = ? AND status = 'Active' AND archived = 0";
        $stmt = $dbConnection->prepare($checkSql);
        $stmt->bindParam(1, $professorID, PDO::PARAM_INT);
        $stmt->bindParam(2, $sectionID, PDO::PARAM_INT);
        $stmt->bindParam(3, $subjectID, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['error_message'] = "Teacher is already assigned to this section and subject.";
        } else {
            // Assign the professor to the section and subject
            $sql = "INSERT INTO advisory_professor_section (professorID, sectionID, subjectID, assignedDate, status) 
                    VALUES (?, ?, ?, NOW(), 'Active')";
            $stmt = $dbConnection->prepare($sql);
            $stmt->bindParam(1, $professorID, PDO::PARAM_INT);
            $stmt->bindParam(2, $sectionID, PDO::PARAM_INT);
            $stmt->bindParam(3, $subjectID, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Teacher assigned successfully.";
            } else {
                $_SESSION['error_message'] = "Error assigning teacher. Please try again.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }

    header("Location: enroll_advisory_professor.php");
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
    <link rel="stylesheet" href="./utils/track_strand.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/modules/kdakwd.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify</title>
    <style>
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
        .success-notification, .error-notification {
            padding: 10px;
            margin: 20px auto;
            max-width: 600px;
            border-radius: 5px;
            color: white;
            text-align: center;
        }
        .success-notification {
            background-color: #28a745;
        }
        .error-notification {
            background-color: #dc3545;
        }
        .btn-download {
            background-color: #3C91E6;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s ease;
        }
        .btn-download:hover {
            background-color: #0056b3;
        }
        .enroll-form {
            max-width: 600px;
            margin: 20px auto;
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .enroll-form .enroll-form {
            box-shadow: 0 1px 1px 6px var(--grey);
        }
        .form-title {
            margin-bottom: 20px;
            color: var(--dark);
            font-size: 24px;
            text-align: center;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--dark);
        }
        .form-select {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            background-color: var(--grey);
            border: 1px solid var(--dark);
            color: var(--dark);
            box-sizing: border-box;
            transition: border-color 0.3s ease;
            margin-bottom: 20px;
        }
        .form-select:focus {
            border-color: #3C91E6;
            outline: none;
        }
        .submit-btn {
            background-color: #3C91E6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: block;
            margin: 20px auto;
        }
        .submit-btn:hover {
            background-color: #0056b3;
        }
        .no-sections {
            text-align: center;
            color: #342E37;
            font-size: 16px;
            margin: 20px 0;
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
            <i class='bx bx-menu'></i>
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
                    <h1>Assign Advisory Teacher</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="enroll_advisory_professor.php">Assign Advisory Teacher</a></li>
                    </ul>
                </div>
                <a href="data_advisory_professor.php" class="btn-download">
                    <i class="bx bxs-show"></i>
                    <span class="text">View Records</span>
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

            <!-- Assignment Form -->
            <div class="enroll-form">
                <h2 class="form-title">Assign Advisory Teacher</h2>
                <form action="enroll_advisory_professor.php" method="POST" class="enroll-form">
                    <label for="professorID" class="form-label">Select Teacher</label>
                    <select name="professorID" id="professorID" class="form-select" required>
                        <option value="" disabled selected>- Select Teacher -</option>
                        <?php
                        $query = "SELECT userID, firstName, lastName FROM users 
                                  WHERE userType = 'Professor' AND archived = 0 
                                  ORDER BY firstName, lastName";
                        $stmt = $dbConnection->prepare($query);
                        $stmt->execute();
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$row['userID']}'>" . htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) . "</option>";
                        }
                        ?>
                    </select>

                    <label for="sectionID" class="form-label">Select Section</label>
                    <select name="sectionID" id="sectionID" class="form-select" required>
                        <option value="" disabled selected>- Select Section -</option>
                        <?php
                        $query = "SELECT sectionID, sectionName, sectionCode FROM sections 
                                  WHERE archived = 0 
                                  ORDER BY sectionName";
                        $stmt = $dbConnection->prepare($query);
                        $stmt->execute();
                        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($sections)) {
                            echo "<option value='' disabled>No sections available</option>";
                        } else {
                            foreach ($sections as $row) {
                                echo "<option value='{$row['sectionID']}'>" . htmlspecialchars($row['sectionName'] . ' ' . $row['sectionCode']) . "</option>";
                            }
                        }
                        ?>
                    </select>

                    <label for="subjectID" class="form-label">Select Subject</label>
                    <select name="subjectID" id="subjectID" class="form-select" required>
                        <option value="" disabled selected>- Select Subject -</option>
                        <?php
                        $query = "SELECT subjectID, subjectName, subjectCode FROM subjects 
                                  WHERE archived = 0 
                                  ORDER BY subjectName";
                        $stmt = $dbConnection->prepare($query);
                        $stmt->execute();
                        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($subjects)) {
                            echo "<option value='' disabled>No subjects available</option>";
                        } else {
                            foreach ($subjects as $row) {
                                echo "<option value='{$row['subjectID']}'>" . htmlspecialchars($row['subjectName'] . ' (' . $row['subjectCode'] . ')') . "</option>";
                            }
                        }
                        ?>
                    </select>

                    <?php if (empty($sections) || empty($subjects)): ?>
                        <p class="no-sections">No sections or subjects are available for assignment.</p>
                    <?php endif; ?>

                    <button type="submit" name="enrollProfessor" class="submit-btn" <?php echo (empty($sections) || empty($subjects)) ? 'disabled' : ''; ?>>Assign Teacher</button>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>