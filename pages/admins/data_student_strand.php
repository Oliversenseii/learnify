<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

if (!isset($_GET['strandName'])) {
    header("Location: data_student_strand.php"); 
    exit;
}

$strandName = isset($_GET['strandName']) ? urldecode($_GET['strandName']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';


try {
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
        ts.strandName = :strandName 
        AND ss.status = 'Enrolled' 
        AND ss.archived = 0
    ";

    if ($search !== '') {
        $query .= " AND (u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search)";
    }

    $stmt = $dbConnection->prepare($query);

    $stmt->bindParam(':strandName', $strandName, PDO::PARAM_STR);
    if ($search !== '') {
        $searchTerm = "%$search%"; 
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $dbConnection->prepare("SELECT strandCode FROM track_strands WHERE strandName = :strandName");
    $stmt->bindParam(':strandName', $strandName, PDO::PARAM_STR);
    $stmt->execute();
    $strand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $strandCode = $strand ? htmlspecialchars($strand['strandCode']) : "Unknown";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
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
	<link rel="stylesheet" href="./utils/notification.css">
	<link rel="stylesheet" href="./utils/animation_slide.css">
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
		.btn-edit, .btn-archive {
			padding: 5px 10px;
			border: none;
			border-radius: 5px;
			cursor: pointer;
			text-decoration: none;
			display: inline-block;
			margin-right: 5px;
		}

		.btn-edit {
			background-color: #008000;
			color: #ffffff;
		}

		.btn-archive {
			background-color: #a90c0c;
			color: #ffffff;
		}

		.btn-edit:hover {
			background-color: #006400;
		}

		.btn-archive:hover {
			background-color: #820a0a;
		}
		.success-notification {
			background-color: #4CAF50; 
			color: var(--light); 
			padding: 15px;
			margin-bottom: 20px;
			border-radius: 5px;
			text-align: center;
			font-weight: bold;
		}
	</style>
	<title>Learnify</title>
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
			<li class="active">
                <a href="./data_admin.php">
                    <i class='bx bxs-user'></i>
                    <span class="text">
                        <?php echo $strandCode; ?> Students
                    </span>
                </a>
            </li>
			<li>
				<a href="./registration.php">
					<i class='bx bx-user-plus'></i>
					<span class="text">Registration</span>
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
					<i class='bx bxs-cog' ></i>
					<span class="text">Settings</span>
				</a>
			</li>
            <li>
				<a href="./location.php">
					<i class='bx bxs-map'></i>
					<span class="text">Geolocation</span>
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
			<!-- <a href="#" class="nav-link">Categories</a> -->
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
					<h1>  <?php echo $strandCode; ?> Students</h1>
					<ul class="breadcrumb">
						<li>
							<a href="#">Home</a>
						</li>
						<li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a class="active" href="data_student_strand.php?strandName=<?php echo urlencode($strandName); ?>">  <?php echo $strandCode; ?> Students</a>
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
                <input type="hidden" name="strandName" value="<?php echo htmlspecialchars($strandName); ?>">
                <input type="text" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="data_student_strand.php?strandName=<?php echo urlencode($strandName); ?>" class="clear-btn">Clear</a> 
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

			<!-- Table to display administrators -->
			<div class="admin-table">
				
    <table>
        <thead>
            <tr>
                <th>Profile</th>
                <th>Name</th>
                <th>Email</th>
                <th>Contact Number</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
							<?php if ($student['image']): ?>
								<img src="<?php echo $student['image']; ?>" alt="Profile Image" class="profile-img">
						    <?php else: ?>
								<img src="./img/noprofile.png" alt="No Profile Image" class="profile-img">
							<?php endif; ?>
						</td>
                        <td><?php echo $student['firstName'] . ' ' . $student['lastName']; ?></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                        <td><?php echo htmlspecialchars($student['contactNumber']); ?></td>
                        <td><?php echo htmlspecialchars($student['status']); ?></td>
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
			
		</main>
		<!-- MAIN -->
	</section>
	<!-- CONTENT -->

	<script src="./utils/script.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>