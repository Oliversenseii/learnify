<?php
session_start();
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit;
}

require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

$currentPage = basename($_SERVER['PHP_SELF']);

if (isset($_GET['id'])) {
    $moduleID = $_GET['id'];
    
    $sql = "SELECT sm.*, ts.strandName FROM strand_modules sm 
            JOIN track_strands ts ON sm.strandID = ts.strandID 
            WHERE sm.moduleID = ? AND sm.archived = 0";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $moduleID);
    $stmt->execute();
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        $_SESSION['error_message'] = "Module not found or has been archived.";
        header("Location: data_modules.php");
        exit;
    }
}

if (isset($_POST['updateModule'])) {
    $strandID = $_POST['strandID'];
    $moduleTitle = $_POST['moduleTitle'];
    $description = $_POST['description'];
    $fileName = $module['fileName'];
    $filePath = $module['filePath'];
    $fileType = $module['fileType'];
    $fileSize = $module['fileSize'];

    $targetDir = "./modules/";
    $allowedTypes = ['application/pdf']; // Only PDF files allowed
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    if (isset($_FILES['moduleFile']) && $_FILES['moduleFile']['error'] == UPLOAD_ERR_OK) {
        $newFileName = time() . '_' . basename($_FILES['moduleFile']['name']);
        $newFilePath = $targetDir . $newFileName;
        $newFileType = $_FILES['moduleFile']['type'];
        $newFileSize = $_FILES['moduleFile']['size'];

        if (!in_array($newFileType, $allowedTypes)) {
            $_SESSION['error_message'] = "Invalid file type. Only PDF files are allowed.";
        } elseif ($newFileSize > $maxFileSize) {
            $_SESSION['error_message'] = "File size exceeds 5MB limit.";
        } else {
            if (move_uploaded_file($_FILES['moduleFile']['tmp_name'], $newFilePath)) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $fileName = $newFileName;
                $filePath = $newFilePath;
                $fileType = $newFileType;
                $fileSize = $newFileSize;
            } else {
                $_SESSION['error_message'] = "Error uploading file. Please try again.";
                header("Location: edit_modules.php?id=$moduleID");
                exit;
            }
        }
    }

    $sql = "UPDATE strand_modules SET strandID = ?, moduleTitle = ?, description = ?, fileName = ?, filePath = ?, fileType = ?, fileSize = ? WHERE moduleID = ?";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $strandID);
    $stmt->bindParam(2, $moduleTitle);
    $stmt->bindParam(3, $description);
    $stmt->bindParam(4, $fileName);
    $stmt->bindParam(5, $filePath);
    $stmt->bindParam(6, $fileType);
    $stmt->bindParam(7, $fileSize);
    $stmt->bindParam(8, $moduleID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Module updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating module. Please try again.";
    }

    header("Location: data_modules.php");
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
    <link rel="stylesheet" href="./utils/edit_track_strand.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Edit Module</title>
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
        .main-container {
            width: 100%;
            max-width: 700px;
            margin: 20px auto;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .submit-btn {
            padding: 10px 20px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: none;
            border-radius: 5px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .success-notification,
        .error-notification {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .success-notification {
            background-color: #4CAF50;
            color: var(--light);
        }

        .error-notification {
            background-color: #dc3545;
            color: var(--light);
        }

        @media screen and (max-width: 768px) {
            .main-container {
                width: 90%;
            }
        }

        .form-group input[type="file"]::file-selector-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            cursor: pointer;
        }

        .form-group input[type="file"]::file-selector-button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .form-group .fileName {
            color: var(--dark);
            text-align: center;
            margin-top: 10px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        label {
            font-weight: 600;
        }
        .span_req {
            color: #c82333;
        }
        .edit-title h2 {
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            font-size: clamp(1.8rem, 3vw, 2rem);
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
                    <input type="search" name="query" placeholder="Search modules, tracks, strands..." required>
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
                    <h1>Edit Module</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./modules.php">Modules</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="data_modules.php">Module Records</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="<?php echo ($currentPage == 'edit_modules.php') ? 'active' : ''; ?>" href="edit_modules.php?id=<?php echo $moduleID; ?>">Edit</a></li>
                    </ul>
                </div>
                <a href="data_modules.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Module
                </a>
            </div>

            <!-- Main Container for Form -->
            <div class="main-container">
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

                <form action="edit_modules.php?id=<?php echo $module['moduleID']; ?>" method="POST" enctype="multipart/form-data">
                    <div class="edit-title">
                        <h2>Edit Module</h2>
                    </div>
                    <div class="form-group">
                        <label for="strandID">Strand</label>
                        <select name="strandID" id="strandID" required>
                            <option value="" disabled>Select Strand</option>
                            <?php
                            $sql = "SELECT strandID, strandName FROM track_strands WHERE archived = 0";
                            $result = $dbConnection->query($sql);
                            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($row['strandID'] == $module['strandID']) ? 'selected' : '';
                                echo "<option value='{$row['strandID']}' $selected>{$row['strandName']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="moduleTitle">Module Title</label>
                        <input type="text" name="moduleTitle" id="moduleTitle" value="<?php echo htmlspecialchars($module['moduleTitle']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" rows="8"><?php echo htmlspecialchars($module['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="moduleFile">Upload New Module File <span class="span_req">(PDF Only, Optional)</span></label>
                        <input type="file" name="moduleFile" id="moduleFile" accept=".pdf">
                        <p class="fileName"><strong>Current file:</strong> <br><?php echo htmlspecialchars($module['fileName']); ?> (<?php echo number_format($module['fileSize'] / 1024, 2); ?> KB)</p>
                    </div>

                    <button type="submit" name="updateModule" class="submit-btn">Save</button>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>