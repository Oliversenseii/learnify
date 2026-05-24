<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT sectionID, sectionName, gradeLevel, semester FROM `sections` WHERE `archived` = 0";

if (!empty($search)) {
    $query .= " AND (`sectionName` LIKE :search)";
}

$stmt = $dbConnection->prepare($query);
if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%');
}
$stmt->execute();

$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Learnify Sections</title>
    <style>
        .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px;
        }

        .search-form input[type="text"] {
            padding: 10px;
            font-size: 1.2rem;
            border: 1px solid #ccc;
            background-color: var(--grey);
            border-radius: 5px;
            color: var(--dark) !important;
            outline: none;
            width: 250px;
            transition: 0.3s;
        }

        .search-form input[type="text"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }

        .search-form button {
            padding: 10px 15px;
            font-size: 1.2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
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
            text-decoration: none;
            display: inline-block;
            padding: 10px 15px;
            border-radius: 5px;
            transition: 0.3s;
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
            font-size: 1rem;
            min-width: 500px;
        }

        .admin-table tr {
            font-size: 1.2rem !important;
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

        .admin-table td {
            color: var(--dark);
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

        .error-notification {
            background-color: #dc3545;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
                margin: 10px;
                width: 100%;
            }

            .search-form input[type="text"],
            .search-form button,
            .search-form .clear-btn {
                width: 100%;
                min-width: unset;
                margin-bottom: 10px;
            }

            .admin-table {
                margin: 10px;
                padding: 5px;
                width: 100%;
            }

            .admin-table table {
                font-size: 0.9rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 8px 10px;
                white-space: nowrap;
            }

            .btn-edit, .btn-archive {
                font-size: 0.8rem;
                padding: 4px 8px;
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

            .btn-edit, .btn-archive {
                font-size: 0.75rem;
                padding: 3px 6px;
                margin-right: 3px;
            }
        }
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
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
                    <h1>Sections</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Home</a>
                        </li>
                        <li><i class='bx bx-chevron-right' ></i></li>
                        <li>
                            <a class="active" href="data_sections.php">Sections</a>
                        </li>
                    </ul>
                </div>
                <a href="./superAdminDash.php" class="back-button"><i class='bx bx-arrow-back'></i> Back to Dashboard</a>
            </div>

            <!-- Search Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by section name" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="data_sections.php" class="clear-btn">Clear</a>
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
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Table to display Sections -->
            <div class="admin-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Section Name</th>
                            <th>Grade Level</th>
                            <!-- <th>Actions</th> -->
                        </tr>
                    </thead>
                     <tbody>
                        <?php if (empty($sections)): ?>
                            <tr>
                                <td colspan="5">No results found.</td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($sections as $section): ?>
                                <tr>
                                    <td><?php echo $counter; ?></td>
                                    <td><?php echo htmlspecialchars($section['sectionName']); ?></td>
                                    <td><?php echo htmlspecialchars($section['gradeLevel']); ?></td>
                                    <!-- <td>
                                        <a href="edit_section.php?id=<?php echo $section['sectionID']; ?>" class="btn-edit">
                                            <i class='bx bxs-edit'></i> Edit
                                        </a>
                                        <a href="archive_section.php?sectionID=<?php echo $section['sectionID']; ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this section?');">
                                            <i class='bx bxs-archive-in'></i> Archive
                                        </a>
                                    </td> -->
                                </tr>
                            <?php $counter++; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>