<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT sm.moduleID, sm.moduleTitle, sm.description, sm.fileName, sm.fileSize, sm.uploadDate, ts.strandName
        FROM strand_modules sm
        JOIN track_strands ts ON sm.strandID = ts.strandID
        WHERE sm.archived = 0";

if ($search !== '') {
    $sql .= " AND (sm.moduleTitle LIKE :search OR ts.strandName LIKE :search)";
}

$stmt = $dbConnection->prepare($sql);

if ($search !== '') {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <style>
        @media screen and (max-width: 768px) {
            .admin-table {
                overflow-x: auto; 
                display: block; 
            }

            .admin-table table {
                min-width: 600px;
            }

            .admin-table th,
            .admin-table td {
                padding: 8px; 
                font-size: 0.9em; 
            }

            .admin-table .profile-img {
                width: 40px; 
                height: 40px;
            }

            .admin-table th:nth-child(3),
            .admin-table td:nth-child(3) {
                display: none;
            }
        }

        @media screen and (max-width: 480px) {
            .admin-table th,
            .admin-table td {
                padding: 6px; 
                font-size: 0.8em; 
            }

            .admin-table .profile-img {
                width: 30px; 
                height: 30px;
            }

            .admin-table th:nth-child(4), 
            .admin-table td:nth-child(4),
            .admin-table th:nth-child(5), 
            .admin-table td:nth-child(5) {
                display: none;
            }
        }

        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px;
            flex-wrap: wrap;
            max-width: 100%;
            width: 40%;
        }

        .search-form input[type="text"] {
            padding: 10px;
            font-size: clamp(1.2rem, 3vw, 1.3rem);
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: var(--light);
            outline: none;
            flex: 1;
            min-width: 200px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .search-form input[type="text"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }

        .search-form button, .search-form .clear-btn {
            padding: 10px 15px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .admin-table {
            margin: 20px;
            padding: 10px;
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .admin-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            min-width: 600px;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border: none;
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

        .admin-table tr:nth-child(even) {
			background-color: var(--grey);
            border: none;
		}
        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--grey), gray);
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
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            transition: background-color 0.3s;
        }

        .success-notification,
        .error-notification {
            max-width: 500px;
            margin: 10px auto;
            padding: 15px;
            border-radius: 5px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            text-align: center;
            font-weight: bold;
        }

        .success-notification {
            background-color: #4CAF50;
            color: white;
        }

        .error-notification {
            background-color: #dc3545;
            color: white;
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

        .btn-download {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
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

        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
                margin: 10px;
                width: 100%;
            }

            .search-form input[type="text"],
            .search-form button,
            .search-form .clear-btn {
                width: 100%;
                min-width: unset;
                margin-bottom: 10px;
            }

            .admin-table {
                margin: 10px;
                padding: 5px;
                width: 100%;
            }

            .admin-table table {
                font-size: 0.9rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 8px 10px;
                white-space: nowrap;
            }

            .btn-edit, .btn-archive, .btn-download {
                font-size: 0.8rem;
                padding: 4px 8px;
            }
        }

        @media (max-width: 480px) {
            .admin-table table {
                font-size: 0.8rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 6px 8px;
            }

            .btn-edit, .btn-archive, .btn-download {
                font-size: 0.75rem;
                padding: 3px 6px;
                margin-right: 3px;
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
    <title>Learnify - Module Records</title>
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
            <li class="active">
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
                    <input type="search" name="query" placeholder="Search modules, tracks, strands..." required>
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
                    <h1>Module Records</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="modules.php">Registration</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="data_modules.php">Module Records</a></li>
                    </ul>
                </div>
                <a href="./modules.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Registration
                </a>
            </div>

            <!-- Search Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by module title or strand name" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="data_modules.php" class="clear-btn">Clear</a>
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

            <!-- Table to display Modules -->
            <div class="admin-table">
                <table>
                    <thead>
                        <tr>
                            <th>Strand Name</th>
                            <th>Module Title</th>
                            <th>Description</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($modules)): ?>
                            <?php foreach ($modules as $module): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($module['strandName']); ?></td>
                                    <td><?php echo htmlspecialchars($module['moduleTitle']); ?></td>
                                    <td><?php echo htmlspecialchars($module['description'] ?? 'No description'); ?></td>
                                    <td><?php echo date('F j, Y \a\t h:i A', strtotime($module['uploadDate'])); ?></td>
                                    <td>
                                        <a href="./modules/<?php echo htmlspecialchars($module['fileName']); ?>" class="btn-download" download>
                                            <i class='bx bxs-download'></i> Download
                                        </a>
                                        <a href="view_file.php?file=<?php echo urlencode($module['fileName']); ?>" class="btn-view">
                                            <i class='bx bxs-file-doc'></i> View File
                                        </a>
                                        <a href="edit_modules.php?id=<?php echo $module['moduleID']; ?>" class="btn-edit">
                                            <i class='bx bxs-edit'></i> Edit
                                        </a>
                                        <a href="archive_modules.php?moduleID=<?php echo $module['moduleID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this module?');">
                                            <i class='bx bxs-archive-in'></i> Archive
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