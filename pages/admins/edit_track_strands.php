<?php
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}

require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Self reload
$currentPage = basename($_SERVER['PHP_SELF']); 

if (isset($_GET['id'])) {
    $strandID = $_GET['id'];
    
    $sql = "SELECT * FROM track_strands WHERE strandID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $strandID);
    $stmt->execute();
    $strand = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$strand) {
        $_SESSION['error_message'] = "Strand not found.";
        header("Location: track-strands.php");
        exit;
    }
}

if (isset($_POST['updateStrand'])) {
    $strandCode = $_POST['strandCode'];
    $strandName = $_POST['strandName'];
    $trackName = $_POST['trackName'];

    $sql = "UPDATE track_strands SET strandCode = ?, strandName = ?, trackName = ? WHERE strandID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $strandCode);
    $stmt->bindParam(2, $strandName);
    $stmt->bindParam(3, $trackName);
    $stmt->bindParam(4, $strandID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Track and Strand updated successfully!";
        $sql = "SELECT * FROM track_strands WHERE strandID = ?";
        $stmt = $dbConnection->prepare($sql);
        $stmt->bindParam(1, $strandID);
        $stmt->execute();
        $strand = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $_SESSION['error_message'] = "Error updating strand. Please try again.";
    }
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
    <link rel="stylesheet" href="./utils/edit_track_strand.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify</title>
    <style>
        .main-container {
            width: 50%;
        }
        .main-container h2 {
            margin-bottom: 20px;
			border-bottom: 1px solid #ccc;
			padding-bottom: 10px;
            font-size: clamp(1.9rem, 3vw, 2rem);
            color: var(--dark);
        }
        @media screen and (max-width: 768px) {
            .main-container {
                width: 90%;
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
        label {
            font-weight: 600;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        .success-notification,
        .error-notification {
            max-width: 500px;
            margin: 10px auto;
            padding: 15px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            color: white;
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
            outline: none;
        }

        .submit-btn {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
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
                    <h1>Edit Track & Strand</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./track-strands.php">Data</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="edit_track_strands.php?id=<?php echo $strandID; ?>" class="<?php echo ($currentPage == 'edit_track_strands.php') ? 'active' : ''; ?>">Edit</a></li>
                    </ul>
                </div>
                <a href="./data_track_strands.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Data
                </a>
            </div>

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

            <!-- Main Container for Form -->
            <div class="main-container">
                <h2>Edit Track and Strand</h2>
                <form action="edit_track_strands.php?id=<?php echo $strand['strandID']; ?>" method="POST">
                    
                    <div class="form-group">
                        <label for="trackName">Track Name</label>
                        <select name="trackName" id="trackName" required>
                            <option value="" disabled>Select Track</option>
                            <option value="Academic" <?php echo $strand['trackName'] == 'Academic' ? 'selected' : ''; ?>>Academic</option>
                            <option value="TVL" <?php echo $strand['trackName'] == 'TVL' ? 'selected' : ''; ?>>TVL</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="strandCode">Strand Code</label>
                        <input type="text" name="strandCode" id="strandCode" value="<?php echo htmlspecialchars($strand['strandCode']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="strandName">Strand Name</label>
                        <input type="text" name="strandName" id="strandName" value="<?php echo htmlspecialchars($strand['strandName']); ?>" required>
                    </div>

                    <button type="submit" name="updateStrand" class="submit-btn">Save</button>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->
    
    <script src="./utils/script.js"></script>
</body>
</html>