<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

if (isset($_POST['registerModule'])) {
    $strandID = $_POST['strandID'];
    $moduleTitle = $_POST['moduleTitle'];

    $targetDir = "./modules/";
    $allowedTypes = [
        'application/pdf' 
    ];
    $maxFileSize = 5 * 1024 * 1024; 

    if (isset($_FILES['moduleFile']) && $_FILES['moduleFile']['error'] == UPLOAD_ERR_OK) {
        $fileName = time() . '_' . basename($_FILES['moduleFile']['name']);
        $filePath = $targetDir . $fileName;
        $fileType = $_FILES['moduleFile']['type'];
        $fileSize = $_FILES['moduleFile']['size'];

        if (!in_array($fileType, $allowedTypes)) {
            $_SESSION['error_message'] = "Invalid file type. Only PDF files are allowed.";
        } elseif ($fileSize > $maxFileSize) {
            $_SESSION['error_message'] = "File size exceeds 5MB limit.";
        } else {
            if (move_uploaded_file($_FILES['moduleFile']['tmp_name'], $filePath)) {
                $description = $_POST['description'];

                $sql = "INSERT INTO strand_modules (strandID, moduleTitle, description, fileName, filePath, fileType, fileSize, uploadDate) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $dbConnection->prepare($sql);
                $stmt->bindParam(1, $strandID);
                $stmt->bindParam(2, $moduleTitle);
                $stmt->bindParam(3, $description);
                $stmt->bindParam(4, $fileName);
                $stmt->bindParam(5, $filePath);
                $stmt->bindParam(6, $fileType);
                $stmt->bindParam(7, $fileSize);

                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Module registered successfully!";
                } else {
                    $_SESSION['error_message'] = "Error registering module. Please try again.";
                }
                $stmt->closeCursor();
            } else {
                $_SESSION['error_message'] = "Error uploading file. Please try again.";
            }
        }
    }
    header("Location: modules.php");
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
    <link rel="stylesheet" href="./utils/track_strand.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Modules</title>
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
        .register-section {
            width: 100%;
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .register-section h2 {
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            font-size: clamp(1.8rem, 3vw, 2rem);
            color: var(--dark);
        }

        .register-section form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 10px;
        }

        .register-section label {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            text-align: left;
        }

        .register-section input,
        .register-section select,
        .register-section textarea {
            padding: 10px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .register-section input:focus,
        .register-section select:focus,
        .register-section textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
            outline: none;
        }

        .reg_module {
            padding: 10px;
            border: none;
            border-radius: 5px;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        .reg_module:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .success-notification,
        .error-notification {
            max-width: 600px;
            margin: 10px auto;
            padding: 15px;
            border-radius: 5px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            text-align: center;
            font-weight: bold;
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .register-section input[type="file"]::file-selector-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            cursor: pointer;
        }

        .register-section input[type="file"]::file-selector-button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        label {
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .register-section {
                max-width: 90%;
                margin: 20px auto;
                padding: 15px;
            }

            .register-section h2 {
                font-size: 1.3rem;
            }

            .register-section input,
            .register-section select,
            .register-section textarea,
            .reg_module {
                font-size: 0.9rem;
                padding: 8px;
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

            .register-section label {
                font-size: 0.9rem;
            }

            .register-section input,
            .register-section select,
            .register-section textarea,
            .reg_module {
                font-size: 0.8rem;
                padding: 7px;
            }

            .success-notification,
            .error-notification {
                max-width: 95%;
                padding: 10px;
                font-size: 0.9rem;
            }
        }
        .span_req {
            color: #c82333;
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
            <li class="active">
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
                    <h1>Register Modules</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="modules.php">Registration</a></li>
                    </ul>
                </div>
                <a href="data_modules.php" class="back-button">
                    <i class="bx bxs-show"></i> View Modules
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

            <!-- Register Form Section -->
            <div class="register-section">
                <h2>Register New Module</h2>
                <form action="modules.php" method="POST" enctype="multipart/form-data">
                    <label for="strandID">Strand <span class="span_req">*</span></label>
                    <select name="strandID" id="strandID" required>
                        <option value="" disabled selected>- Select Strand -</option>
                        <?php
                        $sql = "SELECT strandID, strandName FROM track_strands WHERE archived = 0";
                        $result = $dbConnection->query($sql);
                        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$row['strandID']}'>{$row['strandName']}</option>";
                        }
                        ?>
                    </select>

                    <label for="moduleTitle">Module Title <span class="span_req">*</span></label>
                    <input type="text" name="moduleTitle" id="moduleTitle" placeholder="Enter Module Title" required>

                    <label for="description">Description (Optional)</label>
                    <textarea name="description" placeholder="Enter Description" id="description" rows="4"></textarea>

                    <label for="moduleFile">Upload Module File <span class="span_req">(PDF Only)*</span></label>
                    <input type="file" name="moduleFile" id="moduleFile" accept=".pdf" required>

                    <button type="submit" name="registerModule" class="reg_module" style="font-size: clamp(1.1rem, 3vw, 1.2rem);">Register Module</button>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>