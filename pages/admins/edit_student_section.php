<?php
session_start();

if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']); 

require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

$academicSessions = ['2025 - 2026', '2026 - 2027', '2027 - 2028', '2028 - 2029', '2029 - 2030'];

if (isset($_GET['id'])) {
    $studentSectionID = (int)$_GET['id'];

    $sql = "
        SELECT ss.*, u.firstName, u.lastName, u.image, s.sectionName, s.sectionCode 
        FROM student_section ss
        JOIN users u ON ss.userID = u.userID
        JOIN sections s ON ss.sectionID = s.sectionID
        WHERE ss.studentSectionID = ?
    ";

    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $studentSectionID, PDO::PARAM_INT);
    $stmt->execute();
    $studentSection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$studentSection) {
        $_SESSION['error_message'] = "Student section not found.";
        header("Location: data_student_section.php?academic_session=" . urlencode($studentSection['academicSession']));
        exit;
    }
}

// Fetch all sections (section name and section code)
$sectionsSQL = "SELECT sectionID, sectionName, sectionCode FROM sections WHERE archived = 0";
$sectionsStmt = $dbConnection->prepare($sectionsSQL);
$sectionsStmt->execute();
$sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['updateStudentSection'])) {
    $sectionName = trim($_POST['sectionName'] ?? '');
    $status = $_POST['status'] ?? '';
    $academicSession = $_POST['academicSession'] ?? '';

    // Validation
    if (empty($sectionName)) {
        $_SESSION['error_message'] = "Section name is required.";
    } elseif (empty($status)) {
        $_SESSION['error_message'] = "Status is required.";
    } elseif (empty($academicSession) || !in_array($academicSession, $academicSessions)) {
        $_SESSION['error_message'] = "Invalid academic session selected.";
    } else {
        // Fetch sectionID based on the sectionName
        $sectionSQL = "SELECT sectionID FROM sections WHERE CONCAT(sectionName, ' ', sectionCode) = ?";
        $sectionStmt = $dbConnection->prepare($sectionSQL);
        $sectionStmt->bindParam(1, $sectionName);
        $sectionStmt->execute();
        $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);

        if ($section) {
            try {
                $sql = "UPDATE student_section SET sectionID = ?, status = ?, academicSession = ? WHERE studentSectionID = ?";
                $stmt = $dbConnection->prepare($sql);
                $stmt->bindParam(1, $section['sectionID'], PDO::PARAM_INT);
                $stmt->bindParam(2, $status, PDO::PARAM_STR);
                $stmt->bindParam(3, $academicSession, PDO::PARAM_STR);
                $stmt->bindParam(4, $studentSectionID, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Student section updated successfully!";
                    // Refresh student section data
                    $sql = "
                        SELECT ss.*, u.firstName, u.lastName, s.sectionName, s.sectionCode 
                        FROM student_section ss
                        JOIN users u ON ss.userID = u.userID
                        JOIN sections s ON ss.sectionID = s.sectionID
                        WHERE ss.studentSectionID = ?
                    ";
                    $stmt = $dbConnection->prepare($sql);
                    $stmt->bindParam(1, $studentSectionID, PDO::PARAM_INT);
                    $stmt->execute();
                    $studentSection = $stmt->fetch(PDO::FETCH_ASSOC);
                    header("Location: data_student_section.php?academic_session=" . urlencode($academicSession));
                    exit;
                } else {
                    $_SESSION['error_message'] = "Error updating student section. Please try again.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error updating student section: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = "Invalid section selected.";
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
    <link rel="stylesheet" href="./utils/edit_section.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Edit Student Section - Learnify</title>
    <style>
        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --light: #F7FAFC;
            --grey: #f5f5f5;
            --dark: #342E37;
            --transition: all 0.3s ease;
            --white: #FFFFFF;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --red: #dc3545;
            --red-dark: #c82333;
        }

        .main-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .main-container form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
        }

        .form-group label {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            margin-bottom: 5px;
            font-weight: bold;
        }

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

        .form-group select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
            outline: none;
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
            color: white;
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .error-notification {
            background: linear-gradient(135deg, var(--red), var(--red-dark));
        }

        .student_data {
            display: flex; 
            align-items: center; 
            gap: 10px;
            background-color: var(--grey);
            border-radius: 5px;
            width: 100%;
            padding: 10px;
        }

        .back-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
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
            font-size: clamp(1.1rem, 3vw, 1.3rem);
        }

        @media (max-width: 768px) {
            .main-container {
                max-width: 90%;
                margin: 10px auto;
                padding: 15px;
            }

            .form-group select,
            .submit-btn {
                font-size: 0.9rem;
                padding: 8px;
            }

            .success-notification,
            .error-notification {
                max-width: 90%;
                padding: 10px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                max-width: 95%;
                padding: 10px;
            }

            .form-group label {
                font-size: 0.9rem;
            }

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

        .main-container h2 {
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            font-size: clamp(1.8rem, 3vw, 2rem);
            color: var(--dark);
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
                    <span class="text">Profile</span>
                </a>
            </li>
            <li>
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li class="active">
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
                    <h1>Edit Student Section</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Enroll</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="data_student_section.php">Data</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Edit</a></li>
                    </ul>
                </div>
                <a href="data_student_section.php?academic_session=<?php echo urlencode($studentSection['academicSession']); ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Data
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

            <div class="main-container">
                <h2>Edit Student Section</h2>
                <form action="edit_student_section.php?id=<?php echo $studentSection['studentSectionID']; ?>" method="POST">
                    <div class="form-group">
                        <label for="fullName">Student</label>
                        <div class="student_data">
                            <img src="<?php echo !empty($studentSection['image']) ? $studentSection['image'] : './img/noprofile.png'; ?>" 
                                alt="Student Image" 
                                style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                            <span style="font-size: clamp(1.1rem, 3vw, 1.2rem); font-weight: 600; color: var(--dark); text-transform: uppercase;">
                                <?php echo htmlspecialchars($studentSection['lastName'] . ', ' . $studentSection['firstName']); ?>
                            </span>
                        </div>
                        <input type="hidden" name="fullName" value="<?php echo htmlspecialchars($studentSection['firstName'] . ' ' . $studentSection['lastName']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="sectionName">Section</label>
                        <select name="sectionName" id="sectionName" required>
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo htmlspecialchars($section['sectionName'] . ' ' . $section['sectionCode']); ?>" 
                                        <?php echo ($section['sectionName'] . ' ' . $section['sectionCode'] == ($sectionName ?? $studentSection['sectionName'] . ' ' . $studentSection['sectionCode'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['sectionName'] . ' ' . $section['sectionCode']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="academicSession">Academic Session</label>
                        <select name="academicSession" id="academicSession" required>
                            <option value="">Select Academic Session</option>
                            <?php foreach ($academicSessions as $session): ?>
                                <option value="<?php echo htmlspecialchars($session); ?>" 
                                        <?php echo ($session == ($academicSession ?? $studentSection['academicSession'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($session); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" required>
                            <option value="Enrolled" <?php echo ($status ?? $studentSection['status']) == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                            <option value="Pending" <?php echo ($status ?? $studentSection['status']) == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Dropped" <?php echo ($status ?? $studentSection['status']) == 'Dropped' ? 'selected' : ''; ?>>Dropped</option>
                            <option value="Completed" <?php echo ($status ?? $studentSection['status']) == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>

                    <button type="submit" name="updateStudentSection" class="submit-btn">Save</button>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->
    
    <script src="./utils/script.js"></script>
</body>
</html>
