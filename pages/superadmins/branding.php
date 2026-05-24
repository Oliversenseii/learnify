<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

// Validate session
if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php");
    exit;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

try {
    $stmt = $dbConnection->prepare("SELECT id, logo_image_path, logo_text, updated_at FROM branding WHERE archived = 0 ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $currentBranding = $stmt->fetch(PDO::FETCH_ASSOC);

    $currentLogoImage = $currentBranding['logo_image_path'] ?? './img/darky-1.png';
    $currentLogoText = $currentBranding['logo_text'] ?? 'Learnify';
    $currentBrandingId = $currentBranding['id'] ?? null;

    $stmt = $dbConnection->prepare("SELECT id, logo_image_path, logo_text, updated_at, archived FROM branding ORDER BY updated_at DESC");
    $stmt->execute();
    $logoHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newLogoText = filter_input(INPUT_POST, 'logo_text', FILTER_SANITIZE_STRING);
        $archivePrevious = isset($_POST['archive_previous']) ? 1 : 0;
        $newLogoImagePath = $currentLogoImage; 

        if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/logo/';
            $fileName = time() . '_' . basename($_FILES['logo_image']['name']); 
            $filePath = $uploadDir . $fileName;

            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
            $maxSize = 5 * 1024 * 1024; 
            $fileType = mime_content_type($_FILES['logo_image']['tmp_name']);
            $fileSize = $_FILES['logo_image']['size'];

            if (!in_array($fileType, $allowedTypes)) {
                $_SESSION['error_message'] = "Invalid file type. Only PNG, JPG, or JPEG files are allowed.";
            } elseif ($fileSize > $maxSize) {
                $_SESSION['error_message'] = "File size exceeds 5MB limit.";
            } elseif (!move_uploaded_file($_FILES['logo_image']['tmp_name'], $filePath)) {
                $_SESSION['error_message'] = "Failed to upload the image. Please try again.";
            } else {
                $newLogoImagePath = $filePath;
            }
        } elseif ($_FILES['logo_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $_SESSION['error_message'] = "An error occurred during file upload.";
        }

        if (!isset($_SESSION['error_message'])) {
            $dbConnection->beginTransaction();

            if ($archivePrevious && $currentBrandingId) {
                $stmt = $dbConnection->prepare("UPDATE branding SET archived = 1 WHERE id = :id");
                $stmt->bindParam(':id', $currentBrandingId, PDO::PARAM_INT);
                $stmt->execute();
            }

            $stmt = $dbConnection->prepare("
                INSERT INTO branding (logo_image_path, logo_text, updated_by, archived)
                VALUES (:logo_image_path, :logo_text, :updated_by, 0)
            ");
            $stmt->bindParam(':logo_image_path', $newLogoImagePath, PDO::PARAM_STR);
            $stmt->bindParam(':logo_text', $newLogoText, PDO::PARAM_STR);
            $stmt->bindParam(':updated_by', $userID, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $dbConnection->commit();
                $_SESSION['success_message'] = "Branding updated successfully.";
                header("Location: branding.php");
                exit;
            } else {
                $dbConnection->rollBack();
                $_SESSION['error_message'] = "Failed to update branding.";
            }
        }
    }
} catch (PDOException $e) {
    if ($dbConnection->inTransaction()) {
        $dbConnection->rollBack();
    }
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "An error occurred: " . htmlspecialchars($e->getMessage()) . ". Please try again later.";
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
    <link rel="stylesheet" href="./utils/box.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js" defer></script>
    <title>Manage Logo and Branding - Learnify</title>
    <style>
        .branding-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: var(--light);
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .branding-container h1 {
            font-size: 2rem;
            color: var(--dark);
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--dark);
            font-size: 1.3rem;
        }
        .form-group input[type="text"],
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--dark-grey);
            cursor: pointer;
            border-radius: 5px;
            font-size: 1.3rem;
        }
        .form-group img {
            max-width: 200px;
            margin-top: 10px;
            border-radius: 5px;
        }
        .form-group input[type="checkbox"] {
            margin-right: 10px;
        }
        #image-preview {
            max-width: 200px;
            margin-top: 10px;
            border-radius: 5px;
            display: none;
        }
        .btn-submit {
            display: block;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        .history-container {
            margin-top: 40px;
        }
        .history-container h2 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 20px;
        }
        .history-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--grey);
        }
        .history-item img {
            max-width: 100px;
            margin-right: 20px;
            border-radius: 5px;
        }
        .activated {
            color: green;
            font-style: italic;
        }
        .history-item p {
            margin: 0;
            font-size: 1.2rem;
            color: var(--dark);
        }
        .history-item .archived {
            color: #dc3545;
            font-style: italic;
        }
        .btn-download {
            display: inline-flex;
            align-items: center;
            margin-top: 5px;
            gap: 5px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-download:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        .error-notification, .success-notification {
            max-width: 700px;
            margin: 10px auto;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            color: white;
            font-size: 1.2rem;
        }
        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
        }
        @media (max-width: 768px) {
            .branding-container {
                padding: 15px;
            }
            .form-group img, #image-preview {
                max-width: 150px;
            }
            .history-item img {
                max-width: 80px;
            }
            .btn-download {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
        }

        .form-group input[type="file"]::file-selector-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
        }

        .form-group input[type="file"]::file-selector-button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <a href="./superAdminDash.php" class="brand">
            <img class="logo_img" src="<?php echo htmlspecialchars($currentLogoImage); ?>" alt="Logo">
            <span class="text" id="logo_text"><?php echo htmlspecialchars($currentLogoText); ?></span>
        </a>
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
            <li class="active">
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
                <img src="<?php echo htmlspecialchars($_SESSION['image']); ?>" alt="Profile Image">
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
                    <h1>Manage Logo and Branding</h1>
                    <ul class="breadcrumb">
                        <li><a href="./superAdminDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./branding.php">Branding</a></li>
                    </ul>
                </div>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class='error-notification'><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class='success-notification'><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <div class="branding-container">
                <h1>Update Logo and Branding</h1>
                <form method="post" enctype="multipart/form-data" id="branding-form">
                    <div class="form-group">
                        <label for="logo_image">Current Logo Image</label>
                        <img src="<?php echo htmlspecialchars($currentLogoImage); ?>" alt="Current Logo">
                    </div>
                    <div class="form-group">
                        <label for="logo_image">Upload New Logo Image (PNG/JPG, max 5MB)</label>
                        <input style="background-color: var(--grey); color: var(--dark);" type="file" id="logo_image" name="logo_image" accept="image/png,image/jpeg,image/jpg">
                        <img id="image-preview" alt="Image Preview">
                    </div>
                    <div class="form-group">
                        <label for="logo_text">Logo Text</label>
                        <input type="text" id="logo_text" name="logo_text" value="<?php echo htmlspecialchars($currentLogoText); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="archive_previous" checked>
                            Archive previous branding record
                        </label>
                    </div>
                    <button type="submit" class="btn-submit">Save Branding Changes</button>
                </form>
            </div>

            <?php if (!empty($logoHistory)): ?>
                <div class="history-container">
                    <h2>Branding History</h2>
                    <?php foreach ($logoHistory as $history): ?>
                        <div class="history-item">
                            <img src="<?php echo htmlspecialchars($history['logo_image_path']); ?>" alt="Logo">
                            <div>
                                <p><strong>Text:</strong> <?php echo htmlspecialchars($history['logo_text']); ?></p>
                                <p><strong>Updated:</strong> 
                                    <?php echo date("F j, Y, h:i a", strtotime($history['updated_at'])); ?>
                                </p>
                                <p><strong>Status:</strong> 
                                    <?php 
                                        echo $history['archived'] 
                                            ? '<span class="archived">Archived</span>' 
                                            : '<span class="activated">Active</span>'; 
                                    ?>
                                </p>
                                <a href="<?php echo htmlspecialchars($history['logo_image_path']); ?>" download class="btn-download">
                                    <i class='bx bxs-download'></i> Download Logo
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script>
        // Image preview functionality
        document.getElementById('logo_image').addEventListener('change', function(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('image-preview');
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>