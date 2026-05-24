<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

$errors = [];
$success = [];

function generateRandomCode($length = 10) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $randomString;
}

if (isset($_POST['archive_log'])) {
    $log_id = (int)$_POST['log_id'];
    try {
        $stmt = $dbConnection->prepare("UPDATE csv_upload_logs SET archived = 1 WHERE log_id = :log_id AND archived = 0");
        $stmt->bindParam(':log_id', $log_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $success[] = "Log ID $log_id has been archived.";
        } else {
            $errors[] = "Log ID $log_id is already archived or does not exist.";
        }
        $_SESSION['success'] = $success;
        $_SESSION['errors'] = $errors;
        header("Location: register-csv.php");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Error archiving log: " . $e->getMessage();
        $_SESSION['errors'] = $errors;
        header("Location: register-csv.php");
        exit;
    }
}

if (isset($_POST['archive_all'])) {
    try {
        $stmt = $dbConnection->prepare("UPDATE csv_upload_logs SET archived = 1 WHERE archived = 0");
        $stmt->execute();
        $affected_rows = $stmt->rowCount();
        if ($affected_rows > 0) {
            $success[] = "$affected_rows log(s) have been archived.";
        } else {
            $success[] = "No logs were available to archive.";
        }
        $_SESSION['success'] = $success;
        $_SESSION['errors'] = $errors;
        header("Location: register-csv.php");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Error archiving all logs: " . $e->getMessage();
        $_SESSION['errors'] = $errors;
        header("Location: register-csv.php");
        exit;
    }
}

if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="user_registration_template.csv"');
    
    $output = fopen('php://output', 'w');
    $headers = [
        'firstName', 'middleName', 'lastName', 'birthday', 'sex', 'address',
        'email', 'contactNumber', 'nationality', 'image', 'userType', 'lrn'
    ];
    fputcsv($output, $headers);
    
    $exampleRow = [
        'John', 'Michael', 'Doe', '1990-01-01', 'Male', '123 Main St, City',
        'john.doe@gmail.com', '09123456789', 'Filipino', 'profile.jpg', 'Student', '123456789012'
    ];
    fputcsv($output, $exampleRow);
    
    fclose($output);
    exit;
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}

if (isset($_POST['submit'])) {
    if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csvFile']['tmp_name'];
        $fileName = $_FILES['csvFile']['name'];
        $successfulRecords = 0;
        $failedRecords = 0;
        $errorLog = [];
        $records = [];

        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileExt !== 'csv') {
            $errors[] = "Invalid file type. Only CSV files are allowed.";
        } else {
            try {
                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM csv_upload_logs WHERE file_name = :file_name");
                $stmt->bindParam(':file_name', $fileName);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "File with name '$fileName' already exists.";
                }
            } catch (PDOException $e) {
                $errors[] = "Error checking file name: " . $e->getMessage();
            }

            if (empty($errors)) {
                $uniqueFileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
                $uploadDir = '../../lib/csv_uploads/';
                $filePath = $uploadDir . $uniqueFileName;

                if (!move_uploaded_file($fileTmpPath, $filePath)) {
                    $errors[] = "Failed to save the CSV file.";
                } else {
                    if (($handle = fopen($filePath, 'r')) !== false) {
                        fgetcsv($handle); // Skip header

                        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                            $firstName = isset($data[0]) ? trim($data[0]) : '';
                            $middleName = isset($data[1]) ? trim($data[1]) : '';
                            $lastName = isset($data[2]) ? trim($data[2]) : '';
                            $birthday = isset($data[3]) ? trim($data[3]) : '';
                            $sex = isset($data[4]) ? trim($data[4]) : '';
                            $address = isset($data[5]) ? trim($data[5]) : '';
                            $email = isset($data[6]) ? trim($data[6]) : '';
                            $contactNumber = isset($data[7]) ? (string)trim($data[7]) : '';
                            $nationality = isset($data[8]) ? trim($data[8]) : '';
                            $image = isset($data[9]) ? trim($data[9]) : '';
                            $userType = isset($data[10]) ? trim($data[10]) : '';
                            $lrn = isset($data[11]) ? trim($data[11]) : '';

                            if (strlen($contactNumber) === 10 && $contactNumber[0] !== '0') {
                                $contactNumber = '0' . $contactNumber;
                            }

                            $password = password_hash('DIHS_12345', PASSWORD_DEFAULT);
                            $status = 'Active';
                            $archived = 0;
                            $generatedCode = generateRandomCode();

                            $recordErrors = [];

                            if (empty($firstName) || empty($lastName) || empty($middleName) || empty($email) || empty($address) || empty($nationality) || empty($contactNumber) || empty($lrn) || empty($userType)) {
                                $recordErrors[] = "Required fields are missing for $firstName $lastName";
                            }

                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $recordErrors[] = "Invalid email format for $email";
                            } elseif (!preg_match('/@(gmail\.com|yahoo\.com)$/', $email)) {
                                $recordErrors[] = "Email must be from @gmail.com or @yahoo.com for $email";
                            } else {
                                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
                                $stmt->bindParam(':email', $email);
                                $stmt->execute();
                                if ($stmt->fetchColumn() > 0) {
                                    $recordErrors[] = "Email already exists: $email";
                                }
                            }

                            if (!preg_match('/^09\d{9}$/', $contactNumber)) {
                                $recordErrors[] = "Invalid contact number for $firstName $lastName. Must be 11 digits and start with 09.";
                            } else {
                                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE contactNumber = :contactNumber");
                                $stmt->bindParam(':contactNumber', $contactNumber);
                                $stmt->execute();
                                if ($stmt->fetchColumn() > 0) {
                                    $recordErrors[] = "Contact number already exists: $contactNumber";
                                }
                            }

                            if (!preg_match('/^\d{12}$/', $lrn)) {
                                $recordErrors[] = "Invalid LRN for $firstName $lastName. LRN must be 12 digits.";
                            } else {
                                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE lrn = :lrn");
                                $stmt->bindParam(':lrn', $lrn);
                                $stmt->execute();
                                if ($stmt->fetchColumn() > 0) {
                                    $recordErrors[] = "LRN already exists: $lrn";
                                }
                            }

                            $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE generated_code = :generatedCode");
                            $stmt->bindParam(':generatedCode', $generatedCode);
                            $stmt->execute();
                            if ($stmt->fetchColumn() > 0) {
                                $recordErrors[] = "Generated QR code already exists for $firstName $lastName. Please try again.";
                            }

                            if (!empty($recordErrors)) {
                                $errorLog = array_merge($errorLog, $recordErrors);
                            } else {
                                $records[] = [
                                    'password' => $password,
                                    'firstName' => $firstName,
                                    'middleName' => $middleName,
                                    'lastName' => $lastName,
                                    'birthday' => $birthday,
                                    'sex' => $sex,
                                    'address' => $address,
                                    'email' => $email,
                                    'contactNumber' => $contactNumber,
                                    'nationality' => $nationality,
                                    'image' => $image,
                                    'userType' => $userType,
                                    'status' => $status,
                                    'lrn' => $lrn,
                                    'archived' => $archived,
                                    'generatedCode' => $generatedCode
                                ];
                            }
                        }

                        fclose($handle);

                        if (!empty($errorLog)) {
                            $errors = $errorLog;
                            @unlink($filePath);
                        } else {
                            try {
                                $dbConnection->beginTransaction();
                                foreach ($records as $record) {
                                    $sql = "INSERT INTO users (password, firstName, middleName, lastName, birthday, sex, address, email, contactNumber, nationality, image, userType, status, lrn, archived, generated_code) 
                                            VALUES (:password, :firstName, :middleName, :lastName, :birthday, :sex, :address, :email, :contactNumber, :nationality, :image, :userType, :status, :lrn, :archived, :generatedCode)";
                                    $stmt = $dbConnection->prepare($sql);
                                    $stmt->execute($record);
                                    $successfulRecords++;
                                    $success[] = "Successfully registered user: {$record['firstName']} {$record['lastName']} with QR code: {$record['generatedCode']}";
                                }
                                $dbConnection->commit();
                            } catch (PDOException $e) {
                                $dbConnection->rollBack();
                                $errors[] = "Error registering users: " . $e->getMessage();
                                $successfulRecords = 0;
                                @unlink($filePath);
                            }

                            try {
                                $uploadedBy = $_SESSION['userID'];
                                $uploadTimestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                                $errorsText = !empty($errorLog) ? implode("\n", $errorLog) : null;
                                $archived = 0;

                                $stmt = $dbConnection->prepare("
                                    INSERT INTO csv_upload_logs (uploaded_by, file_name, file_path, upload_timestamp, successful_records, failed_records, errors, archived)
                                    VALUES (:uploaded_by, :file_name, :file_path, :upload_timestamp, :successful_records, :failed_records, :errors, :archived)
                                ");
                                $stmt->bindParam(':uploaded_by', $uploadedBy, PDO::PARAM_INT);
                                $stmt->bindParam(':file_name', $fileName);
                                $stmt->bindParam(':file_path', $filePath);
                                $stmt->bindParam(':upload_timestamp', $uploadTimestamp);
                                $stmt->bindParam(':successful_records', $successfulRecords, PDO::PARAM_INT);
                                $stmt->bindParam(':failed_records', $failedRecords, PDO::PARAM_INT);
                                $stmt->bindParam(':errors', $errorsText);
                                $stmt->bindParam(':archived', $archived, PDO::PARAM_INT);
                                $stmt->execute();
                            } catch (PDOException $e) {
                                $errors[] = "Error logging upload transaction: " . $e->getMessage();
                                @unlink($filePath);
                            }

                            if (!empty($errorLog)) {
                                $errors = $errorLog;
                            }
                        }
                    } else {
                        $errors[] = "Error opening the CSV file.";
                        @unlink($filePath);
                    }
                }
            }
        }
    } else {
        $errors[] = "No file uploaded or an error occurred during upload.";
    }
}

$logHistory = [];
try {
    $stmt = $dbConnection->prepare("
        SELECT l.*, u.email 
        FROM csv_upload_logs l 
        JOIN users u ON l.uploaded_by = u.userID 
        WHERE l.archived = 0 
        ORDER BY l.upload_timestamp DESC
    ");
    $stmt->execute();
    $logHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        $errors[] = "Log history unavailable: Please create the 'csv_upload_logs' table in the database.";
    } else {
        $errors[] = "Error fetching log history: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/section.css">
    <link rel="stylesheet" href="./utils/csv.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <title>Learnify</title>
    <script src="./logout.js"></script>
    <style>
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        .modal {
            z-index: 1000000;
        }
        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .log-history {
            margin-top: 30px;
            background-color: var(--light);
            padding: 20px;
        }
        .log-history h2 {
            color: var(--dark);
            font-size: clamp(2rem, 3vw, 2.5rem);
        }
        .log-history table {
            width: 100%;
            border-collapse: collapse;
            background: var(--light);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0px 1px var(--dark);
        }
        .log-history th, .log-history td {
            padding: 12px;
            text-align: left;
        }
        .log-history td {
            color: var(--dark);
            border-bottom: 1px solid #ddd;
        }
        .log-history tr:nth-child(even) {
            background-color: var(--grey);
        }
        .log-history th {
            background: #0056b3;
            color: #fff;
            font-weight: bold;
        }
        .log-history .errors-cell {
            max-width: 300px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-history .view-csv a {
            color: #007bff;
            text-decoration: none;
        }
        .log-history .view-csv a:hover {
            text-decoration: underline;
        }
        .log-history .archive-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .log-history .archive-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        .archive-all-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 7px 10px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            float: right;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .archive-all-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        .error-view-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }
        .error-view-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        #errorDisplay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            justify-content: center;
            align-items: center;
        }
        .errorContainer {
            background: var(--light);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0, 0, 0,0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .errorHeader {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 12px 16px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .errorClose {
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            line-height: 1;
        }
        .errorClose:hover {
            color: #f8d7da;
        }
        .errorContent {
            padding: 16px;
            overflow-y: auto;
        }
        .errorGroup {
            margin-bottom: 16px;
        }
        .errorGroup:last-child {
            margin-bottom: 0;
        }
        .errorGroup p {
            color: var(--dark);
            margin: 4px 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }
        .errorGroup hr {
            border: 0;
            border-top: 1px solid var(--dark);
            margin: 8px 0;
        }
        .no-uploads {
            color: var(--dark-grey);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }
        .template-download {
            margin-bottom: 10px;
        }
        .template-download a {
            color: #007bff;
            text-decoration: none;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }
        .template-download a:hover {
            text-decoration: underline;
        }
        .preview-container {
            margin-top: 20px;
            background-color: var(--light);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 1px var(--dark);
        }
        .preview-container h2 {
            color: var(--dark);
            font-size: clamp(2rem, 3vw, 2.5rem);
            margin-bottom: 15px;
        }
        .preview-container table {
            width: 100%;
            border-collapse: collapse;
            background: var(--light);
            border-radius: 8px;
            overflow: hidden;
        }
        .preview-container th, .preview-container td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .preview-container th {
            background: #0056b3;
            color: #fff;
            font-weight: bold;
        }
        .preview-container tr:nth-child(even) {
            background-color: var(--grey);
        }
        .preview-container td {
            color: var(--dark);
        }
        @media (max-width: 600px) {
            .errorContainer {
                width: 95%;
            }
            .errorHeader {
                font-size: 16px;
            }
            .errorGroup p {
                font-size: 13px;
            }
            .preview-container table {
                font-size: 12px;
            }
            .preview-container th, .preview-container td {
                padding: 8px;
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
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./superAdminDash.php">
                    <i class="bx bxs-dashboard"></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li class="active">
                <a href="./registration.php">
                    <i class="bx bx-user-plus"></i>
                    <span class="text">Registration</span>
                </a>
            </li>
            <li>
                <a href="./backup_data.php">
                    <i class="bx bxs-data"></i>
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
                    <i class="bx bxs-cog"></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0);" class="logout" onclick="showLogoutModal()">
                    <i class="bx bxs-log-out-circle"></i>
                    <span class="text">Logout</span>
                </a>
            </li>
        </ul>
    </section>

    <section id="content">
        <nav>
            <i class="bx bx-menu"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required>
                    <button type="submit" class="search-btn"><i class="bx bx-search"></i></button>
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

        <?php require_once './view/modal.php'; ?>

        <div id="errorDisplay">
            <div class="errorContainer">
                <div class="errorHeader">
                    <span>Upload Errors</span>
                    <span class="errorClose" onclick="closeErrorDisplay()">X</span>
                </div>
                <div class="errorContent">
                    <div id="errorList"></div>
                </div>
            </div>
        </div>

        <form id="logArchiveForm" method="POST" action="register-csv.php" style="display: none;">
            <input type="hidden" name="log_id" id="log_id">
            <input type="hidden" name="archive_log" value="1">
        </form>
        <form id="archiveAllForm" method="POST" action="register-csv.php" style="display: none;">
            <input type="hidden" name="archive_all" value="1">
        </form>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>CSV Registration</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class="bx bx-chevron-right"></i></li>
                        <li><a href="#">User Registration</a></li>
                        <li><i class="bx bx-chevron-right"></i></li>
                        <li><a class="active" href="register-csv.php">Upload CSV</a></li>
                    </ul>
                </div>
                <a href="./registration.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Registration
                </a>
            </div>

            <?php if (!empty($success)): ?>
                <div class="success-notification">
                    <ul>
                        <?php foreach ($success as $message): ?>
                            <li><?php echo htmlspecialchars($message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="success-notification" style="background-color: #dc3545;">
                    <ul>
                        <?php foreach ($errors as $message): ?>
                            <li><?php echo htmlspecialchars($message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <div class="template-download">
                    <a href="register-csv.php?download_template=1" download>Download CSV Template</a>
                </div>
                <form action="register-csv.php" method="POST" enctype="multipart/form-data">
                    <label for="csvFile">Upload CSV File:</label>
                    <input type="file" name="csvFile" id="csvFile" accept=".csv" required>
                    <button type="submit" name="submit">Upload</button>
                </form>
            </div>

            <div class="preview-container" id="previewContainer" style="display: none;">
                <h2>CSV File Preview</h2>
                <table id="previewTable">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Middle Name</th>
                            <th>Last Name</th>
                            <th>Birthday</th>
                            <th>Sex</th>
                            <th>Address</th>
                            <th>Email</th>
                            <th>Contact Number</th>
                            <th>Nationality</th>
                            <th>Image</th>
                            <th>User Type</th>
                            <th>LRN</th>
                        </tr>
                    </thead>
                    <tbody id="previewBody"></tbody>
                </table>
            </div>

            <div class="log-history">
                <h2>CSV Upload History</h2>
                <button class="archive-all-btn" onclick="showArchiveAllAlert()">Archive All Logs</button>
                <?php if (!empty($logHistory)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Upload Timestamp</th>
                                <th>Uploaded By</th>
                                <th>File Name</th>
                                <th>View CSV</th>
                                <th>Successful Records</th>
                                <th>Failed Records</th>
                                <th>Errors</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logHistory as $log): ?>
                                <tr>
                                    <td><?php
                                        $date = new DateTime($log['upload_timestamp'], new DateTimeZone('UTC'));
                                        $date->setTimezone(new DateTimeZone('Asia/Manila'));
                                        echo htmlspecialchars($date->format('F j, Y \a\t h:i a'));
                                    ?></td>
                                    <td><?php echo htmlspecialchars($log['email']); ?></td>
                                    <td><?php echo htmlspecialchars($log['file_name']); ?></td>
                                    <td class="view-csv">
                                        <?php if (!empty($log['file_path']) && file_exists($log['file_path'])): ?>
                                            <a href="download_csv.php?log_id=<?php echo (int)$log['log_id']; ?>" target="_blank">View/Download</a>
                                        <?php else: ?>
                                            Not Available
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$log['successful_records']; ?></td>
                                    <td><?php echo (int)$log['failed_records']; ?></td>
                                    <td class="errors-cell">
                                        <?php if (!empty($log['errors']) && $log['errors'] !== 'None'): ?>
                                            <button class="error-view-btn" onclick="showErrorDisplay(<?php echo (int)$log['log_id']; ?>)">View Errors</button>
                                            <div id="errors-<?php echo (int)$log['log_id']; ?>" style="display: none;"><?php echo htmlspecialchars($log['errors']); ?></div>
                                        <?php else: ?>
                                            None
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0);" class="archive-btn" onclick="showLogArchiveAlert(<?php echo (int)$log['log_id']; ?>)">Archive</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-uploads">No upload history available. <?php echo !empty($errors) && strpos(implode($errors), 'csv_upload_logs') !== false ? 'Please create the csv_upload_logs table.' : ''; ?></p>
                <?php endif; ?>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        const userArchiveModal = document.getElementById('archiveModal');
        const userSpan = userArchiveModal ? userArchiveModal.getElementsByClassName('close')[0] : null;

        if (userArchiveModal && userSpan) {
            function confirmArchive(id) {
                userArchiveModal.style.display = 'block';
                document.getElementById('sectionID').value = id;
            }

            function closeModal() {
                userArchiveModal.style.display = 'none';
            }

            userSpan.onclick = () => {
                userArchiveModal.style.display = 'none';
            };

            window.onclick = (event) => {
                if (event.target === userArchiveModal) {
                    userArchiveModal.style.display = 'none';
                }
            };
        }

        function showErrorDisplay(logId) {
            const errorDisplay = document.getElementById('errorDisplay');
            const errorList = document.getElementById('errorList');
            const errorData = document.getElementById(`errors-${logId}`);
            
            if (errorDisplay && errorList && errorData) {
                errorList.innerHTML = '';
                const errors = errorData.textContent.split('\n').filter(error => error.trim() !== '');
                
                for (let i = 0; i < errors.length; i++) {
                    const groupDiv = document.createElement('div');
                    groupDiv.className = 'errorGroup';
                    const pError = document.createElement('p');
                    pError.textContent = errors[i];
                    groupDiv.appendChild(pError);
                    if (i < errors.length - 1) {
                        const hr = document.createElement('hr');
                        groupDiv.appendChild(hr);
                    }
                    errorList.appendChild(groupDiv);
                }
                
                errorDisplay.style.display = 'flex';
            }
        }

        function closeErrorDisplay() {
            const errorDisplay = document.getElementById('errorDisplay');
            if (errorDisplay) {
                errorDisplay.style.display = 'none';
            }
        }

        window.addEventListener('click', function(event) {
            const errorDisplay = document.getElementById('errorDisplay');
            if (event.target === errorDisplay) {
                closeErrorDisplay();
            }
        });

        function showLogArchiveAlert(logId) {
            if (confirm('Are you sure you want to archive this log?')) {
                const form = document.getElementById('logArchiveForm');
                document.getElementById('log_id').value = logId;
                form.submit();
            }
        }

        function showArchiveAllAlert() {
            if (confirm('Are you sure you want to archive all logs?')) {
                const form = document.getElementById('archiveAllForm');
                form.submit();
            }
        }

        document.getElementById('csvFile').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file && file.type === 'text/csv') {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const text = e.target.result;
                    const rows = text.split('\n').map(row => row.split(',').map(cell => cell.trim()));
                    
                    const previewBody = document.getElementById('previewBody');
                    const previewContainer = document.getElementById('previewContainer');
                    previewBody.innerHTML = '';

                    for (let i = 1; i < rows.length; i++) {
                        const row = rows[i];
                        if (row.length >= 12) {
                            const tr = document.createElement('tr');
                            for (let j = 0; j < 12; j++) {
                                const td = document.createElement('td');
                                td.textContent = row[j] || '';
                                tr.appendChild(td);
                            }
                            previewBody.appendChild(tr);
                        }
                    }

                    previewContainer.style.display = previewBody.children.length > 0 ? 'block' : 'none';
                };
                reader.readAsText(file);
            } else {
                document.getElementById('previewContainer').style.display = 'none';
                alert('Please select a valid CSV file.');
            }
        });
    </script>
</body>
</html>