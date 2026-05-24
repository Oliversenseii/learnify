<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Query to fetch teachers and their total section count
$teacher_sql = "
    SELECT u.userID, u.firstName, u.lastName, COUNT(DISTINCT ts.teacherSectionID) as total_sections
    FROM users u
    LEFT JOIN teacher_section ts ON u.userID = ts.teacherID AND ts.archived = 0
    WHERE u.userType = 'Professor' AND u.archived = 0
    GROUP BY u.userID, u.firstName, u.lastName
    ORDER BY u.firstName, u.lastName
    LIMIT :limit OFFSET :offset
";

try {
    $stmt = $dbConnection->prepare($teacher_sql);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query to fetch all assignments for the displayed teachers
    $teacher_ids = array_column($teachers, 'userID');
    $assignments_sql = "
        SELECT ts.teacherSectionID, ts.startTime, ts.endTime, ts.day, ts.assignmentDate, ts.advisory,
               u.userID, u.firstName, u.lastName, 
               s.sectionName, s.sectionCode,
               sub.subjectName, sub.subjectCode
        FROM teacher_section ts
        JOIN users u ON ts.teacherID = u.userID
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        WHERE ts.archived = 0 AND u.userID IN (" . implode(',', array_fill(0, count($teacher_ids), '?')) . ")
        ORDER BY u.firstName, u.lastName, ts.day, ts.startTime
    ";
    $stmt = $dbConnection->prepare($assignments_sql);
    $stmt->execute($teacher_ids);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group assignments by teacher and day
    $teacher_assignments = [];
    foreach ($assignments as $assignment) {
        $teacher_id = $assignment['userID'];
        $day = $assignment['day'];
        if (!isset($teacher_assignments[$teacher_id])) {
            $teacher_assignments[$teacher_id] = [];
        }
        if (!isset($teacher_assignments[$teacher_id][$day])) {
            $teacher_assignments[$teacher_id][$day] = [];
        }
        $teacher_assignments[$teacher_id][$day][] = $assignment;
    }

    // Total teachers for pagination
    $total_sql = "SELECT COUNT(DISTINCT u.userID) as total FROM users u WHERE u.userType = 'Professor' AND u.archived = 0";
    $total_stmt = $dbConnection->prepare($total_sql);
    $total_stmt->execute();
    $total_records = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    error_log("Query failed: " . $e->getMessage(), 3, 'C:\xampp\htdocs\capstone-lms\logs\error.log');
    $_SESSION['error_message'] = "Failed to fetch data. Please try again.";
    header('Location: adminDash.php');
    exit();
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
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Teacher Section Assignments</title>
    <style>
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --grey: #f3f4f6;
            --dark-grey: #9ca3af;
            --dark: #1f2937;
            --green: #10b981;
            --red: #ef4444;
        }

        .admin-table {
            margin: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .admin-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            min-width: 600px;
        }

        .admin-table th,
        .admin-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--grey);
        }

        .admin-table th {
            background: #0056b3;
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

        .admin-table td {
            border: none;
        }

        .admin-table details {
            margin-bottom: 10px;
        }

        .admin-table summary {
            cursor: pointer;
            padding: 1rem;
            font-weight: 600;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            background-color: var(--grey);
            color: var(--dark);
            border-radius: 5px;
            border-left: 4px solid #007bff;
            transition: background-color 0.3s ease;
        }

        .admin-table tr:hover {
            background-color: linear-gradient(135deg, var(--light), gray);
        }

         .admin-table summary:hover {
             background: linear-gradient(135deg, var(--light), gray);
         }

        .admin-table summary span {
            color: white;
            margin-left: 10px;
            background-color: #b21f2d;
            padding: 5px;
            max-width: 400px;
            width: 100%;
            max-height: 70px;
            font-size: clamp(0.9rem, 3vw, 1rem);
            border-radius: 5px;
        }

        .btn-edit, .btn-archive {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            transition: background-color 0.3s ease;
        }

        .btn-edit {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: black;
            margin-left: 10px;
        }

        .btn-archive {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            margin-left: 10px;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
        }

        .btn-archive:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .success-notification, .error-notification {
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border-radius: 0.375rem;
            text-align: center;
            font-weight: 600;
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem 0;
        }

        .pagination a {
            padding: 0.5rem 0.75rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: 1px solid var(--dark-grey);
            border-radius: 0.375rem;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
        }

        .pagination a.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-color: var(--blue);
        }

        .pagination a:hover:not(.disabled) {
            background: linear-gradient(135deg, #0056b3, #003d80);
            color: white;
        }

        .pagination a.disabled {
            color: var(--dark-grey);
            cursor: not-allowed;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            margin: 10px 0;
            border: none;
        }

        .details-table th,
        .details-table td {
            padding: 0.75rem;
            text-align: left;
        }

        .details-table th {
            background: #007bff;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
        }

        .details-table td {
            color: var(--dark);
        }

        .details-table tr:nth-child(odd) {
            background: var(--grey);
        }

        .details-table tr:nth-child(even):hover,
        .details-table tr:nth-child(odd):hover {
            background: linear-gradient(135deg, var(--grey), gray);
        }

        .no-data {
            text-align: center;
            color: var(--dark-grey);
            padding: 1rem;
            font-style: italic;
        }

        .day-details {
            margin: 10px 20px;
        }

        .day-details .day-button,
        .day-details .all-schedules-button {
            cursor: pointer;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            background: var(--grey);
            border-radius: 0.375rem;
            border: none;
            display: block;
            width: 100%;
            text-align: left;
            margin-bottom: 10px;
            transition: background-color 0.3s ease;
        }

        .day-details .day-button:hover,
        .day-details .all-schedules-button:hover {
            background: #0056b3;
            color: white;
        }

        .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1rem;
            text-align: left;
            font-size: clamp(1.9rem, 3vw, 2rem);
            font-weight: 600;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--grey);
            border: 1px solid var(--dark);
            border-radius: 5px;
            font-size: 1.5rem;
            padding: 5px;
            color: var(--dark);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--red);
             border: 1px solid var(--red);
        }

        .modal-day-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
        }

        .modal-day-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .modal-day-section h3 {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            text-align: left;
            margin-bottom: 0.5rem;
        }

        @media screen and (max-width: 768px) {
            .admin-table {
                margin: 1rem;
                padding: 0.5rem;
                overflow-x: auto;
            }

            .admin-table table {
                font-size: 0.75rem;
                min-width: 600px;
            }

            .admin-table th,
            .admin-table td {
                padding: 0.5rem;
            }

            .details-table {
                font-size: 0.75rem;
            }

            .details-table th,
            .details-table td {
                padding: 0.5rem;
            }

            .modal-content {
                width: 95%;
            }
        }

        @media screen and (max-width: 480px) {
            .admin-table table {
                font-size: 0.7rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 0.4rem;
            }

            .btn-edit, .btn-archive {
                padding: 0.4rem 0.8rem;
                font-size: 0.7rem;
            }

            .pagination a {
                padding: 0.4rem 0.6rem;
                font-size: 0.7rem;
            }

            .details-table {
                font-size: 0.7rem;
            }

            .details-table th,
            .details-table td {
                padding: 0.4rem;
            }

            .admin-table summary,
            .day-details .day-button,
            .day-details .all-schedules-button {
                font-size: 0.7rem;
            }

            .modal-header {
                font-size: 0.9rem;
            }

            .modal-day-section h3 {
                font-size: 0.8rem;
            }
        }

        .register {
            background: linear-gradient(135deg, #28a745, #218838) !important;
        }

        .register:hover {
            background: linear-gradient(135deg, #218838, #1e7e34) !important;
        }

        .side-menu li.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }

        .side-menu li.active a {
            color: white;
        }

        .side-menu li.active a:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        
    </style>
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
            <li class="active"><a href="./enroll_teacher_section.php"><i class='bx bxs-user-check'></i><span class="text">Assign Teacher Schedule</span></a></li>
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
                    <h1>Data Schedule</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Assign</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./data_teacher_section.php">Data</a></li>
                    </ul>
                </div>
                <a href="./enroll_teacher_section.php" class="btn-download register">
                    <i class='bx bxs-user-plus'></i>
                    <span class="text">Assign New Teacher</span>
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
                <table>
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($teachers) > 0): ?>
                            <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td>
                                        <details>
                                            <summary><?php echo htmlspecialchars($teacher['firstName'] . ' ' . $teacher['lastName']); ?> <span><?php echo htmlspecialchars($teacher['total_sections']); ?> records</span></summary>
                                            <div class="day-details">
                                                <?php
                                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                                $has_assignments = false;
                                                // Add "See All Schedules" button if there are assignments
                                                foreach ($days as $day) {
                                                    if (isset($teacher_assignments[$teacher['userID']][$day]) && count($teacher_assignments[$teacher['userID']][$day]) > 0) {
                                                        $has_assignments = true;
                                                        break;
                                                    }
                                                }
                                                if ($has_assignments) {
                                                    $all_modal_id = 'modal-all-' . $teacher['userID'];
                                                    echo "<button class='all-schedules-button' onclick=\"openModal('$all_modal_id')\">See All Schedules</button>";
                                                    // All Schedules Modal
                                                    echo "<div id='$all_modal_id' class='modal'>";
                                                    echo "<div class='modal-content'>";
                                                    echo "<div class='modal-header'>All Schedules for " . htmlspecialchars($teacher['firstName'] . ' ' . $teacher['lastName']) . "<button class='modal-close' onclick=\"closeModal('$all_modal_id')\">×</button></div>";
                                                    echo "<div class='modal-body'>";
                                                    foreach ($days as $day) {
                                                        if (isset($teacher_assignments[$teacher['userID']][$day]) && count($teacher_assignments[$teacher['userID']][$day]) > 0) {
                                                            echo "<div class='modal-day-section'>";
                                                            echo "<h3>$day</h3>";
                                                            echo '<table class="details-table">';
                                                            echo '<thead>';
                                                            echo '<tr>';
                                                            echo '<th>Section</th>';
                                                            echo '<th>Subject</th>';
                                                            echo '<th>Day</th>';
                                                            echo '<th>Time Slot</th>';
                                                            echo '<th>Advisory</th>';
                                                            
                                                            echo '</tr>';
                                                            echo '</thead>';
                                                            echo '<tbody>';
                                                            foreach ($teacher_assignments[$teacher['userID']][$day] as $assignment) {
                                                                echo '<tr>';
                                                                echo '<td>' . htmlspecialchars($assignment['sectionName'] . ' (' . $assignment['sectionCode'] . ')') . '</td>';
                                                                echo '<td>' . htmlspecialchars($assignment['subjectCode'] . ' - ' . $assignment['subjectName']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($assignment['day']) . '</td>';
                                                                echo '<td>' . htmlspecialchars(date('h:i A', strtotime($assignment['startTime'])) . ' - ' . date('h:i A', strtotime($assignment['endTime']))) . '</td>';
                                                                echo '<td>' . ($assignment['advisory'] ? '✅ Yes' : '❌ No') . '</td>';
                                                                echo '</tr>';
                                                            }
                                                            echo '</tbody>';
                                                            echo '</table>';
                                                            echo '</div>';
                                                        }
                                                    }
                                                    echo '</div>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                }
                                                // Individual Day Modals
                                                foreach ($days as $day) {
                                                    if (isset($teacher_assignments[$teacher['userID']][$day]) && count($teacher_assignments[$teacher['userID']][$day]) > 0) {
                                                        $has_assignments = true;
                                                        $modal_id = 'modal-' . $teacher['userID'] . '-' . strtolower($day);
                                                        echo "<button class='day-button' onclick=\"openModal('$modal_id')\">Schedules for $day</button>";
                                                        echo "<div id='$modal_id' class='modal'>";
                                                        echo "<div class='modal-content'>";
                                                        echo "<div class='modal-header'>Schedules for $day<button class='modal-close' onclick=\"closeModal('$modal_id')\">×</button></div>";
                                                        echo "<div class='modal-body'>";
                                                        echo '<table class="details-table">';
                                                        echo '<thead>';
                                                        echo '<tr>';
                                                        echo '<th>Section</th>';
                                                        echo '<th>Subject</th>';
                                                        echo '<th>Day</th>';
                                                        echo '<th>Time Slot</th>';
                                                        echo '<th>Advisory</th>';
                                                        echo '</tr>';
                                                        echo '</thead>';
                                                        echo '<tbody>';
                                                        foreach ($teacher_assignments[$teacher['userID']][$day] as $assignment) {
                                                            echo '<tr>';
                                                            echo '<td>' . htmlspecialchars($assignment['sectionName'] . ' (' . $assignment['sectionCode'] . ')') . '</td>';
                                                            echo '<td>' . htmlspecialchars($assignment['subjectCode'] . ' - ' . $assignment['subjectName']) . '</td>';
                                                            echo '<td>' . htmlspecialchars($assignment['day']) . '</td>';
                                                            echo '<td>' . htmlspecialchars(date('h:i A', strtotime($assignment['startTime'])) . ' - ' . date('h:i A', strtotime($assignment['endTime']))) . '</td>';
                                                            echo '<td>' . ($assignment['advisory'] ? '✅ Yes' : '❌ No') . '</td>';
                                                            echo '</tr>';
                                                        }
                                                        echo '</tbody>';
                                                        echo '</table>';
                                                        echo '</div>';
                                                        echo '</div>';
                                                        echo '</div>';
                                                    }
                                                }
                                                if (!$has_assignments) {
                                                    echo '<p class="no-data">No schedules found for this teacher.</p>';
                                                }
                                                ?>
                                            </div>
                                        </details>
                                    </td>
                                    <td>
                                        <a href="edit_teacher_section.php?teacherID=<?php echo $teacher['userID']; ?>" class="btn-edit">
                                            <i class='bx bxs-edit'></i> Edit
                                        </a>
                                        <!-- <a href="archive_teacher_section.php?teacherID=<?php echo $teacher['userID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive all assignments for this teacher?');">
                                            <i class='bx bxs-archive-in'></i> Archive
                                        </a> -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="no-data">No teachers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                    <?php else: ?>
                        <a class="disabled">&laquo; Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                    <?php else: ?>
                        <a class="disabled">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
        }

        // Close modal when clicking outside the modal content
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });
    </script>
</body>
</html>