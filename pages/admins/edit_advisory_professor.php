<?php
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}

require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Check if advisoryID is provided and fetch its details
if (isset($_GET['id'])) {
    $advisoryID = $_GET['id'];

    // Fetch current advisory details
    $sql = "SELECT * FROM advisory_professor_section WHERE advisoryID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $advisoryID, PDO::PARAM_INT);
    $stmt->execute();
    $advisory = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no advisory found, redirect with error message
    if (!$advisory) {
        $_SESSION['error_message'] = "Advisory teacher not found.";
        header("Location: enroll_advisory_professor.php");
        exit;
    }
}

// Update advisory professor, section, and subject on form submission
if (isset($_POST['updateAdvisoryProfessor'])) {
    $professorID = $_POST['professorID'] ?? '';
    $sectionID = $_POST['sectionID'] ?? '';
    $subjectID = $_POST['subjectID'] ?? '';

    // Validate inputs
    if (empty($professorID) || empty($sectionID) || empty($subjectID)) {
        $_SESSION['error_message'] = "Please select a teacher, section, and subject.";
        header("Location: edit_advisory_professor.php?id=$advisoryID");
        exit;
    }

    try {
        // Ensure the selected user is a professor
        $checkProfessor = "SELECT * FROM users WHERE userID = ? AND userType = 'Professor' AND archived = 0";
        $stmt = $dbConnection->prepare($checkProfessor);
        $stmt->bindParam(1, $professorID, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $_SESSION['error_message'] = "Selected user is not a professor or is archived.";
            header("Location: edit_advisory_professor.php?id=$advisoryID");
            exit;
        }

        // Ensure the selected section exists
        $checkSection = "SELECT * FROM sections WHERE sectionID = ? AND archived = 0";
        $stmt = $dbConnection->prepare($checkSection);
        $stmt->bindParam(1, $sectionID, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $_SESSION['error_message'] = "Selected section is invalid or archived.";
            header("Location: edit_advisory_professor.php?id=$advisoryID");
            exit;
        }

        // Ensure the selected subject exists
        $checkSubject = "SELECT * FROM subjects WHERE subjectID = ? AND archived = 0";
        $stmt = $dbConnection->prepare($checkSubject);
        $stmt->bindParam(1, $subjectID, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $_SESSION['error_message'] = "Selected subject is invalid or archived.";
            header("Location: edit_advisory_professor.php?id=$advisoryID");
            exit;
        }

        // Check for duplicate assignment (same professor, section, and subject)
        $checkDuplicate = "SELECT * FROM advisory_professor_section 
                          WHERE professorID = ? AND sectionID = ? AND subjectID = ? 
                          AND advisoryID != ? AND status = 'Active' AND archived = 0";
        $stmt = $dbConnection->prepare($checkDuplicate);
        $stmt->bindParam(1, $professorID, PDO::PARAM_INT);
        $stmt->bindParam(2, $sectionID, PDO::PARAM_INT);
        $stmt->bindParam(3, $subjectID, PDO::PARAM_INT);
        $stmt->bindParam(4, $advisoryID, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['error_message'] = "This teacher is already assigned to the selected section and subject.";
            header("Location: edit_advisory_professor.php?id=$advisoryID");
            exit;
        }

        // Update the advisory professor, section, and subject in the database
        $sql = "UPDATE advisory_professor_section SET professorID = ?, sectionID = ?, subjectID = ? WHERE advisoryID = ?";
        $stmt = $dbConnection->prepare($sql);
        $stmt->bindParam(1, $professorID, PDO::PARAM_INT);
        $stmt->bindParam(2, $sectionID, PDO::PARAM_INT);
        $stmt->bindParam(3, $subjectID, PDO::PARAM_INT);
        $stmt->bindParam(4, $advisoryID, PDO::PARAM_INT);

        // If the update is successful, redirect with success message
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Advisory teacher, section, and subject updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating advisory teacher, section, or subject. Please try again.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }

    // Redirect back to advisory professors list page
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
    <link rel="stylesheet" href="./utils/edit_track_strand.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
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
        .main-container {
            max-width: 600px;
            margin: 20px auto;
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--dark);
        }
        .form-group select {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            background-color: var(--grey);
            border: 1px solid var(--dark);
            color: var(--dark);
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        .form-group select:focus {
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
                    <h1>Edit Advisory Teacher</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./enroll_advisory_professor.php">Assign Advisory Teacher</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="edit_advisory_professor.php?id=<?php echo $advisoryID; ?>">Edit Advisory Teacher</a></li>
                    </ul>
                </div>
                <a href="./data_advisory_professor.php" class="btn-download">
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

            <!-- Main Container for Form -->
            <div class="main-container">
                <form action="edit_advisory_professor.php?id=<?php echo $advisory['advisoryID']; ?>" method="POST">
                    <div class="form-group">
                        <label for="professorID">Select Teacher</label>
                        <select name="professorID" id="professorID" required>
                            <option value="" disabled>-- Choose Teacher --</option>
                            <?php
                            // Fetch all professors and pre-select the current one
                            $query = "SELECT userID, firstName, lastName FROM users WHERE userType = 'Professor' AND archived = 0 ORDER BY firstName, lastName";
                            $stmt = $dbConnection->prepare($query);
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($row['userID'] == $advisory['professorID']) ? 'selected' : '';
                                echo "<option value='{$row['userID']}' {$selected}>" . htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sectionID">Select Section</label>
                        <select name="sectionID" id="sectionID" required>
                            <option value="" disabled>-- Choose Section --</option>
                            <?php
                            // Fetch all sections and pre-select the current one
                            $query = "SELECT sectionID, sectionName, sectionCode FROM sections WHERE archived = 0 ORDER BY sectionName";
                            $stmt = $dbConnection->prepare($query);
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($row['sectionID'] == $advisory['sectionID']) ? 'selected' : '';
                                echo "<option value='{$row['sectionID']}' {$selected}>" . htmlspecialchars($row['sectionName'] . ' ' . $row['sectionCode']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subjectID">Select Subject</label>
                        <select name="subjectID" id="subjectID" required>
                            <option value="" disabled>-- Choose Subject --</option>
                            <?php
                            // Fetch all subjects and pre-select the current one
                            $query = "SELECT subjectID, subjectName, subjectCode FROM subjects WHERE archived = 0 ORDER BY subjectName";
                            $stmt = $dbConnection->prepare($query);
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($row['subjectID'] == $advisory['subjectID']) ? 'selected' : '';
                                echo "<option value='{$row['subjectID']}' {$selected}>" . htmlspecialchars($row['subjectName'] . ' (' . $row['subjectCode'] . ')') . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Submit button for saving changes -->
                    <button type="submit" name="updateAdvisoryProfessor" class="submit-btn">Save Changes</button>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>