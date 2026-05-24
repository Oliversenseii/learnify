<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

// Validate and sanitize inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sex = isset($_GET['sex']) ? strtolower(trim($_GET['sex'])) : '';
$age_group = isset($_GET['age_group']) ? trim($_GET['age_group']) : '';
$valid_sex = in_array($sex, ['male', 'female']) ? $sex : null;
$valid_age_groups = ['0-17', '18-24', '25-34', '35-44', '45+'];
$valid_age_group = in_array($age_group, $valid_age_groups) ? $age_group : null;

// Encryption function for userID
function encryptID($id) {
    $key = "b7e1a8f9c3d4e2b69a7f5d8e1c4b3a61";
    return urlencode(base64_encode(openssl_encrypt($id, 'AES-128-ECB', $key, 0, "")));
}

// Build SQL query
$sql = "SELECT userID, firstName, lastName, email, contactNumber, status, birthday, image 
        FROM users 
        WHERE userType = 'Student' 
        AND userType != 'SuperAdmin' 
        AND archived = 0";

$params = [];
if ($valid_sex) {
    $sql .= " AND sex = :sex";
    $params[':sex'] = $valid_sex;
}
if ($valid_age_group) {
    $sql .= " AND CASE 
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) < 18 THEN '0-17'
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) BETWEEN 18 AND 24 THEN '18-24'
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) BETWEEN 25 AND 34 THEN '25-34'
                    WHEN FLOOR(DATEDIFF(CURDATE(), birthday)/365) BETWEEN 35 AND 44 THEN '35-44'
                    ELSE '45+'
                END = :age_group";
    $params[':age_group'] = $valid_age_group;
}
if ($search !== '') {
    $sql .= " AND (firstName LIKE :search OR lastName LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}

try {
    $stmt = $dbConnection->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while fetching data: " . htmlspecialchars($e->getMessage());
    $students = [];
}

// Calculate age function
function calculateAge($birthday) {
    if (!$birthday) return 'N/A';
    $birthDate = new DateTime($birthday);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
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
    <script src="./logout.js" defer></script>
    <style>
        :root {
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
        }
        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
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
        .success-notification, .error-notification, .filter-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 15px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            margin-bottom: 20px;
            margin-top: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        .filter-notification {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        .admin-table {
            width: 90%;
            overflow-x: auto;
            margin-bottom: 20px;
        }
        .admin-table td {
            border: none;
        }
        .admin-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease;
        }
        .admin-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }
        .search-form {
            margin-bottom: 20px;
        }
        .search-form input[type="text"] {
            padding: 8px;
            width: 250px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
         .search-form select {
            padding: 8px;
            width: 150px;
            border-radius: 5px;
            border: 1px solid #ccc;
            color: var(--dark);
            background-color: var(--grey);
            font-size: clamp(1.1rem, 2.5vw, 1.2rem);
            margin-left: 5px;
        }

        .search-form select:focus {
            border: 1px solid #007bff;
        }
        .search-form button, .search-form .clear-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 5px;
        }
        .search-form button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        .search-form .clear-btn {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            text-decoration: none;
        }
        .search-form button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        .search-form .clear-btn:hover {
            background: linear-gradient(135deg, #5a6268, #4b5357);
        }
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
            .search-form input[type="text"], .search-form select {
                width: 100%;
                margin-bottom: 10px;
            }
            .search-form button, .search-form .clear-btn {
                width: 100%;
                margin-left: 0;
                margin-bottom: 10px;
            }
        }
        .status-active {
            background-color: #4CAF50;
            padding: 5px;
            text-align: center;
            border-radius: 10px;
            color: #f0f0f0;
        }
        .status-inactive {
            background-color: #dc3545;
            padding: 5px;
            text-align: center;
            border-radius: 10px;
            color: #f0f0f0;
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
                    <i class='bx bxs-dashboard'></i>
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
                    <i class='bx bxs-data'></i>
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
                <img src="<?php echo isset($_SESSION['image']) ? htmlspecialchars($_SESSION['image']) : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Students<?php echo ($valid_sex || $valid_age_group) ? ' (' . ($valid_sex ? ucfirst($valid_sex) : '') . ($valid_sex && $valid_age_group ? ', ' : '') . ($valid_age_group ? 'Age ' . $valid_age_group : '') . ')' : ''; ?></h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="data_student.php">Students</a></li>
                    </ul>
                </div>
                <a href="./superAdminDash.php" class="back-button"><i class='bx bx-arrow-back'></i> Back to Dashboard</a>
            </div>

            <!-- Filter Notification -->
            <?php if ($valid_sex || $valid_age_group): ?>
                <div class="filter-notification">
                    Showing <?php echo $valid_sex ? ucfirst($valid_sex) : ''; ?>
                    <?php echo ($valid_sex && $valid_age_group) ? ' and ' : ''; ?>
                    <?php echo $valid_age_group ? 'Age ' . $valid_age_group : ''; ?> Students
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Search Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                <select name="age_group">
                    <option value="">All Ages</option>
                    <?php foreach ($valid_age_groups as $group): ?>
                        <option value="<?php echo htmlspecialchars($group); ?>" <?php echo $valid_age_group === $group ? 'selected' : ''; ?>><?php echo htmlspecialchars($group); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Search</button>
                <?php if (!empty($search) || $valid_sex || $valid_age_group): ?>
                    <a href="data_student.php" class="clear-btn">Clear</a>
                <?php endif; ?>
                <?php if ($valid_sex): ?>
                    <input type="hidden" name="sex" value="<?php echo htmlspecialchars($valid_sex); ?>">
                <?php endif; ?>
            </form>

            <!-- Table to display students -->
            <div class="admin-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Profile Image</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Age</th>
                            <th>Status</th>
                            <!-- <th>Actions</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo $counter; ?></td>
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
                                    <td><?php echo calculateAge($student['birthday']); ?></td>
                                    <td>
                                        <p class="<?php echo strtolower($student['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo htmlspecialchars($student['status']); ?>
                                        </p>
                                    </td>
                                    <!-- <td>
                                        <a href="edit_student.php?userID=<?php echo encryptID($student['userID']); ?>" class="btn-edit">
                                            <i class='bx bxs-edit'></i> Edit
                                        </a>
                                        <a href="archive_student.php?userID=<?php echo encryptID($student['userID']); ?>" class="btn-archive" onclick="return confirm('Are you sure you want to archive this student?');">
                                            <i class='bx bxs-archive-in'></i> Archive
                                        </a>
                                    </td> -->
                                </tr>
                                <?php $counter++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    No <?php echo $valid_sex ? strtolower($valid_sex) : ''; ?>
                                    <?php echo $valid_age_group ? 'age ' . $valid_age_group : 'students'; ?>
                                    <?php echo $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?>
                                    found.
                                </td>
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