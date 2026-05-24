<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Validate and sanitize inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sex = isset($_GET['sex']) ? strtolower(trim($_GET['sex'])) : '';
$age_group = isset($_GET['age_group']) ? trim($_GET['age_group']) : '';
$valid_sex = in_array($sex, ['male', 'female']) ? $sex : null;
$valid_age_groups = ['0-17', '18-24', '25-34', '35-44', '45+'];
$valid_age_group = in_array($age_group, $valid_age_groups) ? $age_group : null;

// Pagination settings
$students_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $students_per_page;

// Encryption function for userID
function encryptID($id) {
    $key = "b7e1a8f9c3d4e2b69a7f5d8e1c4b3a61";
    return urlencode(base64_encode(openssl_encrypt($id, 'AES-128-ECB', $key, 0, "")));
}

// Build SQL query for counting total students
$count_sql = "SELECT COUNT(*) as total 
              FROM users 
              WHERE userType = 'Student' 
              AND userType != 'SuperAdmin' 
              AND archived = 0";

$count_params = [];
if ($valid_sex) {
    $count_sql .= " AND sex = :sex";
    $count_params[':sex'] = $valid_sex;
}
if ($valid_age_group) {
    $count_sql .= " AND CASE 
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) < 18 THEN '0-17'
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) BETWEEN 18 AND 24 THEN '18-24'
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) BETWEEN 25 AND 34 THEN '25-34'
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) BETWEEN 35 AND 44 THEN '35-44'
                    ELSE '45+'
                END = :age_group";
    $count_params[':age_group'] = $valid_age_group;
}
if ($search !== '') {
    $count_sql .= " AND (firstName LIKE :search OR lastName LIKE :search OR email LIKE :search)";
    $count_params[':search'] = "%$search%";
}

// Get total number of students
try {
    $count_stmt = $dbConnection->prepare($count_sql);
    foreach ($count_params as $key => $value) {
        $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_students = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_students / $students_per_page);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while counting data: " . htmlspecialchars($e->getMessage());
    $total_students = 0;
    $total_pages = 1;
}

// Build SQL query for fetching paginated students
$sql = "SELECT userID, firstName, lastName, email, contactNumber, status, birthday, image 
        FROM users 
        WHERE userType = 'Student' 
        AND userType != 'SuperAdmin' 
        AND archived = 0";

$params = [];
if ($valid_sex) {
    $sql .= " AND sex = :sex";
    $params[':sex'] = $valid_sex;
}
if ($valid_age_group) {
    $sql .= " AND CASE 
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) < 18 THEN '0-17'
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) BETWEEN 18 AND 24 THEN '18-24'
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) BETWEEN 25 AND 34 THEN '25-34'
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) BETWEEN 35 AND 44 THEN '35-44'
                    ELSE '45+'
                END = :age_group";
    $params[':age_group'] = $valid_age_group;
}
if ($search !== '') {
    $sql .= " AND (firstName LIKE :search OR lastName LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " LIMIT :limit OFFSET :offset";
try {
    $stmt = $dbConnection->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $students_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while fetching data: " . htmlspecialchars($e->getMessage());
    $students = [];
}

// Calculate age function
function calculateAge($birthday) {
    if (!$birthday) return 'N/A';
    $birthDate = new DateTime($birthday);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
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
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js" defer></script>
    <style>
        :root {
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --transition: all 0.3s ease;
            --white: #FFFFFF;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }
        .status-legend{
            text-align: center;
            background-color: var(--grey);
            width: 100%;
            max-width: 350px;
            padding: 1rem;
            margin: 0 auto;
            border-radius: 10px;
        }
        .status-legend h2 {
            font-size: clamp(1.2rem, 3vw, 2rem);
            border-bottom: 1px solid #ccc;
            color: var(--dark);
            margin: 1rem;
        }
        legend {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 10px;
        }
        legend span {
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.3rem);
        }
        .buttons-edit-legend,
        .buttons-archive-legend {
            padding: 8px 13px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            margin-right: 5px;
        }
        .buttons-archive-legend {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #ffffff;
        }
        .buttons-edit-legend {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: black;
        }
        @media screen and (max-width: 460px) {
            .buttons-edit-legend,
            .buttons-archive-legend {
                padding: 5px 10px;
                width: fit-content;
            }
            legend {
                flex-direction: column;
                align-items: center;
            }
            .buttons {
                display: flex;
                gap: 10px;
                flex-direction: column;
            }
        }
        .buttons {
            gap: 5px !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-edit, .btn-archive {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            width: fit-content;            
        }
        .btn-edit {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: black;
        }
        .btn-archive {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #ffffff;
        }
        .btn-edit:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
        }
        .btn-archive:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        .success-notification, .error-notification, .filter-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 15px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            margin-bottom: 20px;
            margin-top: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        .filter-notification {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        .admin-table {
            width: 90%;
            overflow-x: auto;
            margin-bottom: 20px;
        }
        .admin-table th, .admin-table td {
            font-size: clamp(1rem ,3vw, 1.2rem);
        }
        .admin-table td {
            border: none;
        }
        .admin-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease;
        }
        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }
        .search-form {
            margin-bottom: 20px;
        }
        .search-form input[type="text"] {
            padding: 8px;
            width: 250px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .search-form select {
            padding: 8px;
            width: 150px;
            border-radius: 5px;
            border: 1px solid #ccc;
            color: var(--dark);
            background-color: var(--grey);
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            margin-left: 5px;
        }
        .search-form select:focus {
            border: 1px solid #007bff;
        }
        .search-form button, .search-form .clear-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 5px;
        }
        .search-form button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        .search-form .clear-btn {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            text-decoration: none;
        }
        .search-form button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        .search-form .clear-btn:hover {
            background: linear-gradient(135deg, #5a6268, #4b5357);
        }
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
            .search-form input[type="text"], .search-form select {
                width: 100%;
                margin-bottom: 10px;
            }
            .search-form button, .search-form .clear-btn {
                width: 100%;
                margin-left: 0;
                margin-bottom: 10px;
            }
        }
        .status-active {
            background-color: #4CAF50;
            padding: 5px;
            text-align: center;
            border-radius: 10px;
            color: #f0f0f0;
        }
        .status-inactive {
            background-color: #dc3545;
            padding: 5px;
            text-align: center;
            border-radius: 10px;
            color: #f0f0f0;
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
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: clamp(5px, 1vw, 8px);
            margin-top: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            padding: 0 10px;
        }
        .pagination a {
            padding: clamp(6px, 1.5vw, 8px) clamp(10px, 2vw, 12px);
            border: 1px solid var(--primary);
            border-radius: 5px;
            text-decoration: none;
            color: var(--primary);
            transition: var(--transition);
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            min-width: 30px;
            text-align: center;
        }
        .pagination a:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
        }
        .pagination a.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            font-weight: bold;
        }
        .pagination a.disabled {
            color: #ccc;
            border-color: #ccc;
            pointer-events: none;
        }
        .pagination .ellipsis {
            padding: clamp(6px, 1.5vw, 8px) clamp(10px, 2vw, 12px);
            color: var(--primary);
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }
        @media screen and (max-width: 600px) {
            .pagination {
                gap: 5px;
                justify-content: center;
            }
            .pagination a {
                padding: 5px 8px;
                font-size: 0.9rem;
                min-width: 25px;
            }
            .pagination .ellipsis {
                padding: 5px 8px;
                font-size: 0.9rem;
            }
        }
        @media screen and (max-width: 400px) {
            .pagination a:not(.active):not(.prev):not(.next) {
                display: none;
            }
            .pagination a.prev, .pagination a.next, .pagination a.active {
                display: inline-block;
            }
            .pagination .ellipsis {
                display: inline-block;
            }
        }
    </style>
    <title>Learnify</title>
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
                <img src="<?php echo isset($_SESSION['image']) ? htmlspecialchars($_SESSION['image']) : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Students<?php echo ($valid_sex || $valid_age_group) ? ' (' . ($valid_sex ? ucfirst($valid_sex) : '') . ($valid_sex && $valid_age_group ? ', ' : '') . ($valid_age_group ? 'Age ' . $valid_age_group : '') . ')' : ''; ?></h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="data_student.php">Students</a></li>
                    </ul>
                </div>
                <a href="./adminDash.php" class="back-button"><i class='bx bx-arrow-back'></i> Back to Dashboard</a>
            </div>

            <!-- Filter Notification -->
            <?php if ($valid_sex || $valid_age_group): ?>
                <div class="filter-notification">
                    Showing <?php echo $valid_sex ? ucfirst($valid_sex) : ''; ?>
                    <?php echo ($valid_sex && $valid_age_group) ? ' and ' : ''; ?>
                    <?php echo $valid_age_group ? 'Age ' . $valid_age_group : ''; ?> Students
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Search Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                <select name="age_group">
                    <option value="">All Ages</option>
                    <?php foreach ($valid_age_groups as $group): ?>
                        <option value="<?php echo htmlspecialchars($group); ?>" <?php echo $valid_age_group === $group ? 'selected' : ''; ?>><?php echo htmlspecialchars($group); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Search</button>
                <?php if (!empty($search) || $valid_sex || $valid_age_group): ?>
                    <a href="data_student.php" class="clear-btn">Clear</a>
                <?php endif; ?>
                <?php if ($valid_sex): ?>
                    <input type="hidden" name="sex" value="<?php echo htmlspecialchars($valid_sex); ?>">
                <?php endif; ?>
            </form>

            <!-- Table to display students -->
            <div class="admin-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Profile Image</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Age</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php $counter = $offset + 1; ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo $counter; ?></td>
                                    <td>
                                        <?php if ($student['image']): ?>
                                            <img src="<?php echo htmlspecialchars($student['image']); ?>" alt="Profile Image" class="profile-img">
                                        <?php else: ?>
                                            <img src="./img/noprofile.png" alt="No Profile Image" class="profile-img">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['contactNumber']); ?></td>
                                    <td><?php echo calculateAge($student['birthday']); ?></td>
                                    <td>
                                        <p class="<?php echo strtolower($student['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo htmlspecialchars($student['status']); ?>
                                        </p>
                                    </td>
                                    <td class="buttons">
                                        <a href="edit_student.php?userID=<?php echo encryptID($student['userID']); ?>" class="btn-edit">
                                            <i class='bx bxs-edit'></i> 
                                        </a>
                                        <a href="archive_student.php?userID=<?php echo encryptID($student['userID']); ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this student?');">
                                            <i class='bx bxs-archive-in'></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php $counter++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    No <?php echo $valid_sex ? strtolower($valid_sex) : ''; ?>
                                    <?php echo $valid_age_group ? 'age ' . $valid_age_group : 'students'; ?>
                                    <?php echo $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?>
                                    found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <?php
                    $query_params = $_GET;
                    $query_params['page'] = max(1, $page - 1);
                    $prev_url = "data_student.php?" . http_build_query($query_params);
                    ?>
                    <a href="<?php echo htmlspecialchars($prev_url); ?>" class="prev <?php echo $page <= 1 ? 'disabled' : ''; ?>">Previous</a>
                    <?php
                    // Responsive pagination logic: show first, last, current, and nearby pages
                    $range = 2; // Number of pages to show on each side of current page
                    $show_first = $page > $range + 1;
                    $show_last = $page < $total_pages - $range;

                    if ($show_first) {
                        $query_params['page'] = 1;
                        $page_url = "data_student.php?" . http_build_query($query_params);
                        echo '<a href="' . htmlspecialchars($page_url) . '">1</a>';
                        if ($page > $range + 2) {
                            echo '<span class="ellipsis">...</span>';
                        }
                    }

                    for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++) {
                        $query_params['page'] = $i;
                        $page_url = "data_student.php?" . http_build_query($query_params);
                        echo '<a href="' . htmlspecialchars($page_url) . '" class="' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
                    }

                    if ($show_last && $total_pages > 1) {
                        if ($page < $total_pages - $range - 1) {
                            echo '<span class="ellipsis">...</span>';
                        }
                        $query_params['page'] = $total_pages;
                        $page_url = "data_student.php?" . http_build_query($query_params);
                        echo '<a href="' . htmlspecialchars($page_url) . '">' . $total_pages . '</a>';
                    }

                    $query_params['page'] = min($total_pages, $page + 1);
                    $next_url = "data_student.php?" . http_build_query($query_params);
                    ?>
                    <a href="<?php echo htmlspecialchars($next_url); ?>" class="next <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next</a>
                </div>

                <div class="status-legend">
                    <h2>Status Legend</h2>
                    <legend>
                        <div class="buttons-edit-legend">
                            <i class='bx bxs-edit'></i> 
                        </div>
                        <span>Edit</span>
                        <div class="buttons-archive-legend">
                            <i class='bx bxs-archive-in'></i> 
                        </div>
                        <span>Archive</span>
                    </legend>
                </div>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>