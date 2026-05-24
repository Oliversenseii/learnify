<?php 
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT strandID, strandCode, strandName, trackName, description
        FROM track_strands
        WHERE archived = 0";

if ($search !== '') {
    $sql .= " AND (strandName LIKE :search OR trackName LIKE :search OR strandCode LIKE :search)";
}

$stmt = $dbConnection->prepare($sql);

if ($search !== '') {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$stmt->execute();
$tracksStrands = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
	<style>
		@media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
		.btn-edit, .btn-archive {
			padding: 5px 10px;
			border: none;
			border-radius: 5px;
			cursor: pointer;
			margin-bottom: 10px;
			text-decoration: none;
			display: inline-block;
			margin-right: 5px;
		}

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

		.admin-table {
            width: 90%;
            overflow-x: auto;
            margin-bottom: 20px;
        }

		.admin-table td {
			padding: 20px;
			border: none;
		}

		.admin-table tr:nth-child(even) {
			background-color: var(--grey);
            border: none;
		}
        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--grey), gray);
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
	<title>Learnify</title>
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
					<h1>Track & Strands</h1>
					<ul class="breadcrumb">
						<li>
							<a href="#">Home</a>
						</li>
						<li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a class="active" href="data_track_strands.php">Track & Strands</a>
						</li>
					</ul>
				</div>
				<a href="./superAdminDash.php" class="back-button"><i class='bx bx-arrow-back'></i> Back to Dashboard</a>
			</div>

            <!-- Search Form -->
			<form method="GET" class="search-form">
				<input type="text" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
				<button type="submit">Search</button>
				<?php if (!empty($search)): ?>
					<a href="data_track_strands.php" class="clear-btn">Clear</a> 
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

			<!-- Table to display Tracks and Strands -->
            <div class="admin-table">
                <table>
                    <thead>
                        <tr>
							<th>#</th>
                            <th>Track Name</th>
                            <th>Strand Code</th>
                            <th>Strand Name</th>
                            <th>Description</th>
                            <!-- <th>Actions</th>  -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tracksStrands)): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($tracksStrands as $trackStrand): ?>
                                <tr>
                                    <td><?php echo $counter; ?></td>
                                    <td><?php echo $trackStrand['trackName']; ?></td>
                                    <td><?php echo $trackStrand['strandCode']; ?></td>
                                    <td><?php echo $trackStrand['strandName']; ?></td>
                                   	<td><?php echo $trackStrand['description']; ?></td>
                                    <!-- <td>
                                        <a href="edit_track_strands.php?strandID=<?php echo $trackStrand['strandID']; ?>" class="btn-edit">
                                            <i class='bx bxs-edit'></i> Edit
                                        </a>
                                        <a href="archive_track_strands.php?strandID=<?php echo $trackStrand['strandID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this strand?');">
                                            <i class='bx bxs-archive-in'></i> Archive
                                        </a>
                                    </td> -->
                                </tr>
                            <?php $counter++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No results found.</td>
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
	<script src="script.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>