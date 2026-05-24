<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';
require_once './check_enrollment_status.php';

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
    header("Location: studentDash.php");
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];

try {
    // Get user details
    $stmt = $dbConnection->prepare("SELECT firstName, userType, image FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
    } else {
        header("Location: ../../index.php");
        exit;
    }

    // Verify student is enrolled in the section
    $checkStmt = $dbConnection->prepare("
        SELECT ts.sectionID, s.sectionName, sub.subjectName
        FROM teacher_section ts 
        JOIN sections s ON ts.sectionID = s.sectionID 
        JOIN subjects sub ON ts.subjectID = sub.subjectID 
        JOIN student_section ss ON ts.sectionID = ss.sectionID 
        WHERE ts.teacherSectionID = :teacherSectionID AND ss.userID = :userID 
        AND ts.archived = 0 AND ss.archived = 0 AND s.archived = 0 AND sub.archived = 0 AND ss.status = 'Enrolled'
    ");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->execute();
    $section = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        $_SESSION['error_message'] = "You are not enrolled in this section.";
        header("Location: studentDash.php");
        exit;
    }

    // Fetch classmates with profile images and separate names
    $classmatesStmt = $dbConnection->prepare("
        SELECT DISTINCT u.userID, u.firstName, u.lastName, u.image
        FROM users u
        JOIN student_section ss ON u.userID = ss.userID
        WHERE ss.sectionID = :sectionID AND ss.status = 'Enrolled' AND u.userID != :userID AND u.archived = 0 AND ss.archived = 0
        ORDER BY u.lastName, u.firstName
    ");
    $classmatesStmt->bindParam(':sectionID', $section['sectionID'], PDO::PARAM_INT);
    $classmatesStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $classmatesStmt->execute();
    $classmates = $classmatesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
}

require_once './get_notification_count.php';

// Ensure userID is set and validated
$userID = isset($_SESSION['userID']) ? filter_var($_SESSION['userID'], FILTER_VALIDATE_INT) : null;
$unreadCount = $userID ? getUnreadAnnouncementCount($dbConnection, $userID) : 0;

// Ensure announcements_viewed session flag is set
if (!isset($_SESSION['announcements_viewed'])) {
    $_SESSION['announcements_viewed'] = false;
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
    <title>Learnify - Classmates</title>
    <style>
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --grey: #f3f4f6;
            --dark-grey: #9ca3af;
            --dark: #1f2937;
            --green: #10b981;
            --red: #ef4444;
            --purple: #8b5cf6;
            --light-blue: #e0f2fe;
            --google-grey: #202124;
            --google-light-grey: #f1f3f4;
            --google-blue: #4285f4;
            --gradient-start: #e3f2fd;
            --gradient-end: #ffffff;
            --modal-bg: #e6f3f5;
            --footer-gradient-start: #4a90e2;
            --footer-gradient-end: #50e3c2;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #007bff, #0056b3) !important; 
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: var(--shadow-sm);
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .back-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
        }

        .classmates-table {
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: var(--shadow-md);
            overflow-x: auto;
            max-width: fit-content;
            margin: auto;
        }

        .classmates-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.9rem;
            min-width: 600px;
            background-color: var(--light);
        }

        .classmates-table th,
        .classmates-table td {
            padding: 1rem;
            text-align: left;
        }

        .classmates-table th {
            background: #0056b3;
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .classmates-table td {
            color: var(--dark);
            vertical-align: middle;
        }

        .classmates-table tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.1); 
            /* border: 1px solid rgba(255, 255, 255, 0.2);  */
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); 
        }

        .classmates-table tr:hover {
             background: var(--grey); 
            box-shadow: 0 6px 40px rgba(0, 0, 0, 0.15); 
        }

        .student-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background-color: var(--light-blue);
            vertical-align: middle;
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .search-container {
            position: relative;
            width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 2rem 0.5rem 2.5rem;
            border: 1px solid var(--dark-grey);
            border-radius: 4px;
            font-size: 0.875rem;
            transition: border-color 0.3s ease;
            background-color: var(--grey);
            color: var(--dark);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--google-blue);
            box-shadow: 0 0 0 2px rgba(66, 133, 244, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-grey);
        }

        .sort-container {
            display: flex;
            gap: 0.5rem;
        }

        .sort-btn {
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .sort-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
        }

        .sort-btn.active {
            background: linear-gradient(135deg, #6610f2, #5208c9);
        }

        @media (max-width: 768px) {
            .classmates-table {
                margin: 1rem;
                padding: 1rem;
            }

            .classmates-table table {
                font-size: 0.85rem;
                min-width: 500px;
            }

            .classmates-table th,
            .classmates-table td {
                padding: 0.75rem;
            }

            .student-image {
                width: 35px;
                height: 35px;
            }

            .table-controls {
                flex-direction: column;
                gap: 1rem;
            }

            .search-container {
                width: 100%;
            }

            .head-title h1 {
                font-size: 1.25rem;
            }

            .back-btn {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .classmates-table {
                margin: 0.5rem;
                padding: 0.5rem;
            }

            .classmates-table table {
                font-size: 0.8rem;
                min-width: 400px;
            }

            .classmates-table th,
            .classmates-table td {
                padding: 0.5rem;
            }

            .student-image {
                width: 30px;
                height: 30px;
            }

            .search-input {
                font-size: 0.8rem;
                padding: 0.4rem 2rem 0.4rem 2.5rem;
            }

            .sort-btn {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }

            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }

            .back-btn {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }
        }

        .notification-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border-radius: 50%;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            display: inline-block;
        }
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./studentDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./announcements.php?viewed=true">
                    <i class='bx bxs-bell'></i>
                    <span class="text">Announcements</span>
                    <?php if ($unreadCount > 0 && !$_SESSION['announcements_viewed']): ?>
                        <span class="notification-badge"><?php echo htmlspecialchars($unreadCount); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li>
                <a href="./game.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Games</span>
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
                <a href="./version.php">
                    <i class='bx bxs-info-circle'></i>
                    <span class="text">Version</span>
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
                    <input type="search" name="query" placeholder="Search..." required aria-label="Search">
                    <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile" aria-label="Profile">
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Classmates - <?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h1>
                    <ul class="breadcrumb">
                        <li><a href="./studentDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./studentDash.php">Class Details</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Classmates</a></li>
                    </ul>
                </div>
                <a href="javascript:history.back()" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
                    <span class="text">Back</span>
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

            <div class="classmates-table">
                <div class="table-controls">
                    <div class="search-container">
                        <i class='bx bx-search search-icon'></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Search by name..." aria-label="Search classmates">
                    </div>
                    <div class="sort-container">
                        <button id="sortAZ" class="sort-btn active">A-Z</button>
                        <button id="sortZA" class="sort-btn">Z-A</button>
                    </div>
                </div>
                <table id="classmatesTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Profile</th>
                            <th>Name</th>
                        </tr>
                    </thead>
                    <tbody id="classmatesBody">
                        <?php if (!empty($classmates)): ?>
                            <?php foreach ($classmates as $index => $classmate): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <img src="<?php echo $classmate['image'] ? htmlspecialchars($classmate['image']) : './img/noprofile.png'; ?>" alt="Profile Image" class="student-image">
                                    </td>
                                    <td><?php echo htmlspecialchars(strtoupper($classmate['lastName'] . ', ' . $classmate['firstName'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">No classmates found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </section>

    <script>
        const searchInput = document.getElementById('searchInput');
        const sortAZBtn = document.getElementById('sortAZ');
        const sortZABtn = document.getElementById('sortZA');
        const tableBody = document.getElementById('classmatesBody');
        const table = document.getElementById('classmatesTable');
        let classmatesData = Array.from(tableBody.querySelectorAll('tr'));

        // Search functionality
        searchInput.addEventListener('input', () => {
            const searchTerm = searchInput.value.trim().toLowerCase();
            const filteredRows = classmatesData.filter(row => {
                const nameCell = row.cells[2].textContent.toLowerCase();
                return nameCell.includes(searchTerm);
            });

            tableBody.innerHTML = '';
            filteredRows.forEach((row, index) => {
                row.cells[0].textContent = index + 1; // Update row numbers
                tableBody.appendChild(row);
            });

            if (filteredRows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="3">No classmates found.</td></tr>';
            }
        });

        // Sort functionality
        function sortTable(order) {
            const sortedRows = Array.from(classmatesData).sort((a, b) => {
                const nameA = a.cells[2].textContent.toLowerCase();
                const nameB = b.cells[2].textContent.toLowerCase();
                return order === 'az' ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
            });

            tableBody.innerHTML = '';
            sortedRows.forEach((row, index) => {
                row.cells[0].textContent = index + 1; // Update row numbers
                tableBody.appendChild(row);
            });
        }

        sortAZBtn.addEventListener('click', () => {
            sortTable('az');
            sortAZBtn.classList.add('active');
            sortZABtn.classList.remove('active');
        });

        sortZABtn.addEventListener('click', () => {
            sortTable('za');
            sortZABtn.classList.add('active');
            sortAZBtn.classList.remove('active');
        });

        // Initialize with A-Z sort
        sortTable('az');
    </script>

    <script src="./utils/script.js"></script>
</body>
</html>