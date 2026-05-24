<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

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
        session_destroy();
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

    $userDisplayNames = [
        'Admin' => 'Administrators',
        'Professor' => 'Teachers',
        'Student' => 'Students',
        'SuperAdmin' => 'SuperAdmins',
    ];

    $counts = [];
    $sexCounts = [
        'admins' => ['male' => 0, 'female' => 0],
        'professors' => ['male' => 0, 'female' => 0],
        'students' => ['male' => 0, 'female' => 0],
        'superadmins' => ['male' => 0, 'female' => 0],
    ];

    foreach ($userCounts as $key => $userType) {
        // Total count
        $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE userType = :userType AND archived = 0");
        $stmt->bindParam(':userType', $userType, PDO::PARAM_STR);
        $stmt->execute();
        $counts[$key] = (int)$stmt->fetchColumn();

        $stmt = $dbConnection->prepare("SELECT sex, COUNT(*) as count FROM users WHERE userType = :userType AND archived = 0 GROUP BY sex");
        $stmt->bindParam(':userType', $userType, PDO::PARAM_STR);
        $stmt->execute();
        $sexData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sexData as $row) {
            if (strtolower($row['sex']) === 'male') {
                $sexCounts[$key]['male'] = (int)$row['count'];
            } elseif (strtolower($row['sex']) === 'female') {
                $sexCounts[$key]['female'] = (int)$row['count'];
            }
        }
    }

    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM track_strands WHERE archived = 0");
    $stmt->execute();
    $strandCount = (int)$stmt->fetchColumn();

    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM sections WHERE archived = 0");
    $stmt->execute();
    $sectionCount = (int)$stmt->fetchColumn();

    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM subjects WHERE archived = 0");
    $stmt->execute();
    $subjectCount = (int)$stmt->fetchColumn();

    // Strand Enrollment Counts
    $stmt = $dbConnection->prepare("
        SELECT ts.strandName, COUNT(ss.userID) as enrollment_count
        FROM track_strands ts
        LEFT JOIN sections s ON ts.strandID = s.strandID
        LEFT JOIN student_section ss ON s.sectionID = ss.sectionID
        LEFT JOIN users u ON ss.userID = u.userID
        WHERE ts.archived = 0 
        AND s.archived = 0 
        AND ss.archived = 0 
        AND u.archived = 0 
        AND ss.status = 'Enrolled'
        AND u.userType = 'Student'
        GROUP BY ts.strandID, ts.strandName
    ");
    $stmt->execute();
    $strandData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Age Distribution by User Type
    $ageGroups = ['0-17', '18-24', '25-34', '35-44', '45+'];
    $ageDataByType = [
        'superadmins' => array_fill_keys($ageGroups, 0),
        'admins' => array_fill_keys($ageGroups, 0),
        'professors' => array_fill_keys($ageGroups, 0),
        'students' => array_fill_keys($ageGroups, 0),
    ];

    foreach ($userCounts as $key => $userType) {
        $stmt = $dbConnection->prepare("
            SELECT 
                CASE 
                    WHEN age < 18 THEN '0-17'
                    WHEN age BETWEEN 18 AND 24 THEN '18-24'
                    WHEN age BETWEEN 25 AND 34 THEN '25-34'
                    WHEN age BETWEEN 35 AND 44 THEN '35-44'
                    ELSE '45+'
                END as age_group,
                COUNT(*) as count
            FROM (
                SELECT FLOOR(DATEDIFF(CURDATE(), birthday)/365) as age
                FROM users 
                WHERE userType = :userType AND archived = 0 AND birthday IS NOT NULL
            ) as ages
            GROUP BY age_group
            ORDER BY FIELD(age_group, '0-17', '18-24', '25-34', '35-44', '45+')
        ");
        $stmt->bindParam(':userType', $userType, PDO::PARAM_STR);
        $stmt->execute();
        $ageData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ageData as $row) {
            $ageDataByType[$key][$row['age_group']] = (int)$row['count'];
        }
    }

    // User Creation Over Time
    $stmt = $dbConnection->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
        FROM users 
        WHERE archived = 0
        GROUP BY month
        ORDER BY month
    ");
    $stmt->execute();
    $creationData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dashboard View Statistics
    $dashboardViews = ['table' => 0, 'card' => 0];
    $stmt = $dbConnection->prepare("
        SELECT dashboard_view, COUNT(*) as count
        FROM users 
        WHERE archived = 0
        GROUP BY dashboard_view
    ");
    $stmt->execute();
    $viewData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($viewData as $row) {
        if (isset($dashboardViews[$row['dashboard_view']])) {
            $dashboardViews[$row['dashboard_view']] = (int)$row['count'];
        }
    }

    // Statistics
    $stmt = $dbConnection->prepare("SELECT AVG(FLOOR(DATEDIFF(CURDATE(), birthday)/365)) as avg_age FROM users WHERE archived = 0 AND birthday IS NOT NULL");
    $stmt->execute();
    $avgAge = round($stmt->fetchColumn(), 1);

    $totalUsers = array_sum($counts);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "An error occurred: " . htmlspecialchars($e->getMessage()) . ". Please try again later.";
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
    <link rel="stylesheet" href="./utils/box.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="./logout.js" defer></script>
    <title>Learnify</title>
    <style>
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        #content main .box-info li {
            position: relative; 
            border-top: 4px solid #007bff; 
            overflow: hidden; 
        }
        #content main .box-info li:hover {
            border-left: 1.5px solid #007bff;
            border-right: 1.5px solid #007bff;
            border-bottom: 1.5px solid #007bff;
        }
        #content main .box-info li::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%; 
            height: 4px;
            background-color: #007bff;
            transition: height 0.3s ease, background-color 0.3s ease; 
            z-index: 1; 
        }
        #content main .box-info li:hover::before {
            height: 8px; 
            background-color: #0056b3; 
        }
        .below {
            margin-top: 40px;
            border-bottom: 1px solid var(--grey);
        }
        .analytics h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            text-transform: uppercase;
            margin-top: 10px;
            color: var(--dark);
        }
        .charts-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            gap: 20px;
            flex-wrap: wrap;
        }
        .chart-box {
            width: 48%;
            background: var(--light);
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .chart-box.strand-chart, .chart-box.creation-chart {
            width: 100%;
            max-width: 900px;
            margin: 20px auto 0;
        }
        .chart-box h1 {
            font-size: 2rem;
            color: var(--dark);
            border-bottom: 1px solid var(--dark);
            text-align: center;
        }
        .chart-container {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            cursor: pointer;
        }
        .sex-charts-container, .age-charts-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-top: 20px;
            gap: 10px;
        }
        .sex-chart, .age-chart {
            width: calc(50% - 10px);
            max-width: 250px;
            margin: 0 auto;
            cursor: pointer;
        }
        .sex-chart h2, .age-chart h2 {
            font-size: 1.8rem;
            color: var(--dark);
            text-align: center;
            margin-bottom: 10px;
        }
        .chart-report-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        #pdf-btn, .chart-pdf-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        #pdf-btn:hover, .chart-pdf-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        #csv-btn {
            background: linear-gradient(135deg, #28a745, #218838);
        }
        #csv-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        .btn-report {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 500;
            text-transform: uppercase;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .btn-report:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .btn-report i {
            font-size: 18px;
        }
        .error-notification {
            max-width: 500px;
            margin: 10px auto;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: var(--light);
        }
        .statistics {
            margin-top: 40px;
            background: var(--light);
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .statistics h1 {
            font-size: 2rem;
            color: var(--dark);
            text-align: center;
            margin-bottom: 20px;
        }
        .stats-list {
            list-style: none;
            padding: 0;
        }
        .stats-list li {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        @media (max-width: 1024px) {
            .chart-box {
                width: 48%;
            }
            .chart-box.strand-chart, .chart-box.creation-chart {
                width: 100%;
                max-width: 700px;
            }
        }
        @media (max-width: 768px) {
            .charts-container {
                flex-direction: column;
                align-items: center;
            }
            .chart-box {
                width: 100%;
                margin-top: 20px;
            }
            .chart-box.strand-chart, .chart-box.creation-chart {
                width: 100%;
                max-width: 500px;
            }
            .sex-charts-container, .age-charts-container {
                flex-direction: column;
            }
            .sex-chart, .age-chart {
                width: 100%;
                max-width: 300px;
            }
            .chart-report-buttons {
                flex-direction: column;
                gap: 10px;
            }
            .btn-report {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
        .box-info .text p {
            font-size: 1.3rem !important;
        }
        .box-info .text h3 {
            font-size: 2.5rem !important;
        }
        
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li class="active">
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
            <li>
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
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image">
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
                    <h1>Dashboard</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./superAdminDash.php">Dashboard</a></li>
                    </ul>
                </div>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class='error-notification'><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="report-buttons-container">
                <form id="csv-form" action="generate_report.php" method="post">
                    <!-- <button type="submit" class="btn-report" id="csv-btn">
                        <i class='bx bxs-file'></i>
                        <span class="text">Generate CSV Report</span>
                    </button> -->
                </form>
                <form id="pdf-form" action="generate_PDF_report.php" method="post">
                    <!-- <button type="submit" class="btn-report" id="pdf-btn">
                        <i class='bx bxs-file'></i>
                        <span class="text">Generate PDF Report</span>
                    </button> -->
                </form>
            </div>

            <!-- Total Counts in Dashboard -->
            <ul class="box-info">
                <?php if ($counts['admins'] > 0): ?>
                    <a href="data_admin.php">
                        <li>
                            <i class='bx bxs-user'></i>
                            <span class="text">
                                <h3><?php echo $counts['admins']; ?></h3>
                                <p><?php echo $userDisplayNames['Admin']; ?></p>
                            </span>
                        </li>
                    </a>
                <?php endif; ?>
                <?php if ($counts['professors'] > 0): ?>
                    <a href="data_professor.php">
                        <li>
                            <i class='bx bxs-user'></i>
                            <span class="text">
                                <h3><?php echo $counts['professors']; ?></h3>
                                <p><?php echo $userDisplayNames['Professor']; ?></p>
                            </span>
                        </li>
                    </a>
                <?php endif; ?>
                <?php if ($counts['students'] > 0): ?>
                    <a href="data_student.php">
                        <li>
                            <i class='bx bxs-user'></i>
                            <span class="text">
                                <h3><?php echo $counts['students']; ?></h3>
                                <p><?php echo $userDisplayNames['Student']; ?></p>
                            </span>
                        </li>
                    </a>
                <?php endif; ?>
                <?php if ($counts['superadmins'] > 0): ?>
                    <a href="data_superAdmin.php">
                        <li>
                            <i class='bx bxs-user'></i>
                            <span class="text">
                                <h3><?php echo $counts['superadmins']; ?></h3>
                                <p><?php echo $userDisplayNames['SuperAdmin']; ?></p>
                            </span>
                        </li>
                    </a>
                <?php endif; ?>
                <?php if ($strandCount > 0): ?>
                    <a href="data_track_strands.php">
                        <li>
                            <i class='bx bxs-book'></i>
                            <span class="text">
                                <h3><?php echo $strandCount; ?></h3>
                                <p>Track & Strands</p>
                            </span>
                        </li>
                    </a>
                <?php endif; ?>
                <?php if ($sectionCount > 0): ?>
                    <a href="data_sections.php">
                        <li>
                            <i class='bx bx-book-content'></i>
                            <span class="text">
                                <h3><?php echo $sectionCount; ?></h3>
                                <p>Sections</p>
                            </span>
                        </li>
                    </a>
                <?php endif; ?>
                <?php if ($subjectCount > 0): ?>
                    <a href="data_subjects.php">
                        <li>
                            <i class='bx bxs-bookmark'></i>
                            <span class="text">
                                <h3><?php echo $subjectCount; ?></h3>
                                <p>Subject</p>
                            </span>
                        </li>
                    </a>
                <?php endif; ?>
            </ul>

            <?php if ($counts['admins'] > 0 || $counts['professors'] > 0 || $counts['students'] > 0 || $counts['superadmins'] > 0 || !empty($strandData)): ?>
                <hr class="below">
                <div class="analytics">
                    <h1>Analytics</h1>
                </div>
                <div class="charts-container">
                    <div class="chart-box">
                        <h1>User Distribution</h1>
                        <div class="chart-container">
                            <canvas id="userPieChart"></canvas>
                        </div>
                        <div class="chart-report-buttons">
                            <form action="generate_user_pdf.php" method="post">
                                <button type="submit" class="btn-report chart-pdf-btn">
                                    <i class='bx bxs-file'></i>
                                    <span class="text">Download User PDF</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="chart-box">
                        <h1>Sex Distribution</h1>
                        <div class="sex-charts-container">
                            <div class="sex-chart">
                                <h2>SuperAdmins</h2>
                                <canvas id="sexPieChartSuperAdmins"></canvas>
                            </div>
                            <div class="sex-chart">
                                <h2>Admins</h2>
                                <canvas id="sexPieChartAdmins"></canvas>
                            </div>
                            <div class="sex-chart">
                                <h2>Teachers</h2>
                                <canvas id="sexPieChartTeachers"></canvas>
                            </div>
                            <div class="sex-chart">
                                <h2>Students</h2>
                                <canvas id="sexPieChartStudents"></canvas>
                            </div>
                        </div>
                        <div class="chart-report-buttons">
                            <form action="generate_sex_pdf.php" method="post">
                                <button type="submit" class="btn-report chart-pdf-btn">
                                    <i class='bx bxs-file'></i>
                                    <span class="text">Download Sex PDF</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php if (!empty($strandData)): ?>
                        <div class="chart-box strand-chart">
                            <h1>Strand Enrollment</h1>
                            <div class="chart-container">
                                <canvas id="strandBarChart"></canvas>
                            </div>
                            <div class="chart-report-buttons">
                                <form action="generate_strand_PDF.php" method="post">
                                    <button type="submit" class="btn-report chart-pdf-btn">
                                        <i class='bx bxs-file'></i>
                                        <span class="text">Download Strand PDF</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="chart-box creation-chart">
                        <h1>Age Distribution by User Type</h1>
                        <div class="age-charts-container">
                            <div class="age-chart">
                                <h2>SuperAdmins</h2>
                                <canvas id="agePieChartSuperAdmins"></canvas>
                            </div>
                            <div class="age-chart">
                                <h2>Admins</h2>
                                <canvas id="agePieChartAdmins"></canvas>
                            </div>
                            <div class="age-chart">
                                <h2>Teachers</h2>
                                <canvas id="agePieChartTeachers"></canvas>
                            </div>
                            <div class="age-chart">
                                <h2>Students</h2>
                                <canvas id="agePieChartStudents"></canvas>
                            </div>
                        </div>
                        <div class="chart-report-buttons">
                            <form action="generate_age_PDF.php" method="post">
                                <button type="submit" class="btn-report chart-pdf-btn">
                                    <i class='bx bxs-file'></i>
                                    <span class="text">Download Age PDF</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- <div class="chart-box">
                        <h1>Dashboard View Preferences</h1>
                        <div class="chart-container">
                            <canvas id="viewPieChart"></canvas>
                        </div>
                        <div class="chart-report-buttons">
                            <form action="generate_view_pdf.php" method="post">
                                <button type="submit" class="btn-report chart-pdf-btn">
                                    <i class='bx bxs-file'></i>
                                    <span class="text">Download View PDF</span>
                                </button>
                            </form>
                        </div>
                    </div> -->
                    <!-- <div class="chart-box creation-chart">
                        <h1>User Creation Over Time</h1>
                        <div class="chart-container">
                            <canvas id="creationLineChart"></canvas>
                        </div>
                        <div class="chart-report-buttons">
                            <form action="generate_creation_pdf.php" method="post">
                                <button type="submit" class="btn-report chart-pdf-btn">
                                    <i class='bx bxs-file'></i>
                                    <span class="text">Download Creation PDF</span>
                                </button>
                            </form>
                        </div>
                    </div> -->
                </div>
                <div class="statistics">
                    <h1>Statistics</h1>
                    <ul class="stats-list">
                        <li><strong>Total Users:</strong> <?php echo $totalUsers; ?></li>
                        <li><strong>Average Age:</strong> <?php echo $avgAge; ?> years</li>
                    </ul>
                </div>
            <?php endif; ?>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script>
    // Prevent form submission issues
    document.getElementById('csv-form').addEventListener('submit', function(e) {
        console.log('CSV form submitted');
    });
    document.getElementById('pdf-form').addEventListener('submit', function(e) {
        console.log('PDF form submitted');
    });

    // User Distribution Pie Chart
    const userData = [
        { label: 'SuperAdmins', value: <?php echo $counts['superadmins']; ?>, url: 'data_superAdmin.php' },
        { label: 'Admins', value: <?php echo $counts['admins']; ?>, url: 'data_admin.php' },
        { label: 'Teachers', value: <?php echo $counts['professors']; ?>, url: 'data_professor.php' },
        { label: 'Students', value: <?php echo $counts['students']; ?>, url: 'data_student.php' }
    ];
    const filteredUserData = userData.filter(item => item.value > 0);
    const userCtx = document.getElementById('userPieChart').getContext('2d');
    if (filteredUserData.length > 0) {
        const userPieChart = new Chart(userCtx, {
            type: 'pie',
            data: {
                labels: filteredUserData.map(item => item.label),
                datasets: [{
                    data: filteredUserData.map(item => item.value),
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'],
                    borderColor: ['#fff', '#fff', '#fff', '#fff'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 18,
                                family: 'Poppins'
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            size: 16,
                            family: 'Poppins',
                            weight: 'bold'
                        },
                        formatter: (value, ctx) => {
                            let sum = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            return value > 0 ? (value / sum * 100).toFixed(1) + '%' : '';
                        }
                    }
                },
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        window.location.href = filteredUserData[index].url;
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    } else {
        userCtx.canvas.parentElement.style.display = 'none';
    }

    // Sex Distribution Pie Charts
    const sexCounts = <?php echo json_encode($sexCounts); ?>;
    const sexChartConfigs = [
        {
            id: 'sexPieChartSuperAdmins',
            userType: 'superadmins',
            url: 'data_superAdmin.php'
        },
        {
            id: 'sexPieChartAdmins',
            userType: 'admins',
            url: 'data_admin.php'
        },
        {
            id: 'sexPieChartTeachers',
            userType: 'professors',
            url: 'data_professor.php'
        },
        {
            id: 'sexPieChartStudents',
            userType: 'students',
            url: 'data_student.php'
        }
    ];

    sexChartConfigs.forEach(config => {
        const ctx = document.getElementById(config.id).getContext('2d');
        const data = [
            { label: 'Male', value: sexCounts[config.userType]['male'] },
            { label: 'Female', value: sexCounts[config.userType]['female'] }
        ];
        const filteredData = data.filter(item => item.value > 0);
        const total = filteredData.reduce((sum, item) => sum + item.value, 0);
        if (total > 0) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: filteredData.map(item => item.label),
                    datasets: [{
                        data: filteredData.map(item => item.value),
                        backgroundColor: ['#36A2EB', '#FF6384'],
                        borderColor: ['#fff', '#fff'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 18,
                                    family: 'Poppins'
                                }
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            font: {
                                size: 16,
                                family: 'Poppins',
                                weight: 'bold'
                            },
                            formatter: (value, ctx) => {
                                let sum = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                return value > 0 ? (value / sum * 100).toFixed(1) + '%' : '';
                            }
                        }
                    },
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const urls = [
                                `${config.url}?sex=male`,
                                `${config.url}?sex=female`
                            ];
                            window.location.href = urls[index];
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
        } else {
            ctx.canvas.parentElement.style.display = 'none';
        }
    });

    // Strand Enrollment Bar Chart
    const strandData = <?php echo json_encode($strandData); ?>;
    const strandBarChartData = {
        labels: strandData.map(item => item.strandName),
        datasets: [{
            label: 'Enrolled Students',
            data: strandData.map(item => parseInt(item.enrollment_count) || 0),
            backgroundColor: '#33FF57',
            borderWidth: 1
        }]
    };

    if (strandData.length > 0) {
        const strandBarCtx = document.getElementById('strandBarChart');
        if (strandBarCtx) {
            new Chart(strandBarCtx.getContext('2d'), {
                type: 'bar',
                data: strandBarChartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 20,
                                    family: 'Poppins',
                                    weight: 'bold'
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            formatter: (value) => value > 0 ? value : '',
                            font: {
                                size: 16
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Enrollments',
                                font: {
                                    size: 16
                                }
                            },
                            ticks: {
                                font: {
                                    size: 18
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Strands',
                                font: {
                                    size: 24
                                }
                            },
                            ticks: {
                                font: {
                                    size: 16
                                }
                            }
                        }
                    },
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const strandName = strandData[index].strandName;
                            window.location.href = `data_strand_students.php?strand=${encodeURIComponent(strandName)}`;
                        }
                    },
                    layout: {
                        padding: 20
                    }
                }
            });
            strandBarCtx.style.width = '800px';
            strandBarCtx.style.height = '400px';
        } else {
            console.error('Strand Bar Chart canvas not found.');
        }
    } else {
        const strandBarChartElement = document.getElementById('strandBarChart');
        if (strandBarChartElement) strandBarChartElement.parentElement.style.display = 'none';
    }

    // Age Distribution Pie Charts by User Type
    const ageDataByType = <?php echo json_encode($ageDataByType); ?>;
    const ageChartConfigs = [
        {
            id: 'agePieChartSuperAdmins',
            userType: 'superadmins',
            url: 'data_superAdmin.php'
        },
        {
            id: 'agePieChartAdmins',
            userType: 'admins',
            url: 'data_admin.php'
        },
        {
            id: 'agePieChartTeachers',
            userType: 'professors',
            url: 'data_professor.php'
        },
        {
            id: 'agePieChartStudents',
            userType: 'students',
            url: 'data_student.php'
        }
    ];

    ageChartConfigs.forEach(config => {
        const ctx = document.getElementById(config.id).getContext('2d');
        const data = [
            { label: '0-17', value: ageDataByType[config.userType]['0-17'] },
            { label: '18-24', value: ageDataByType[config.userType]['18-24'] },
            { label: '25-34', value: ageDataByType[config.userType]['25-34'] },
            { label: '35-44', value: ageDataByType[config.userType]['35-44'] },
            { label: '45+', value: ageDataByType[config.userType]['45+'] }
        ];
        const filteredData = data.filter(item => item.value > 0);
        const total = filteredData.reduce((sum, item) => sum + item.value, 0);
        if (total > 0) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: filteredData.map(item => item.label),
                    datasets: [{
                        data: filteredData.map(item => item.value),
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                        borderColor: ['#fff', '#fff', '#fff', '#fff', '#fff'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 18,
                                    family: 'Poppins'
                                }
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            font: {
                                size: 16,
                                family: 'Poppins',
                                weight: 'bold'
                            },
                            formatter: (value, ctx) => {
                                let sum = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                return value > 0 ? (value / sum * 100).toFixed(1) + '%' : '';
                            }
                        }
                    },
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const ageGroups = ['0-17', '18-24', '25-34', '35-44', '45+'];
                            const filteredAgeGroups = filteredData.map(item => item.label);
                            const ageGroup = filteredAgeGroups[index];
                            window.location.href = `${config.url}?age_group=${encodeURIComponent(ageGroup)}`;
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
        } else {
            ctx.canvas.parentElement.style.display = 'none';
        }
    });

    // Dashboard View Pie Chart
    const viewData = [
        { label: 'Table', value: <?php echo $dashboardViews['table']; ?> },
        { label: 'Card', value: <?php echo $dashboardViews['card']; ?> }
    ];
    const filteredViewData = viewData.filter(item => item.value > 0);
    const viewPieCtx = document.getElementById('viewPieChart');
    if (viewPieCtx && filteredViewData.length > 0) {
        const viewPieChart = new Chart(viewPieCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: filteredViewData.map(item => item.label),
                datasets: [{
                    data: filteredViewData.map(item => item.value),
                    backgroundColor: ['#FFCE56', '#36A2EB'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 18,
                                family: 'Poppins'
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            size: 16,
                            family: 'Poppins',
                            weight: 'bold'
                        },
                        formatter: (value, ctx) => {
                            let sum = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            return value > 0 ? (value / sum * 100).toFixed(1) + '%' : '';
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    } else if (viewPieCtx) {
        viewPieCtx.parentElement.style.display = 'none';
    }

    // User Creation Line Chart
    const creationData = <?php echo json_encode($creationData); ?>;
    const creationLabels = creationData.map(item => item.month);
    const creationCounts = creationData.map(item => parseInt(item.count));

    const creationLineCtx = document.getElementById('creationLineChart').getContext('2d');
    const creationLineChart = new Chart(creationLineCtx, {
        type: 'line',
        data: {
            labels: creationLabels,
            datasets: [{
                label: 'New Users',
                data: creationCounts,
                borderColor: '#FF6384',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                },
                datalabels: {
                    align: 'top',
                    formatter: (value) => value
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        },
        plugins: [ChartDataLabels]
    });
    </script>
</body>
</html>