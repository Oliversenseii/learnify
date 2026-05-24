<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$strandID = isset($_GET['strandID']) ? $_GET['strandID'] : '';
$yearLevel = isset($_GET['yearLevel']) ? $_GET['yearLevel'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$subjectType = isset($_GET['subjectType']) ? $_GET['subjectType'] : '';
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 15;
$offset = ($currentPage - 1) * $recordsPerPage;

$trackStrandTitle = "All Track and Strand";
if ($strandID !== '') {
    $trackSQL = "SELECT trackName, strandName FROM track_strands WHERE strandID = :strandID AND archived = 0";
    $trackStmt = $dbConnection->prepare($trackSQL);
    $trackStmt->bindValue(':strandID', $strandID, PDO::PARAM_INT);
    $trackStmt->execute();
    $trackStrand = $trackStmt->fetch(PDO::FETCH_ASSOC);
    if ($trackStrand) {
        $trackStrandTitle = htmlspecialchars($trackStrand['trackName'] . ' - ' . $trackStrand['strandName']);
    }
}

$countSQL = "SELECT COUNT(*) as total FROM subjects s
             LEFT JOIN track_strands ts ON s.strandID = ts.strandID
             WHERE s.archived = 0";
$params = [];
if ($search !== '') {
    $countSQL .= " AND (s.subjectName LIKE :search OR s.subjectCode LIKE :search OR s.subjectType LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($strandID !== '') {
    $countSQL .= " AND s.strandID = :strandID";
    $params[':strandID'] = $strandID;
}
if ($yearLevel !== '') {
    $countSQL .= " AND s.yearLevel = :yearLevel";
    $params[':yearLevel'] = $yearLevel;
}
if ($semester !== '') {
    $countSQL .= " AND s.semester = :semester";
    $params[':semester'] = $semester;
}
if ($subjectType !== '') {
    $countSQL .= " AND s.subjectType = :subjectType";
    $params[':subjectType'] = $subjectType;
}

$countStmt = $dbConnection->prepare($countSQL);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$sql = "SELECT s.subjectID, s.subjectName, s.yearLevel, s.semester, s.subjectCode, s.subjectType, ts.trackName, ts.strandName
        FROM subjects s
        LEFT JOIN track_strands ts ON s.strandID = ts.strandID
        WHERE s.archived = 0";

if ($search !== '') {
    $sql .= " AND (s.subjectName LIKE :search OR s.subjectCode LIKE :search OR s.subjectType LIKE :search)";
}
if ($strandID !== '') {
    $sql .= " AND s.strandID = :strandID";
}
if ($yearLevel !== '') {
    $sql .= " AND s.yearLevel = :yearLevel";
}
if ($semester !== '') {
    $sql .= " AND s.semester = :semester";
}
if ($subjectType !== '') {
    $sql .= " AND s.subjectType = :subjectType";
}
$sql .= " LIMIT :offset, :recordsPerPage";

$stmt = $dbConnection->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$strandSQL = "SELECT strandID, trackName, strandName FROM track_strands WHERE archived = 0 ORDER BY trackName, strandName";
$strandStmt = $dbConnection->prepare($strandSQL);
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
    <link rel="stylesheet" href="./utils/data_admin.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify</title>
    <style>
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        /* Search Form Styling */
        .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px;
            flex-wrap: wrap;
            max-width: 100%;
        }

        .search-form input[type="text"],
        .search-form select {
            padding: 10px;
            font-size: 1.2rem;
            border: 1px solid #ccc;
            background-color: var(--light);
            border-radius: 5px;
            outline: none;
            flex: 1;
            min-width: 150px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .search-form select {
            color: var(--dark);
        }

        .search-form input[type="text"]:focus,
        .search-form select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }

        .search-form button, .search-form .clear-btn {
            padding: 10px 15px;
            font-size: 1.2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            text-align: center;
            min-width: 100px;
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

        /* Admin Table Styling */
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
            font-size: 1.2rem;
            min-width: 600px;
        }

        .admin-table td {
			border: none;
		}

		.admin-table tr:nth-child(even) {
			background-color: var(--grey);
            border: none;
		}
        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--grey), gray);
        }

        .admin-table th,
        .admin-table td {
            padding: 20px;
            text-align: left;
        }

        .admin-table th {
            background: #0056b3;
            color: #fff;
            font-weight: bold;
            white-space: nowrap;
        }

        .admin-table th.track-strand-title {
             background: rgba(255, 255, 255, 0.1); 
            /* border: 1px solid rgba(255, 255, 255, 0.2);  */
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); 
            color: var(--dark);
            text-align: center;
            font-size: 1.4rem !important;
            padding: 15px;
            border-bottom: 1px solid #0056b3;
        }

        .admin-table td {
            color: var(--dark);
        }

        .admin-table tr {
            font-size: 1.2rem;
        }

        .admin-table tr:hover {
            background: var(--grey);
        }

        /* Action Buttons */

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

		.success-notification {
			background: linear-gradient(135deg, #28a745, #218838);
			color: white;
			padding: 15px;
			margin-bottom: 20px;
			border-radius: 5px;
			text-align: center;
			font-weight: bold;
		}

        /* Pagination Styling */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .pagination a {
            padding: 8px 12px;
            font-size: 1.2rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            text-decoration: none;
            color: var(--dark);
            transition: background-color 0.3s, color 0.3s;
        }

        .pagination a.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .pagination a:hover:not(.disabled) {
            background: linear-gradient(135deg, #0056b3, #003d80);
            color: white;
        }

        .pagination a.disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
                margin: 10px;
            }

            .search-form input[type="text"],
            .search-form select,
            .search-form button,
            .search-form .clear-btn {
                width: 100%;
                min-width: unset;
                margin-bottom: 10px;
            }

            .admin-table {
                margin: 10px;
                padding: 5px;
            }

            .admin-table table {
                font-size: 0.9rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 8px 10px;
                white-space: nowrap;
            }

            .admin-table th.track-strand-title {
                font-size: 1.1rem;
                padding: 10px;
            }

            .btn-edit, .btn-archive {
                font-size: 0.8rem;
                padding: 4px 8px;
            }

            .pagination a {
                padding: 6px 10px;
                font-size: 0.9rem;
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

            .admin-table th.track-strand-title {
                font-size: 1rem;
                padding: 8px;
            }

            .btn-edit, .btn-archive {
                font-size: 0.75rem;
                padding: 3px 6px;
                margin-right: 3px;
            }

            .pagination a {
                padding: 5px 8px;
                font-size: 0.8rem;
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
				<a href="./superAdminDash.php">
					<i class='bx bxs-dashboard' ></i>
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
					<i class='bx bxs-data' ></i>
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
					<i class='bx bxs-cog' ></i>
					<span class="text">Settings</span>
				</a>
			</li>
			<li>
				<a href="javascript:void(0);" class="logout" onclick="showLogoutModal()">
						<i class='bx bxs-log-out-circle' ></i>
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
                    <h1>Subjects</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Home</a>
                        </li>
                        <li><i class='bx bx-chevron-right' ></i></li>
                        <li>
                            <a class="active" href="subjects.php">Subjects</a>
                        </li>
                    </ul>
                </div>
                <a href="./superAdminDash.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </div>

            <!-- Search and Filter Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by subject code, name, or type" value="<?php echo htmlspecialchars($search); ?>">
                <select name="strandID" id="strandID">
                    <option value="">All Tracks and Strands</option>
                    <?php foreach ($strands as $strand): ?>
                        <option value="<?php echo $strand['strandID']; ?>" <?php echo $strandID == $strand['strandID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($strand['trackName'] . ' - ' . $strand['strandName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="yearLevel" id="yearLevel">
                    <option value="">All Year Levels</option>
                    <option value="Grade 11" <?php echo $yearLevel == 'Grade 11' ? 'selected' : ''; ?>>Grade 11</option>
                    <option value="Grade 12" <?php echo $yearLevel == 'Grade 12' ? 'selected' : ''; ?>>Grade 12</option>
                </select>
                <!-- <select name="semester" id="semester">
                    <option value="">All Semesters</option>
                    <option value="1st Sem" <?php echo $semester == '1st Sem' ? 'selected' : ''; ?>>1st Sem</option>
                    <option value="2nd Sem" <?php echo $semester == '2nd Sem' ? 'selected' : ''; ?>>2nd Sem</option>
                </select> -->
                <select name="subjectType" id="subjectType">
                    <option value="">All Subject Types</option>
                    <option value="Core Subject" <?php echo $subjectType == 'Core Subject' ? 'selected' : ''; ?>>Core Subject</option>
                    <option value="Applied Subject" <?php echo $subjectType == 'Applied Subject' ? 'selected' : ''; ?>>Applied Subject</option>
                    <option value="Specialized Subject" <?php echo $subjectType == 'Specialized Subject' ? 'selected' : ''; ?>>Specialized Subject</option>
                </select>
                <button type="submit">Filter</button>
                <?php if (!empty($search) || !empty($strandID) || !empty($yearLevel) || !empty($semester) || !empty($subjectType)): ?>
                    <a href="data_subjects.php" class="clear-btn">Clear</a>
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

            <!-- Table to display Subjects -->
            <div class="admin-table">
                <table>
                    <thead>
                        <tr>
                            <th class="track-strand-title" colspan="4"><?php echo $trackStrandTitle; ?></th>
                        </tr>
                    </thead>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <!-- <th>Actions</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($subjects)): ?>
                            <?php $rowNumber = $offset + 1; ?>
                            <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><?php echo $rowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars($subject['subjectCode']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['subjectName']); ?></td>
                                    <!-- <td>
                                        <a href="edit_subjects.php?id=<?php echo $subject['subjectID']; ?>" class="btn-edit">
                                            <i class='bx bxs-edit'></i> Edit
                                        </a>
                                        <a href="archive_subject.php?subjectID=<?php echo $subject['subjectID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this subject?');">
                                            <i class='bx bxs-archive-in'></i> Archive
                                        </a>
                                    </td> -->
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No results found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Pagination Controls -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php
                        $queryParams = array_filter([
                            'search' => $search,
                            'strandID' => $strandID,
                            'yearLevel' => $yearLevel,
                            'semester' => $semester,
                            'subjectType' => $subjectType
                        ]);
                        $queryString = http_build_query($queryParams);

                        // Previous button
                        if ($currentPage > 1) {
                            $prevPage = $currentPage - 1;
                            echo "<a href='data_subjects.php?page=$prevPage&$queryString'>Previous</a>";
                        } else {
                            echo "<a class='disabled'>Previous</a>";
                        }

                        // Page numbers
                        for ($i = 1; $i <= $totalPages; $i++) {
                            $activeClass = $i == $currentPage ? 'active' : '';
                            echo "<a href='data_subjects.php?page=$i&$queryString' class='$activeClass'>$i</a>";
                        }

                        // Next button
                        if ($currentPage < $totalPages) {
                            $nextPage = $currentPage + 1;
                            echo "<a href='data_subjects.php?page=$nextPage&$queryString'>Next</a>";
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>