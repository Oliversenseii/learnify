<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

$subjectID = $_GET['subjectID'] ?? null;
$subjectName = '';
$assignedProfessorIDs = [];

if ($subjectID) {
    // Fetch subject details
    $sql = "SELECT subjectName FROM subjects WHERE subjectID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->execute([$subjectID]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($subject) {
        $subjectName = $subject['subjectName'];
    }

    // Fetch assigned professors
    $sql = "SELECT professorID FROM subject_professor WHERE subjectID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->execute([$subjectID]);
    $assignedProfessorIDs = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (isset($_POST['updateSubject'])) {
    $newProfessorIDs = $_POST['professorID'] ?? [];

    try {
        // Delete existing assignments
        $sqlDelete = "DELETE FROM subject_professor WHERE subjectID = ?";
        $stmtDelete = $dbConnection->prepare($sqlDelete);
        $stmtDelete->execute([$subjectID]);

        // Insert new assignments
        $sqlInsert = "INSERT INTO subject_professor (subjectID, professorID) VALUES (?, ?)";
        $stmtInsert = $dbConnection->prepare($sqlInsert);
        $success = true;

        foreach ($newProfessorIDs as $professorID) {
            if (!$stmtInsert->execute([$subjectID, $professorID])) {
                $success = false;
                break;
            }
        }

        if ($success) {
            $_SESSION['success_message'] = "Teacher(s) updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating teacher(s).";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }

    header("Location: enroll_prof_sub.php");
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
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
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
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --light-blue: #CFE8FF;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --red: #a90c0c;
            --yellow: #FFCE26;
            --light-yellow: #FFF2C6;
            --orange: #FD7238;
            --light-orange: #FFE0D3;
        }

        .form-container {
            max-width: 600px;
            margin: 20px auto;
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .form-container h2 {
            margin-bottom: 20px;
            color: var(--dark);
            font-size: 24px;
            text-align: center;
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

        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            background-color: var(--grey);
            border: 1px solid var(--dark-grey);
            color: var(--dark);
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }

        .form-group input[readonly] {
            background-color: #f9f9f9;
        }

        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: var(--grey);
            border: 1px solid var(--dark-grey);
            border-radius: 4px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--dark);
            cursor: pointer;
        }

        .checkbox-group input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid var(--dark-grey);
            border-radius: 4px;
            margin-right: 10px;
            position: relative;
            transition: all 0.3s ease;
        }

        .checkbox-group input[type="checkbox"]:checked {
            background-color: var(--blue);
            border-color: var(--blue);
        }

        .checkbox-group input[type="checkbox"]:checked::after {
            content: '\2713';
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
        }

        .checkbox-group label:hover input[type="checkbox"] {
            border-color: var(--blue);
        }

        .form-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .form-actions button {
            background-color: var(--blue);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-actions button:hover {
            background-color: #0056b3;
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

        #subjectName {
            background-color: var(--grey);
        }
    </style>
    <title>Learnify</title>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li><a href="./adminDash.php"><i class='bx bxs-dashboard'></i><span class="text">Dashboard</span></a></li>
            <li>
                <a href="./message_professor.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Teachers</span>
                </a>
            </li>
            <li><a href="./registration.php"><i class='bx bx-user-plus'></i><span class="text">Registration</span></a></li>
            <li>
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li><a href="./enroll_student_section.php"><i class='bx bxs-user-check'></i><span class="text">Enroll Student Section</span></a></li>
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
            <li><a href="./settings.php"><i class='bx bxs-cog'></i><span class="text">Settings</span></a></li>
            <li><a href="javascript:void(0);" class="logout" onclick="showLogoutModal()"><i class='bx bxs-log-out-circle'></i><span class="text">Logout</span></a></li>
        </ul>
    </section>
    <!-- SIDEBAR -->

    <?php require_once './view/modal.php' ?>

    <!-- CONTENT -->
    <section id="content">
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

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Edit Teacher(s)</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./enroll_prof_sub.php">Assign</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Edit</a></li>
                    </ul>
                </div>
                <a href="./enroll_prof_sub.php" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
                    <span class="text">Back</span>
                </a>
            </div>

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

            <div class="form-container">
                <h2>Edit Teacher(s) for <?php echo htmlspecialchars($subjectName); ?></h2>
                <form action="edit_enroll_prof_sub.php?subjectID=<?php echo $subjectID; ?>" method="POST">
                    <div class="form-group">
                        <label for="subjectName">Subject</label>
                        <input type="text" name="subjectName" id="subjectName" value="<?php echo htmlspecialchars($subjectName); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Assigned Teacher(s)</label>
                        <div class="checkbox-group">
                            <?php
                            $sql = "SELECT userID, firstName, lastName FROM users WHERE userType = 'Professor' ORDER BY firstName, lastName";
                            $stmt = $dbConnection->prepare($sql);
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $checked = in_array($row['userID'], $assignedProfessorIDs) ? 'checked' : '';
                                echo '<label><input type="checkbox" name="professorID[]" value="' . $row['userID'] . '" ' . $checked . '> ' . htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) . '</label>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="updateSubject">Update Assignment</button>
                    </div>
                </form>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
</body>
</html>
