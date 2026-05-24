<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

$errors = [];

if (isset($_POST['registerSubject'])) {
    $subjectCode = trim($_POST['subjectCode'] ?? '');
    $subjectName = trim($_POST['subjectName'] ?? '');
    $subjectType = $_POST['subjectType'] ?? '';
    $yearLevel = $_POST['yearLevel'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $strandID = $_POST['strandID'] ?? '';

    // Validation
    if (empty($subjectCode)) {
        $errors['subjectCode'] = "⚠️ Subject Code is required.";
    }
    if (empty($subjectName)) {
        $errors['subjectName'] = "⚠️ Subject Name is required.";
    }
    if (empty($subjectType)) {
        $errors['subjectType'] = "⚠️ Subject Type is required.";
    }
    if (empty($yearLevel)) {
        $errors['yearLevel'] = "⚠️ Year Level is required.";
    }
    if (empty($semester)) {
        $errors['semester'] = "⚠️ Semester is required.";
    }
    if (empty($strandID)) {
        $errors['strandID'] = "⚠️ Track and Strand are required.";
    }

    // Check for duplicate subject code
    if (empty($errors)) {
        $checkSQL = "SELECT * FROM subjects WHERE subjectCode = ? AND archived = 0";
        $checkStmt = $dbConnection->prepare($checkSQL);
        $checkStmt->bindParam(1, $subjectCode);
        $checkStmt->execute();
        if ($checkStmt->rowCount() > 0) {
            $errors['subjectCode'] = "⚠️ Subject Code already exists.";
        }
    }

    if (empty($errors)) {
        $sql = "INSERT INTO subjects (subjectCode, subjectName, subjectType, yearLevel, semester, strandID, dateCreated) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $dbConnection->prepare($sql);
        $stmt->bindParam(1, $subjectCode);
        $stmt->bindParam(2, $subjectName);
        $stmt->bindParam(3, $subjectType);
        $stmt->bindParam(4, $yearLevel);
        $stmt->bindParam(5, $semester);
        $stmt->bindParam(6, $strandID);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Subject registered successfully!";
            header("Location: subjects.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Error registering subject. Please try again.";
        }

        $stmt->closeCursor();
    }
}

// Fetch track-strands for dropdown
$strandSQL = "SELECT strandID, trackName, strandName FROM track_strands WHERE archived = 0";
$strandStmt = $dbConnection->prepare($strandSQL);
$strandStmt->execute();
$strands = $strandStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="./utils/modules/kdakwd.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify</title>
    <style>
         :root {
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --transition: all 0.3s ease;
            --white: #FFFFFF;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
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
        .register-section {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .register-section h2 {
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: clamp(1.9rem, 3vw, 2rem);
            color: var(--dark);
            text-align: left;
        }

        .register-section form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .register-section label {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            font-weight: 600;
            flex: 0 0 200px;
            text-align: left;
            
        }

        .register-section input,
        .register-section select {
            padding: 10px;
            background-color: var(--grey);
            color: var(--dark);
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            flex: 1;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

         .register-section select {
            width: fit-content;
         }

        .register-section input:focus,
        .register-section select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
            outline: none;
        }

        .register-section button {
            padding: 10px;
            font-size: clamp(1.1rem, 3vw, 1.2rem) !important;
            border: none;
            border-radius: 5px;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        .register-section button:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .register-section label span {
            color: #c82333;
        }

        .success-notification,
        .error-notification {
            max-width: 500px;
            margin: 10px auto;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            padding: 15px;
            width: fit-content;
            margin: 0 auto;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .success-notification {
            background: linear-gradient(135deg, #218838, #1e7e34);
            color: white;
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .error-text {
            color: #dc3545;
            font-size: 0.9rem;
            text-align: left;
            margin-top: -10px;
            margin-bottom: 10px;
            margin-left: 160px;
        }

        .form-group label .bx {
            background-color: #007bff;
            padding: 5px;
            color: white;
            font-size: clamp(1.2rem, 3vw, 1.2rem);
            margin-right: 5px;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .register-section {
                max-width: 90%;
                margin: 10px auto;
                padding: 15px;
            }

            .register-section h2 {
                font-size: 1.3rem;
            }

            .register-section label {
                font-size: 0.9rem;
                flex: 0 0 120px;
            }

            .register-section input,
            .register-section select,
            .register-section button {
                font-size: 0.9rem;
                padding: 8px;
            }

            .error-text {
                margin-left: 130px;
            }
        }

        @media (max-width: 480px) {
            .register-section {
                max-width: 95%;
                padding: 10px;
            }

            .register-section h2 {
                font-size: 1.2rem;
            }

            .form-group {
                flex-direction: column;
                align-items: flex-start;
            }

            .register-section label {
                font-size: 0.9rem;
                flex: none;
                width: 100%;
            }

            .register-section input,
            .register-section select {
                font-size: 0.8rem;
                padding: 7px;
                width: 100%;
            }

            .register-section button {
                font-size: 0.8rem;
                padding: 7px;
            }

            .success-notification,
            .error-notification {
                max-width: 95%;
                padding: 10px;
                font-size: 0.9rem;
            }

            .error-text {
                margin-left: 0;
            }
        }

        .back-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.3rem);
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
            font-size: clamp(1rem, 3vw, 1.3rem);
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
                    <h1>Subjects</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./subjects.php">Subjects</a></li>
                    </ul>
                </div>
                <a href="./data_subjects.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Subjects
                </a>
            </div>

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
            <?php if (!empty($errors)): ?>
                <div class="error-notification">
                    <?php foreach ($errors as $error): ?>
                        <div class="error-text"><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="register-section">
                <h2>Register Subject</h2>
                <form action="subjects.php" method="POST">
                    <div class="form-group">
                        <label for="subjectCode"><i class='bx bx-barcode'></i> Subject Code <span>*</span></label>
                        <input type="text" name="subjectCode" id="subjectCode" value="<?php echo isset($subjectCode) ? htmlspecialchars($subjectCode) : ''; ?>" required placeholder="Enter Subject Code">
                    </div>
                    <?php if (isset($errors['subjectCode'])): ?>
                        <div class="error-text"><?php echo $errors['subjectCode']; ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="subjectName"><i class='bx bx-book'></i> Subject Name <span>*</span></label>
                        <input type="text" name="subjectName" id="subjectName" value="<?php echo isset($subjectName) ? htmlspecialchars($subjectName) : ''; ?>" required placeholder="Enter Subject Name">
                    </div>
                    <?php if (isset($errors['subjectName'])): ?>
                        <div class="error-text"><?php echo $errors['subjectName']; ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="subjectType"><i class='bx bx-category'></i> Subject Type <span>*</span></label>
                        <select name="subjectType" id="subjectType" required>
                            <option value="" disabled <?php echo !isset($subjectType) ? 'selected' : ''; ?>>- Select Subject Type -</option>
                            <option value="Core Subject" <?php echo isset($subjectType) && $subjectType == 'Core Subject' ? 'selected' : ''; ?>>Core Subject</option>
                            <option value="Applied Subject" <?php echo isset($subjectType) && $subjectType == 'Applied Subject' ? 'selected' : ''; ?>>Applied Subject</option>
                            <option value="Specialized Subject" <?php echo isset($subjectType) && $subjectType == 'Specialized Subject' ? 'selected' : ''; ?>>Specialized Subject</option>
                        </select>
                    </div>
                    <?php if (isset($errors['subjectType'])): ?>
                        <div class="error-text"><?php echo $errors['subjectType']; ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="yearLevel"><i class='bx bxs-school'></i> Year Level</label>
                        <select name="yearLevel" id="yearLevel" required>
                            <option value="" disabled <?php echo !isset($yearLevel) ? 'selected' : ''; ?>>- Select Year Level -</option>
                            <option value="Grade 11" <?php echo isset($yearLevel) && $yearLevel == 'Grade 11' ? 'selected' : ''; ?>>Grade 11</option>
                            <option value="Grade 12" <?php echo isset($yearLevel) && $yearLevel == 'Grade 12' ? 'selected' : ''; ?>>Grade 12</option>
                        </select>
                    </div>
                    <?php if (isset($errors['yearLevel'])): ?>
                        <div class="error-text"><?php echo $errors['yearLevel']; ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="semester"><i class='bx bx-calendar'></i> Semester</label>
                        <select name="semester" id="semester" required>
                            <option value="" disabled <?php echo !isset($semester) ? 'selected' : ''; ?>>- Select Semester -</option>
                            <option value="1st Sem" <?php echo isset($semester) && $semester == '1st Sem' ? 'selected' : ''; ?>>1st Sem</option>
                            <option value="2nd Sem" <?php echo isset($semester) && $semester == '2nd Sem' ? 'selected' : ''; ?>>2nd Sem</option>
                        </select>
                    </div>
                    <?php if (isset($errors['semester'])): ?>
                        <div class="error-text"><?php echo $errors['semester']; ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="strandID"><i class='bx bx-network-chart'></i> Track and Strand <span>*</span></label>
                        <select name="strandID" id="strandID" required>
                            <option value="" disabled <?php echo !isset($strandID) ? 'selected' : ''; ?>>- Select Track and Strand -</option>
                            <?php foreach ($strands as $strand): ?>
                                <option value="<?php echo $strand['strandID']; ?>" <?php echo isset($strandID) && $strandID == $strand['strandID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($strand['trackName'] . ' - ' . $strand['strandName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (isset($errors['strandID'])): ?>
                        <div class="error-text"><?php echo $errors['strandID']; ?></div>
                    <?php endif; ?>
                    <?php if (empty($strands)): ?>
                        <div class="error-text">⚠️ No tracks and strands available. Please add them first.</div>
                    <?php endif; ?>

                    <button type="submit" name="registerSubject">Register Subject</button>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->
    
    <script src="./utils/script.js"></script>
</body>
</html>