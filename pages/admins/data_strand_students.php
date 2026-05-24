<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Check if strand parameter is set
if (!isset($_GET['strand'])) {
    header("Location: data_student_strand.php"); 
    exit;
}

$strand = isset($_GET['strand']) ? urldecode(trim($_GET['strand'])) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 5; // Students per page
$offset = ($page - 1) * $perPage;

try {
    // Check if strand exists (case-insensitive)
    $stmt = $dbConnection->prepare("SELECT strandID, strandCode, strandName FROM track_strands WHERE LOWER(strandName) = LOWER(:strand) AND archived = 0");
    $stmt->bindParam(':strand', $strand, PDO::PARAM_STR);
    $stmt->execute();
    $strandData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$strandData) {
        echo "<div style='background-color: #f44336; color: #ffffff; padding: 15px; text-align: center; border-radius: 5px;'>Error: Strand '$strand' not found in the database.</div>";
        exit;
    }

    $strandID = $strandData['strandID'];
    $strandCode = htmlspecialchars($strandData['strandCode']);
    $strandName = htmlspecialchars($strandData['strandName']);

    // Count total students for pagination
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM student_section ss
        JOIN sections s ON ss.sectionID = s.sectionID
        JOIN track_strands ts ON s.strandID = ts.strandID
        JOIN users u ON ss.userID = u.userID
        WHERE ts.strandID = :strandID 
        AND ss.status = 'Enrolled' 
        AND ss.archived = 0
        AND s.archived = 0
        AND u.archived = 0
    ";

    if ($search !== '') {
        $countQuery .= " AND (LOWER(u.firstName) LIKE LOWER(:search) OR LOWER(u.lastName) LIKE LOWER(:search) OR LOWER(u.email) LIKE LOWER(:search))";
    }

    $stmt = $dbConnection->prepare($countQuery);
    $stmt->bindParam(':strandID', $strandID, PDO::PARAM_INT);
    if ($search !== '') {
        $searchTerm = "%$search%";
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    $stmt->execute();
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalStudents / $perPage);

    // Main query to fetch students with pagination
    $query = "
        SELECT 
            u.image,
            u.firstName, 
            u.lastName, 
            u.email, 
            u.contactNumber, 
            u.status
        FROM 
            student_section ss
        JOIN 
            sections s ON ss.sectionID = s.sectionID
        JOIN 
            track_strands ts ON s.strandID = ts.strandID
        JOIN 
            users u ON ss.userID = u.userID
        WHERE 
            ts.strandID = :strandID 
            AND ss.status = 'Enrolled' 
            AND ss.archived = 0
            AND s.archived = 0
            AND u.archived = 0
    ";

    if ($search !== '') {
        $query .= " AND (LOWER(u.firstName) LIKE LOWER(:search) OR LOWER(u.lastName) LIKE LOWER(:search) OR LOWER(u.email) LIKE LOWER(:search))";
    }

    $query .= " LIMIT :perPage OFFSET :offset";

    $stmt = $dbConnection->prepare($query);
    $stmt->bindParam(':strandID', $strandID, PDO::PARAM_INT);
    if ($search !== '') {
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    $stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debugging: Log student data
    error_log("Strand: $strandName, StrandID: $strandID, Search: $search, Page: $page, Students Found: " . count($students) . ", Total Students: $totalStudents");
    if (count($students) > 0) {
        error_log("Students: " . json_encode($students));
    } else {
        // Check userIDs in student_section
        $stmt = $dbConnection->prepare("
            SELECT ss.userID 
            FROM student_section ss 
            JOIN sections s ON ss.sectionID = s.sectionID 
            WHERE s.strandID = :strandID AND ss.status = 'Enrolled' AND ss.archived = 0 AND s.archived = 0
        ");
        $stmt->bindParam(':strandID', $strandID, PDO::PARAM_INT);
        $stmt->execute();
        $userIDs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        error_log("UserIDs in student_section: " . json_encode($userIDs));
        if ($userIDs) {
            $stmt = $dbConnection->prepare("SELECT userID FROM users WHERE userID IN (" . implode(',', array_fill(0, count($userIDs), '?')) . ") AND archived = 0");
            foreach ($userIDs as $i => $userID) {
                $stmt->bindValue($i + 1, $userID, PDO::PARAM_INT);
            }
            $stmt->execute();
            error_log("Matching users found: " . $stmt->rowCount());
        }
    }

} catch (PDOException $e) {
    echo "<div style='background-color: #f44336; color: #ffffff; padding: 15px; text-align: center; border-radius: 5px;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("Database Error: " . $e->getMessage());
    exit;
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
	<script src="./logout.js"></script>
	<style>
		body {
			font-family: 'Poppins', sans-serif;
		}
		@media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
		.success-notification {
			background-color: #4CAF50; 
			color: #ffffff; 
			padding: 15px;
			margin-bottom: 20px;
			border-radius: 5px;
			text-align: center;
			font-weight: bold;
			font-size: 16px;
		}
		.error-message {
			background-color: #f44336;
			color: #ffffff;
			padding: 15px;
			margin-bottom: 20px;
			border-radius: 5px;
			text-align: center;
			font-size: 16px;
		}
		.admin-table table {
			width: 100%;
			border-collapse: collapse;
			font-size: 14px;
		}
		.admin-table th, .admin-table td {
			padding: 10px;
			text-align: left;
            border: none;
		}
        .admin-table tr:nth-child(even) {
			background-color: var(--grey);
            border: none;
		}
        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--grey), gray);
        }
		.admin-table th {
			background-color: #007bff; 
			color: #ffffff;
			font-weight: bold;
			font-size: 16px;
		}
		.admin-table td {
			font-size: 14px;
		}

        .strand-title {
            text-align: center;
        }

        .strand-title h2 {
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            color: var(--dark);
            margin-top: 10px;
        }
		
		.profile-img {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			object-fit: cover;
		}
		.search-form input[type="text"] {
			padding: 8px;
			font-size: 14px;
			font-family: 'Poppins', sans-serif;
		}
		.search-form button, .search-form .clear-btn {
			padding: 8px 15px;
			font-size: 14px;
			font-family: 'Poppins', sans-serif;
		}
		.head-title h1 {
			font-size: 24px;
			font-weight: bold;
		}
		.breadcrumb li a {
			font-size: 14px;
		}
		.pagination {
			margin-top: 20px;
			text-align: center;
		}
		.pagination a {
			display: inline-block;
			padding: 8px 12px;
			margin: 0 5px;
			border: 1px solid #ddd;
			border-radius: 5px;
			text-decoration: none;
			color: #007bff;
			font-size: 14px;
		}
		.pagination a.active {
			background-color: #007bff;
			color: #ffffff;
			border-color: #007bff;
		}
		.pagination a:hover {
			background-color: #0056b3;
            color: #ffffff;
            border-color: #0056b3;
		}
		.pagination a.disabled {
			color: #ccc;
			pointer-events: none;
		}
        #status {
            background-color: #4CAF50;
            padding: 5px;
            text-align: center;
            border-radius: 10px;
            color: #f0f0f0;
        }
	</style>
	<title>Learnify - <?php echo htmlspecialchars($strandCode); ?> Students</title>
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
                    <span class="text">Enroll Student Schedule</span>
                </a>
            </li>
            <li>
                <a href="./enroll_teacher_section.php">
                    <i class='bx bxs-user-check'></i>
                    <span class="text">Assign Teacher Section</span>
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
					<h1><?php echo htmlspecialchars($strandCode); ?> Students</h1>
					<ul class="breadcrumb">
						<li>
							<a href="#">Home</a>
						</li>
						<li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a class="active" href="data_strand_students.php?strand=<?php echo urlencode($strand); ?>"><?php echo htmlspecialchars($strandCode); ?> Students</a>
						</li>
					</ul>
				</div>
				<a href="./adminDash.php" class="btn-download">
					<i class='bx bxs-left-arrow'></i>
					<span class="text">Back</span>
				</a>
			</div>

			<!-- Search Form -->
			<form method="GET" class="search-form">
				<input type="hidden" name="strand" value="<?php echo htmlspecialchars($strand); ?>">
				<input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
				<input type="text" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
				<button type="submit">Search</button>
				<?php if (!empty($search)): ?>
					<a href="data_strand_students.php?strand=<?php echo urlencode($strand); ?>&page=1" class="clear-btn">Clear</a> 
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

			<!-- Table to display students -->
			<div class="admin-table">
                <div class="strand-title">
                    <h2><?php echo htmlspecialchars($strandName); ?></h2>
                </div>
				<table>
					<thead>
						<tr>
							<th>No.</th>
							<th>Profile</th>
							<th>Name</th>
							<th>Email</th>
							<th>Contact Number</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						<?php if (count($students) > 0): ?>
							<?php foreach ($students as $index => $student): ?>
								<tr>
									<td><?php echo $offset + $index + 1; ?></td>
									<td>
										<?php if ($student['image']): ?>
											<img src="<?php echo htmlspecialchars($student['image']); ?>" alt="Profile Image" class="profile-img">
										<?php else: ?>
											<img src="./img/noprofile.png" alt="No Profile Image" class="profile-img">
										<?php endif; ?>
									</td>
									<td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
									<td><?php echo htmlspecialchars($student['email']); ?></td>
									<td><?php echo htmlspecialchars($student['contactNumber']); ?></td>
									<td class="<?php echo $student['status'] === 'Active' ? 'status-active' : ''; ?>">
										<p id="status"><?php echo htmlspecialchars($student['status']); ?></p>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="6">No students found for this strand.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Pagination -->
			<?php if ($totalPages > 1): ?>
				<div class="pagination">
					<?php
						// Previous page link
						if ($page > 1) {
							$prevPage = $page - 1;
							$prevUrl = "data_strand_students.php?strand=" . urlencode($strand) . "&page=$prevPage" . ($search ? "&search=" . urlencode($search) : "");
							echo "<a href='$prevUrl'>Previous</a>";
						} else {
							echo "<a class='disabled'>Previous</a>";
						}

						// Page number links
						for ($i = 1; $i <= $totalPages; $i++) {
							$pageUrl = "data_strand_students.php?strand=" . urlencode($strand) . "&page=$i" . ($search ? "&search=" . urlencode($search) : "");
							$activeClass = ($i == $page) ? 'active' : '';
							echo "<a href='$pageUrl' class='$activeClass'>$i</a>";
						}

						// Next page link
						if ($page < $totalPages) {
							$nextPage = $page + 1;
							$nextUrl = "data_strand_students.php?strand=" . urlencode($strand) . "&page=$nextPage" . ($search ? "&search=" . urlencode($search) : "");
							echo "<a href='$nextUrl'>Next</a>";
						} else {
							echo "<a class='disabled'>Next</a>";
						}
					?>
				</div>
			<?php endif; ?>
		</main>
		<!-- MAIN -->
	</section>
	<!-- CONTENT -->

	<script src="./utils/script.js"></script>
	<script src="script.js"></script>
</body>
</html>