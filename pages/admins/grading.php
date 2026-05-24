<?php
date_default_timezone_set('Asia/Manila');
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Validate session
if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php");
    exit;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false || $userID <= 0) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

// Fetch all teacher sections
try {
    $query = "SELECT teacherSectionID FROM teacher_section WHERE archived = 0";
    $stmt = $dbConnection->prepare($query);
    $stmt->execute();
    $teacherSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to fetch teacher sections.";
    $teacherSections = [];
}

// Fetch existing grading weights
try {
    $query = "SELECT teacherSectionID, attendance_weight, quiz_weight, assignment_weight 
              FROM grading_weights";
    $stmt = $dbConnection->prepare($query);
    $stmt->execute();
    $gradingWeights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $weightsBySection = [];
    foreach ($gradingWeights as $weight) {
        $weightsBySection[$weight['teacherSectionID']] = $weight;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to fetch grading weights.";
    $weightsBySection = [];
}

// Handle grading weights submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_weight'], $_POST['quiz_weight'], $_POST['assignment_weight'])) {
    $attendance_weight = filter_var($_POST['attendance_weight'], FILTER_VALIDATE_FLOAT);
    $quiz_weight = filter_var($_POST['quiz_weight'], FILTER_VALIDATE_FLOAT);
    $assignment_weight = filter_var($_POST['assignment_weight'], FILTER_VALIDATE_FLOAT);

    // Validate weights sum to 100 and are non-negative
    if ($attendance_weight === false || $quiz_weight === false || $assignment_weight === false ||
        $attendance_weight < 0 || $quiz_weight < 0 || $assignment_weight < 0 ||
        abs($attendance_weight + $quiz_weight + $assignment_weight - 100) > 0.01) {
        $_SESSION['error_message'] = "Invalid weights. They must sum to 100% and be non-negative.";
    } else {
        try {
            $dbConnection->beginTransaction();
            $query = "INSERT INTO grading_weights (teacherSectionID, attendance_weight, quiz_weight, assignment_weight)
                      VALUES (:teacherSectionID, :attendance_weight, :quiz_weight, :assignment_weight)
                      ON DUPLICATE KEY UPDATE 
                      attendance_weight = :attendance_weight,
                      quiz_weight = :quiz_weight,
                      assignment_weight = :assignment_weight,
                      updated_at = CURRENT_TIMESTAMP";
            $stmt = $dbConnection->prepare($query);

            foreach ($teacherSections as $teacherSectionID) {
                $stmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                $stmt->bindParam(':attendance_weight', $attendance_weight, PDO::PARAM_STR);
                $stmt->bindParam(':quiz_weight', $quiz_weight, PDO::PARAM_STR);
                $stmt->bindParam(':assignment_weight', $assignment_weight, PDO::PARAM_STR);
                $stmt->execute();
            }

            $dbConnection->commit();
            $_SESSION['success_message'] = "Grading weights updated successfully for all teachers.";
            header("Location: grading.php");
            exit;
        } catch (PDOException $e) {
            $dbConnection->rollBack();
            error_log("Database Error: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to update grading weights.";
        }
    }
}

// Handle weight deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_weights'])) {
    try {
        $query = "DELETE FROM grading_weights";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute();
        $_SESSION['success_message'] = "Grading weights removed successfully.";
        header("Location: grading.php");
        exit;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to remove grading weights.";
    }
}

// Determine initial weights for display
$initialWeights = !empty($weightsBySection) ? reset($weightsBySection) : [
    'attendance_weight' => '0',
    'quiz_weight' => '0',
    'assignment_weight' => '0'
];
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
    <title>Learnify - Grading Settings</title>
    <style>
        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --background: #F7FAFC;
            --text: #1A202C;
            --text-secondary: #4A5568;
            --border: #E2E8F0;
            --success: #38A169;
            --error: #E53E3E;
            --white: #FFFFFF;
            --accent: #EDF2F7;
            --transition: all 0.3s ease;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
        }

        body.dark {
            --background: #0C0C1E;
            --grey: #060714;
            --dark: #FBFBFB;
        }

        .settings-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .settings-section {
            margin-bottom: 1rem;
            background: var(--background);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .settings-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            color: var(--dark);
            cursor: pointer;
            background-color: var(--background);
            transition: var(--transition);
        }

        .settings-section-header:hover {
            background: linear-gradient(135deg, var(--background), var(--primary-dark));
        }

        .settings-section-header h2 {
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .settings-section-header i {
            font-size: 1.5rem;
            color: var(--text);
            background-color: var(--white);
            transition: var(--transition);
        }

        .settings-section-header.active i {
            transform: rotate(180deg);
        }

        .settings-section-content {
            max-height: 0;
            overflow: hidden;
            padding: 0 1.5rem;
            transition: max-height 0.4s ease, padding 0.4s ease;
        }

        .settings-section-content.active {
            max-height: 1000px;
            padding: 1.5rem;
            border-top: 1px solid var(--text-secondary);
        }

        .settings-section-content p {
            font-size: clamp(1rem, 3vw, 1.2rem);
            margin-bottom: 10px;
            color: var(--dark);
        }

        .grading-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: var(--background);
            padding: 1rem;
            border-radius: 6px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .grading-form label {
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 500;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .grading-form input {
            margin-top: 10px;
            margin-bottom: 10px;
            padding: 0.6rem 1.2rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            background: var(--grey);
            color: var(--dark);
            width: 100px;
            transition: var(--transition);
        }

        .grading-form input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.2);
        }

        .grading-form button {
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
        }

        .grading-form button:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1A4971);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        #removeBtn {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
        }

        .saved-data {
            margin-top: 20px;
            padding: 15px;
            background-color: var(--white);
            border: 1px solid var(--border);
            border-radius: 4px;
            display: none;
        }

        .saved-data p {
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--text-secondary);
            margin: 0.5rem 0;
        }

        .notification {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            color: var(--white);
            font-size: clamp(1rem, 3vw, 1.2rem);
            z-index: 1000;
            box-shadow: var(--shadow);
            transition: opacity 0.3s ease;
        }

        .success-notification {
            background: var(--success);
        }

        .error-notification {
            background: var(--error);
        }

        @media (max-width: 768px) {
            .settings-container {
                margin: 1rem;
            }

            .settings-section-header h2 {
                font-size: 1.2rem;
            }

            .grading-form input,
            .grading-form button {
                font-size: 0.95rem;
                padding: 0.5rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .settings-section-header h2 {
                font-size: 1.1rem;
            }

            .grading-form {
                flex-direction: column;
                align-items: flex-start;
                height: auto;
            }

            .grading-form input,
            .grading-form button {
                font-size: 0.9rem;
                padding: 0.5rem 0.8rem;
                width: 100%;
            }

            .grading-form button {
                margin-bottom: 5px;
            }

            .notification {
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li><a href="./adminDash.php"><i class='bx bxs-dashboard'></i><span class="text">Dashboard</span></a></li>
            <li><a href="./message_professor.php"><i class='bx bxs-message'></i><span class="text">Message Teachers</span></a></li>
            <li><a href="./registration.php"><i class='bx bx-user-plus'></i><span class="text">Registration</span></a></li>
            <li><a href="./modules.php"><i class='bx bxs-bookmark'></i><span class="text">Modules</span></a></li>
            <li><a href="./enroll_student_section.php"><i class='bx bxs-user-check'></i><span class="text">Enroll Student Section</span></a></li>
            <li><a href="./enroll_teacher_section.php"><i class='bx bxs-user-check'></i><span class="text">Assign Teacher Schedule</span></a></li>
            <li><a href="./game.php"><i class='bx bxs-game'></i><span class="text">Games</span></a></li>
            <li><a href="./admin_calendar.php"><i class='bx bxs-calendar'></i><span class="text">Academic Calendar</span></a></li>
            <li class="active"><a href="./settings.php"><i class='bx bxs-book-content'></i><span class="text">Student Grades</span></a></li>
        </ul>
        <ul class="side-menu">
            <li><a href="./settings.php"><i class='bx bxs-cog'></i><span class="text">Settings</span></a></li>
            <li><a href="javascript:void(0);" class="logout" onclick="showLogoutModal()"><i class='bx bxs-log-out-circle'></i><span class="text">Logout</span></a></li>
        </ul>
    </section>

    <?php require_once './view/modal.php' ?>

    <section id="content">
        <nav>
            <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required aria-label="Search">
                    <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile" aria-label="Profile">
                <img src="<?php echo isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'; ?>" alt="Profile Image">
                <div><small><?php echo htmlspecialchars($_SESSION['userType']); ?></small></div>
            </a>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Grading Settings</h1>
                    <ul class="breadcrumb">
                        <li><a href="./adminDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./grading.php">Grading Settings</a></li>
                    </ul>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="notification success-notification" id="success-notification">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="notification error-notification" id="error-notification">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="settings-container">
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h2><i class='bx bxs-calculator' style="margin-right: 5px; background-color: var(--primary); color: white; padding: 7px; border-radius: 50%;"></i> Grading Weights</h2>
                        <i class='bx bx-chevron-down'></i>
                    </div>
                    <div class="settings-section-content active">
                        <p>Configure the grading weights for attendance, quizzes, and assignments. Ensure the weights sum to 100%. These settings apply to all teachers.</p>
                        <div class="grading-form">
                            <form id="gradeForm" action="grading.php" method="post" onsubmit="return validateWeights()">
                                <label for="attendance_weight"><i class='bx bxs-user-check'></i> Attendance Weight (%):</label>
                                <input type="number" name="attendance_weight" id="attendance_weight" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars(number_format($initialWeights['attendance_weight'], 2)); ?>" required>
                                <label for="quiz_weight"><i class='bx bxs-book'></i> Quiz Weight (%):</label>
                                <input type="number" name="quiz_weight" id="quiz_weight" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars(number_format($initialWeights['quiz_weight'], 2)); ?>" required>
                                <label for="assignment_weight"><i class='bx bxs-book'></i> Assignment Weight (%):</label>
                                <input type="number" name="assignment_weight" id="assignment_weight" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars(number_format($initialWeights['assignment_weight'], 2)); ?>" required>
                                <br><br>
                                <button type="submit"><i class='bx bx-save'></i> Save Weights</button>
                                <?php if (!empty($weightsBySection)): ?>
                                    <button type="button" id="removeBtn" onclick="document.getElementById('deleteForm').submit();"><i class='bx bx-trash'></i> Remove</button>
                                <?php endif; ?>
                            </form>
                            <form id="deleteForm" action="grading.php" method="post" style="display: none;">
                                <input type="hidden" name="delete_weights" value="1">
                            </form>
                        </div>
                        <?php if (!empty($weightsBySection)): ?>
                            <div id="savedData" class="saved-data">
                                <h3>Saved Weights</h3>
                                <p>Attendance Weight: <span id="savedAttendance"><?php echo htmlspecialchars(number_format($initialWeights['attendance_weight'], 2)); ?>%</span></p>
                                <p>Quiz Weight: <span id="savedQuiz"><?php echo htmlspecialchars(number_format($initialWeights['quiz_weight'], 2)); ?>%</span></p>
                                <p>Assignment Weight: <span id="savedAssignment"><?php echo htmlspecialchars(number_format($initialWeights['assignment_weight'], 2)); ?>%</span></p>
                                <button id="editBtn"><i class='bx bx-edit'></i> Edit Weights</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        function validateWeights() {
            const attendance = parseFloat(document.getElementById('attendance_weight').value) || 0;
            const quiz = parseFloat(document.getElementById('quiz_weight').value) || 0;
            const assignment = parseFloat(document.getElementById('assignment_weight').value) || 0;
            const total = attendance + quiz + assignment;

            if (Math.abs(total - 100) > 0.01) {
                alert('Error: The weights must sum to exactly 100%.');
                return false;
            }
            if (attendance < 0 || quiz < 0 || assignment < 0) {
                alert('Error: Weights cannot be negative.');
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const gradeForm = document.getElementById('gradeForm');
            const savedData = document.getElementById('savedData');
            const editBtn = document.getElementById('editBtn');
            const removeBtn = document.getElementById('removeBtn');

            // Toggle section functionality
            const headers = document.querySelectorAll('.settings-section-header');
            headers.forEach(header => {
                header.addEventListener('click', () => {
                    const content = header.nextElementSibling;
                    const isActive = content.classList.contains('active');

                    if (isActive) {
                        content.classList.remove('active');
                        content.style.maxHeight = null;
                        content.style.padding = '0 1.5rem';
                        header.classList.remove('active');
                    } else {
                        content.classList.add('active');
                        content.style.maxHeight = content.scrollHeight + 30 + 'px';
                        content.style.padding = '1.5rem';
                        header.classList.add('active');
                    }
                });
            });

            // Ensure grading section is open by default
            const gradingContent = document.querySelector('.settings-section-content');
            const gradingHeader = document.querySelector('.settings-section-header');
            if (gradingContent && gradingHeader) {
                gradingContent.classList.add('active');
                gradingContent.style.maxHeight = gradingContent.scrollHeight + 30 + 'px';
                gradingContent.style.padding = '1.5rem';
                gradingHeader.classList.add('active');
            }

            // Initialize form visibility
            gradeForm.style.display = 'block';
            if (savedData) savedData.style.display = 'none';
            if (editBtn) editBtn.style.display = <?php echo !empty($weightsBySection) ? "'inline-flex'" : "'none'"; ?>;
            if (removeBtn) removeBtn.style.display = <?php echo !empty($weightsBySection) ? "'inline-flex'" : "'none'"; ?>;

            // Edit button functionality
            if (editBtn) {
                editBtn.addEventListener('click', () => {
                    gradeForm.style.display = 'block';
                    if (savedData) savedData.style.display = 'none';
                    editBtn.style.display = 'none';
                    if (removeBtn) removeBtn.style.display = 'inline-flex';
                });
            }

            // Notification fade-out
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>