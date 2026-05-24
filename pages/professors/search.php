<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';

$pageTitle = "Search Results";

if (isset($_GET['query']) && !empty($_GET['query'])) {
    $query = trim($_GET['query']);

    try {
        $sql = "SELECT userID, firstName, middleName, lastName, email, image, userType, status 
                FROM users 
                WHERE (firstName LIKE :query OR middleName LIKE :query OR lastName LIKE :query) 
                  AND archived = 0";
        $stmt = $dbConnection->prepare($sql);
        $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update the title if only one result is found
        if (count($results) === 1) {
            $user = $results[0];
            $pageTitle = htmlspecialchars($user['firstName'] . ' ' . $user['lastName']);
        }
    } catch (PDOException $e) {
        echo 'Error: ' . htmlspecialchars($e->getMessage());
    }
} else {
    echo '<p>Please enter a search query.</p>';
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
    <link rel="stylesheet" href="./utils/search.css">
	<link rel="stylesheet" href="./utils/logout.css">
	<link rel="stylesheet" href="./utils/animation_slide.css">
	<link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <style>
		.modal {
            z-index: 10000;
        }
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
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
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
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
             <li>
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
			<li class="active">
				<a href="#">
					<i class='bx bx-search'></i>
					<span class="text"><?php echo htmlspecialchars($pageTitle); ?></span>
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
			<!-- <a href="#" class="nav-link">Categories</a> -->
                <form action="search.php" method="get">
                    <div class="form-input">
                        <input type="search" name="query" placeholder="Search users..." required>
                        <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                    </div>
                </form>
			<input type="checkbox" id="switch-mode" hidden>
			<label for="switch-mode" class="switch-mode"></label>
			<!-- <a href="#" class="notification">
				<i class='bx bxs-bell' ></i>
				<span class="num">8</span>
			</a> -->
			<a href="./main_acc.php" class="profile">
				<img src="<?php echo isset($_SESSION['image']) ? htmlspecialchars($_SESSION['image']) : './img/noprofile.png'; ?>" alt="Profile Image">
				<div>
					<p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
					<small>Teacher</small>
				</div>
			</a>
            
		</nav>
		<!-- NAVBAR -->

		<!-- MAIN -->
		<main>
			<div class="head-title">
				<div class="left">
                <h1>
                    <?php
                    if (!empty($results) && count($results) === 1) {
                        // Display userType for the single searched user, replacing Professor with Teacher
                        echo htmlspecialchars($results[0]['userType'] === 'Professor' ? 'Teacher' : $results[0]['userType']);
                    } else {
                        echo "Search Results";
                    }
                    ?>
                </h1>
					<ul class="breadcrumb">
						<li>
							<a href="#">Home</a>
						</li>
						<li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a class="active" href="./search.php"><?php echo htmlspecialchars($pageTitle); ?></a>
						</li>
					</ul>
				</div>
				<a href="professor_main_dash.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
			</div>

            <div class="search-results">
                <?php
                if (!empty($results)) {
                    foreach ($results as $user) {
                        echo '<div class="user">';
                        echo '<div class="user-left">';
                        echo '<div class="user-image">';
                        echo '<img src="' . ($user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png') . '" alt="Profile Image">';
                        echo '</div>';
                        echo '<h3>' . htmlspecialchars($user['firstName'] . ' ' . $user['middleName'] . ' ' . $user['lastName']) . '</h3>';
                        echo '</div>';
                        echo '<div class="user-right">';
                        echo '<table>';
                        echo '<tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($user['email']) . '</td></tr>';
                        echo '<tr><td><strong>User Type:</strong></td><td>' . htmlspecialchars($user['userType'] === 'Professor' ? 'Teacher' : $user['userType']) . '</td></tr>';
                        $statusClass = $user['status'] === 'Active' ? 'status-active' : '';
                        echo '<tr><td><strong>Status:</strong></td><td><span class="' . $statusClass . '">' . htmlspecialchars($user['status']) . '</span></td></tr>';
                        echo '</table>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="no-users">';
                    echo '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search">';
                    echo '<circle cx="11" cy="11" r="8"></circle>';
                    echo '<line x1="21" y1="21" x2="16.65" y2="16.65"></line>';
                    echo '</svg>';
                    echo '<p>No users found for your search.</p>';
                    echo '</div>';
                }
                ?>
            </div>

		</main>
		<!-- MAIN -->
	</section>
	<!-- CONTENT -->

	<script src="./utils/script.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>