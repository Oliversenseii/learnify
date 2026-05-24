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
    $stmt = $dbConnection->prepare("SELECT firstName, lastName, userType, image FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['lastName'] = htmlspecialchars($user['lastName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
    } else {
        header("Location: ../../index.php");
        exit;
    }

    // Handle search and pagination
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'images';
    $itemsPerPage = 10;
    
    // Images pagination
    $imagePage = isset($_GET['image_page']) ? max(1, filter_var($_GET['image_page'], FILTER_VALIDATE_INT)) : 1;
    $imageOffset = ($imagePage - 1) * $itemsPerPage;

    // Fetch total image count for pagination
    $imageCountStmt = $dbConnection->prepare("
        SELECT COUNT(*) as total
        FROM speech_to_text_game_images
        WHERE TRIM(description) LIKE :search OR correctAnswer LIKE :search
    ");
    $imageCountStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $imageCountStmt->execute();
    $imageTotal = $imageCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $imageTotalPages = ceil($imageTotal / $itemsPerPage);

    // Fetch data for Images with pagination
    $imageStmt = $dbConnection->prepare("
        SELECT imageID, imageFile1, imageFile2, imageFile3, correctAnswer, TRIM(description) as description, level, archived
        FROM speech_to_text_game_images
        WHERE TRIM(description) LIKE :search OR correctAnswer LIKE :search
        LIMIT :limit OFFSET :offset
    ");
    $imageStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $imageStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $imageStmt->bindValue(':offset', $imageOffset, PDO::PARAM_INT);
    $imageStmt->execute();
    $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

    // User Progress pagination
    $progressPage = isset($_GET['progress_page']) ? max(1, filter_var($_GET['progress_page'], FILTER_VALIDATE_INT)) : 1;
    $progressOffset = ($progressPage - 1) * $itemsPerPage;

    // Fetch total progress count for pagination
    $progressCountStmt = $dbConnection->prepare("
        SELECT COUNT(*) as total
        FROM speech_to_text_user_progress_image p
        JOIN users u ON p.userID = u.userID
        WHERE CONCAT(u.lastName, ', ', u.firstName) LIKE :search
    ");
    $progressCountStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $progressCountStmt->execute();
    $progressTotal = $progressCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $progressTotalPages = ceil($progressTotal / $itemsPerPage);

    // Fetch data for User Progress with pagination
    $progressStmt = $dbConnection->prepare("
        SELECT p.progressID, p.userID, u.firstName, u.lastName, u.image, p.currentLevel, p.totalAttempts, p.totalCorrect, p.totalPoints, p.lastAttemptDate
        FROM speech_to_text_user_progress_image p
        JOIN users u ON p.userID = u.userID
        WHERE CONCAT(u.lastName, ', ', u.firstName) LIKE :search
        LIMIT :limit OFFSET :offset
    ");
    $progressStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $progressStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $progressStmt->bindValue(':offset', $progressOffset, PDO::PARAM_INT);
    $progressStmt->execute();
    $progresses = $progressStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Learnify - Pixora Controller</title>
    <style>
        :root {
            --primary-color: #1a73e8;
            --primary-hover: #1557b0;
            --light-grey: #f1f3f4;
            --border-color: #dadce0;
            --text-color: #202124;
            --hover-bg: #f8f9fa;
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
        }

        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 1.5rem;
            background: var(--light);
            padding: 8px;
            margin-top: 5px;
            border-radius: 8px;
        }

        .tab {
            padding: 12px 24px;
            background: transparent;
            color: var(--dark);
            border-radius: 8px;
            border: 1px solid #ccc;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .tab.active {
            background: var(--primary-color);
            color: white;
        }

        .tab:hover {
            transform: translateY(-2px);
        }

        .btn-edit, .btn-add {
            padding: 8px 16px;
            border: none;
            width: 100px;
            border-radius: 4px;
            font-size: clamp(1rem, 3vw, 1.1rem);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            z-index: 1;
            position: relative;
        }

        .btn-archive {
            padding: 6px 16px;
            border: none;
            width: 100px;
            border-radius: 4px;
            font-size: clamp(1rem, 3vw, 1.1rem);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            z-index: 1;
            position: relative;
        }

        .btn-edit {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: black;
        }

        .btn-archive {
            background: #d93025;
            color: white;
        }

        .btn-add {
            background: var(--primary-color);
            color: white;
        }

        .btn-edit:hover {
             background: linear-gradient(135deg, #e0a800, #c69500);
        }

        .btn-archive:hover {
            background: #b71c1c;
        }

        .btn-add:hover {
            background: var(--primary-hover);
        }

        .modal-content-g2 {
            background: var(--light);
            max-width: 90vw; /* Constrain width to 90% of viewport */
            max-height: 90vh; /* Constrain height to 90% of viewport */
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            overflow-y: auto; /* Enable scrolling for overflow */
            position: relative;
            box-sizing: border-box; /* Ensure padding is included in dimensions */
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 16px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: var(--dark);
            font-weight: 500;
        }

        .modal-header .close-btn {
            background: transparent;
            border: none;
            font-size: clamp(1.5rem, 3vw, 2rem);
            cursor: pointer;
            color: var(--dark);
        }

        .modal-header .close-btn:hover {
            color: #d93025;
        }

        .modal-body {
            padding: 16px 0;
        }

        .modal-body label {
            display: block;
            margin-bottom: 8px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 500;
            color: var(--dark);
        }

        .modal-body input,
        .modal-body select,
        .modal-body textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            color: var(--dark);
            background: var(--grey);
        }

        .modal-body input[readonly] {
            background: var(--grey);
            cursor: not-allowed;
            color: var(--dark);
        }

        .modal-body input[type="file"] {
            padding: 8px;
        }

        .modal-body textarea {
            resize: vertical;
            min-height: 100px;
        }

        .image-container {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            justify-content: center; 
        }

        .image-preview {
            max-width: 150px; 
            max-height: 150px;
            object-fit: contain;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 4px;
            display: block; 
        }

        .table-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 4px;
            transition: transform 0.2s ease;
        }

        .table-image:hover {
            transform: scale(1.1);
        }

        .modal-footer {
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .modal-footer button {
            padding: 10px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .modal-footer .save-btn {
            background: var(--primary-color);
            color: white;
        }

        .modal-footer .save-btn:hover {
            background: var(--primary-hover);
        }

        .modal-footer .close-btn {
            background: var(--light-grey);
            color: var(--text-color);
        }

        .modal-footer .close-btn:hover {
            background: #e8eaed;
        }

        .notification-modal .modal-content {
            width: 400px;
            height: auto;
            padding: 24px;
            border-radius: 8px;
            text-align: center;
        }

        .notification-modal.success .modal-content {
            background: var(--light);
        }

        .notification-modal.error .modal-content {
            background: #fce8e6;
        }

        .notification-modal p {
            margin: 0 0 16px;
            font-family: clamp(1.5rem, 3vw, 2rem);
            color: var(--dark);
        }

        .image-view-modal .modal-content {
            width: 80%;
            max-width: 800px;
            height: auto;
            padding: 24px;
            border-radius: 8px;
            text-align: center;
        }

        .image-view-modal img {
            max-width: 100%;
            max-height: 60vh;
            object-fit: contain;
            border-radius: 4px;
        }

        .table-filter {
            margin-bottom: 16px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .table-filter select,
        .table-filter input {
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: clamp(1rem, 2vw, 1.2rem);
            background: var(--grey);
            color: var(--dark);
        }

        .admin-table {
            background: var(--light);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        th, td {
            padding: 12px 16px;
            text-align: left;
            font-size: clamp(1rem, 2vw, 1.2rem);
            color: var(--dark);
        }

        .profile-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            padding: 8px;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.2rem);
            transition: all 0.2s ease;
        }

        .pagination a:hover {
            border: 1px solid #1557b0;
            color: #1557b0;
        }

        .pagination a.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination a.disabled {
            color: #80868b;
            cursor: not-allowed;
        }

        .description-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 768px) {
            .modal-content {
                padding: 16px;
            }

            .image-container {
                flex-direction: column;
            }

            .image-preview {
                max-width: 100%;
            }

            .table-filter {
                flex-direction: column;
            }

            .admin-table {
                overflow-x: auto;
            }

            .image-view-modal .modal-content {
                width: 90%;
            }
        }

        @media (max-width: 480px) {
            .tabs {
                flex-direction: column;
                gap: 4px;
            }

            .tab {
                padding: 8px;
                font-size: 12px;
            }

            .btn-edit, .btn-archive, .btn-add {
                padding: 6px 12px;
                font-size: 12px;
                margin-top: 3px;
            }

            .notification-modal .modal-content {
                width: 90%;
            }

            .table-image {
                width: 32px;
                height: 32px;
            }

            .pagination a {
                padding: 6px 10px;
                font-size: 12px;
            }
        }

        .admin-table .btn-add {
            background: linear-gradient(135deg, #28a745, #218838) !important;
            float: right;
            padding: 1rem;
            border-radius: 10px;
            font-size: clamp(1rem, 3vw, 1.1rem);
            color: white;
            margin-bottom: 10px;
        }
        .admin-table .btn-add:hover {
            background: linear-gradient(135deg, #218838, #1e7e34) !important;
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
                    <h1>Pixora Controller</h1>
                    <ul class="breadcrumb">
                        <li><a href="./superAdminDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./game.php">Games</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./game_controller_2.php">Pixora Controller</a></li>
                    </ul>
                </div>
                <a href="./game.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Games
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div id="success-notification" class="modal notification-modal success">
                    <div class="modal-content">
                        <p><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
                        <div class="modal-footer">
                            <button class="close-btn" onclick="closeModal('success-notification')">OK</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div id="error-notification" class="modal notification-modal error">
                    <div class="modal-content">
                        <p><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
                        <div class="modal-footer">
                            <button class="close-btn" onclick="closeModal('error-notification')">OK</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <a href="?tab=images<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'images' ? 'active' : ''; ?>">Images</a>
                <a href="?tab=progress<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'progress' ? 'active' : ''; ?>">User Progress</a>
            </div>

            <!-- Images Section -->
            <?php if ($activeTab == 'images'): ?>
                <div class="admin-table">
                    <button class="btn-add" onclick="openModal('add-image-modal')">Add Image</button>
                    <div class="table-filter">
                        <select onchange="filterTable('images-table', this, 0)">
                            <option value="">All Levels</option>
                            <?php
                            $uniqueLevels = array_unique(array_column($images, 'level'));
                            sort($uniqueLevels);
                            foreach ($uniqueLevels as $level): ?>
                                <option value="<?php echo htmlspecialchars($level); ?>"><?php echo htmlspecialchars($level); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="filterTable('images-table', this, 4)">
                            <option value="">All Status</option>
                            <option value="0">Active</option>
                            <option value="1">Archived</option>
                        </select>
                    </div>
                    <table id="images-table">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th>Images</th>
                                <th>Correct Answer</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($images)): ?>
                                <?php foreach ($images as $image): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($image['level']); ?></td>
                                        <td>
                                            <div class="image-container">
                                                <img src="<?php echo htmlspecialchars($image['imageFile1']); ?>" alt="Image 1" class="table-image" onclick="openImageViewModal('<?php echo htmlspecialchars($image['imageFile1']); ?>')">
                                                <img src="<?php echo htmlspecialchars($image['imageFile2']); ?>" alt="Image 2" class="table-image" onclick="openImageViewModal('<?php echo htmlspecialchars($image['imageFile2']); ?>')">
                                                <?php echo $image['imageFile3'] ? '<img src="' . htmlspecialchars($image['imageFile3']) . '" alt="Image 3" class="table-image" onclick="openImageViewModal(\'' . htmlspecialchars($image['imageFile3']) . '\')">' : ''; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($image['correctAnswer']); ?></td>
                                        <td class="description-cell"><?php
                                            $description = htmlspecialchars(trim($image['description'] ?? ''));
                                            if (strlen($description) > 40) {
                                                $description = substr($description, 0, 40) . "...";
                                            }
                                            echo $description;
                                        ?></td>
                                        <td><?php echo $image['archived'] ? 'Archived' : 'Active'; ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditImageModal(<?php echo $image['imageID']; ?>, <?php echo $image['level']; ?>, '<?php echo htmlspecialchars($image['correctAnswer']); ?>', '<?php echo htmlspecialchars(trim($image['description'] ?? '')); ?>', '<?php echo htmlspecialchars($image['imageFile1']); ?>', '<?php echo htmlspecialchars($image['imageFile2']); ?>', '<?php echo htmlspecialchars($image['imageFile3'] ?? ''); ?>', <?php echo $image['archived']; ?>)">
                                                <i class='bx bxs-edit'></i> Edit
                                            </button>
                                            <a href="manage_game_2.php?action=archive_image&imageID=<?php echo $image['imageID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this image?');">
                                                <i class='bx bxs-archive-in'></i> Archive
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No images found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <!-- Image Pagination -->
                    <div class="pagination">
                        <?php if ($imageTotalPages > 1): ?>
                            <a href="?tab=images&image_page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $imagePage == 1 ? 'disabled' : ''; ?>">First</a>
                            <a href="?tab=images&image_page=<?php echo $imagePage - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $imagePage == 1 ? 'disabled' : ''; ?>">Previous</a>
                            <?php
                            $startPage = max(1, $imagePage - 2);
                            $endPage = min($imageTotalPages, $startPage + 4);
                            if ($endPage - $startPage < 4) {
                                $startPage = max(1, $endPage - 4);
                            }
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?tab=images&image_page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $imagePage == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <a href="?tab=images&image_page=<?php echo $imagePage + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $imagePage == $imageTotalPages ? 'disabled' : ''; ?>">Next</a>
                            <a href="?tab=images&image_page=<?php echo $imageTotalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $imagePage == $imageTotalPages ? 'disabled' : ''; ?>">Last</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Add Image Modal -->
                <div id="add-image-modal" class="modal">
                    <div class="modal-content-g2">
                        <div class="modal-header">
                            <h3>Add Image</h3>
                            <button class="close-btn" onclick="closeModal('add-image-modal')"><i class='bx bx-x'></i></button>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game_2.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_image">
                                <label>Level (1-50)</label>
                                <input type="number" name="level" min="1" max="50" required>
                                <div class="image-container">
                                    <div>
                                        <label>Image File 1</label>
                                        <input type="file" name="imageFile1" accept="image/*" required>
                                        <img class="image-preview" id="add-image1-preview" alt="Image 1 Preview">
                                    </div>
                                    <div>
                                        <label>Image File 2</label>
                                        <input type="file" name="imageFile2" accept="image/*" required>
                                        <img class="image-preview" id="add-image2-preview" alt="Image 2 Preview">
                                    </div>
                                    <div>
                                        <label>Image File 3 (Optional)</label>
                                        <input type="file" name="imageFile3" accept="image/*">
                                        <img class="image-preview" id="add-image3-preview" alt="Image 3 Preview">
                                    </div>
                                </div>
                                <label>Correct Answer</label>
                                <input type="text" name="correctAnswer" required>
                                <label>Description (Optional)</label>
                                <textarea name="description"></textarea>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('add-image-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Image Modal -->
                <div id="edit-image-modal" class="modal">
                    <div class="modal-content-g2">
                        <div class="modal-header">
                            <h3>Edit Image</h3>
                            <button class="close-btn" onclick="closeModal('edit-image-modal')"><i class='bx bx-x'></i></button>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game_2.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="edit_image">
                                <input type="hidden" name="imageID" id="edit-image-id">
                                <label>Level (1-50)</label>
                                <input type="number" name="level" id="edit-image-level" min="1" max="50" readonly>
                                <div class="image-container">
                                    <div>
                                        <label>Image File 1</label>
                                        <input type="file" name="imageFile1" accept="image/*">
                                        <img class="image-preview" id="edit-image1-preview" alt="Image 1 Preview">
                                    </div>
                                    <div>
                                        <label>Image File 2</label>
                                        <input type="file" name="imageFile2" accept="image/*">
                                        <img class="image-preview" id="edit-image2-preview" alt="Image 2 Preview">
                                    </div>
                                    <div>
                                        <label>Image File 3 (Optional)</label>
                                        <input type="file" name="imageFile3" accept="image/*">
                                        <img class="image-preview" id="edit-image3-preview" alt="Image 3 Preview">
                                    </div>
                                </div>
                                <label>Correct Answer</label>
                                <input type="text" name="correctAnswer" id="edit-image-correct" required>
                                <label>Description (Optional)</label>
                                <textarea name="description" id="edit-image-description"></textarea>
                                <label>Status</label>
                                <select name="archived" id="edit-image-archived" required>
                                    <option value="0">Active</option>
                                    <option value="1">Archived</option>
                                </select>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn" onclick="disableEditButton(this)">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('edit-image-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Image View Modal -->
                <div id="image-view-modal" class="modal image-view-modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Image Preview</h3>
                            <button class="close-btn" onclick="closeModal('image-view-modal')"><i class='bx bx-x'></i></button>
                        </div>
                        <div class="modal-body">
                            <img id="view-image" src="" alt="Image Preview">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- User Progress Section -->
            <?php if ($activeTab == 'progress'): ?>
                <div class="admin-table">
                    <div class="table-filter">
                        <input type="text" id="progress-search" placeholder="Search by user..." oninput="searchTable('progress-table', this)">
                        <select onchange="filterTable('progress-table', this, 2)">
                            <option value="">All Levels</option>
                            <?php
                            $uniqueLevels = array_unique(array_column($progresses, 'currentLevel'));
                            sort($uniqueLevels);
                            foreach ($uniqueLevels as $level): ?>
                                <option value="<?php echo htmlspecialchars($level); ?>"><?php echo htmlspecialchars($level); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <table id="progress-table">
                        <thead>
                            <tr>
                                <th>Profile</th>
                                <th>User</th>
                                <th>Current Level</th>
                                <th>Total Attempts</th>
                                <th>Total Correct</th>
                                <th>Total Points</th>
                                <th>Last Attempt</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($progresses)): ?>
                                <?php foreach ($progresses as $progress): ?>
                                    <tr>
                                        <td><img src="<?php echo htmlspecialchars($progress['image'] ?? './img/noprofile.png'); ?>" alt="Profile" class="profile-img"></td>
                                        <td><?php echo htmlspecialchars($progress['lastName'] . ', ' . $progress['firstName']); ?></td>
                                        <td><?php echo htmlspecialchars($progress['currentLevel']); ?></td>
                                        <td><?php echo htmlspecialchars($progress['totalAttempts']); ?></td>
                                        <td><?php echo htmlspecialchars($progress['totalCorrect']); ?></td>
                                        <td><?php echo htmlspecialchars($progress['totalPoints']); ?></td>
                                        <td><?php
                                            if ($progress['lastAttemptDate']) {
                                                $date = new DateTime($progress['lastAttemptDate']);
                                                echo $date->format('F j, Y g:i a');
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditProgressModal(<?php echo $progress['progressID']; ?>, <?php echo $progress['userID']; ?>, '<?php echo htmlspecialchars($progress['lastName'] . ', ' . $progress['firstName']); ?>', <?php echo $progress['currentLevel']; ?>, <?php echo $progress['totalAttempts']; ?>, <?php echo $progress['totalCorrect']; ?>, <?php echo $progress['totalPoints']; ?>)">
                                                <i class='bx bxs-edit'></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">No progress records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <!-- Progress Pagination -->
                    <div class="pagination">
                        <?php if ($progressTotalPages > 1): ?>
                            <a href="?tab=progress&progress_page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $progressPage == 1 ? 'disabled' : ''; ?>">First</a>
                            <a href="?tab=progress&progress_page=<?php echo $progressPage - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $progressPage == 1 ? 'disabled' : ''; ?>">Previous</a>
                            <?php
                            $startPage = max(1, $progressPage - 2);
                            $endPage = min($progressTotalPages, $startPage + 4);
                            if ($endPage - $startPage < 4) {
                                $startPage = max(1, $endPage - 4);
                            }
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?tab=progress&progress_page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $progressPage == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <a href="?tab=progress&progress_page=<?php echo $progressPage + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $progressPage == $progressTotalPages ? 'disabled' : ''; ?>">Next</a>
                            <a href="?tab=progress&progress_page=<?php echo $progressTotalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $progressPage == $progressTotalPages ? 'disabled' : ''; ?>">Last</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Edit Progress Modal -->
                <div id="edit-progress-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit User Progress</h3>
                            <button class="close-btn" onclick="closeModal('edit-progress-modal')"><i class='bx bx-x'></i></button>
                        </div>
                        <div class="modal-body">
                            <form action="manage_game_2.php" method="post">
                                <input type="hidden" name="action" value="edit_progress">
                                <input type="hidden" name="progressID" id="edit-progress-id">
                                <label>User</label>
                                <input type="text" id="edit-progress-user" disabled>
                                <input type="hidden" name="userID" id="edit-progress-userID">
                                <label>Current Level (1-50)</label>
                                <input type="number" name="currentLevel" id="edit-progress-level" min="1" max="50" readonly>
                                <label>Total Attempts</label>
                                <input type="number" name="totalAttempts" id="edit-progress-attempts" min="0" required>
                                <label>Total Correct</label>
                                <input type="number" name="totalCorrect" id="edit-progress-correct" min="0" required>
                                <label>Total Points</label>
                                <input type="number" name="totalPoints" id="edit-progress-points" min="0" required>
                                <div class="modal-footer">
                                    <button type="submit" class="save-btn" onclick="disableEditButton(this)">Save</button>
                                    <button type="button" class="close-btn" onclick="closeModal('edit-progress-modal')">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <script>
        let editedImages = new Set();

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
            if (modalId.includes('notification')) {
                setTimeout(() => closeModal(modalId), 3000);
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
            if (modalId.includes('image') && !modalId.includes('view')) {
                document.querySelectorAll(`#${modalId} .image-preview`).forEach(preview => {
                    preview.style.display = 'none';
                    preview.src = '';
                });
            }
        }

        function disableEditButton(button) {
            const form = button.closest('form');
            const imageID = form.querySelector('input[name="imageID"]')?.value;
            const progressID = form.querySelector('input[name="progressID"]')?.value;
            if (imageID) {
                editedImages.add(imageID);
            }
        }

        function openEditImageModal(id, level, correctAnswer, description, imageFile1, imageFile2, imageFile3, archived) {
            if (editedImages.has(id.toString())) {
                alert('This image has already been edited. Please refresh the page to edit again.');
                return;
            }
            document.getElementById('edit-image-id').value = id;
            document.getElementById('edit-image-level').value = level;
            document.getElementById('edit-image-correct').value = correctAnswer;
            document.getElementById('edit-image-description').value = description;
            document.getElementById('edit-image-archived').value = archived;
            const image1Preview = document.getElementById('edit-image1-preview');
            const image2Preview = document.getElementById('edit-image2-preview');
            const image3Preview = document.getElementById('edit-image3-preview');
            image1Preview.src = imageFile1;
            image1Preview.style.display = imageFile1 ? 'block' : 'none';
            image2Preview.src = imageFile2;
            image2Preview.style.display = imageFile2 ? 'block' : 'none';
            image3Preview.src = imageFile3;
            image3Preview.style.display = imageFile3 ? 'block' : 'none';
            openModal('edit-image-modal');
        }

        function openImageViewModal(imageSrc) {
            const modal = document.getElementById('image-view-modal');
            const image = document.getElementById('view-image');
            image.src = imageSrc;
            openModal('image-view-modal');
        }

        function openEditProgressModal(id, userID, userName, currentLevel, totalAttempts, totalCorrect, totalPoints) {
            document.getElementById('edit-progress-id').value = id;
            document.getElementById('edit-progress-userID').value = userID;
            document.getElementById('edit-progress-user').value = userName;
            document.getElementById('edit-progress-level').value = currentLevel;
            document.getElementById('edit-progress-attempts').value = totalAttempts;
            document.getElementById('edit-progress-correct').value = totalCorrect;
            document.getElementById('edit-progress-points').value = totalPoints;
            openModal('edit-progress-modal');
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

        function searchTable(tableId, inputElement) {
            const searchValue = inputElement.value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                for (let j = 0; j < cells.length - 1; j++) {
                    const cellText = cells[j].textContent || cells[j].innerText;
                    if (cellText.toLowerCase().includes(searchValue)) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            ['imageFile1', 'imageFile2', 'imageFile3'].forEach((field, index) => {
                const addInput = document.querySelector(`#add-image-modal input[name="${field}"]`);
                const editInput = document.querySelector(`#edit-image-modal input[name="${field}"]`);
                if (addInput) {
                    addInput.addEventListener('change', (e) => {
                        const file = e.target.files[0];
                        const preview = document.getElementById(`add-image${index + 1}-preview`);
                        if (file) {
                            preview.src = URL.createObjectURL(file);
                            preview.style.display = 'block';
                            preview.style.maxWidth = '150px'; 
                            preview.style.maxHeight = '150px';
                        } else {
                            preview.src = '';
                            preview.style.display = 'none';
                        }
                    });
                }
                if (editInput) {
                    editInput.addEventListener('change', (e) => {
                        const file = e.target.files[0];
                        const preview = document.getElementById(`edit-image${index + 1}-preview`);
                        if (file) {
                            preview.src = URL.createObjectURL(file);
                            preview.style.display = 'block';
                            preview.style.maxWidth = '150px';
                            preview.style.maxHeight = '150px';
                        } else {
                            preview.src = '';
                            preview.style.display = 'none';
                        }
                    });
                }
            });

            if (document.getElementById('success-notification')) {
                openModal('success-notification');
            }
            if (document.getElementById('error-notification')) {
                openModal('error-notification');
            }
        });
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>