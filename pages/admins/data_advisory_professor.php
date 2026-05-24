<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Get search and filter parameters
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$subjectFilter = isset($_GET['subjectID']) ? (int)$_GET['subjectID'] : 0;
$sectionFilter = isset($_GET['sectionID']) ? (int)$_GET['sectionID'] : 0;

$recordsPerPage = 15; // Match records per page with data_teacher_section.php
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Fetch sections present in advisory_professor_section
$sectionSQL = "SELECT DISTINCT s.sectionID, s.sectionName, s.sectionCode 
               FROM sections s
               JOIN advisory_professor_section aps ON s.sectionID = aps.sectionID
               WHERE s.archived = 0 AND aps.archived = 0 
               ORDER BY s.sectionName";
$sectionStmt = $dbConnection->prepare($sectionSQL);
$sectionStmt->execute();
$sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch subjects present in advisory_professor_section
$subjectSQL = "SELECT DISTINCT sub.subjectID, sub.subjectName, sub.subjectCode
               FROM subjects sub
               JOIN advisory_professor_section aps ON sub.subjectID = aps.subjectID
               WHERE sub.archived = 0 
               ORDER BY sub.subjectName";
$subjectStmt = $dbConnection->prepare($subjectSQL);
$subjectStmt->execute();
$subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

// Count total records
$sqlCount = "SELECT COUNT(*) 
             FROM advisory_professor_section aps
             JOIN users u ON aps.professorID = u.userID
             JOIN sections s ON aps.sectionID = s.sectionID
             LEFT JOIN subjects sub ON aps.subjectID = sub.subjectID
             WHERE aps.archived = 0";
$params = [];

if ($searchQuery) {
    $sqlCount .= " AND (u.firstName LIKE :search OR u.lastName LIKE :search OR s.sectionName LIKE :search OR s.sectionCode LIKE :search)";
    $params[':search'] = "%$searchQuery%";
}

if ($statusFilter) {
    $sqlCount .= " AND aps.status = :status";
    $params[':status'] = $statusFilter;
}

if ($subjectFilter) {
    $sqlCount .= " AND aps.subjectID = :subjectID";
    $params[':subjectID'] = $subjectFilter;
}

if ($sectionFilter) {
    $sqlCount .= " AND aps.sectionID = :sectionID";
    $params[':sectionID'] = $sectionFilter;
}

$stmtCount = $dbConnection->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch advisory assignments
$sql = "SELECT aps.*, 
        u.firstName AS professorFirstName, 
        u.lastName AS professorLastName, 
        u.image AS professorImage, 
        s.sectionName, 
        s.sectionCode,
        sub.subjectName,
        sub.subjectCode
        FROM advisory_professor_section aps
        JOIN users u ON aps.professorID = u.userID
        JOIN sections s ON aps.sectionID = s.sectionID
        LEFT JOIN subjects sub ON aps.subjectID = sub.subjectID
        WHERE aps.archived = 0";

if ($searchQuery) {
    $sql .= " AND (u.firstName LIKE :search OR u.lastName LIKE :search OR s.sectionName LIKE :search OR s.sectionCode LIKE :search)";
}

if ($statusFilter) {
    $sql .= " AND aps.status = :status";
}

if ($subjectFilter) {
    $sql .= " AND aps.subjectID = :subjectID";
}

if ($sectionFilter) {
    $sql .= " AND aps.sectionID = :sectionID";
}

$sql .= " ORDER BY aps.assignedDate DESC LIMIT :offset, :recordsPerPage";
$params[':offset'] = $offset;
$params[':recordsPerPage'] = $recordsPerPage;

$stmt = $dbConnection->prepare($sql);
foreach ($params as $key => &$value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Learnify - Advisory Teachers</title>
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
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --grey: #f3f4f6;
            --dark-grey: #9ca3af;
            --dark: #1f2937;
            --green: #10b981;
            --red: #ef4444;
        }

        .search-form {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem;
            flex-wrap: wrap;
            max-width: 100%;
            background: var(--light);
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .search-form input[type="text"],
        .search-form select {
            padding: 0.75rem;
            font-size: 0.875rem;
            border: 1px solid var(--dark-grey);
            background-color: var(--grey);
            color: var(--dark);
            border-radius: 0.375rem;
            outline: none;
            flex: 1;
            min-width: 150px;
            transition: all 0.3s ease;
        }

        .search-form select {
            color: var(--dark);
        }

        .search-form input[type="text"]:focus,
        .search-form select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-form button, .search-form .clear-btn {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            text-align: center;
            min-width: 100px;
        }

        .search-form button[type="submit"] {
            background-color: var(--blue);
            color: white;
        }

        .search-form button[type="submit"]:hover {
            background-color: #2563eb;
        }

        .search-form .clear-btn {
            background-color: var(--red);
            color: white;
        }

        .search-form .clear-btn:hover {
            background-color: #dc2626;
        }

        .admin-table {
            margin: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .admin-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.875rem;
            min-width: 600px;
        }

        .admin-table th,
        .admin-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--grey);
        }

        .admin-table th {
            background-color: var(--blue);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .admin-table td {
            color: var(--dark);
            vertical-align: middle;
            text-transform: capitalize;
        }

        .admin-table tr:hover {
            background: var(--grey);
            transition: background-color 0.2s ease;
        }

        .btn-edit, .btn-archive {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            transition: background-color 0.3s ease;
        }

        .btn-edit {
            background-color: #FFCE26;
            color: #342E37;
        }

        .btn-archive {
            background-color: var(--red);
            color: white;
            margin-left: 10px;
        }

        .btn-edit:hover {
            background-color: #e6b800;
        }

        .btn-archive:hover {
            background-color: #dc2626;
        }

        .success-notification, .error-notification {
            background-color: var(--green);
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 0.375rem;
            text-align: center;
            font-weight: 600;
        }

        .error-notification {
            background-color: var(--red);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem 0;
        }

        .pagination a {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid var(--dark-grey);
            border-radius: 0.375rem;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
        }

        .pagination a.active {
            background-color: var(--blue);
            color: white;
            border-color: var(--blue);
        }

        .pagination a:hover:not(.disabled) {
            background-color: var(--blue);
            color: white;
        }

        .pagination a.disabled {
            color: var(--dark-grey);
            cursor: not-allowed;
        }

        .professor-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            vertical-align: middle;
        }

        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                margin: 1rem;
            }

            .search-form input[type="text"],
            .search-form select,
            .search-form button,
            .search-form .clear-btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .admin-table {
                margin: 1rem;
                padding: 0.5rem;
            }

            .admin-table table {
                font-size: 0.75rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 0.5rem;
            }

            .btn-edit, .btn-archive {
                padding: 0.4rem 0.8rem;
                font-size: 0.7rem;
            }

            .pagination a {
                padding: 0.4rem 0.6rem;
                font-size: 0.7rem;
            }
        }

        @media (max-width: 480px) {
            .admin-table table {
                font-size: 0.7rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 0.4rem;
            }

            .btn-edit, .btn-archive {
                padding: 0.4rem 0.8rem;
                font-size: 0.7rem;
            }

            .pagination a {
                padding: 0.4rem 0.6rem;
                font-size: 0.7rem;
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
                    <span class="text">Assign Teacher Section</span>
                </a>
            </li>
            <li class="active">
                <a href="./enroll_advisory_professor.php">
                    <i class='bx bxs-user-check'></i>
                    <span class="text">Assign Advisory Teacher</span>
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
                    <h1>View Advisory Teachers</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="data_advisory_professor.php">View Advisory Teachers</a></li>
                    </ul>
                </div>
                <a href="enroll_advisory_professor.php" class="btn-download">
                    <i class="bx bxs-left-arrow"></i>
                    <span class="text">Back</span>
                </a>
            </div>

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

            <!-- Search and Filter Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by teacher or section" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <select name="status" id="status">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $statusFilter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <select name="subjectID" id="subjectID">
                    <option value="0">All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['subjectID']; ?>" <?php echo $subjectFilter == $subject['subjectID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subjectName'] . ' (' . $subject['subjectCode'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="sectionID" id="sectionID">
                    <option value="0">All Sections</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['sectionID']; ?>" <?php echo $sectionFilter == $section['sectionID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section['sectionName'] . ' (' . $section['sectionCode'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filter</button>
                <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($subjectFilter) || !empty($sectionFilter)): ?>
                    <a href="data_advisory_professor.php" class="clear-btn">Clear</a>
                <?php endif; ?>
            </form>

            <!-- Advisory Teachers Table -->
            <div class="admin-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Section</th>
                            <th>Subject</th>
                            <th>Advisory Teacher</th>
                            <th>Assigned Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assignments)): ?>
                            <?php $rowNumber = $offset + 1; ?>
                            <?php foreach ($assignments as $row): ?>
                                <tr>
                                    <td><?php echo $rowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars($row['sectionName'] . ' (' . $row['sectionCode'] . ')'); ?></td>
                                    <td><?php echo ($row['subjectName'] ? htmlspecialchars($row['subjectName'] . ' (' . $row['subjectCode'] . ')') : 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($row['professorImage'])): ?>
                                            <img src="<?php echo htmlspecialchars($row['professorImage']); ?>" alt="Professor Image" class="professor-img">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars(ucwords($row['professorFirstName'] . ' ' . $row['professorLastName'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date("F d, Y", strtotime($row['assignedDate']))); ?></td>
                                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                                    <td>
                                        <a href="edit_advisory_professor.php?id=<?php echo $row['advisoryID']; ?>" class="btn-edit">
                                            <i class='bx bxs-edit'></i> Edit
                                        </a>
                                        <a href="archive_advisory_professor.php?id=<?php echo $row['advisoryID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this advisory professor assignment?');">
                                            <i class='bx bxs-archive-in'></i> Archive
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No advisory teacher assignments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php
                        $queryParams = array_filter([
                            'search' => $searchQuery,
                            'status' => $statusFilter,
                            'subjectID' => $subjectFilter,
                            'sectionID' => $sectionFilter
                        ]);
                        $queryString = http_build_query($queryParams);

                        if ($currentPage > 1) {
                            $prevPage = $currentPage - 1;
                            echo "<a href='data_advisory_professor.php?page=$prevPage&$queryString'>Previous</a>";
                        } else {
                            echo "<a class='disabled'>Previous</a>";
                        }

                        for ($i = 1; $i <= $totalPages; $i++) {
                            $activeClass = $i == $currentPage ? 'active' : '';
                            echo "<a href='data_advisory_professor.php?page=$i&$queryString' class='$activeClass'>$i</a>";
                        }

                        if ($currentPage < $totalPages) {
                            $nextPage = $currentPage + 1;
                            echo "<a href='data_advisory_professor.php?page=$nextPage&$queryString'>Next</a>";
                        } else {
                            echo "<a class='disabled'>Next</a>";
                        }
                        ?>
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