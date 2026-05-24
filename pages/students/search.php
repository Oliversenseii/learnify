<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';
require_once './check_enrollment_status.php';

$pageTitle = "Search Results";

if (isset($_GET['query']) && !empty($_GET['query'])) {
    $query = trim($_GET['query']);

    $redirectMap = [
        'version' => 'version.php',
        'dashboard' => 'studentDash.php',
        'class' => 'studentDash.php',
        'announcements' => 'announcements.php',
        'calendar' => 'calendar.php',
        'game' => 'game.php',
        'setting' => 'settings.php',
        'profile' => ' main_acc.php',
        'edit' => 'userAcc.php',
        'password' => 'update_password.php' 
    ];

    $queryLower = strtolower($query);
    if (array_key_exists($queryLower, $redirectMap)) {
        header("Location: " . $redirectMap[$queryLower]);
        exit();
    }

    try {
        $sql = "SELECT userID, firstName, middleName, lastName, email, image, userType, status 
                FROM users 
                WHERE (firstName LIKE :query OR middleName LIKE :query OR lastName LIKE :query) 
                  AND archived = 0 
                  AND userType != 'SuperAdmin'
                  AND userType != 'Admin'";
        $stmt = $dbConnection->prepare($sql);
        $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

require_once './get_notification_count.php';

$userID = isset($_SESSION['userID']) ? filter_var($_SESSION['userID'], FILTER_VALIDATE_INT) : null;
$unreadCount = $userID ? getUnreadAnnouncementCount($dbConnection, $userID) : 0;

if (!isset($_SESSION['announcements_viewed'])) {
    $_SESSION['announcements_viewed'] = false;
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
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <style>
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --light-blue: #CFE8FF;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --transition: all 0.3s ease;
            --white: #FFFFFF;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .search-results {
            display: flex;
            flex-direction: column;
            gap: clamp(10px, 2vw, 15px);
            margin-top: clamp(10px, 2vw, 15px);
            background-color: var(--light);
            border-radius: 10px;
            padding: clamp(10px, 2vw, 15px);
            box-sizing: border-box;
            width: 100%;
        }

        .search-results:hover {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .user {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: clamp(10px, 2vw, 15px);
            padding: clamp(10px, 2vw, 12px);
            border-radius: 10px;
            background-color: transparent;
            box-shadow: none;
            transition: transform 0.3s ease;
            border-bottom: 2px solid var(--dark-grey);
        }

        .user-left {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        .user-image img {
            width: clamp(100px, 25vw, 150px);
            height: clamp(100px, 25vw, 150px);
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid transparent;
            margin-bottom: clamp(6px, 1.5vw, 8px);
        }

        .full-name {
            text-transform: uppercase !important;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .user-right table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-right td {
            padding: clamp(3px, 1vw, 4px);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            word-break: break-word;
        }

        .user-right td:first-child {
            font-weight: 600;
            color: var(--dark);
        }

        .user-right td:last-child {
            color: var(--dark-grey);
        }

        .status-active {
            background: #10b981;
            color: white !important;
            padding: clamp(3px, 1vw, 5px);
            width: clamp(60px, 15vw, 80px);
            border-radius: 10px;
            display: inline-block;
            text-align: center;
            vertical-align: middle;
        }

        .no-users {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: clamp(15px, 3vw, 20px);
        }

        .no-users svg {
            fill: none;
            stroke: linear-gradient(135deg, #dc3545, #c82333);
            margin-bottom: clamp(6px, 1.5vw, 8px);
            width: clamp(30px, 8vw, 40px);
            height: clamp(30px, 8vw, 40px);
        }

        .no-users p {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            padding: clamp(6px, 1.5vw, 8px);
            color: var(--dark);
            border-radius: 10px;
            margin-top: clamp(6px, 1.5vw, 8px);
        }

        .notification-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border-radius: 50%;
            padding: clamp(0.2rem, 0.8vw, 0.3rem) clamp(0.4rem, 1vw, 0.5rem);
            font-size: clamp(0.7rem, 1.5vw, 0.8rem);
            margin-left: clamp(0.3rem, 1vw, 0.5rem);
            display: inline-block;
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
        

        @media screen and (max-width: 768px) {
            .search-results {
                padding: clamp(8px, 2vw, 10px);
                margin-top: clamp(8px, 2vw, 10px);
            }

            .user {
                grid-template-columns: 1fr;
                gap: clamp(8px, 2vw, 10px);
                padding: clamp(8px, 2vw, 10px);
            }

            .user-left,
            .user-right td:nth-child(2) {
                flex-direction: row;
                gap: clamp(8px, 2vw, 10px);
                align-items: center;
                justify-content: flex-start;
            }

        }

        @media screen and (max-width: 480px) {
            .user-left {
                flex-direction: column;
                justify-content: center;
                align-items: center;
                text-align: center;
            }
            .full-name {
                white-space: nowrap; 
                overflow: hidden; 
                text-overflow: ellipsis;
                max-width: 50%; 
            }
            table tr {
                display: flex;
                flex-direction: column;
            }
        }

        /* Pending Tasks Badge in Header */
        .dashboard-header-badge {
            background: #ED8936;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.55rem;
            font-size: 0.9rem;
            font-weight: 600;
            min-width: 15px;
            text-align: center;
            margin-left: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

	<!-- SIDEBAR -->
	<section id="sidebar">
		<?php require_once './brand.php' ?>
        
		<ul class="side-menu top">
            <?php require_once './dashboard_nav_item.php' ?>
            <li>
                <a href="./announcements.php?viewed=true">
                    <i class='bx bxs-bell'></i>
                    <span class="text">Announcements</span>
                </a>
            </li>
			<li class="active">
				<a href="#">
					<i class='bx bx-search'></i>
					<span class="text"><?php echo htmlspecialchars($pageTitle); ?></span>
				</a>
			</li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li>
                <a href="./game.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Games</span>
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
                        <input type="search" name="query" placeholder="Search Teachers and Students" required>
                        <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                    </div>
                </form>
			<input type="checkbox" id="switch-mode" hidden>
			<label for="switch-mode" class="switch-mode"></label>
			<a href="./main_acc.php" class="profile">
				<img src="<?php echo isset($_SESSION['image']) ? htmlspecialchars($_SESSION['image']) : './img/noprofile.png'; ?>" alt="Profile Image">
				<div>
					<p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
					<small><?php echo htmlspecialchars($_SESSION['userType'] === 'Professor' ? 'Teacher' : $_SESSION['userType']); ?></small>
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
							<a class="active" href="#"><?php echo htmlspecialchars($pageTitle); ?></a>
						</li>
					</ul>
				</div>
				<a href="./studentDash.php" class="back-button">
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
                        echo '<h3 class="full-name">' . htmlspecialchars($user['lastName'] . ', ' . $user['firstName'] . ' ' . $user['middleName']) . '</h3>';
                        echo '</div>';
                        echo '<div class="user-right">';
                        echo '<table>';
                        echo '<tr class"email"><td><strong>Email:</strong></td><td>' . htmlspecialchars($user['email']) . '</td></tr>';
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