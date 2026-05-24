<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';

// Initialize filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$strandID = isset($_GET['strandID']) ? (int)$_GET['strandID'] : 0;

// Base SQL query for modules
$sql = "SELECT sm.moduleID, sm.moduleTitle, sm.description, sm.fileName, sm.fileSize, sm.uploadDate, ts.strandName
        FROM strand_modules sm
        JOIN track_strands ts ON sm.strandID = ts.strandID
        WHERE sm.archived = 0";

// Add filters to the query
$params = [];
if ($strandID > 0) {
    $sql .= " AND ts.strandID = :strandID";
    $params[':strandID'] = $strandID;
}
if ($search !== '') {
    $sql .= " AND (sm.moduleTitle LIKE :search OR ts.strandName LIKE :search)";
    $params[':search'] = "%$search%";
}

// Prepare and execute the query
$stmt = $dbConnection->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch strands for the strand dropdown
$strandStmt = $dbConnection->prepare("SELECT strandID, strandName FROM track_strands WHERE archived = 0 ORDER BY strandName");
$strandStmt->execute();
$strands = $strandStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="./utils/notification.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <style>
        :root {
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
        }
        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }
        .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px;
            flex-wrap: wrap;
            max-width: 100%;
            width: 60%;
        }

        .search-form input[type="text"],
        .search-form select {
            padding: 10px;
            font-size: clamp(1rem, 3vw, 1.3rem);
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: var(--light);
            color: var(--dark);
            outline: none;
            flex: 1;
            min-width: 200px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .search-form select {
            min-width: 150px;
        }

        .search-form input[type="text"]:focus,
        .search-form select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }

        .search-form button, .search-form .clear-btn {
            padding: 10px 15px;
            font-size: clamp(1rem, 3vw, 1.3rem);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .search-form button[type="submit"] {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .search-form button[type="submit"]:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .search-form .clear-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .search-form .clear-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .admin-table {
            margin: 20px;
            padding: 10px;
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .admin-table h2 {
            color: var(--dark);
            font-size: clamp(1.2rem, 3vw, 2rem) !important;
            margin-bottom: 10px;
        }

        .admin-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1rem, 3vw, 1.3rem);
            min-width: 600px;
        }

        .admin-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .admin-table th,
        .admin-table td {
            padding: 12px 15px;
            text-align: left;
        }

        .admin-table th {
            background: #0056b3;
            color: #fff;
            font-weight: bold;
            white-space: nowrap;
        }

        .admin-table td {
            color: var(--dark);
        }

        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .btn-edit, .btn-archive, .btn-download, .btn-view {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 10px;
            font-size: clamp(1rem, 3vw, 1.3rem);
            transition: background-color 0.3s;
        }

        .btn-edit {
            background-color: #FFCE26;
            color: #342E37;
        }

        .btn-archive {
            background-color: #a90c0c;
            color: #ffffff;
        }

        .btn-download {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #ffffff;
        }

        .btn-edit:hover {
            background-color: #e6b800;
        }

        .btn-archive:hover {
            background-color: #820a0a;
        }

        .btn-download:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .btn-view {
            background: linear-gradient(135deg, #6f42c1, #5a32a8);
            color: white;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #5a32a8, #4a288f);
        }

        .success-notification, .error-notification {
            padding: 15px;
            margin: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .success-notification {
            background: #10b981;
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        @media screen and (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
                margin: 10px;
                width: 100%;
            }

            .search-form input[type="text"],
            .search-form select,
            .search-form button,
            .search-form .clear-btn {
                width: 90%;
                min-width: unset;
                margin-bottom: 10px;
                padding: 8px 13px;
            }
        }

        @media screen and (max-width: 460px) {
            .admin-table {
                margin: 1rem 0rem;
                padding: 1rem;
            }

            .admin-table h2 {
                padding: 15px;
            }
            .btn-edit, .btn-archive, .btn-download, .btn-view {
                padding: 3px 8px;
            }
        }
    </style>
    <title>Learnify - Module Records</title>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./professor_main_dash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li class="active">
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li>
                <a href="./message_admin.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Admin</span>
                </a>
            </li>
            <li>
                <a href="./game_controller.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Game</span>
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
                    <input type="search" name="query" placeholder="Search users..." required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
                <img src="<?php echo isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <p><?php echo $_SESSION['firstName']; ?></p>
                    <small>Teacher</small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Module Records</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="modules.php" class="active">Module Records</a></li>
                    </ul>
                </div>
            </div>

            <!-- Search and Filter Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by module title or strand name" value="<?php echo htmlspecialchars($search); ?>">
                <select name="strandID" onchange="this.form.submit()">
                    <option value="0">All Strands</option>
                    <?php foreach ($strands as $strand): ?>
                        <option value="<?php echo $strand['strandID']; ?>" <?php echo $strandID == $strand['strandID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($strand['strandName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Search</button>
                <?php if ($strandID > 0 || !empty($search)): ?>
                    <a href="modules.php" class="clear-btn">Clear</a>
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

            <div class="admin-table">
                <h2>Modules uploaded by the Administrator</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Strand Name</th>
                            <th>Module Title</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($modules)): ?>
                            <?php foreach ($modules as $module): ?>
                                <tr class="module-row">
                                    <td><?php echo htmlspecialchars($module['strandName']); ?></td>
                                    <td><?php echo htmlspecialchars($module['moduleTitle']); ?></td>
                                    <td><?php echo htmlspecialchars($module['description'] ?? 'No description'); ?></td>
                                    <td class="action-buttons">
                                        <a href="../../pages/admins/modules/<?php echo htmlspecialchars($module['fileName']); ?>" class="btn-download" download>
                                            <i class='bx bxs-download'></i> Download
                                        </a>
                                        <a href="view_file.php?file=<?php echo urlencode($module['fileName']); ?>" class="btn-view">
                                            <i class='bx bxs-file-doc'></i> View File
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No modules found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>