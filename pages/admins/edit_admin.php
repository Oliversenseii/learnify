<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Check if userID is provided in the URL
if (!isset($_GET['userID'])) {
    header('Location: data_admin.php');
    exit();
}

function decryptID($encryptedID) {
    $key = "b7e1a8f9c3d4e2b69a7f5d8e1c4b3a62"; 
    return openssl_decrypt(base64_decode(urldecode($encryptedID)), 'AES-128-ECB', $key, 0, "");
}

$userID = decryptID($_GET['userID']);


// Fetch admin details from the database
$sql = "SELECT userID, firstName, lastName, email, contactNumber, status, birthday, image 
        FROM users 
        WHERE userID = :userID";
$stmt = $dbConnection->prepare($sql);
$stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header('Location: data_admin.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $email = $_POST['email'];
    $contactNumber = $_POST['contactNumber'];
    $status = $_POST['status'];
    $birthday = $_POST['birthday'];

    // Update admin details in the database
    $updateSql = "UPDATE users 
                  SET firstName = :firstName, lastName = :lastName, email = :email, 
                      contactNumber = :contactNumber, status = :status, birthday = :birthday 
                  WHERE userID = :userID";
    $updateStmt = $dbConnection->prepare($updateSql);
    $updateStmt->bindParam(':firstName', $firstName, PDO::PARAM_STR);
    $updateStmt->bindParam(':lastName', $lastName, PDO::PARAM_STR);
    $updateStmt->bindParam(':email', $email, PDO::PARAM_STR);
    $updateStmt->bindParam(':contactNumber', $contactNumber, PDO::PARAM_STR);
    $updateStmt->bindParam(':status', $status, PDO::PARAM_STR);
    $updateStmt->bindParam(':birthday', $birthday, PDO::PARAM_STR);
    $updateStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $updateStmt->execute();

	if ($updateStmt->execute()) {
        $_SESSION['success_message'] = "Admin user updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating admin user. Please try again.";
    }

	$updateStmt->closeCursor();
    header('Location: data_admin.php');
    exit();
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
					<h1><?php echo htmlspecialchars($admin['lastName']); ?>'s Data</h1>
					<ul class="breadcrumb">
						<li>
							<a href="#">Home</a>
						</li>
						<li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a href="#">Administrators</a>
						</li>
						<li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a class="active" href="edit_admin.php?userID=<?php echo $admin['userID']; ?>">Edit</a>
						</li>
					</ul>
				</div>
				<a href="./data_admin.php" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
					<span class="text">Back to data</span>
				</a>
			</div>

			  <!-- Edit Form -->
			  <form method="POST" class="edit-form">
                <div class="form-group">
                    <label for="firstName">First Name</label>
                    <input type="text" name="firstName" value="<?php echo htmlspecialchars($admin['firstName']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name</label>
                    <input type="text" name="lastName" value="<?php echo htmlspecialchars($admin['lastName']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="contactNumber">Contact Number</label>
                    <input type="text" name="contactNumber" value="<?php echo htmlspecialchars($admin['contactNumber']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" required>
                        <option value="Active" <?php echo $admin['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $admin['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="birthday">Birthday</label>
                    <input type="date" name="birthday" value="<?php echo htmlspecialchars($admin['birthday']); ?>" required>
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