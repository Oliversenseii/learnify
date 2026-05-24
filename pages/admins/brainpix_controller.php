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
        $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'maps';
        $itemsPerPage = 10;

        // Maps pagination
        $mapPage = isset($_GET['map_page']) ? max(1, filter_var($_GET['map_page'], FILTER_VALIDATE_INT)) : 1;
        $mapOffset = ($mapPage - 1) * $itemsPerPage;

        // Fetch total map count for pagination
        $mapCountStmt = $dbConnection->prepare("
            SELECT COUNT(*) as total
            FROM brainpix_maps
            WHERE mapName LIKE :search OR description LIKE :search
        ");
        $mapCountStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $mapCountStmt->execute();
        $mapTotal = $mapCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $mapTotalPages = ceil($mapTotal / $itemsPerPage);

        // Fetch data for Maps with pagination
        $mapStmt = $dbConnection->prepare("
            SELECT mapID, mapName, description, orderNum
            FROM brainpix_maps
            WHERE mapName LIKE :search OR description LIKE :search
            ORDER BY orderNum ASC
            LIMIT :limit OFFSET :offset
        ");
        $mapStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $mapStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $mapStmt->bindValue(':offset', $mapOffset, PDO::PARAM_INT);
        $mapStmt->execute();
        $maps = $mapStmt->fetchAll(PDO::FETCH_ASSOC);

        // Levels pagination
        $levelPage = isset($_GET['level_page']) ? max(1, filter_var($_GET['level_page'], FILTER_VALIDATE_INT)) : 1;
        $levelOffset = ($levelPage - 1) * $itemsPerPage;

        // Fetch total level count for pagination
        $levelCountStmt = $dbConnection->prepare("
            SELECT COUNT(*) as total
            FROM brainpix_levels l
            JOIN brainpix_maps m ON l.mapID = m.mapID
            WHERE m.mapName LIKE :search OR l.correctAnswer LIKE :search OR l.hint LIKE :search
        ");
        $levelCountStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $levelCountStmt->execute();
        $levelTotal = $levelCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $levelTotalPages = ceil($levelTotal / $itemsPerPage);

        // Fetch data for Levels with pagination
        $levelStmt = $dbConnection->prepare("
            SELECT l.levelID, l.mapID, m.mapName, l.levelNum, l.imageURL, l.correctAnswer, l.hint
            FROM brainpix_levels l
            JOIN brainpix_maps m ON l.mapID = m.mapID
            WHERE m.mapName LIKE :search OR l.correctAnswer LIKE :search OR l.hint LIKE :search
            ORDER BY l.mapID, l.levelNum ASC
            LIMIT :limit OFFSET :offset
        ");
        $levelStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $levelStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $levelStmt->bindValue(':offset', $levelOffset, PDO::PARAM_INT);
        $levelStmt->execute();
        $levels = $levelStmt->fetchAll(PDO::FETCH_ASSOC);

        // Badges pagination
        $badgePage = isset($_GET['badge_page']) ? max(1, filter_var($_GET['badge_page'], FILTER_VALIDATE_INT)) : 1;
        $badgeOffset = ($badgePage - 1) * $itemsPerPage;

        // Fetch total badge count for pagination
        $badgeCountStmt = $dbConnection->prepare("
            SELECT COUNT(*) as total
            FROM brainpix_badges b
            JOIN brainpix_maps m ON b.mapID = m.mapID
            WHERE b.badgeName LIKE :search OR b.description LIKE :search OR m.mapName LIKE :search
        ");
        $badgeCountStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $badgeCountStmt->execute();
        $badgeTotal = $badgeCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $badgeTotalPages = ceil($badgeTotal / $itemsPerPage);

        // Fetch data for Badges with pagination
        $badgeStmt = $dbConnection->prepare("
            SELECT b.badgeID, b.badgeName, b.description, b.imageURL, b.mapID, m.mapName
            FROM brainpix_badges b
            JOIN brainpix_maps m ON b.mapID = m.mapID
            WHERE b.badgeName LIKE :search OR b.description LIKE :search OR m.mapName LIKE :search
            ORDER BY b.badgeID ASC
            LIMIT :limit OFFSET :offset
        ");
        $badgeStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $badgeStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $badgeStmt->bindValue(':offset', $badgeOffset, PDO::PARAM_INT);
        $badgeStmt->execute();
        $badges = $badgeStmt->fetchAll(PDO::FETCH_ASSOC);

        // User Badges pagination
        $userBadgePage = isset($_GET['user_badge_page']) ? max(1, filter_var($_GET['user_badge_page'], FILTER_VALIDATE_INT)) : 1;
        $userBadgeOffset = ($userBadgePage - 1) * $itemsPerPage;

        // Fetch total user badge count for pagination
        $userBadgeCountStmt = $dbConnection->prepare("
            SELECT COUNT(*) as total
            FROM brainpix_user_badges ub
            JOIN users u ON ub.userID = u.userID
            JOIN brainpix_badges b ON ub.badgeID = b.badgeID
            WHERE CONCAT(u.lastName, ', ', u.firstName) LIKE :search OR b.badgeName LIKE :search
        ");
        $userBadgeCountStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $userBadgeCountStmt->execute();
        $userBadgeTotal = $userBadgeCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $userBadgeTotalPages = ceil($userBadgeTotal / $itemsPerPage);

        // Fetch data for User Badges with pagination
        $userBadgeStmt = $dbConnection->prepare("
            SELECT ub.userBadgeID, ub.userID, u.firstName, u.lastName, u.image, ub.badgeID, b.badgeName, ub.awardedDate
            FROM brainpix_user_badges ub
            JOIN users u ON ub.userID = u.userID
            JOIN brainpix_badges b ON ub.badgeID = b.badgeID
            WHERE CONCAT(u.lastName, ', ', u.firstName) LIKE :search OR b.badgeName LIKE :search
            ORDER BY ub.awardedDate DESC
            LIMIT :limit OFFSET :offset
        ");
        $userBadgeStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $userBadgeStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $userBadgeStmt->bindValue(':offset', $userBadgeOffset, PDO::PARAM_INT);
        $userBadgeStmt->execute();
        $userBadges = $userBadgeStmt->fetchAll(PDO::FETCH_ASSOC);

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
        <title>Learnify - BrainPix Controller</title>
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
                width: 100vw;
                height: 100vh;
                padding: 24px;
                border-radius: 0;
                box-shadow: none;
                overflow-y: auto;
                position: relative;
            }

            #modal-content {
                max-width: 600px;
                width: 100%;
                height: 90vh;
                overflow-y: auto;
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
            }

            .image-preview {
                max-width: 200px;
                max-height: 200px;
                object-fit: contain;
                border: 1px solid var(--border-color);
                border-radius: 4px;
                padding: 4px;
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
                    <a href="./brainpix_controller.php">
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
                        <p><?php echo htmlspecialchars($_SESSION['lastName'] . ', ' . $_SESSION['firstName']); ?></p>
                        <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                    </div>
                </a>
            </nav>

            <main>
                <div class="head-title">
                    <div class="left">
                        <h1>Thinking-It-Out Controller</h1>
                        <ul class="breadcrumb">
                            <li><a href="./superAdminDash.php">Home</a></li>
                            <li><i class='bx bx-chevron-right'></i></li>
                            <li><a href="./game.php">Games</a></li>
                            <li><i class='bx bx-chevron-right'></i></li>
                            <li><a class="active" href="./brainpix_controller.php">Thinking-It-Out Controller</a></li>
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
                <!-- <?php if (isset($_SESSION['error_message'])): ?>
                    <div id="error-notification" class="modal notification-modal error">
                        <div class="modal-content">
                            <p><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
                            <div class="modal-footer">
                                <button class="close-btn" onclick="closeModal('error-notification')">OK</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?> -->

                <div class="tabs">
                    <a href="?tab=maps<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'maps' ? 'active' : ''; ?>">Maps</a>
                    <a href="?tab=levels<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'levels' ? 'active' : ''; ?>">Levels</a>
                    <a href="?tab=badges<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'badges' ? 'active' : ''; ?>">Badges</a>
                    <a href="?tab=user_badges<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $activeTab == 'user_badges' ? 'active' : ''; ?>">User Badges</a>
                </div>

                <!-- Maps Section -->
                <?php if ($activeTab == 'maps'): ?>
                    <div class="admin-table">
                        <button class="btn-add" onclick="openModal('add-map-modal')">Add Map</button>
                        <div class="table-filter">
                            <select onchange="filterTable('maps-table', this, 0)">
                                <option value="">All Maps</option>
                                <?php foreach ($maps as $map): ?>
                                    <option value="<?php echo htmlspecialchars($map['mapName']); ?>"><?php echo htmlspecialchars($map['mapName']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <table id="maps-table">
                            <thead>
                                <tr>
                                    <th>Map Name</th>
                                    <th>Description</th>
                                    <th>Order Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($maps)): ?>
                                    <?php foreach ($maps as $map): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($map['mapName']); ?></td>
                                            <td class="description-cell"><?php
                                                $description = htmlspecialchars(trim($map['description'] ?? ''));
                                                if (strlen($description) > 40) {
                                                    $description = substr($description, 0, 40) . "...";
                                                }
                                                echo $description;
                                            ?></td>
                                            <td><?php echo htmlspecialchars($map['orderNum']); ?></td>
                                            <td>
                                                <button class="btn-edit" onclick="openEditMapModal(<?php echo $map['mapID']; ?>, '<?php echo htmlspecialchars($map['mapName']); ?>', '<?php echo htmlspecialchars(trim($map['description'] ?? '')); ?>', <?php echo $map['orderNum']; ?>)">
                                                    <i class='bx bxs-edit'></i> Edit
                                                </button>
                                                <a href="manage_brainpix.php?action=delete_map&mapID=<?php echo $map['mapID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to delete this map?');">
                                                    <i class='bx bxs-trash'></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4">No maps found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <!-- Map Pagination -->
                        <div class="pagination">
                            <?php if ($mapTotalPages > 1): ?>
                                <a href="?tab=maps&map_page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $mapPage == 1 ? 'disabled' : ''; ?>">First</a>
                                <a href="?tab=maps&map_page=<?php echo $mapPage - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $mapPage == 1 ? 'disabled' : ''; ?>">Previous</a>
                                <?php
                                $startPage = max(1, $mapPage - 2);
                                $endPage = min($mapTotalPages, $startPage + 4);
                                if ($endPage - $startPage < 4) {
                                    $startPage = max(1, $endPage - 4);
                                }
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?tab=maps&map_page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $mapPage == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <a href="?tab=maps&map_page=<?php echo $mapPage + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $mapPage == $mapTotalPages ? 'disabled' : ''; ?>">Next</a>
                                <a href="?tab=maps&map_page=<?php echo $mapTotalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $mapPage == $mapTotalPages ? 'disabled' : ''; ?>">Last</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Add Map Modal -->
                    <div id="add-map-modal" class="modal">
                        <div class="modal-content" id="modal-content">
                            <div class="modal-header">
                                <h3>Add Map</h3>
                                <button class="close-btn" onclick="closeModal('add-map-modal')"><i class='bx bx-x'></i></button>
                            </div>
                            <div class="modal-body">
                                <form action="manage_brainpix.php" method="post">
                                    <input type="hidden" name="action" value="add_map">
                                    <label>Map Name</label>
                                    <input type="text" name="mapName" required>
                                    <label>Description (Optional)</label>
                                    <textarea name="description"></textarea>
                                    <label>Order Number</label>
                                    <input type="number" name="orderNum" min="0" value="0" required>
                                    <div class="modal-footer">
                                        <button type="submit" class="save-btn">Save</button>
                                        <button type="button" class="close-btn" onclick="closeModal('add-map-modal')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Map Modal -->
                    <div id="edit-map-modal" class="modal">
                        <div class="modal-content" id="modal-content">
                            <div class="modal-header">
                                <h3>Edit Map</h3>
                                <button class="close-btn" onclick="closeModal('edit-map-modal')"><i class='bx bx-x'></i></button>
                            </div>
                            <div class="modal-body">
                                <form action="manage_brainpix.php" method="post">
                                    <input type="hidden" name="action" value="edit_map">
                                    <input type="hidden" name="mapID" id="edit-map-id">
                                    <label>Map Name</label>
                                    <input type="text" name="mapName" id="edit-map-name" required>
                                    <label>Description (Optional)</label>
                                    <textarea name="description" id="edit-map-description"></textarea>
                                    <label>Order Number</label>
                                    <input type="number" name="orderNum" id="edit-map-order" min="0" required>
                                    <div class="modal-footer">
                                        <button type="submit" class="save-btn">Save</button>
                                        <button type="button" class="close-btn" onclick="closeModal('edit-map-modal')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Levels Section -->
                <?php if ($activeTab == 'levels'): ?>
                    <div class="admin-table">
                        <button class="btn-add" onclick="openModal('add-level-modal')">Add Level</button>
                        <div class="table-filter">
                            <select onchange="filterTable('levels-table', this, 0)">
                                <option value="">All Maps</option>
                                <?php
                                $uniqueMaps = array_unique(array_column($levels, 'mapName'));
                                sort($uniqueMaps);
                                foreach ($uniqueMaps as $mapName): ?>
                                    <option value="<?php echo htmlspecialchars($mapName); ?>"><?php echo htmlspecialchars($mapName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    
                        <table id="levels-table">
                            <thead>
                                <tr>
                                    <th>Map</th>
                                    <th>Level Number</th>
                                    <th>Image</th>
                                    <th>Correct Answer</th>
                                    <th>Hint</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($levels)): ?>
                                    <?php foreach ($levels as $level): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($level['mapName']); ?></td>
                                            <td><?php echo htmlspecialchars($level['levelNum']); ?></td>
                                            <td>
                                                <img src="<?php echo htmlspecialchars($level['imageURL']); ?>" alt="Level Image" class="table-image" onclick="openImageViewModal('<?php echo htmlspecialchars($level['imageURL']); ?>')">
                                            </td>
                                            <td><?php echo htmlspecialchars($level['correctAnswer']); ?></td>
                                            <td class="description-cell"><?php
                                                $hint = htmlspecialchars(trim($level['hint'] ?? ''));
                                                if (strlen($hint) > 40) {
                                                    $hint = substr($hint, 0, 40) . "...";
                                                }
                                                echo $hint;
                                            ?></td>
                                            <td>
                                                <button class="btn-edit" onclick="openEditLevelModal(<?php echo $level['levelID']; ?>, <?php echo $level['mapID']; ?>, '<?php echo htmlspecialchars($level['mapName']); ?>', <?php echo $level['levelNum']; ?>, '<?php echo htmlspecialchars($level['imageURL']); ?>', '<?php echo htmlspecialchars($level['correctAnswer']); ?>', '<?php echo htmlspecialchars(trim($level['hint'] ?? '')); ?>')">
                                                    <i class='bx bxs-edit'></i> Edit
                                                </button>
                                                <a href="manage_brainpix.php?action=delete_level&levelID=<?php echo $level['levelID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to delete this level?');">
                                                    <i class='bx bxs-trash'></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">No levels found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <!-- Level Pagination -->
                        <div class="pagination">
                            <?php if ($levelTotalPages > 1): ?>
                                <a href="?tab=levels&level_page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $levelPage == 1 ? 'disabled' : ''; ?>">First</a>
                                <a href="?tab=levels&level_page=<?php echo $levelPage - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $levelPage == 1 ? 'disabled' : ''; ?>">Previous</a>
                                <?php
                                $startPage = max(1, $levelPage - 2);
                                $endPage = min($levelTotalPages, $startPage + 4);
                                if ($endPage - $startPage < 4) {
                                    $startPage = max(1, $endPage - 4);
                                }
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?tab=levels&level_page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $levelPage == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <a href="?tab=levels&level_page=<?php echo $levelPage + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $levelPage == $levelTotalPages ? 'disabled' : ''; ?>">Next</a>
                                <a href="?tab=levels&level_page=<?php echo $levelTotalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $levelPage == $levelTotalPages ? 'disabled' : ''; ?>">Last</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Add Level Modal -->
                    <div id="add-level-modal" class="modal">
                        <div class="modal-content" id="modal-content">
                            <div class="modal-header">
                                <h3>Add Level</h3>
                                <button class="close-btn" onclick="closeModal('add-level-modal')"><i class='bx bx-x'></i></button>
                            </div>
                            <div class="modal-body">
                                <form action="manage_brainpix.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="add_level">
                                    <label>Map</label>
                                    <select name="mapID" required>
                                        <option value="">Select Map</option>
                                        <?php foreach ($maps as $map): ?>
                                            <option value="<?php echo $map['mapID']; ?>"><?php echo htmlspecialchars($map['mapName']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Level Number (1-30)</label>
                                    <input type="number" name="levelNum" min="1" max="30" required>
                                    <label>Image File</label>
                                    <input type="file" name="imageURL" accept="image/*" required>
                                    <img class="image-preview" id="add-level-image-preview" alt="Image Preview">
                                    <label>Correct Answer</label>
                                    <input type="text" name="correctAnswer" required>
                                    <label>Hint (Optional)</label>
                                    <textarea name="hint"></textarea>
                                    <div class="modal-footer">
                                        <button type="submit" class="save-btn">Save</button>
                                        <button type="button" class="close-btn" onclick="closeModal('add-level-modal')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Level Modal -->
                    <div id="edit-level-modal" class="modal">
                        <div class="modal-content-g2" id="modal-content">
                            <div class="modal-header">
                                <h3>Edit Level</h3>
                                <button class="close-btn" onclick="closeModal('edit-level-modal')"><i class='bx bx-x'></i></button>
                            </div>
                            <div class="modal-body">
                                <form action="manage_brainpix.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="edit_level">
                                    <input type="hidden" name="levelID" id="edit-level-id">
                                    <label>Map</label>
                                    <input type="text" id="edit-level-mapName" readonly>
                                    <input type="hidden" name="mapID" id="edit-level-mapID">
                                    <label>Level Number (1-30)</label>
                                    <input type="number" name="levelNum" id="edit-level-num" min="1" max="30" readonly>
                                    <label>Image File</label>
                                    <input type="file" name="imageURL" accept="image/*">
                                    <img class="image-preview" id="edit-level-image-preview" alt="Image Preview">
                                    <label>Correct Answer</label>
                                    <input type="text" name="correctAnswer" id="edit-level-correct" required>
                                    <label>Hint (Optional)</label>
                                    <textarea name="hint" id="edit-level-hint"></textarea>
                                    <div class="modal-footer">
                                        <button type="submit" class="save-btn" onclick="disableEditButton(this)">Save</button>
                                        <button type="button" class="close-btn" onclick="closeModal('edit-level-modal')">Cancel</button>
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

                <!-- Badges Section -->
                <?php if ($activeTab == 'badges'): ?>
                    <div class="admin-table">
                        <button class="btn-add" onclick="openModal('add-badge-modal')">Add Badge</button>
                        <div class="table-filter">
                            <select onchange="filterTable('badges-table', this, 0)">
                                <option value="">All Maps</option>
                                <?php
                                $uniqueMaps = array_unique(array_column($badges, 'mapName'));
                                sort($uniqueMaps);
                                foreach ($uniqueMaps as $mapName): ?>
                                    <option value="<?php echo htmlspecialchars($mapName); ?>"><?php echo htmlspecialchars($mapName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <table id="badges-table">
                            <thead>
                                <tr>
                                    <th>Map</th>
                                    <th>Badge Name</th>
                                    <th>Description</th>
                                    <th>Image</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($badges)): ?>
                                    <?php foreach ($badges as $badge): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($badge['mapName']); ?></td>
                                            <td><?php echo htmlspecialchars($badge['badgeName']); ?></td>
                                            <td class="description-cell"><?php
                                                $description = htmlspecialchars(trim($badge['description'] ?? ''));
                                                if (strlen($description) > 40) {
                                                    $description = substr($description, 0, 40) . "...";
                                                }
                                                echo $description;
                                            ?></td>
                                            <td>
                                                <img src="<?php echo htmlspecialchars($badge['imageURL']); ?>" alt="Badge Image" class="table-image" onclick="openImageViewModal('<?php echo htmlspecialchars($badge['imageURL']); ?>')">
                                            </td>
                                            <td>
                                                <button class="btn-edit" onclick="openEditBadgeModal(<?php echo $badge['badgeID']; ?>, <?php echo $badge['mapID']; ?>, '<?php echo htmlspecialchars($badge['mapName']); ?>', '<?php echo htmlspecialchars($badge['badgeName']); ?>', '<?php echo htmlspecialchars(trim($badge['description'] ?? '')); ?>', '<?php echo htmlspecialchars($badge['imageURL']); ?>')">
                                                    <i class='bx bxs-edit'></i> Edit
                                                </button>
                                                <a href="manage_brainpix.php?action=delete_badge&badgeID=<?php echo $badge['badgeID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to delete this badge?');">
                                                    <i class='bx bxs-trash'></i> Delete
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
                        <!-- Badge Pagination -->
                        <div class="pagination">
                            <?php if ($badgeTotalPages > 1): ?>
                                <a href="?tab=badges&badge_page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $badgePage == 1 ? 'disabled' : ''; ?>">First</a>
                                <a href="?tab=badges&badge_page=<?php echo $badgePage - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $badgePage == 1 ? 'disabled' : ''; ?>">Previous</a>
                                <?php
                                $startPage = max(1, $badgePage - 2);
                                $endPage = min($badgeTotalPages, $startPage + 4);
                                if ($endPage - $startPage < 4) {
                                    $startPage = max(1, $endPage - 4);
                                }
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?tab=badges&badge_page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $badgePage == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <a href="?tab=badges&badge_page=<?php echo $badgePage + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $badgePage == $badgeTotalPages ? 'disabled' : ''; ?>">Next</a>
                                <a href="?tab=badges&badge_page=<?php echo $badgeTotalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $badgePage == $badgeTotalPages ? 'disabled' : ''; ?>">Last</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Add Badge Modal -->
                    <div id="add-badge-modal" class="modal">
                        <div class="modal-content" id="modal-content">
                            <div class="modal-header">
                                <h3>Add Badge</h3>
                                <button class="close-btn" onclick="closeModal('add-badge-modal')"><i class='bx bx-x'></i></button>
                            </div>
                            <div class="modal-body">
                                <form action="manage_brainpix.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="add_badge">
                                    <label>Map</label>
                                    <select name="mapID" required>
                                        <option value="">Select Map</option>
                                        <?php foreach ($maps as $map): ?>
                                            <option value="<?php echo $map['mapID']; ?>"><?php echo htmlspecialchars($map['mapName']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Badge Name</label>
                                    <input type="text" name="badgeName" required>
                                    <label>Description (Optional)</label>
                                    <textarea name="description"></textarea>
                                    <label>Image File</label>
                                    <input type="file" name="imageURL" accept="image/*" required>
                                    <img class="image-preview" id="add-badge-image-preview" alt="Image Preview">
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
                        <div class="modal-content-g2" id="modal-content">
                            <div class="modal-header">
                                <h3>Edit Badge</h3>
                                <button class="close-btn" onclick="closeModal('edit-badge-modal')"><i class='bx bx-x'></i></button>
                            </div>
                            <div class="modal-body">
                                <form action="manage_brainpix.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="edit_badge">
                                    <input type="hidden" name="badgeID" id="edit-badge-id">
                                    <label>Map</label>
                                    <input type="text" id="edit-badge-mapName" readonly>
                                    <input type="hidden" name="mapID" id="edit-badge-mapID">
                                    <label>Badge Name</label>
                                    <input type="text" name="badgeName" id="edit-badge-name" required>
                                    <label>Description (Optional)</label>
                                    <textarea name="description" id="edit-badge-description"></textarea>
                                    <label>Image File</label>
                                    <input type="file" name="imageURL" accept="image/*">
                                    <img class="image-preview" id="edit-badge-image-preview" alt="Image Preview">
                                    <div class="modal-footer">
                                        <button type="submit" class="save-btn" onclick="disableEditButton(this)">Save</button>
                                        <button type="button" class="close-btn" onclick="closeModal('edit-badge-modal')">Cancel</button>
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

                <!-- User Badges Section -->
                <?php if ($activeTab == 'user_badges'): ?>
                    <div class="admin-table">
                        <!-- <button class="btn-add" onclick="openModal('add-user-badge-modal')">Add User Badge</button> -->
                        <div class="table-filter">
                            <input type="text" id="user-badges-search" placeholder="Search by user..." oninput="searchTable('user-badges-table', this)">
                            <select onchange="filterTable('user-badges-table', this, 2)">
                                <option value="">All Badges</option>
                                <?php
                                $uniqueBadges = array_unique(array_column($userBadges, 'badgeName'));
                                sort($uniqueBadges);
                                foreach ($uniqueBadges as $badgeName): ?>
                                    <option value="<?php echo htmlspecialchars($badgeName); ?>"><?php echo htmlspecialchars($badgeName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <table id="user-badges-table">
                            <thead>
                                <tr>
                                    <th>Profile</th>
                                    <th>User</th>
                                    <th>Badge</th>
                                    <th>Awarded Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($userBadges)): ?>
                                    <?php foreach ($userBadges as $userBadge): ?>
                                        <tr>
                                            <td><img src="<?php echo htmlspecialchars($userBadge['image'] ?? './img/noprofile.png'); ?>" alt="Profile" class="profile-img"></td>
                                            <td><?php echo htmlspecialchars($userBadge['lastName'] . ', ' . $userBadge['firstName']); ?></td>
                                            <td><?php echo htmlspecialchars($userBadge['badgeName']); ?></td>
                                            <td><?php
                                                if ($userBadge['awardedDate']) {
                                                    $date = new DateTime($userBadge['awardedDate']);
                                                    echo $date->format('F j, Y g:i a');
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?></td>
                                            <td>
                                                <button class="btn-edit" onclick="openEditUserBadgeModal(<?php echo $userBadge['userBadgeID']; ?>, <?php echo $userBadge['userID']; ?>, '<?php echo htmlspecialchars($userBadge['lastName'] . ', ' . $userBadge['firstName']); ?>', <?php echo $userBadge['badgeID']; ?>, '<?php echo htmlspecialchars($userBadge['badgeName']); ?>', '<?php echo $userBadge['awardedDate'] ? htmlspecialchars($userBadge['awardedDate']) : ''; ?>')">
                                                    <i class='bx bxs-edit'></i> Edit
                                                </button>
                                                <a href="manage_brainpix.php?action=delete_user_badge&userBadgeID=<?php echo $userBadge['userBadgeID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to delete this user badge?');">
                                                    <i class='bx bxs-trash'></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No user badges found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <!-- User Badge Pagination -->
                        <div class="pagination">
                            <?php if ($userBadgeTotalPages > 1): ?>
                                <a href="?tab=user_badges&user_badge_page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $userBadgePage == 1 ? 'disabled' : ''; ?>">First</a>
                                <a href="?tab=user_badges&user_badge_page=<?php echo $userBadgePage - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $userBadgePage == 1 ? 'disabled' : ''; ?>">Previous</a>
                                <?php
                                $startPage = max(1, $userBadgePage - 2);
                                $endPage = min($userBadgeTotalPages, $startPage + 4);
                                if ($endPage - $startPage < 4) {
                                    $startPage = max(1, $endPage - 4);
                                }
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?tab=user_badges&user_badge_page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $userBadgePage == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <a href="?tab=user_badges&user_badge_page=<?php echo $userBadgePage + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $userBadgePage == $userBadgeTotalPages ? 'disabled' : ''; ?>">Next</a>
                                <a href="?tab=user_badges&user_badge_page=<?php echo $userBadgeTotalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="<?php echo $userBadgePage == $userBadgeTotalPages ? 'disabled' : ''; ?>">Last</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Add User Badge Modal -->
                    <div id="add-user-badge-modal" class="modal">
                        <div class="modal-content" id="modal-content">
                            <div class="modal-header">
                                <h3>Add User Badge</h3>
                                <button class="close-btn" onclick="closeModal('add-user-badge-modal')"><i class='bx bx-x'></i></button>
                            </div>
                            <div class="modal-body">
                                <form action="manage_brainpix.php" method="post">
                                    <input type="hidden" name="action" value="add_user_badge">
                                    <label>User</label>
                                    <select name="userID" required>
                                        <option value="">Select User</option>
                                        <?php
                                        $userStmt = $dbConnection->prepare("SELECT userID, firstName, lastName FROM users ORDER BY lastName, firstName");
                                        $userStmt->execute();
                                        $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($users as $user): ?>
                                            <option value="<?php echo $user['userID']; ?>"><?php echo htmlspecialchars($user['lastName'] . ', ' . $user['firstName']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Badge</label>
                                    <select name="badgeID" required>
                                        <option value="">Select Badge</option>
                                        <?php
                                        $badgeStmt = $dbConnection->prepare("SELECT badgeID, badgeName FROM brainpix_badges ORDER BY badgeName");
                                        $badgeStmt->execute();
                                        $allBadges = $badgeStmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($allBadges as $badge): ?>
                                            <option value="<?php echo $badge['badgeID']; ?>"><?php echo htmlspecialchars($badge['badgeName']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Awarded Date</label>
                                    <input type="datetime-local" name="awardedDate" required>
                                    <div class="modal-footer">
                                        <button type="submit" class="save-btn">Save</button>
                                        <button type="button" class="close-btn" onclick="closeModal('add-user-badge-modal')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit User Badge Modal -->
                    <div id="edit-user-badge-modal" class="modal">
                        <div class="modal-content" id="modal-content">
                            <div class="modal-header">
                                <h3>Edit User Badge</h3>
                                <button class="close-btn" onclick="closeModal('edit-user-badge-modal')"><i class='bx bx-x'></i></button>
                            </div>
                            <div class="modal-body">
                                <form action="manage_brainpix.php" method="post">
                                    <input type="hidden" name="action" value="edit_user_badge">
                                    <input type="hidden" name="userBadgeID" id="edit-user-badge-id">
                                    <label>User</label>
                                    <input type="text" id="edit-user-badge-user" readonly>
                                    <input type="hidden" name="userID" id="edit-user-badge-userID">
                                    <label>Badge</label>
                                    <input type="text" id="edit-user-badge-badge" readonly>
                                    <input type="hidden" name="badgeID" id="edit-user-badge-badgeID">
                                    <label>Awarded Date</label>
                                    <input type="datetime-local" name="awardedDate" id="edit-user-badge-date" required>
                                    <div class="modal-footer">
                                        <button type="submit" class="save-btn" onclick="disableEditButton(this)">Save</button>
                                        <button type="button" class="close-btn" onclick="closeModal('edit-user-badge-modal')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </section>

        <script>
            let editedItems = new Set();

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
                if (modalId.includes('level') || modalId.includes('badge')) {
                    document.querySelectorAll(`#${modalId} .image-preview`).forEach(preview => {
                        preview.style.display = 'none';
                        preview.src = '';
                    });
                }
            }

            function disableEditButton(button) {
                const form = button.closest('form');
                const id = form.querySelector('input[name="mapID"], input[name="levelID"], input[name="badgeID"], input[name="userBadgeID"]')?.value;
                if (id) {
                    editedItems.add(id);
                }
            }

            function openEditMapModal(id, name, description, orderNum) {
                if (editedItems.has(id.toString())) {
                    alert('This map has already been edited. Please refresh the page to edit again.');
                    return;
                }
                document.getElementById('edit-map-id').value = id;
                document.getElementById('edit-map-name').value = name;
                document.getElementById('edit-map-description').value = description;
                document.getElementById('edit-map-order').value = orderNum;
                openModal('edit-map-modal');
            }

            function openEditLevelModal(id, mapID, mapName, levelNum, imageURL, correctAnswer, hint) {
                if (editedItems.has(id.toString())) {
                    alert('This level has already been edited. Please refresh the page to edit again.');
                    return;
                }
                document.getElementById('edit-level-id').value = id;
                document.getElementById('edit-level-mapID').value = mapID;
                document.getElementById('edit-level-mapName').value = mapName;
                document.getElementById('edit-level-num').value = levelNum;
                document.getElementById('edit-level-correct').value = correctAnswer;
                document.getElementById('edit-level-hint').value = hint;
                const imagePreview = document.getElementById('edit-level-image-preview');
                imagePreview.src = imageURL;
                imagePreview.style.display = imageURL ? 'block' : 'none';
                openModal('edit-level-modal');
            }

            function openEditBadgeModal(id, mapID, mapName, badgeName, description, imageURL) {
                if (editedItems.has(id.toString())) {
                    alert('This badge has already been edited. Please refresh the page to edit again.');
                    return;
                }
                document.getElementById('edit-badge-id').value = id;
                document.getElementById('edit-badge-mapID').value = mapID;
                document.getElementById('edit-badge-mapName').value = mapName;
                document.getElementById('edit-badge-name').value = badgeName;
                document.getElementById('edit-badge-description').value = description;
                const imagePreview = document.getElementById('edit-badge-image-preview');
                imagePreview.src = imageURL;
                imagePreview.style.display = imageURL ? 'block' : 'none';
                openModal('edit-badge-modal');
            }

            function openEditUserBadgeModal(id, userID, userName, badgeID, badgeName, awardedDate) {
                if (editedItems.has(id.toString())) {
                    alert('This user badge has already been edited. Please refresh the page to edit again.');
                    return;
                }
                document.getElementById('edit-user-badge-id').value = id;
                document.getElementById('edit-user-badge-userID').value = userID;
                document.getElementById('edit-user-badge-user').value = userName;
                document.getElementById('edit-user-badge-badgeID').value = badgeID;
                document.getElementById('edit-user-badge-badge').value = badgeName;
                document.getElementById('edit-user-badge-date').value = awardedDate ? new Date(awardedDate).toISOString().slice(0, 16) : '';
                openModal('edit-user-badge-modal');
            }

            function openImageViewModal(imageSrc) {
                const modal = document.getElementById('image-view-modal');
                const image = document.getElementById('view-image');
                image.src = imageSrc;
                openModal('image-view-modal');
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
                const addLevelInput = document.querySelector('#add-level-modal input[name="imageURL"]');
                const editLevelInput = document.querySelector('#edit-level-modal input[name="imageURL"]');
                const addBadgeInput = document.querySelector('#add-badge-modal input[name="imageURL"]');
                const editBadgeInput = document.querySelector('#edit-badge-modal input[name="imageURL"]');
                
                if (addLevelInput) {
                    addLevelInput.addEventListener('change', (e) => {
                        const file = e.target.files[0];
                        const preview = document.getElementById('add-level-image-preview');
                        preview.src = file ? URL.createObjectURL(file) : '';
                        preview.style.display = file ? 'block' : 'none';
                    });
                }
                if (editLevelInput) {
                    editLevelInput.addEventListener('change', (e) => {
                        const file = e.target.files[0];
                        const preview = document.getElementById('edit-level-image-preview');
                        preview.src = file ? URL.createObjectURL(file) : '';
                        preview.style.display = file ? 'block' : 'none';
                    });
                }
                if (addBadgeInput) {
                    addBadgeInput.addEventListener('change', (e) => {
                        const file = e.target.files[0];
                        const preview = document.getElementById('add-badge-image-preview');
                        preview.src = file ? URL.createObjectURL(file) : '';
                        preview.style.display = file ? 'block' : 'none';
                    });
                }
                if (editBadgeInput) {
                    editBadgeInput.addEventListener('change', (e) => {
                        const file = e.target.files[0];
                        const preview = document.getElementById('edit-badge-image-preview');
                        preview.src = file ? URL.createObjectURL(file) : '';
                        preview.style.display = file ? 'block' : 'none';
                    });
                }

                // Auto-show notification modals
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