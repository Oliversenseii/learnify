<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT sectionID, sectionName, gradeLevel, semester FROM `sections` WHERE `archived` = 0";

if (!empty($search)) {
    $query .= " AND (`sectionName` LIKE :search)";
}

$stmt = $dbConnection->prepare($query);
if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%');
}
$stmt->execute();

$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Learnify Sections</title>
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

        .admin-table h3 {
             font-size: clamp(1.9rem, 3vw, 2rem);
             color: var(--dark);
        }
        
        .admin-table .btn-register {
            background: linear-gradient(135deg, #28a745, #218838) !important;
            padding: 8px 10px !important;
            border-radius: 10px;
            font-size: clamp(1rem, 3vw, 1.1rem);
            color: white;
            margin-bottom: 10px;
        }
        .admin-table .btn-register:hover {
            background: linear-gradient(135deg, #218838, #1e7e34) !important;
        }

        .admin-table .container {
            flex-direction: row;
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #ccc;
        }

        @media screen and (max-width: 768px) {
            .admin-table .container {
                flex-direction: column;
                display: flex;
                justify-content: center;
            }
            .admin-table .btn-register {
                width: fit-content;
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
                    <i class='bx bxs-dashboard' ></i>
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
                    <h1>Sections</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Home</a>
                        </li>
                        <li><i class='bx bx-chevron-right' ></i></li>
                        <li>
                            <a class="active" href="data_sections.php">Data</a>
                        </li>
                    </ul>
                </div>
                <a href="./adminDash.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </div>

            <!-- Search Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by section name or grade level" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="data_sections.php" class="clear-btn">Clear</a>
                <?php endif; ?>
            </form>

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

            <!-- Table to display Sections -->
            <div class="admin-table">
                <div class="container">
                    <h3>All Sections</h3>
                    <a href="./section.php" class="btn-register">
                        <i class='bx bx-user-plus'></i>
                        <span class="text">Register</span>
                    </a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Section Name</th>
                            <th>Grade Level</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sections)): ?>
                            <tr>
                                <td colspan="4">No results found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sections as $section): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($section['sectionName']); ?></td>
                                    <td><?php echo htmlspecialchars($section['gradeLevel']); ?></td>
                                    <td class="buttons">
                                        <!-- Edit Button -->
                                        <a href="edit_section.php?id=<?php echo $section['sectionID']; ?>" class="btn-edit">
                                            <i class='bx bxs-edit'></i>
                                        </a>
                                        <!-- Archive Button -->
                                        <a href="archive_section.php?sectionID=<?php echo $section['sectionID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this section?');">
                                            <i class='bx bxs-archive-in'></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

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
</body>
</html>