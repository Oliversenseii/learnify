<?php
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
if ($userID === false) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

// Get user details
try {
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

    // Handle search
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'test_types';

    // Fetch data for each table
    // Test Types
    $testTypeStmt = $dbConnection->prepare("
        SELECT testTypeID, testTypeName, totalQuestions, passingScore, archived
        FROM game_test_types
        WHERE testTypeName LIKE :search
    ");
    $testTypeStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $testTypeStmt->execute();
    $testTypes = $testTypeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Tests
    $testStmt = $dbConnection->prepare("
        SELECT t.testID, t.title, t.strandID, s.strandName, t.testTypeID, tt.testTypeName
        FROM game_tests t
        JOIN track_strands s ON t.strandID = s.strandID
        JOIN game_test_types tt ON t.testTypeID = tt.testTypeID
        WHERE t.archived = 0 AND (t.title LIKE :search OR s.strandName LIKE :search)
    ");
    $testStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $testStmt->execute();
    $tests = $testStmt->fetchAll(PDO::FETCH_ASSOC);

    // Questions with options and question number
    $questionStmt = $dbConnection->prepare("
        SELECT q.questionID, q.testID, t.title AS testTitle, q.questionText, 
               q.optionA, q.optionB, q.optionC, q.optionD, q.correctAnswer,
               (SELECT COUNT(*) FROM game_questions q2 WHERE q2.testID = q.testID AND q2.questionID <= q.questionID AND q2.archived = 0) AS questionNumber
        FROM game_questions q
        JOIN game_tests t ON q.testID = t.testID
        WHERE q.archived = 0 AND (q.questionText LIKE :search OR t.title LIKE :search OR q.optionA LIKE :search OR q.optionB LIKE :search OR q.optionC LIKE :search OR q.optionD LIKE :search)
    ");
    $questionStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $questionStmt->execute();
    $questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);

    // Lectures
    $lectureStmt = $dbConnection->prepare("
        SELECT l.lectureID, l.strandID, s.strandName, l.title, l.content, l.image
        FROM game_lectures l
        JOIN track_strands s ON l.strandID = s.strandID
        WHERE l.archived = 0 AND (l.title LIKE :search OR s.strandName LIKE :search)
    ");
    $lectureStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $lectureStmt->execute();
    $lectures = $lectureStmt->fetchAll(PDO::FETCH_ASSOC);

    // Badges
    $badgeStmt = $dbConnection->prepare("
        SELECT level, badgeName, icon, description
        FROM game_badges
        WHERE archived = 0 AND (badgeName LIKE :search OR description LIKE :search)
    ");
    $badgeStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $badgeStmt->execute();
    $badges = $badgeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch strands and test types for dropdowns in forms
    $strandStmt = $dbConnection->prepare("SELECT strandID, strandName FROM track_strands WHERE archived = 0");
    $strandStmt->execute();
    $strands = $strandStmt->fetchAll(PDO::FETCH_ASSOC);

    $testTypeStmt = $dbConnection->prepare("SELECT testTypeID, testTypeName FROM game_test_types WHERE archived = 0");
    $testTypeStmt->execute();
    $allTestTypes = $testTypeStmt->fetchAll(PDO::FETCH_ASSOC);

    $testStmt = $dbConnection->prepare("SELECT testID, title FROM game_tests WHERE archived = 0");
    $testStmt->execute();
    $allTests = $testStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred.";
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
    <script src="./logout.js"></script>
    <title>Learnify - Game Controller</title>
    <style>
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        .tabs {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: var(--light);
            color: var(--dark);
            margin-top: 10px;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .tab.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .tab:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            color: #ffffff;
        }

        .btn-archive {
            padding: 4px 10px !important;
        }

        .btn-edit, .btn-archive, .btn-add, .btn-add-question {
            padding: 7px 12px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: black;
            margin-bottom: 5px;
        }

        .btn-archive {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #ffffff;
        }

        .btn-add, .btn-add-question {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
        }

        .btn-archive:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .btn-add:hover, .btn-add-question:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .success-notification, .error-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 15px;
            margin: 1.5rem;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .modal {
            z-index: 1000000;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .table-filter select, .table-filter input {
            background-color: var(--grey);
            color: var(--dark);
            padding: 0.5rem;
            border: 1px solid var(--grey);
            border-radius: 0.375rem;
            font-size: 0.9rem;
            max-width: 200px;
        }

        .modal-content {
            background: var(--light);
            max-width: 400px;
            width: 50%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            position: relative;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-sizing: border-box;
            transition: max-width 0.3s ease-in-out, max-height 0.3s ease-in-out, width 0.3s ease-in-out, height 0.3s ease-in-out, padding 0.3s ease-in-out;
            animation: slideIn 0.3s ease-in-out;
        }

        @media screen and (max-width: 768px) {
            .modal-content {
                width: 95%;
            }
        }

        #test-types-table,
        #tests-table,
        #questions-table,
        #lectures-table,
        #badges-table {
            font-size: 0.9rem;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-fullscreen .modal-content {
            max-width: 100vw;
            max-height: 100vh;
            width: 100vw;
            height: 100vh;
            padding: 2rem;
            border-radius: 0;
            box-shadow: none;
        }

        .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            padding: 1.5rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body input[type="file"]:hover {
            cursor: pointer;
        }

        .modal-body input[type="file"]::file-selector-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }

        .modal-body input[type="file"]::file-selector-button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .modal-header .close-btn, .modal-header .fullscreen-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s ease;
            margin-left: 0.5rem;
        }

        .modal-header .close-btn:hover, .modal-header .fullscreen-btn:hover {
            color: var(--red);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-body label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 900;
            text-align: left;
        }

        .modal-body input, .modal-body select, .modal-body textarea {
            width: 100%;
            padding: 0.7rem;
            margin-bottom: 1rem;
            background-color: var(--grey);
            color: var(--dark);
            border: 1px solid var(--grey);
            border-radius: 0.375rem;
            font-size: 0.9rem;
        }

        .modal-body input[type="file"] {
            padding: 0.2rem;
        }

        .modal-body textarea {
            height: 100px;
            resize: vertical;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--grey);
            display: flex;
            justify-content: flex-end;
            background: var(--light);
        }

        .modal-footer button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
        }

        .modal-footer .save-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }

        .modal-footer .save-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .modal-footer .close-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            margin-left: 0.5rem;
        }

        .modal-footer .close-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .table-filter {
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .question-fieldset {
            border: 1px solid var(--grey);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.375rem;
            position: relative;
        }

        .question-fieldset legend {
            font-weight: 500;
            padding: 0 0.5rem;
        }

        .remove-question-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            border-radius: 0.375rem;
            padding: 0.2rem 0.5rem;
            cursor: pointer;
        }

        .remove-question-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .image-preview {
            max-width: 100%;
            max-height: 150px;
            margin-top: 0.5rem;
            display: none;
        }

        #test-types-table, 
        #tests-table,
        #questions-table,
        #lectures-table,
        #badges-table {
            background-color: var(--light);
        }

        #test-types-table tr:nth-child(even),
        #tests-table tr:nth-child(even),
        #questions-table tr:nth-child(even),
        #lectures-table tr:nth-child(even),
        #badges-table tr:nth-child(even) {
             background: rgba(255, 255, 255, 0.1); 
            /* border: 1px solid rgba(255, 255, 255, 0.2);  */
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); 
        }

        #test-types-table td,
        #tests-table td,
        #questions-table td,
        #lectures-table td,
        #badges-table td {
            border: none;
        }

        @media (max-width: 768px) {
            .tabs {
                flex-direction: column; 
                align-items: center;
            }

            .tab {
                width: 100%; 
                text-align: center;
                padding: 0.5rem;
                font-size: 0.85rem;
            }

            .btn-edit, .btn-archive, .btn-add, .btn-add-question {
                padding: 5px 8px;
            }

            .table-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .table-filter select, .table-filter input {
                font-size: 0.8rem;
                padding: 0.3rem;
            }

            .modal-content {
                max-width: 95%;
                padding: 0.8rem;
            }

            .modal-fullscreen .modal-content {
                max-width: 100vw;
                max-height: 100vh;
                width: 100vw;
                height: 100vh;
                padding: 0.8rem;
            }

            .modal-header h3 {
                font-size: 1.3rem;
            }

            .modal-body {
                padding: 0.8rem;
            }

            .modal-body label {
                font-size: 0.85rem;
            }

            .modal-body input, .modal-body select, .modal-body textarea {
                font-size: 0.8rem;
                padding: 0.3rem;
            }

            .modal-footer button {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }

            .question-fieldset {
                padding: 0.6rem;
            }

            .question-fieldset legend {
                font-size: 0.85rem;
            }

            .remove-question-btn {
                padding: 0.15rem 0.3rem;
                font-size: 0.8rem;
            }

            .image-preview {
                max-height: 100px;
            }

            .admin-table {
                overflow-x: auto;
            }

            table {
                min-width: 600px; 
            }
        }


        @media (max-width: 480px) {
            .tabs {
                gap: 0.3rem;
            }

            .tab {
                padding: 0.4rem;
                font-size: 0.8rem;
            }

            .btn-edit, .btn-archive, .btn-add, .btn-add-question {
                padding: 4px 6px;
                font-size: 0.9rem;
            }

            .success-notification, .error-notification {
                padding: 8px;
                margin: 0.8rem;
                font-size: 0.8rem;
            }

            .modal-content {
                max-width: 98%;
                padding: 0.6rem;
            }

            .modal-header h3 {
                font-size: 1.2rem;
            }

            .modal-body {
                padding: 0.6rem;
            }

            .modal-body label {
                font-size: 0.8rem;
            }

            .modal-body input, .modal-body select, .modal-body textarea {
                font-size: 0.75rem;
                padding: 0.25rem;
            }

            .modal-footer button {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
            }

            .question-fieldset {
                padding: 0.5rem;
            }

            .question-fieldset legend {
                font-size: 0.8rem;
            }

            .remove-question-btn {
                padding: 0.1rem 0.25rem;
                font-size: 0.75rem;
            }

            .image-preview {
                max-height: 80px;
            }
        }
    </style>
</head>
<body>
    
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
            <li class="active">
                <a href="./game_controller.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Learnify Braniacs</span>
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
                    <h1>Learnify Brainiacs Controller</h1>
                    <ul class="breadcrumb">
                        <li><a href="./superAdminDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./superAdminDash.php">Games</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./game_controller.php">Learnify Brainiacs Controller</a></li>
                    </ul>
                </div>
                <a href="./game.php" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
                    <span class="text">Back</span>
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <a href="?tab=test_types<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'test_types' ? 'active' : ''; ?>">Test Types</a>
                <a href="?tab=tests<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'tests' ? 'active' : ''; ?>">Tests</a>
                <a href="?tab=questions<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'questions' ? 'active' : ''; ?>">Questions</a>
                <a href="?tab=lectures<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'lectures' ? 'active' : ''; ?>">Lectures</a>
                <a href="?tab=badges<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'badges' ? 'active' : ''; ?>">Badges</a>
            </div>

            <!-- Test Types Section -->
            <?php if ($activeTab == 'test_types'): ?>
                <div class="admin-table">
                    <div class="table-filter">
                        <select onchange="filterTable('test-types-table', this, 0)">
                            <option value="">All Test Types</option>
                            <?php
                            $uniqueTestTypes = array_unique(array_column($testTypes, 'testTypeName'));
                            sort($uniqueTestTypes);
                            foreach ($uniqueTestTypes as $typeName): ?>
                                <option value="<?php echo htmlspecialchars($typeName); ?>"><?php echo htmlspecialchars($typeName); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="filterTable('test-types-table', this, 3)">
                            <option value="">All Status</option>
                            <option value="0">Active</option>
                            <option value="1">Archived</option>
                        </select>
                    </div>
                    <button class="btn-add" onclick="openModal('add-test-type-modal')">Add Test Type</button>
                    <table id="test-types-table">
                        <thead>
                            <tr>
                                <th>Test Type Name</th>
                                <th>Total Questions</th>
                                <th>Passing Score</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($testTypes)): ?>
                                <?php foreach ($testTypes as $testType): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($testType['testTypeName']); ?></td>
                                        <td><?php echo htmlspecialchars($testType['totalQuestions']); ?></td>
                                        <td><?php echo htmlspecialchars($testType['passingScore']); ?></td>
                                        <td><?php echo $testType['archived'] ? 'Archived' : 'Active'; ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditTestTypeModal(<?php echo $testType['testTypeID']; ?>, '<?php echo htmlspecialchars($testType['testTypeName']); ?>', <?php echo $testType['totalQuestions']; ?>, <?php echo $testType['passingScore']; ?>, <?php echo $testType['archived']; ?>)">
                                                <i class='bx bxs-edit'></i> Edit
                                            </button>
                                            <a href="manage_game.php?action=archive_test_type&testTypeID=<?php echo $testType['testTypeID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this test type?');">
                                                <i class='bx bxs-archive-in'></i> Archive
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No test types found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Test Type Modal -->
                <div id="add-test-type-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Add Test Type</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('add-test-type-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('add-test-type-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post">
                                <input type="hidden" name="action" value="add_test_type">
                                <label>Test Type Name</label>
                                <input type="text" name="testTypeName" required>
                                <label>Total Questions</label>
                                <input type="number" name="totalQuestions" min="1" required>
                                <label>Passing Score</label>
                                <input type="number" name="passingScore" min="0" required>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('add-test-type-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Test Type Modal -->
                <div id="edit-test-type-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Test Type</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('edit-test-type-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('edit-test-type-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post">
                                <input type="hidden" name="action" value="edit_test_type">
                                <input type="hidden" name="testTypeID" id="edit-test-type-id">
                                <label>Test Type Name</label>
                                <input type="text" name="testTypeName" id="edit-test-type-name" required>
                                <label>Total Questions</label>
                                <input type="number" name="totalQuestions" id="edit-test-type-questions" min="1" required>
                                <label>Passing Score</label>
                                <input type="number" name="passingScore" id="edit-test-type-passing" min="0" required>
                                <label>Status</label>
                                <select name="archived" id="edit-test-type-archived" required>
                                    <option value="0">Active</option>
                                    <option value="1">Archived</option>
                                </select>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('edit-test-type-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tests Section -->
            <?php if ($activeTab == 'tests'): ?>
                <div class="admin-table">
                    <div class="table-filter">
                        <select onchange="filterTable('tests-table', this, 0)">
                            <option value="">All Titles</option>
                            <?php
                            $uniqueTitles = array_unique(array_column($tests, 'title'));
                            sort($uniqueTitles);
                            foreach ($uniqueTitles as $title): ?>
                                <option value="<?php echo htmlspecialchars($title); ?>"><?php echo htmlspecialchars($title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="filterTable('tests-table', this, 1)">
                            <option value="">All Strands</option>
                            <?php
                            $uniqueStrands = array_unique(array_column($tests, 'strandName'));
                            sort($uniqueStrands);
                            foreach ($uniqueStrands as $strandName): ?>
                                <option value="<?php echo htmlspecialchars($strandName); ?>"><?php echo htmlspecialchars($strandName); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="filterTable('tests-table', this, 2)">
                            <option value="">All Test Types</option>
                            <?php
                            $uniqueTestTypes = array_unique(array_column($tests, 'testTypeName'));
                            sort($uniqueTestTypes);
                            foreach ($uniqueTestTypes as $testTypeName): ?>
                                <option value="<?php echo htmlspecialchars($testTypeName); ?>"><?php echo htmlspecialchars($testTypeName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn-add" onclick="openModal('add-test-modal')">Add Test</button>
                    <table id="tests-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Strand</th>
                                <th>Test Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($tests)): ?>
                                <?php foreach ($tests as $test): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($test['title']); ?></td>
                                        <td><?php echo htmlspecialchars($test['strandName']); ?></td>
                                        <td><?php echo htmlspecialchars($test['testTypeName']); ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditTestModal(<?php echo $test['testID']; ?>, '<?php echo htmlspecialchars($test['title']); ?>', <?php echo $test['strandID']; ?>, <?php echo $test['testTypeID']; ?>)">
                                                <i class='bx bxs-edit'></i> Edit
                                            </button>
                                            <a href="manage_game.php?action=archive_test&testID=<?php echo $test['testID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this test?');">
                                                <i class='bx bxs-archive-in'></i> Archive
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">No tests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Test Modal -->
                <div id="add-test-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Add Test</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('add-test-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('add-test-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post">
                                <input type="hidden" name="action" value="add_test">
                                <label>Title</label>
                                <input type="text" name="title" required>
                                <label>Strand</label>
                                <select name="strandID" required>
                                    <?php foreach ($strands as $strand): ?>
                                        <option value="<?php echo $strand['strandID']; ?>"><?php echo htmlspecialchars($strand['strandName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Test Type</label>
                                <select name="testTypeID" required>
                                    <?php foreach ($allTestTypes as $testType): ?>
                                        <option value="<?php echo $testType['testTypeID']; ?>"><?php echo htmlspecialchars($testType['testTypeName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('add-test-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Test Modal -->
                <div id="edit-test-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Test</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('edit-test-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('edit-test-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post">
                                <input type="hidden" name="action" value="edit_test">
                                <input type="hidden" name="testID" id="edit-test-id">
                                <label>Title</label>
                                <input type="text" name="title" id="edit-test-title" required>
                                <label>Strand</label>
                                <select name="strandID" id="edit-test-strand" required>
                                    <?php foreach ($strands as $strand): ?>
                                        <option value="<?php echo $strand['strandID']; ?>"><?php echo htmlspecialchars($strand['strandName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Test Type</label>
                                <select name="testTypeID" id="edit-test-type" required>
                                    <?php foreach ($allTestTypes as $testType): ?>
                                        <option value="<?php echo $testType['testTypeID']; ?>"><?php echo htmlspecialchars($testType['testTypeName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('edit-test-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Questions Section -->
            <?php if ($activeTab == 'questions'): ?>
                <div class="admin-table">
                    <div class="table-filter">
                        <input type="text" id="question-search" placeholder="Search questions..." oninput="searchQuestions('questions-table', this)">
                        <select onchange="filterTable('questions-table', this, 1)">
                            <option value="">All Tests</option>
                            <?php
                            $uniqueTests = array_unique(array_column($questions, 'testTitle'));
                            sort($uniqueTests);
                            foreach ($uniqueTests as $testTitle): ?>
                                <option value="<?php echo htmlspecialchars($testTitle); ?>"><?php echo htmlspecialchars($testTitle); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="filterTable('questions-table', this, 7)">
                            <option value="">All Correct Answers</option>
                            <?php
                            $uniqueCorrectAnswers = array_unique(array_column($questions, 'correctAnswer'));
                            sort($uniqueCorrectAnswers);
                            foreach ($uniqueCorrectAnswers as $correctAnswer): ?>
                                <option value="<?php echo htmlspecialchars($correctAnswer); ?>"><?php echo htmlspecialchars($correctAnswer); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn-add" onclick="openModal('add-question-modal')">Add Question(s)</button>
                    <table id="questions-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Test</th>
                                <th>Question Text</th>
                                <th>Option A</th>
                                <th>Option B</th>
                                <th>Option C</th>
                                <th>Option D</th>
                                <th>Correct Answer</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($questions)): ?>
                                <?php foreach ($questions as $question): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($question['questionNumber']); ?></td>
                                        <td><?php echo htmlspecialchars($question['testTitle']); ?></td>
                                        <td><?php
                                            $text = htmlspecialchars($question['questionText']);
                                            if (strlen($text) > 40) {
                                                $text = substr($text, 0, 40) . "...";
                                            }
                                            echo $text;
                                        ?></td>
                                        <td><?php
                                            $optionA = htmlspecialchars($question['optionA']);
                                            if (strlen($optionA) > 40) {
                                                $optionA = substr($optionA, 0, 40) . "...";
                                            }
                                            echo $optionA;
                                        ?></td>
                                        <td><?php
                                            $optionB = htmlspecialchars($question['optionB']);
                                            if (strlen($optionB) > 40) {
                                                $optionB = substr($optionB, 0, 40) . "...";
                                            }
                                            echo $optionB;
                                        ?></td>
                                        <td><?php
                                            $optionC = htmlspecialchars($question['optionC']);
                                            if (strlen($optionC) > 40) {
                                                $optionC = substr($optionC, 0, 40) . "...";
                                            }
                                            echo $optionC;
                                        ?></td>
                                        <td><?php
                                            $optionD = htmlspecialchars($question['optionD']);
                                            if (strlen($optionD) > 40) {
                                                $optionD = substr($optionD, 0, 40) . "...";
                                            }
                                            echo $optionD;
                                        ?></td>
                                        <td><?php echo htmlspecialchars($question['correctAnswer']); ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditQuestionModal(<?php echo $question['questionID']; ?>, <?php echo $question['testID']; ?>, '<?php echo htmlspecialchars($question['questionText']); ?>', '<?php echo htmlspecialchars($question['optionA']); ?>', '<?php echo htmlspecialchars($question['optionB']); ?>', '<?php echo htmlspecialchars($question['optionC']); ?>', '<?php echo htmlspecialchars($question['optionD']); ?>', '<?php echo htmlspecialchars($question['correctAnswer']); ?>')">
                                                <i class='bx bxs-edit'></i> Edit
                                            </button>
                                            <a href="manage_game.php?action=archive_question&questionID=<?php echo $question['questionID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this question?');">
                                                <i class='bx bxs-archive-in'></i> Archive
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">No questions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Question Modal -->
                <div id="add-question-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Add Question(s)</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('add-question-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('add-question-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_question">
                                <label>Test</label>
                                <select name="testID" required>
                                    <?php foreach ($allTests as $test): ?>
                                        <option value="<?php echo $test['testID']; ?>"><?php echo htmlspecialchars($test['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="questions-container">
                                    <fieldset class="question-fieldset">
                                        <legend>Question 1</legend>
                                        <button type="button" class="remove-question-btn" onclick="removeQuestionField(this)" style="display: none;"><i class='bx bx-x'></i></button>
                                        <label>Question Text</label>
                                        <textarea name="questions[0][questionText]" required></textarea>
                                        <label>Option A</label>
                                        <input type="text" name="questions[0][optionA]" required>
                                        <label>Option B</label>
                                        <input type="text" name="questions[0][optionB]" required>
                                        <label>Option C</label>
                                        <input type="text" name="questions[0][optionC]" required>
                                        <label>Option D</label>
                                        <input type="text" name="questions[0][optionD]" required>
                                        <label>Correct Answer</label>
                                        <select name="questions[0][correctAnswer]" required>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="D">D</option>
                                        </select>
                                    </fieldset>
                                </div>
                                <button type="button" class="btn-add-question" onclick="addQuestionField()">Add Another Question</button>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('add-question-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Question Modal -->
                <div id="edit-question-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Question</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('edit-question-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('edit-question-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post">
                                <input type="hidden" name="action" value="edit_question">
                                <input type="hidden" name="questionID" id="edit-question-id">
                                <label>Test</label>
                                <select name="testID" id="edit-question-test" required>
                                    <?php foreach ($allTests as $test): ?>
                                        <option value="<?php echo $test['testID']; ?>"><?php echo htmlspecialchars($test['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Question Text</label>
                                <textarea name="questionText" id="edit-question-text" required></textarea>
                                <label>Option A</label>
                                <input type="text" name="optionA" id="edit-question-optionA" required>
                                <label>Option B</label>
                                <input type="text" name="optionB" id="edit-question-optionB" required>
                                <label>Option C</label>
                                <input type="text" name="optionC" id="edit-question-optionC" required>
                                <label>Option D</label>
                                <input type="text" name="optionD" id="edit-question-optionD" required>
                                <label>Correct Answer</label>
                                <select name="correctAnswer" id="edit-question-correct" required>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('edit-question-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Lectures Section -->
            <?php if ($activeTab == 'lectures'): ?>
                <div class="admin-table">
                    <div class="table-filter">
                        <select onchange="filterTable('lectures-table', this, 0)">
                            <option value="">All Strands</option>
                            <?php
                            $uniqueStrands = array_unique(array_column($lectures, 'strandName'));
                            sort($uniqueStrands);
                            foreach ($uniqueStrands as $strandName): ?>
                                <option value="<?php echo htmlspecialchars($strandName); ?>"><?php echo htmlspecialchars($strandName); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="filterTable('lectures-table', this, 1)">
                            <option value="">All Titles</option>
                            <?php
                            $uniqueTitles = array_unique(array_column($lectures, 'title'));
                            sort($uniqueTitles);
                            foreach ($uniqueTitles as $title): ?>
                                <option value="<?php echo htmlspecialchars($title); ?>"><?php echo htmlspecialchars($title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn-add" onclick="openModal('add-lecture-modal')">Add Lecture</button>
                    <table id="lectures-table">
                        <thead>
                            <tr>
                                <th>Strand</th>
                                <th>Title</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($lectures)): ?>
                                <?php foreach ($lectures as $lecture): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($lecture['strandName']); ?></td>
                                        <td><?php echo htmlspecialchars($lecture['title']); ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditLectureModal(<?php echo $lecture['lectureID']; ?>, <?php echo $lecture['strandID']; ?>, '<?php echo htmlspecialchars($lecture['title']); ?>', decodeURIComponent('<?php echo rawurlencode($lecture['content']); ?>'), '<?php echo htmlspecialchars($lecture['image'] ?? ''); ?>')">
                                                <i class='bx bxs-edit'></i> Edit
                                            </button>
                                            <a href="manage_game.php?action=archive_lecture&lectureID=<?php echo $lecture['lectureID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this lecture?');">
                                                <i class='bx bxs-archive-in'></i> Archive
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">No lectures found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Lecture Modal -->
                <div id="add-lecture-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Add Lecture</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('add-lecture-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('add-lecture-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_lecture">
                                <label>Strand</label>
                                <select name="strandID" required>
                                    <?php foreach ($strands as $strand): ?>
                                        <option value="<?php echo $strand['strandID']; ?>"><?php echo htmlspecialchars($strand['strandName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Title</label>
                                <input type="text" name="title" required>
                                <label>Content</label>
                                <textarea name="content" required></textarea>
                                <label>Image (Optional)</label>
                                <input type="file" name="image" accept="image/*">
                                <img class="image-preview" id="add-lecture-image-preview" alt="Image Preview">
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('add-lecture-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Lecture Modal -->
                <div id="edit-lecture-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Lecture</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('edit-lecture-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('edit-lecture-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="edit_lecture">
                                <input type="hidden" name="lectureID" id="edit-lecture-id">
                                <label>Strand</label>
                                <select name="strandID" id="edit-lecture-strand" required>
                                    <?php foreach ($strands as $strand): ?>
                                        <option value="<?php echo $strand['strandID']; ?>"><?php echo htmlspecialchars($strand['strandName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Title</label>
                                <input type="text" name="title" id="edit-lecture-title" required>
                                <label>Content</label>
                                <textarea name="content" id="edit-lecture-content" required></textarea>
                                <label>Image (Optional)</label>
                                <input type="file" name="image" id="edit-lecture-image-input" accept="image/*">
                                <img class="image-preview" id="edit-lecture-image-preview" alt="Image Preview">
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('edit-lecture-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Badges Section -->
            <?php if ($activeTab == 'badges'): ?>
                <div class="admin-table">
                    <div class="table-filter">
                        <select onchange="filterTable('badges-table', this, 1)">
                            <option value="">All Badge Names</option>
                            <?php
                            $uniqueBadgeNames = array_unique(array_column($badges, 'badgeName'));
                            sort($uniqueBadgeNames);
                            foreach ($uniqueBadgeNames as $badgeName): ?>
                                <option value="<?php echo htmlspecialchars($badgeName); ?>"><?php echo htmlspecialchars($badgeName); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="filterTable('badges-table', this, 0)">
                            <option value="">All Levels</option>
                            <?php
                            $uniqueLevels = array_unique(array_column($badges, 'level'));
                            sort($uniqueLevels);
                            foreach ($uniqueLevels as $level): ?>
                                <option value="<?php echo htmlspecialchars($level); ?>"><?php echo htmlspecialchars($level); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn-add" onclick="openModal('add-badge-modal')">Add Badge</button>
                    <table id="badges-table">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th>Badge Name</th>
                                <th>Icon</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($badges)): ?>
                                <?php foreach ($badges as $badge): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($badge['level']); ?></td>
                                        <td><?php echo htmlspecialchars($badge['badgeName']); ?></td>
                                        <td><?php echo htmlspecialchars($badge['icon']); ?></td>
                                        <td><?php
                                            $description = htmlspecialchars($badge['description']);
                                            if (strlen($description) > 40) {
                                                $description = substr($description, 0, 40) . "...";
                                            }
                                            echo $description;
                                        ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditBadgeModal(<?php echo $badge['level']; ?>, '<?php echo htmlspecialchars($badge['badgeName']); ?>', '<?php echo htmlspecialchars($badge['icon']); ?>', '<?php echo htmlspecialchars($badge['description']); ?>')">
                                                <i class='bx bxs-edit'></i> Edit
                                            </button>
                                            <a href="manage_game.php?action=archive_badge&level=<?php echo $badge['level']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this badge?');">
                                                <i class='bx bxs-archive-in'></i> Archive
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No badges found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Badge Modal -->
                <div id="add-badge-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Add Badge</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('add-badge-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('add-badge-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post">
                                <input type="hidden" name="action" value="add_badge">
                                <label>Level</label>
                                <input type="number" name="level" min="0" required>
                                <label>Badge Name</label>
                                <input type="text" name="badgeName" required>
                                <label>Icon (e.g., emoji or text)</label>
                                <input type="text" name="icon" required>
                                <label>Description</label>
                                <textarea name="description" required></textarea>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('add-badge-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Badge Modal -->
                <div id="edit-badge-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Badge</h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullScreen('edit-badge-modal')"><i class='bx bx-fullscreen'></i></button>
                                <button class="close-btn" onclick="closeModal('edit-badge-modal')"><i class='bx bx-x'></i></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game.php" method="post">
                                <input type="hidden" name="action" value="edit_badge">
                                <input type="hidden" name="level" id="edit-badge-level">
                                <label>Level</label>
                                <input type="number" id="edit-badge-level-display" disabled>
                                <label>Badge Name</label>
                                <input type="text" name="badgeName" id="edit-badge-name" required>
                                <label>Icon (e.g., emoji or text)</label>
                                <input type="text" name="icon" id="edit-badge-icon" required>
                                <label>Description</label>
                                <textarea name="description" id="edit-badge-description" required></textarea>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('edit-badge-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('modal-fullscreen');
            modal.querySelector('.fullscreen-btn i').className = 'bx bx-fullscreen';
            modal.style.display = 'flex';
            if (modalId === 'add-question-modal') {
                resetQuestionFields();
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('modal-fullscreen');
            modal.querySelector('.fullscreen-btn i').className = 'bx bx-fullscreen';
            modal.style.display = 'none';
            if (modalId === 'add-lecture-modal' || modalId === 'edit-lecture-modal') {
                document.getElementById(modalId === 'add-lecture-modal' ? 'add-lecture-image-preview' : 'edit-lecture-image-preview').style.display = 'none';
            }
        }

        function toggleFullScreen(modalId) {
            const modal = document.getElementById(modalId);
            const fullscreenBtn = modal.querySelector('.fullscreen-btn i');
            modal.classList.toggle('modal-fullscreen');
            if (modal.classList.contains('modal-fullscreen')) {
                fullscreenBtn.className = 'bx bx-exit-fullscreen';
            } else {
                fullscreenBtn.className = 'bx bx-fullscreen';
            }
        }

        function openEditTestTypeModal(id, name, questions, passing, archived) {
            document.getElementById('edit-test-type-id').value = id;
            document.getElementById('edit-test-type-name').value = name;
            document.getElementById('edit-test-type-questions').value = questions;
            document.getElementById('edit-test-type-passing').value = passing;
            document.getElementById('edit-test-type-archived').value = archived;
            openModal('edit-test-type-modal');
        }

        function openEditTestModal(id, title, strandID, testTypeID) {
            document.getElementById('edit-test-id').value = id;
            document.getElementById('edit-test-title').value = title;
            document.getElementById('edit-test-strand').value = strandID;
            document.getElementById('edit-test-type').value = testTypeID;
            openModal('edit-test-modal');
        }

        function openEditQuestionModal(id, testID, text, optionA, optionB, optionC, optionD, correctAnswer) {
            document.getElementById('edit-question-id').value = id;
            document.getElementById('edit-question-test').value = testID;
            document.getElementById('edit-question-text').value = text;
            document.getElementById('edit-question-optionA').value = optionA;
            document.getElementById('edit-question-optionB').value = optionB;
            document.getElementById('edit-question-optionC').value = optionC;
            document.getElementById('edit-question-optionD').value = optionD;
            document.getElementById('edit-question-correct').value = correctAnswer;
            openModal('edit-question-modal');
        }

        function openEditLectureModal(id, strandID, title, content, image) {
            document.getElementById('edit-lecture-id').value = id;
            document.getElementById('edit-lecture-strand').value = strandID;
            document.getElementById('edit-lecture-title').value = title;
            document.getElementById('edit-lecture-content').value = content; // Set content
            const imagePreview = document.getElementById('edit-lecture-image-preview');
            if (image) {
                imagePreview.src = image;
                imagePreview.style.display = 'block';
            } else {
                imagePreview.style.display = 'none';
            }
            openModal('edit-lecture-modal');
        }

        function openEditBadgeModal(level, name, icon, description) {
            document.getElementById('edit-badge-level').value = level;
            document.getElementById('edit-badge-level-display').value = level;
            document.getElementById('edit-badge-name').value = name;
            document.getElementById('edit-badge-icon').value = icon;
            document.getElementById('edit-badge-description').value = description;
            openModal('edit-badge-modal');
        }

        function filterTable(tableId, selectElement, columnIndex) {
            const filterValue = selectElement.value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cell = rows[i].getElementsByTagName('td')[columnIndex];
                if (cell) {
                    const cellText = cell.textContent || cell.innerText;
                    rows[i].style.display = filterValue === '' || cellText.toLowerCase() === filterValue ? '' : 'none';
                }
            }
        }

        function searchQuestions(tableId, inputElement) {
            const searchValue = inputElement.value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                for (let j = 1; j < cells.length - 1; j++) { // Skip first (#) and last (Actions) columns
                    const cellText = cells[j].textContent || cells[j].innerText;
                    if (cellText.toLowerCase().includes(searchValue)) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }

        let questionCount = 1;

        function addQuestionField() {
            const container = document.getElementById('questions-container');
            const newFieldset = document.createElement('fieldset');
            newFieldset.className = 'question-fieldset';
            newFieldset.innerHTML = `
                <legend>Question ${questionCount + 1}</legend>
                <button type="button" class="remove-question-btn" onclick="removeQuestionField(this)"><i class='bx bx-x'></i></button>
                <label>Question Text</label>
                <textarea name="questions[${questionCount}][questionText]" required></textarea>
                <label>Option A</label>
                <input type="text" name="questions[${questionCount}][optionA]" required>
                <label>Option B</label>
                <input type="text" name="questions[${questionCount}][optionB]" required>
                <label>Option C</label>
                <input type="text" name="questions[${questionCount}][optionC]" required>
                <label>Option D</label>
                <input type="text" name="questions[${questionCount}][optionD]" required>
                <label>Correct Answer</label>
                <select name="questions[${questionCount}][correctAnswer]" required>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            `;
            container.appendChild(newFieldset);
            questionCount++;
        }

        function removeQuestionField(button) {
            button.parentElement.remove();
            questionCount--;
            updateQuestionLegends();
        }

        function updateQuestionLegends() {
            const fieldsets = document.getElementById('questions-container').getElementsByTagName('fieldset');
            for (let i = 0; i < fieldsets.length; i++) {
                fieldsets[i].querySelector('legend').textContent = `Question ${i + 1}`;
                const inputs = fieldsets[i].getElementsByTagName('input');
                const textarea = fieldsets[i].getElementsByTagName('textarea')[0];
                const select = fieldsets[i].getElementsByTagName('select')[0];
                textarea.name = `questions[${i}][questionText]`;
                inputs[0].name = `questions[${i}][optionA]`;
                inputs[1].name = `questions[${i}][optionB]`;
                inputs[2].name = `questions[${i}][optionC]`;
                inputs[3].name = `questions[${i}][optionD]`;
                select.name = `questions[${i}][correctAnswer]`;
                fieldsets[i].querySelector('.remove-question-btn').style.display = i === 0 ? 'none' : 'block';
            }
        }

        function resetQuestionFields() {
            const container = document.getElementById('questions-container');
            container.innerHTML = `
                <fieldset class="question-fieldset">
                    <legend>Question 1</legend>
                    <button type="button" class="remove-question-btn" onclick="removeQuestionField(this)" style="display: none;"><i class='bx bx-x'></i></button>
                    <label>Question Text</label>
                    <textarea name="questions[0][questionText]" required></textarea>
                    <label>Option A</label>
                    <input type="text" name="questions[0][optionA]" required>
                    <label>Option B</label>
                    <input type="text" name="questions[0][optionB]" required>
                    <label>Option C</label>
                    <input type="text" name="questions[0][optionC]" required>
                    <label>Option D</label>
                    <input type="text" name="questions[0][optionD]" required>
                    <label>Correct Answer</label>
                    <select name="questions[0][correctAnswer]" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </fieldset>
            `;
            questionCount = 1;
        }

        // Image preview for add/edit lecture modals
        document.addEventListener('DOMContentLoaded', () => {
            const addImageInput = document.querySelector('#add-lecture-modal input[type="file"]');
            const editImageInput = document.querySelector('#edit-lecture-modal input[type="file"]');
            if (addImageInput) {
                addImageInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    const preview = document.getElementById('add-lecture-image-preview');
                    if (file) {
                        preview.src = URL.createObjectURL(file);
                        preview.style.display = 'block';
                    } else {
                        preview.style.display = 'none';
                    }
                });
            }
            if (editImageInput) {
                editImageInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    const preview = document.getElementById('edit-lecture-image-preview');
                    if (file) {
                        preview.src = URL.createObjectURL(file);
                        preview.style.display = 'block';
                    }
                });
            }
        });
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>
