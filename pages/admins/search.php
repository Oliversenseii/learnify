<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Enable PDO error reporting for debugging
$dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pageTitle = "Search Results";

// Display mapping for user types
$userDisplayNames = [
    'Admin' => 'Administrator',
    'Professor' => 'Teacher',
    'Student' => 'Student',
    'Superadmin' => 'Superadmin',
];

$results = [
    'users' => [],
    'subjects' => [],
    'track_strands' => [],
    'sections' => []
];

if (isset($_GET['query']) && !empty($_GET['query'])) {
    $query = trim($_GET['query']);

    try {
        // Search Users (exclude SuperAdmin, include generated_code)
        $sql_users = "SELECT userID, firstName, middleName, lastName, email, image, userType, status, generated_code 
                      FROM users 
                      WHERE (firstName LIKE :query OR middleName LIKE :query OR lastName LIKE :query OR email LIKE :query) 
                        AND userType != 'SuperAdmin' 
                        AND archived = 0";
        $stmt = $dbConnection->prepare($sql_users);
        $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        $stmt->execute();
        $results['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Search Subjects
        $sql_subjects = "SELECT subjectID, subjectName, subjectCode, subjectType 
                         FROM subjects 
                         WHERE (subjectName LIKE :query OR subjectCode LIKE :query OR subjectType LIKE :query) 
                           AND archived = 0";
        $stmt = $dbConnection->prepare($sql_subjects);
        $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        $stmt->execute();
        $results['subjects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Search Track and Strands
        $sql_track_strands = "SELECT strandName, trackName, strandCode 
                              FROM track_strands 
                              WHERE (strandName LIKE :query OR trackName LIKE :query OR strandCode LIKE :query) 
                                AND archived = 0";
        $stmt = $dbConnection->prepare($sql_track_strands);
        $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        $stmt->execute();
        $results['track_strands'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Search Sections with Strand and Grade Level
        $sql_sections = "SELECT s.sectionID, s.sectionName, s.gradeLevel, t.strandName 
                         FROM sections s 
                         LEFT JOIN track_strands t ON s.strandID = t.strandID 
                         WHERE s.sectionName LIKE :query 
                           AND s.archived = 0";
        $stmt = $dbConnection->prepare($sql_sections);
        $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        $stmt->execute();
        $results['sections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update the title if only one user result is found
        if (count($results['users']) === 1 && empty($results['subjects']) && empty($results['track_strands']) && empty($results['sections'])) {
            $user = $results['users'][0];
            $pageTitle = htmlspecialchars($user['firstName'] . ' ' . $user['lastName']);
        }

    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error executing search: " . $e->getMessage();
        error_log("Search Error: " . $e->getMessage());
    }
} else {
    $_SESSION['error_message'] = "Please enter a search query.";
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
    <script src="./logout.js" defer></script>
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <style>
    :root {
        --light: #F9F9F9;
        --blue: #3C91E6;
        --light-blue: #CFE8FF;
        --grey: #eee;
        --dark-grey: #AAAAAA;
        --dark: #342E37;
        --success: #28A745;
        --danger: #DC3545;
    }

    #content main .head-title .left h1 {
        font-size: clamp(2rem, 3vw, 2.5rem);
        font-weight: 600;
        margin-bottom: clamp(8px, 1.5vw, 10px);
        color: var(--dark);
        line-height: 1.2;
    }

    .search-results {
        margin-top: clamp(16px, 2.5vw, 20px);
        display: flex;
        flex-direction: column;
        gap: clamp(20px, 3vw, 24px);
    }

    .result-section {
        background: var(--light);
        padding: clamp(16px, 2.5vw, 20px);
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .result-section h2 {
        font-size: clamp(1.7rem, 3vw, 2rem);
        color: var(--dark);
        margin-bottom: clamp(12px, 2vw, 15px);
        border-bottom: 2px solid var(--blue);
        padding-bottom: clamp(4px, 1vw, 5px);
    }

    .user, .subject, .track-strand, .section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: clamp(20px, 3vw, 24px);
        padding: clamp(12px, 3vw, 15px);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        margin-bottom: clamp(8px, 1.5vw, 10px);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .user:hover, .subject:hover, .track-strand:hover, .section:hover {
        background: var(--grey);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .result-left {
        display: flex;
        flex-direction: row;
        gap: clamp(50px, 3vw, 24px);
        color: var(--dark);
    }

    .user-info h3 {
        margin-top: clamp(4px, 1vw, 5px);
        text-align: center;
        text-transform: uppercase;
        font-size: clamp(1.4rem, 3vw, 1.5rem);
        line-height: 1.3;
    }

    .user-image img {
        width: clamp(14px, 40vw, 200px);
        height: clamp(140px, 240vw, 200px);
        border-radius: 5px;
        object-fit: cover;
    }

    .qr-code-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: clamp(8px, 1.5vw, 10px);
    }

    .qr-code-container img {
        width: clamp(130px, 25vw, 170px);
        height: clamp(130px, 25vw, 170px);
        border: 1px solid var(--grey);
        border-radius: 5px;
    }

    .qr-code-container p {
        font-size: clamp(1.1rem, 3vw, 1.2rem) !important;
        color: var(--dark-grey);
        margin: 0;
        text-align: center;
    }

    .download-qr {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        padding: clamp(8px, 1.5vw, 10px) clamp(12px, 2vw, 16px);
        width: fit-content;
        border-radius: 5px;
        text-decoration: none;
        font-size: clamp(1.1rem, 3vw, 1.2rem) !important;
        text-align: center;
        transition: background 0.2s ease;
        line-height: 1.5;
    }

    .download-qr:hover {
        background: linear-gradient(135deg, #0056b3, #003d80);
    }

    .icon-placeholder {
        width: clamp(80px, 15vw, 100px);
        height: clamp(80px, 15vw, 100px);
        background: var(--light-blue);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: clamp(8px, 1.5vw, 10px);
    }

    .icon-placeholder i {
        font-size: clamp(1.1rem, 3vw, 1.2rem) !important;
        color: var(--blue);
    }

    .result-right table {
        width: 100%;
        border-collapse: collapse;
    }

    .result-right td {
        padding: clamp(6px, 1.5vw, 8px);
        font-size: clamp(1.4rem, 3vw, 1.5rem) !important;
        color: var(--dark-grey);
        word-break: break-word;
        overflow-wrap: break-word;
    }

    .result-right td:first-child {
        font-weight: 600;
        color: var(--dark);
        width: 30%;
    }

    .status-active, .status-inactive, .status-suspended {
        padding: clamp(4px, 1vw, 6px) clamp(8px, 1.5vw, 10px);
        border-radius: 12px;
        display: inline-block;
        text-align: center;
        font-size: clamp(1.1rem, 3vw, 1.2rem) !important;
    }

    .status-active {
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
    }

    .status-inactive {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }

    .status-suspended {
        background: var(--danger);
        color: white;
    }

    .no-results {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: clamp(20px, 4vw, 24px);
        text-align: center;
        background: var(--light);
        border-radius: 8px;
    }

    .no-results i {
        font-size: clamp(2rem, 3vw, 2.5rem) !important;
        color: var(--blue);
        margin-bottom: clamp(8px, 1.5vw, 10px);
    }

    .no-results p {
        font-size: clamp(1.1rem, 3vw, 1.2rem) !important;
        color: var(--dark);
    }

    .error-notification {
        max-width: clamp(300px, 90vw, 450px);
        margin: clamp(8px, 1.5vw, 10px) auto;
        padding: clamp(12px, 2vw, 15px);
        border-radius: 5px;
        text-align: center;
        font-weight: bold;
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: var(--light);
        font-size: clamp(1.1rem, 3vw, 1.2rem) !important;
    }

    /* Tablet and smaller devices */
    @media (max-width: 768px) {
        .user, .subject, .track-strand, .section {
            grid-template-columns: 1fr;
            text-align: center;
            gap: clamp(12px, 2vw, 16px);
        }

        .result-left {
            flex-direction: column;
            align-items: center;
            gap: clamp(16px, 2.5vw, 20px);
        }

        .result-right table {
            width: 100%;
        }

        .result-right td {
            display: block;
            width: 100%;
            text-align: center;
            padding: clamp(4px, 1vw, 6px);
        }

        .result-right td:first-child {
            width: 100%;
            margin-bottom: clamp(4px, 1vw, 5px);
        }

        .user-image img {
            width: clamp(100px, 25vw, 120px);
            height: clamp(100px, 25vw, 120px);
        }

        .qr-code-container img {
            width: clamp(80px, 20vw, 100px);
            height: clamp(80px, 20vw, 100px);
        }

        .download-qr {
            margin-top: clamp(6px, 1.5vw, 8px);
            padding: clamp(10px, 2vw, 12px) clamp(14px, 2.5vw, 16px);
        }

        .result-section {
            padding: clamp(12px, 2vw, 16px);
        }

        .result-right td {
            word-break: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
        }
    }

    /* Small mobile devices */
    @media (max-width: 480px) {
        #content main .head-title .left h1 {
            font-size: clamp(1.25rem, 3.5vw, 1.5rem);
        }

        .result-section h2 {
            font-size: clamp(1rem, 3vw, 1.25rem);
        }

        .user, .subject, .track-strand, .section {
            padding: clamp(8px, 2vw, 12px);
        }

        .user-info h3 {
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .user-image img {
            width: clamp(80px, 22vw, 100px);
            height: clamp(80px, 22vw, 100px);
        }

        .qr-code-container img {
            width: clamp(60px, 18vw, 80px);
            height: clamp(60px, 18vw, 80px);
        }

        .icon-placeholder {
            width: clamp(60px, 15vw, 80px);
            height: clamp(60px, 15vw, 80px);
        }

        .icon-placeholder i {
            font-size: clamp(1.2rem, 3vw, 1.5rem);
        }

        .download-qr {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            padding: clamp(8px, 2vw, 10px) clamp(12px, 2.5vw, 14px);
        }

        .no-results {
            padding: clamp(16px, 4vw, 20px);
        }

        .no-results i {
            font-size: clamp(1.5rem, 4vw, 2rem);
        }

        .no-results p {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
        }

        .error-notification {
            max-width: clamp(250px, 85vw, 300px);
            font-size: clamp(0.8rem, 2vw, 0.9rem);
        }

        .result-right td {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            word-break: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }

        .result-right td:first-child {
            white-space: normal;
        }

        .status-active, .status-inactive, .status-suspended {
            font-size: clamp(0.7rem, 1.5vw, 0.8rem);
        }
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

        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
             --transition: all 0.3s ease;
             --white: #FFFFFF;
             --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
                <img src="<?php echo isset($_SESSION['image']) ? htmlspecialchars($_SESSION['image']) : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small><?php echo htmlspecialchars($userDisplayNames[$_SESSION['userType']] ?? $_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Search Results</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Home</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="active" href="#"><?php echo htmlspecialchars($pageTitle); ?></a>
                        </li>
                    </ul>
                </div>
                <a href="./adminDash.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </div>

            <!-- Error Message -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="search-results">
                <!-- Users Section -->
                <?php if (!empty($results['users'])): ?>
                    <div class="result-section">
                        <h2>Users</h2>
                        <?php foreach ($results['users'] as $user): ?>
                            <div class="user">
                                <div class="result-left">
                                    <div class="qr-code-container">
                                        <?php if (!empty($user['generated_code'])): ?>
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($user['generated_code']); ?>" alt="QR Code">
                                            <a href="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($user['generated_code']); ?>" class="download-qr" download="QR_Code_<?php echo htmlspecialchars($user['generated_code']); ?>.png" target="_blank">View QR Code</a>
                                        <?php else: ?>
                                            <p>No QR code available</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-info">
                                        <div class="user-image">
                                            <img src="<?php echo ($user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png'); ?>" alt="Profile Image">
                                        </div>
                                        <h3>
                                            <?php
                                                echo htmlspecialchars(
                                                    trim(
                                                        $user['lastName'] . ', ' . $user['firstName'] . 
                                                        ($user['middleName'] ? ' ' . $user['middleName'] : '')
                                                    )
                                                );
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                                <div class="result-right">
                                    <table>
                                        <tr><td>Email:</td><td><?php echo htmlspecialchars($user['email']); ?></td></tr>
                                        <tr><td>User Type:</td><td><?php echo htmlspecialchars($userDisplayNames[$user['userType']] ?? $user['userType']); ?></td></tr>
                                        <tr><td>Status:</td><td><span class="status-<?php echo strtolower($user['status']); ?>"><?php echo htmlspecialchars($user['status']); ?></span></td></tr>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Subjects Section -->
                <?php if (!empty($results['subjects'])): ?>
                    <div class="result-section">
                        <h2>Subjects</h2>
                        <?php foreach ($results['subjects'] as $subject): ?>
                            <div class="subject">
                                <div class="result-left">
                                    <div class="icon-placeholder">
                                        <i class='bx bxs-book'></i>
                                    </div>
                                    <h3><?php echo htmlspecialchars($subject['subjectName']); ?></h3>
                                </div>
                                <div class="result-right">
                                    <table>
                                        <tr><td>Subject Code:</td><td><?php echo htmlspecialchars($userDisplayNames[$subject['subjectCode']] ?? $subject['subjectCode']); ?></td></tr>
                                        <tr><td>Subject Type:</td><td><?php echo htmlspecialchars($subject['subjectType'] ?: 'N/A'); ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Track and Strands Section -->
                <?php if (!empty($results['track_strands'])): ?>
                    <div class="result-section">
                        <h2>Tracks & Strands</h2>
                        <?php foreach ($results['track_strands'] as $track): ?>
                            <div class="track-strand">
                                <div class="result-left">
                                    <div class="icon-placeholder">
                                        <i class='bx bxs-bookmark'></i>
                                    </div>
                                    <h3><?php echo htmlspecialchars($track['strandName']); ?></h3>
                                </div>
                                <div class="result-right">
                                    <table>
                                        <tr><td>Track Name:</td><td><?php echo htmlspecialchars($track['trackName']); ?></td></tr>
                                        <tr><td>Strand Code:</td><td><?php echo htmlspecialchars($track['strandCode'] ?: 'N/A'); ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Sections Section -->
                <?php if (!empty($results['sections'])): ?>
                    <div class="result-section">
                        <h2>Sections</h2>
                        <?php foreach ($results['sections'] as $section): ?>
                            <div class="section">
                                <div class="result-left">
                                    <div class="icon-placeholder">
                                        <i class='bx bx-book-content'></i>
                                    </div>
                                    <h3><?php echo htmlspecialchars($section['sectionName']); ?></h3>
                                </div>
                                <div class="result-right">
                                    <table>
                                        <tr><td>Strand:</td><td><?php echo htmlspecialchars($section['strandName'] ?: 'N/A'); ?></td></tr>
                                        <tr><td>Grade Level:</td><td><?php echo htmlspecialchars($section['gradeLevel'] ?: 'N/A'); ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- No Results -->
                <?php if (empty($results['users']) && empty($results['subjects']) && empty($results['track_strands']) && empty($results['sections'])): ?>
                    <div class="no-results">
                        <i class='bx bx-search'></i>
                        <p>No results found for your search.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>