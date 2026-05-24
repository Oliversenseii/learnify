<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

$userID = $_SESSION['userID'];

try {
    // Fetch user data
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

    // User counts
    $userCounts = [
        'admins' => 'Admin',
        'professors' => 'Professor',
        'students' => 'Student',
        'superadmins' => 'SuperAdmin',
    ];

    $totalUserCount = 0;

    $counts = [];
    foreach ($userCounts as $key => $userType) {
        $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE userType = :userType AND archived = 0");
        $stmt->bindParam(':userType', $userType, PDO::PARAM_STR);
        $stmt->execute();
        $counts[$key] = (int)$stmt->fetchColumn();
        
        $totalUserCount += $counts[$key];
    }

    // Counts for tracks, sections, and subjects
    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM track_strands WHERE archived = 0");
    $stmt->execute();
    $strandCount = (int)$stmt->fetchColumn();

    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM sections WHERE archived = 0");
    $stmt->execute();
    $sectionCount = (int)$stmt->fetchColumn();

    // Module count removed as per request
    // $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM modules WHERE archived = 0");
    // $stmt->execute();
    // $moduleCount = (int)$stmt->fetchColumn();

    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM subjects WHERE archived = 0");
    $stmt->execute();
    $subjectCount = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo "<div class='error-notification'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

$hashedLink = 'lp{d1]\awdlll,>>121_!333';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/box.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <script src="./logout.js" defer></script>
    <title>Learnify</title>
    <style>
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }

        #data-title  {
            font-size: clamp(2rem, 3vw, 2.5rem) !important;
        }
        .data-cri {
            font-size: clamp(1.1rem, 3vw, 1.2rem) !important;
        }
        
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./superAdminDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./registration.php">
                    <i class="bx bx-user-plus"></i>
                    <span class="text">Registration</span>
                </a>
            </li>
            <li class="active">
                <a href="./backup_data.php">
                    <i class='bx bxs-data'></i>
                    <span class="text">Backup Data</span>
                </a>
            </li>
            <li>
                <a href="./branding.php">
                    <i class='bx bxs-image'></i>
                    <span class="text">Branding</span>
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
            <div class="table-data">
                <div class="todo">
                    <div class="head">
                        <h3 id="data-title">Backup Data</h3>
                    </div>
                    <ul class="todo-list">
                        <li class="completed">
                            <p class="data-cri">Clicking the box below will automatically download the full database of your data.</p>
                            <i class='bx bx-dots-vertical-rounded'></i>
                        </li>
                        <!-- <li class="not-completed">
                            <p>Once you’ve selected the data type, click the download button below to save the backup file.</p>
                            <i class='bx bx-dots-vertical-rounded'></i>
                        </li> -->
                    </ul>
                </div>
            </div>

            <!-- Total Counts in Dashboard -->
            <ul class="box-info">
                <!-- <a href="backup_users.php?hash=<?php echo $hashedLink; ?>">
                    <li>
                        <i class='bx bxs-user'></i>
                        <span class="text">
                            <h3><?php echo $totalUserCount; ?></h3>
                            <p>Users</p>
                        </span>
                    </li>
                </a>
                <a href="backup_track_strands.php?hash=<?php echo $hashedLink; ?>">
                    <li>
                        <i class='bx bxs-book'></i>
                        <span class="text">
                            <h3><?php echo $strandCount; ?></h3>
                            <p>Track & Strands</p>
                        </span>
                    </li>
                </a>
                <a href="backup_sections.php?hash=<?php echo $hashedLink; ?>">
                    <li>
                        <i class='bx bx-book-content'></i>
                        <span class="text">
                            <h3><?php echo $sectionCount; ?></h3>
                            <p>Sections</p>
                        </span>
                    </li>
                </a>
                <a href="backup_subjects.php?hash=<?php echo $hashedLink; ?>">
                    <li>
                        <i class='bx bxs-bookmark'></i>
                        <span class="text">
                            <h3><?php echo $subjectCount; ?></h3>
                            <p>Subject</p>
                        </span>
                    </li>
                </a> -->
                <a href="backup_full_database.php?hash=<?php echo $hashedLink; ?>">
                    <li>
                        <i class='bx bxs-data'></i>
                        <span class="text">
                            <h3 id="data-title">All Data</h3>
                            <p class="data-cri">Full Database</p>
                        </span>
                    </li>
                </a>
            </ul>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script src="script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>