<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

if (!isset($_GET['subjectID']) || empty($_GET['subjectID'])) {
    die("Invalid request.");
}

$subjectID = $_GET['subjectID'];

// Fetch subject details
$sql = "SELECT subjectID, subjectName, description, yearLevel, semester, subjectCode, subjectType FROM subjects WHERE subjectID = :subjectID";
$stmt = $dbConnection->prepare($sql);
$stmt->bindParam(':subjectID', $subjectID, PDO::PARAM_INT);
$stmt->execute();
$subject = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subject) {
    die("Subject not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectName = trim($_POST['subjectName']);
    $description = trim($_POST['description']);
    $yearLevel = trim($_POST['yearLevel']);
    $semester = trim($_POST['semester']);
    $subjectCode = trim($_POST['subjectCode']);
    $subjectType = trim($_POST['subjectType']);

    $updateSql = "UPDATE subjects SET subjectName = :subjectName, description = :description, yearLevel = :yearLevel, semester = :semester, subjectCode = :subjectCode, subjectType = :subjectType WHERE subjectID = :subjectID";
    $updateStmt = $dbConnection->prepare($updateSql);
    $updateStmt->bindParam(':subjectName', $subjectName);
    $updateStmt->bindParam(':description', $description);
    $updateStmt->bindParam(':yearLevel', $yearLevel);
    $updateStmt->bindParam(':semester', $semester);
    $updateStmt->bindParam(':subjectCode', $subjectCode);
    $updateStmt->bindParam(':subjectType', $subjectType);
    $updateStmt->bindParam(':subjectID', $subjectID, PDO::PARAM_INT);
    
    if ($updateStmt->execute()) {
        $_SESSION['success_message'] = "Subject updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating subject. Please try again.";
    }

	$updateStmt->closeCursor();
    header('Location: data_subjects.php');
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
	<link rel="stylesheet" href="./utils/edit_data.css">
	<link rel="stylesheet" href="./utils/animation_slide.css">
	<link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <style>
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
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .edit-form .form-group input,
        .edit-form .form-group select,
        .edit-form .form-group textarea {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            font-size: 16px;
            color: var(--dark);
            background-color: var(--grey);
            transition: border-color 0.3s ease;
        }
        .edit-form .form-group input:focus,
        .edit-form .form-group textarea:focus,
        .edit-form .form-group select:focus {
            border-color: #007bff;
            outline: none;
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
				<a href="./enroll_prof_sub.php">
                    <i class='bx bxs-user-check'></i>
					<span class="text">Assign Teacher Subject</span>
				</a>
			</li>
			<li>
				<a href="./section.php">
					<i class='bx bx-book-content'></i>
					<span class="text">Section</span>
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
			<!-- <a href="#" class="nav-link">Categories</a> -->
                <form action="search.php" method="get">
                    <div class="form-input">
                        <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required>
                        <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                    </div>
                </form>
			<input type="checkbox" id="switch-mode" hidden>
			<label for="switch-mode" class="switch-mode"></label>
			<!-- <a href="#" class="notification" id="newEnrollmentsLink">
				<i class='bx bxs-bell'></i>
				<span class="num"><?php echo $newEnrollmentsCount; ?></span>
			</a> -->
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
					<h1>Edit Subjects</h1>
					<ul class="breadcrumb">
						<li>
							<a href="#">Home</a>
						</li>
						<li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a href="edit_admin.php">Subjects</a>
						</li>
                        <li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a class="active" href="edit_subjects.php?subjectID=<?php echo $subject['subjectID']; ?>">Edit</a>
						</li>
					</ul>
				</div>
				<a href="./data_sections.php" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
					<span class="text">Back</span>
				</a>
			</div>

            <form method="POST" class="edit-form">
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" name="subjectCode" value="<?php echo htmlspecialchars($subject['subjectCode']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="subjectName" value="<?php echo htmlspecialchars($subject['subjectName']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" required><?php echo htmlspecialchars($subject['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Subject Type</label>
                    <select name="subjectType" required>
                        <option value="Core Subject" <?php echo ($subject['subjectType'] == "Core Subject") ? "selected" : ""; ?>>Core Subject</option>
                        <option value="Applied Subject" <?php echo ($subject['subjectType'] == "Applied Subject") ? "selected" : ""; ?>>Applied Subject</option>
                        <option value="Specialized Subject" <?php echo ($subject['subjectType'] == "Specialized Subject") ? "selected" : ""; ?>>Specialized Subject</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="yearLevel" required>
                        <option value="Grade 11" <?php echo ($subject['yearLevel'] == "Grade 11") ? "selected" : ""; ?>>Grade 11</option>
                        <option value="Grade 12" <?php echo ($subject['yearLevel'] == "Grade 12") ? "selected" : ""; ?>>Grade 12</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" required>
                        <option value="1st Sem" <?= ($subject['semester'] === "1st Sem") ? "selected" : ""; ?>>1st sem</option>
                        <option value="2nd Sem" <?= ($subject['semester'] === "2nd Sem") ? "selected" : ""; ?>>2nd sem</option>
                    </select>
                </div>

                <button type="submit" class="btn-save">Save Changes</button>
            </form>
		</main>
		<!-- MAIN -->
	</section>
	<!-- CONTENT -->

	<script src="./utils/script.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>