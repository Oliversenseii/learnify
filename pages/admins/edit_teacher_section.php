<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

if (!isset($_GET['teacherID']) || !is_numeric($_GET['teacherID'])) {
    $_SESSION['error_message'] = "Invalid or missing teacher ID.";
    header('Location: data_teacher_section.php');
    exit;
}

$teacherID = (int)$_GET['teacherID'];

// Define time slots
$timeSlots = [
    '6:00 AM - 8:00 AM' => ['start' => '06:00:00', 'end' => '08:00:00'],
    '8:00 AM - 10:00 AM' => ['start' => '08:00:00', 'end' => '10:00:00'],
    '10:00 AM - 12:00 PM' => ['start' => '10:00:00', 'end' => '12:00:00'],
    '1:00 PM - 3:00 PM' => ['start' => '13:00:00', 'end' => '15:00:00'],
    '3:00 PM - 5:00 PM' => ['start' => '15:00:00', 'end' => '17:00:00'],
    '5:00 PM - 7:00 PM' => ['start' => '17:00:00', 'end' => '19:00:00']
];

// Fetch teacher details
$teacher_sql = "SELECT firstName, lastName FROM users WHERE userID = :teacherID AND userType = 'Professor' AND archived = 0";
$stmt = $dbConnection->prepare($teacher_sql);
$stmt->bindParam(':teacherID', $teacherID, PDO::PARAM_INT);
$stmt->execute();
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    $_SESSION['error_message'] = "Teacher not found.";
    header('Location: data_teacher_section.php');
    exit;
}

// Fetch all assignments for the teacher
$assignments_sql = "
    SELECT ts.teacherSectionID, ts.startTime, ts.endTime, ts.day, ts.advisory,
           s.sectionName, s.sectionCode,
           sub.subjectName, sub.subjectCode
    FROM teacher_section ts
    JOIN sections s ON ts.sectionID = s.sectionID
    JOIN subjects sub ON ts.subjectID = sub.subjectID
    WHERE ts.teacherID = :teacherID AND ts.archived = 0
    ORDER BY ts.day, ts.startTime
";
$stmt = $dbConnection->prepare($assignments_sql);
$stmt->bindParam(':teacherID', $teacherID, PDO::PARAM_INT);
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch data for form dropdowns
$section_sql = "SELECT sectionID, sectionName, sectionCode FROM sections WHERE archived = 0 ORDER BY sectionName";
$section_stmt = $dbConnection->prepare($section_sql);
$section_stmt->execute();
$sections = $section_stmt->fetchAll(PDO::FETCH_ASSOC);

$subject_sql = "SELECT subjectID, subjectName, subjectCode FROM subjects WHERE archived = 0 ORDER BY subjectName";
$subject_stmt = $dbConnection->prepare($subject_sql);
$subject_stmt->execute();
$subjects = $subject_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch assignment details for editing (if teacherSectionID is provided)
$assignment = null;
$currentTimeSlot = '';
if (isset($_GET['teacherSectionID']) && is_numeric($_GET['teacherSectionID'])) {
    $teacherSectionID = (int)$_GET['teacherSectionID'];
    $assignment_sql = "
        SELECT ts.teacherSectionID, ts.teacherID, ts.sectionID, ts.subjectID, ts.startTime, ts.endTime, ts.day, ts.advisory,
               s.sectionName, s.sectionCode,
               sub.subjectName, sub.subjectCode
        FROM teacher_section ts
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        WHERE ts.teacherSectionID = :teacherSectionID AND ts.teacherID = :teacherID AND ts.archived = 0
    ";
    $stmt = $dbConnection->prepare($assignment_sql);
    $stmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $stmt->bindParam(':teacherID', $teacherID, PDO::PARAM_INT);
    $stmt->execute();
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        $_SESSION['error_message'] = "Assignment not found.";
        header("Location: edit_teacher_section.php?teacherID=$teacherID");
        exit;
    }

    // Determine current time slot
    foreach ($timeSlots as $slot => $times) {
        if ($assignment['startTime'] == $times['start'] && $assignment['endTime'] == $times['end']) {
            $currentTimeSlot = $slot;
            break;
        }
    }
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $teacherSectionID = trim($_POST['teacherSectionID']);
    $sectionID = trim($_POST['sectionID']);
    $subjectID = trim($_POST['subjectID']);
    $timeSlot = trim($_POST['timeSlot']);
    $day = trim($_POST['day']);
    $advisory = trim($_POST['advisory']);

    if (empty($teacherSectionID) || empty($sectionID) || empty($subjectID) || empty($timeSlot) || empty($day) || !isset($_POST['advisory'])) {
        $_SESSION['error_message'] = "Please fill all required fields.";
    } elseif (!isset($timeSlots[$timeSlot])) {
        $_SESSION['error_message'] = "Invalid time slot selected.";
    } else {
        try {
            $startTime = $timeSlots[$timeSlot]['start'];
            $endTime = $timeSlots[$timeSlot]['end'];

            // Check for duplicate assignment (excluding current record)
            $check_sql = "SELECT * FROM teacher_section WHERE teacherID = ? AND sectionID = ? AND subjectID = ? AND day = ? AND archived = 0 AND teacherSectionID != ?";
            $stmt = $dbConnection->prepare($check_sql);
            $stmt->execute([$teacherID, $sectionID, $subjectID, $day, $teacherSectionID]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error_message'] = "Teacher is already assigned to this section, subject, and day.";
            } else {
                // Check for time slot conflict on the same day (excluding current record)
                $conflict_sql = "
                    SELECT * FROM teacher_section 
                    WHERE teacherID = ? AND day = ? AND archived = 0 AND teacherSectionID != ?
                    AND (
                        (startTime < ? AND endTime > ?) 
                        OR (startTime < ? AND endTime > ?)
                        OR (startTime >= ? AND endTime <= ?)
                    )";
                $stmt = $dbConnection->prepare($conflict_sql);
                $stmt->execute([$teacherID, $day, $teacherSectionID, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error_message'] = "Time slot conflict detected for this teacher on $day.";
                } else {
                    // Check teacher advisory constraint (max 2 advisories per teacher)
                    if ($advisory == 1) {
                        $teacher_advisory_sql = "SELECT COUNT(*) as advisory_count FROM teacher_section WHERE teacherID = ? AND advisory = 1 AND archived = 0 AND teacherSectionID != ?";
                        $stmt = $dbConnection->prepare($teacher_advisory_sql);
                        $stmt->execute([$teacherID, $teacherSectionID]);
                        $current_advisories = $stmt->fetch(PDO::FETCH_ASSOC)['advisory_count'];
                        
                        if ($current_advisories >= 2) {
                            $_SESSION['error_message'] = "This teacher can have a maximum of 2 advisory roles. They already have $current_advisories.";
                            header("Location: edit_teacher_section.php?teacherID=$teacherID");
                            exit;
                        }
                    }
                    
                    // Update assignment
                    $update_sql = "UPDATE teacher_section SET sectionID = :sectionID, subjectID = :subjectID, startTime = :startTime, endTime = :endTime, day = :day, advisory = :advisory WHERE teacherSectionID = :teacherSectionID AND teacherID = :teacherID";
                    $update_stmt = $dbConnection->prepare($update_sql);
                    $update_stmt->bindParam(':sectionID', $sectionID, PDO::PARAM_INT);
                    $update_stmt->bindParam(':subjectID', $subjectID, PDO::PARAM_INT);
                    $update_stmt->bindParam(':startTime', $startTime);
                    $update_stmt->bindParam(':endTime', $endTime);
                    $update_stmt->bindParam(':day', $day);
                    $update_stmt->bindParam(':advisory', $advisory, PDO::PARAM_INT);
                    $update_stmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                    $update_stmt->bindParam(':teacherID', $teacherID, PDO::PARAM_INT);
                    
                    if ($update_stmt->execute()) {
                        $_SESSION['success_message'] = "Schedule updated successfully!";
                        header("Location: edit_teacher_section.php?teacherID=$teacherID");
                        exit;
                    } else {
                        $_SESSION['error_message'] = "Error updating Schedule. Please try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Edit failed: " . $e->getMessage(), 3, 'C:\xampp\htdocs\capstone-lms\logs\error.log');
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
        header("Location: edit_teacher_section.php?teacherID=$teacherID");
        exit;
    }
}

// Handle archive action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive') {
    $teacherSectionID = trim($_POST['teacherSectionID']);
    try {
        $archive_sql = "UPDATE teacher_section SET archived = 1 WHERE teacherSectionID = :teacherSectionID AND teacherID = :teacherID";
        $archive_stmt = $dbConnection->prepare($archive_sql);
        $archive_stmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $archive_stmt->bindParam(':teacherID', $teacherID, PDO::PARAM_INT);
        
        if ($archive_stmt->execute()) {
            $_SESSION['success_message'] = "Assignment archived successfully!";
        } else {
            $_SESSION['error_message'] = "Error archiving assignment. Please try again.";
        }
    } catch (PDOException $e) {
        error_log("Archive failed: " . $e->getMessage(), 3, 'C:\xampp\htdocs\capstone-lms\logs\error.log');
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    header("Location: edit_teacher_section.php?teacherID=$teacherID");
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
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/data_admin.css">
    <link rel="stylesheet" href="./utils/notification.css">
    <link rel="stylesheet" href="./utils/edit_data.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Edit Teacher Section Assignments</title>
    <style>
        :root {
            --green: #10b981;
            --green-hover: #059669;
            --red: #ef4444;
            --red-hover: #dc2626;
            --yellow: #f59e0b;
            --yellow-hover: #d97706;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: var(--light);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: fit-content;
            transform: translateY(-50px);
            animation: slideIn 0.3s ease-out forwards;
        }

        #archiveModal .modal-content {
            width: 100%;
            max-width: 450px;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-content .form-group {
            margin-bottom: 1.25rem;
        }

        .modal-content .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            text-align: left;
            color: var(--dark);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        .modal-content .form-group select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            background-color: var(--grey);
            border: 1px solid var(--dark-grey);
            transition: all 0.2s ease;
        }

        .modal-content .form-group select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            outline: none;
        }

        .modal-content .form-group select option[disabled] {
            color: #9ca3af;
            text-decoration: line-through;
        }

        .modal-buttons {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }

        .btn-edit, .btn-confirm, .btn-cancel, .btn-save, .btn-archive {
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            text-align: center;
            gap: 0.5rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 0.75rem 1rem;
        }

        .btn-edit {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #000;
        }

        .btn-confirm {
            background: linear-gradient(135deg, #28a745, #218838) !important;
            color: white;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
            color: white;
        }

        .btn-save {
            background: linear-gradient(135deg, #218838, #1e7e34) !important;
            color: white;
        }

        .btn-archive {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            color: white;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
        }

        .btn-confirm:hover {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
        }

        .btn-cancel:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d) !important;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #047857, #065f46) !important;
        }

        .btn-archive:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d) !important;
        }

        .success-notification, .error-notification {
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            width: fit-content;
            margin: 0 auto;
            box-shadow: var(--shadow);
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .error-notification {
            background: linear-gradient(135deg, var(--red), #b91c1c);
        }

        .admin-table {
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .admin-table h2 {
            font-size: clamp(1.9rem, 3vw, 2rem);
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .admin-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            min-width: 600px;
            border-radius: 8px;
            overflow: hidden;
        }

        .admin-table th,
        .admin-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--grey);
        }

        .admin-table th {
            background-color: #0056b3;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .admin-table td {
            color: var(--dark);
            vertical-align: middle;
            text-transform: capitalize;
        }

        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--light), gray);
        }

        .admin-table tr:nth-child(even) {
            background-color: var(--grey);
        }

        .admin-table tr:hover {
            background-color: #e5e7eb;
            transition: background-color 0.2s ease;
        }

        .no-data {
            text-align: center;
            color: var(--dark-grey);
            padding: 1.5rem;
            font-style: italic;
            font-size: 1rem;
        }

        .form-group label span {
            color: var(--red);
        }

        .btn-download {
            background: linear-gradient(135deg, var(--blue), #1e3a8a);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-download:hover {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
        }

        @media screen and (max-width: 768px) {
            .admin-table {
                margin: 1rem;
                padding: 1rem;
            }

            .admin-table table {
                font-size: 0.85rem;
            }

            .modal-content {
                padding: 1rem;
                width: 95%;
            }

            .modal-content h2 {
                font-size: 1.5rem;
            }

            .modal-content .form-group select {
                font-size: 0.85rem;
            }

            .btn-edit, .btn-confirm, .btn-cancel, .btn-save, .btn-archive {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }
        }

        @media screen and (max-width: 480px) {
            .admin-table table {
                font-size: 0.8rem;
            }

            .modal-content {
                padding: 0.75rem;
            }

            .modal-content h2 {
                font-size: 1.25rem;
            }

            .modal-content .form-group select {
                font-size: 0.8rem;
            }

            .btn-edit, .btn-confirm, .btn-cancel, .btn-save, .btn-archive {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }

            .modal-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .btn-confirm, .btn-save {
                order: 1;
            }

            .btn-cancel, .btn-archive {
                order: 2;
            }
        }
    </style>
    <script>
        function showEditModal() {
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            window.location.href = 'edit_teacher_section.php?teacherID=<?php echo $teacherID; ?>';
        }

        function showArchiveModal(teacherSectionID, sectionName) {
            document.getElementById('archiveModal').style.display = 'block';
            document.getElementById('archiveTeacherSectionID').value = teacherSectionID;
            document.getElementById('archiveSectionName').textContent = sectionName;
        }

        function closeArchiveModal() {
            document.getElementById('archiveModal').style.display = 'none';
        }

        function updateTimeSlots() {
            const teacherID = <?php echo json_encode($teacherID); ?>;
            const day = document.getElementById('day').value;
            const timeSlotSelect = document.getElementById('timeSlot');
            const currentTeacherSectionID = <?php echo isset($teacherSectionID) ? json_encode($teacherSectionID) : 'null'; ?>;
            const currentTimeSlot = <?php echo json_encode($currentTimeSlot); ?>;

            // Reset time slot options
            timeSlotSelect.innerHTML = `
                <option value="" disabled selected>- Select Time Slot -</option>
                <optgroup label="Morning Shift">
                    <option value="6:00 AM - 8:00 AM">6:00 AM - 8:00 AM</option>
                    <option value="8:00 AM - 10:00 AM">8:00 AM - 10:00 AM</option>
                    <option value="10:00 AM - 12:00 PM">10:00 AM - 12:00 PM</option>
                </optgroup>
                <optgroup label="Afternoon Shift">
                    <option value="1:00 PM - 3:00 PM">1:00 PM - 3:00 PM</option>
                    <option value="3:00 PM - 5:00 PM">3:00 PM - 5:00 PM</option>
                    <option value="5:00 PM - 7:00 PM">5:00 PM - 7:00 PM</option>
                </optgroup>
            `;

            if (!day) {
                return; // Don't fetch if day isn't selected
            }

            // Fetch teacher's schedules to determine taken time slots
            fetch('fetch_teacher_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'teacherID=' + encodeURIComponent(teacherID)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (!data.error && data.schedules) {
                    // Filter schedules for the selected day, excluding the current assignment
                    const takenTimeSlots = data.schedules
                        .filter(schedule => schedule.day === day && (!currentTeacherSectionID || schedule.teacherSectionID !== currentTeacherSectionID))
                        .map(schedule => schedule.timeSlot);

                    // Disable taken time slots
                    Array.from(timeSlotSelect.options).forEach(option => {
                        if (takenTimeSlots.includes(option.value) && option.value !== currentTimeSlot) {
                            option.disabled = true;
                            option.style.textDecoration = 'line-through';
                        }
                    });

                    // Set current time slot as selected
                    if (currentTimeSlot) {
                        timeSlotSelect.value = currentTimeSlot;
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching schedules:', error);
            });
        }

        // Show modals if needed on page load
        window.onload = function() {
            <?php if ($assignment): ?>
                showEditModal();
            <?php endif; ?>
        };
    </script>
</head>
<body>
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
            <li class="active">
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

    <?php require_once './view/modal.php' ?>

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
                    <h1>Edit Schedules for <?php echo htmlspecialchars($teacher['firstName'] . ' ' . $teacher['lastName']); ?></h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Assign</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./data_teacher_section.php">Data</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Edit</a></li>
                    </ul>
                </div>
                <a href="./data_teacher_section.php" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
                    <span class="text">Back to Data</span>
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

            <div class="admin-table">
                <h2>Current Schedules</h2>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Section</th>
                            <th>Subject</th>
                            <th>Day</th>
                            <th>Time Slot</th>
                            <th>Advisory</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($assignments) > 0): ?>
                            <?php foreach ($assignments as $index => $row): ?>
                                <?php
                                // Determine time slot for display
                                $displayTimeSlot = '';
                                foreach ($timeSlots as $slot => $times) {
                                    if ($row['startTime'] == $times['start'] && $row['endTime'] == $times['end']) {
                                        $displayTimeSlot = $slot;
                                        break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($row['sectionName'] . ' (' . $row['sectionCode'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars($row['subjectCode'] . ' - ' . $row['subjectName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['day']); ?></td>
                                    <td><?php echo htmlspecialchars($displayTimeSlot); ?></td>
                                    <td><?php echo $row['advisory'] ? '✅ Yes' : '❌ No'; ?></td>
                                    <td>
                                        <a href="edit_teacher_section.php?teacherID=<?php echo $teacherID; ?>&teacherSectionID=<?php echo $row['teacherSectionID']; ?>" class="btn-edit" onclick="showEditModal(); return false;">
                                            <i class='bx bxs-edit'></i> Edit
                                        </a>
                                        <button class="btn-archive" onclick="showArchiveModal(<?php echo $row['teacherSectionID']; ?>, '<?php echo htmlspecialchars($row['sectionName']); ?>')">
                                            <i class='bx bxs-archive'></i> Archive
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">No assignments found for this teacher.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Edit Assignment Modal -->
            <?php if ($assignment): ?>
                <div id="editModal" class="modal">
                    <div class="modal-content">
                        <h2>Edit Assignment</h2>
                        <form method="POST" action="edit_teacher_section.php?teacherID=<?php echo $teacherID; ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="teacherSectionID" value="<?php echo htmlspecialchars($assignment['teacherSectionID']); ?>">
                            <div class="form-group">
                                <label for="sectionID">Select Section <span>*</span></label>
                                <select name="sectionID" id="sectionID" required>
                                    <option value="" disabled>- Select Section -</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo $section['sectionID']; ?>" <?php echo $section['sectionID'] == $assignment['sectionID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($section['sectionName'] . ' (' . $section['sectionCode'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="subjectID">Select Subject <span>*</span></label>
                                <select name="subjectID" id="subjectID" required>
                                    <option value="" disabled>- Select Subject -</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['subjectID']; ?>" <?php echo $subject['subjectID'] == $assignment['subjectID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subjectName'] . ' (' . $subject['subjectCode'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="day">Day <span>*</span></label>
                                <select name="day" id="day" required onchange="updateTimeSlots()">
                                    <option value="" disabled>- Select Day -</option>
                                    <option value="Monday" <?php echo $assignment['day'] == 'Monday' ? 'selected' : ''; ?>>Monday</option>
                                    <option value="Tuesday" <?php echo $assignment['day'] == 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                    <option value="Wednesday" <?php echo $assignment['day'] == 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                    <option value="Thursday" <?php echo $assignment['day'] == 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                                    <option value="Friday" <?php echo $assignment['day'] == 'Friday' ? 'selected' : ''; ?>>Friday</option>
                                    <option value="Saturday" <?php echo $assignment['day'] == 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                                    <option value="Sunday" <?php echo $assignment['day'] == 'Sunday' ? 'selected' : ''; ?>>Sunday</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="timeSlot">Time Slot <span>*</span></label>
                                <select name="timeSlot" id="timeSlot" required>
                                    <option value="" disabled selected>- Select Time Slot -</option>
                                    <optgroup label="Morning Shift">
                                        <option value="6:00 AM - 8:00 AM" <?php echo $currentTimeSlot == '6:00 AM - 8:00 AM' ? 'selected' : ''; ?>>6:00 AM - 8:00 AM</option>
                                        <option value="8:00 AM - 10:00 AM" <?php echo $currentTimeSlot == '8:00 AM - 10:00 AM' ? 'selected' : ''; ?>>8:00 AM - 10:00 AM</option>
                                        <option value="10:00 AM - 12:00 PM" <?php echo $currentTimeSlot == '10:00 AM - 12:00 PM' ? 'selected' : ''; ?>>10:00 AM - 12:00 PM</option>
                                    </optgroup>
                                    <optgroup label="Afternoon Shift">
                                        <option value="1:00 PM - 3:00 PM" <?php echo $currentTimeSlot == '1:00 PM - 3:00 PM' ? 'selected' : ''; ?>>1:00 PM - 3:00 PM</option>
                                        <option value="3:00 PM - 5:00 PM" <?php echo $currentTimeSlot == '3:00 PM - 5:00 PM' ? 'selected' : ''; ?>>3:00 PM - 5:00 PM</option>
                                        <option value="5:00 PM - 7:00 PM" <?php echo $currentTimeSlot == '5:00 PM - 7:00 PM' ? 'selected' : ''; ?>>5:00 PM - 7:00 PM</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="advisory">Advisory Role <span>*</span></label>
                                <select name="advisory" id="advisory" required>
                                    <option value="0" <?php echo $assignment['advisory'] == 0 ? 'selected' : ''; ?>>No</option>
                                    <option value="1" <?php echo $assignment['advisory'] == 1 ? 'selected' : ''; ?>>Yes</option>
                                </select>
                            </div>
                            <div class="modal-buttons">
                                <button type="submit" class="btn-save">
                                    <i class='bx bxs-save'></i> Save Changes
                                </button>
                                <button type="button" class="btn-cancel" onclick="closeEditModal()">
                                    <i class='bx bxs-x-circle'></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Archive Confirmation Modal -->
            <div id="archiveModal" class="modal">
                <div class="modal-content">
                    <h2>Confirm Archive</h2>
                    <p>Are you sure you want to archive the schedule for section <span id="archiveSectionName"></span>?</p>
                    <form method="POST" action="edit_teacher_section.php?teacherID=<?php echo $teacherID; ?>">
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="teacherSectionID" id="archiveTeacherSectionID">
                        <div class="modal-buttons">
                            <button type="submit" class="btn-confirm">
                                <i class='bx bxs-check-circle'></i> Confirm
                            </button>
                            <button type="button" class="btn-cancel" onclick="closeArchiveModal()">
                                <i class='bx bxs-x-circle'></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        if (document.getElementById('day') && document.getElementById('day').value) {
            updateTimeSlots();
        }
    </script>
</body>
</html>