<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

$errors = [];
$success = [];

// Handle download template
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="csv_template.csv"');
    $output = fopen('php://output', 'w');
    // Write headers
    fputcsv($output, ['First Name', 'Middle Name', 'Last Name', 'Birthday', 'Sex', 'Address', 'Email', 'Contact Number', 'Nationality', 'Image', 'User Type', 'LRN']);
    // Write sample data
    fputcsv($output, [
        'John', 'A', 'Doe', '2000-01-01', 'Male', '123 Sample St, Manila', 'johndoe@gmail.com', '09123456789', 'Filipino', '', 'path/to/image.jpg', 'Student', '123456789012'
    ]);
    fputcsv($output, [
        'Jane', 'B', 'Smith', '1995-05-15', 'Female', '456 Example Ave, Quezon City', 'janesmith@yahoo.com', '09876543210', 'Filipino', 'path/to/image.jpg', 'Professor', '12345'
    ]);
    exit;
}

// Function to generate a random string for QR code
function generateRandomCode($length = 10) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Handle Archive Log Action
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

// Handle Archive All Logs Action
if (isset($_POST['archive_all_logs'])) {
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

// Handle Archive User Action
if (isset($_POST['archive_user'])) {
    $user_id = (int)$_POST['user_id'];
    try {
        $stmt = $dbConnection->prepare("UPDATE users SET archived = 1 WHERE userID = :user_id AND archived = 0 AND uploaded_via = 'csv'");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $success[] = "User ID $user_id has been archived.";
        } else {
            $errors[] = "User ID $user_id is already archived or does not exist.";
        }
        $_SESSION['success'] = $success;
        $_SESSION['errors'] = $errors;
        header("Location: register-csv.php?tab=users");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Error archiving user: " . $e->getMessage();
        $_SESSION['errors'] = $errors;
        header("Location: register-csv.php?tab=users");
        exit;
    }
}

// Handle Archive All Users Action
if (isset($_POST['archive_all_users'])) {
    try {
        $stmt = $dbConnection->prepare("UPDATE users SET archived = 1 WHERE archived = 0 AND userType IN ('Student', 'Professor') AND uploaded_via = 'csv'");
        $stmt->execute();
        $affected_rows = $stmt->rowCount();
        if ($affected_rows > 0) {
            $success[] = "$affected_rows user(s) have been archived.";
        } else {
            $success[] = "No users were available to archive.";
        }
        $_SESSION['success'] = $success;
        $_SESSION['errors'] = $errors;
        header("Location: register-csv.php?tab=users");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Error archiving all users: " . $e->getMessage();
        $_SESSION['errors'] = $errors;
        header("Location: register-csv.php?tab=users");
        exit;
    }
}

// Display session messages and clear them
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

        // Validate file type
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileExt !== 'csv') {
            $errors[] = "Invalid file type. Only CSV files are allowed.";
        } else {
            // Check if file name already exists in csv_upload_logs
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
                // Generate unique filename to avoid overwrites
                $uniqueFileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
                $uploadDir = '../../lib/csv_uploads/';
                $filePath = $uploadDir . $uniqueFileName;

                // Move the uploaded file
                if (!move_uploaded_file($fileTmpPath, $filePath)) {
                    $errors[] = "Failed to save the CSV file.";
                } else {
                    if (($handle = fopen($filePath, 'r')) !== false) {
                        // Skip the first line (header)
                        fgetcsv($handle);

                        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                            // Sanitize and assign CSV data
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
                            $uploaded_via = 'csv';

                            $recordErrors = [];

                            // Validate required fields
                            if (empty($firstName) || empty($lastName) || empty($middleName) || empty($email) || empty($address) || empty($nationality) || empty($contactNumber) || empty($lrn) || empty($userType)) {
                                $recordErrors[] = "Required fields are missing for $firstName $lastName";
                            }

                            // Validate userType
                            if (!in_array($userType, ['Student', 'Professor'])) {
                                $recordErrors[] = "Only 'Student' or 'Professor' are allowed.";
                            }

                            // Validate email
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

                            // Validate contact number
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

                            // Validate LRN based on User Type
                            $expectedLrnLength = ($userType === 'Student') ? 12 : 5;
                            $expectedTypeName = ($userType === 'Student') ? 'Student LRN' : 'Professor ID';

                            if (!preg_match('/^\d{' . $expectedLrnLength . '}$/', $lrn)) {
                                $recordErrors[] = "Invalid $expectedTypeName for $firstName $lastName. Must be exactly $expectedLrnLength digits.";
                            } else {
                                $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE lrn = :lrn");
                                $stmt->bindParam(':lrn', $lrn);
                                $stmt->execute();
                                if ($stmt->fetchColumn() > 0) {
                                    $recordErrors[] = "$expectedTypeName already exists: $lrn";
                                }
                            }

                            // Validate generated code uniqueness
                            $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE generated_code = :generatedCode");
                            $stmt->bindParam(':generatedCode', $generatedCode);
                            $stmt->execute();
                            if ($stmt->fetchColumn() > 0) {
                                $recordErrors[] = "Generated QR code already exists for $firstName $lastName. Please try again.";
                            }

                            if (empty($recordErrors)) {
                                try {
                                    $sql = "INSERT INTO users (password, firstName, middleName, lastName, birthday, sex, address, email, contactNumber, nationality, image, userType, status, lrn, archived, generated_code, uploaded_via) 
                                            VALUES (:password, :firstName, :middleName, :lastName, :birthday, :sex, :address, :email, :contactNumber, :nationality, :image, :userType, :status, :lrn, :archived, :generatedCode, :uploaded_via)";
                                    $stmt = $dbConnection->prepare($sql);
                                    $stmt->bindParam(':password', $password);
                                    $stmt->bindParam(':firstName', $firstName);
                                    $stmt->bindParam(':middleName', $middleName);
                                    $stmt->bindParam(':lastName', $lastName);
                                    $stmt->bindParam(':birthday', $birthday);
                                    $stmt->bindParam(':sex', $sex);
                                    $stmt->bindParam(':address', $address);
                                    $stmt->bindParam(':email', $email);
                                    $stmt->bindParam(':contactNumber', $contactNumber);
                                    $stmt->bindParam(':nationality', $nationality);
                                    $stmt->bindParam(':image', $image);
                                    $stmt->bindParam(':userType', $userType);
                                    $stmt->bindParam(':status', $status);
                                    $stmt->bindParam(':lrn', $lrn);
                                    $stmt->bindParam(':archived', $archived);
                                    $stmt->bindParam(':generatedCode', $generatedCode);
                                    $stmt->bindParam(':uploaded_via', $uploaded_via);
                                    $stmt->execute();
                                    $success[] = "Successfully registered user: $firstName $lastName with QR code: $generatedCode";
                                    $successfulRecords++;
                                } catch (PDOException $e) {
                                    $recordErrors[] = "Error registering user $firstName $lastName: " . $e->getMessage();
                                    $failedRecords++;
                                }
                            } else {
                                $failedRecords++;
                            }

                            if (!empty($recordErrors)) {
                                $errorLog = array_merge($errorLog, $recordErrors);
                            }
                        }

                        fclose($handle);

                        // Log the upload transaction
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

// Fetch log history (only for Admins)
$logHistory = [];
try {
    $stmt = $dbConnection->prepare("
        SELECT l.*, u.email 
        FROM csv_upload_logs l 
        JOIN users u ON l.uploaded_by = u.userID 
        WHERE l.archived = 0 AND u.userType = 'Admin'
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

// Fetch uploaded users
$uploadedUsers = [];
try {
    $stmt = $dbConnection->prepare("
        SELECT userID, firstName, middleName, lastName, email, userType, lrn, dateCreated
        FROM users 
        WHERE archived = 0 AND userType IN ('Student', 'Professor') AND uploaded_via = 'csv'
        ORDER BY dateCreated DESC
    ");
    $stmt->execute();
    $uploadedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching uploaded users: " . $e->getMessage();
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
    <!-- <link rel="stylesheet" href="./utils/section.css"> -->
    <!-- <link rel="stylesheet" href="./utils/csv.css"> -->
    <!-- <link rel="stylesheet" href="./utils/animation_slide.css"> -->
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <title>Learnify</title>
    <script src="./logout.js"></script>
    <style>
        .modal {
            padding: 0;
            margin: 0;
        }
        .modal-header {
            background: linear-gradient(135deg, #0056b3, #003d82);
            color: white;
            padding: 16px;
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-close {
            cursor: pointer;
            font-size: clamp(1.2rem, 3vw, 2rem);
            transition: color 0.2s ease;
        }
        .modal-close:hover {
            color: #c82333;
        }
        .modal-body {
            overflow-y: auto;
            background: var(--grey);
        }
        .excel-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1rem, 3vw, 1.4rem);
            margin-top: 10px;
            background: #fff;
            border: none;
        }
        .excel-table th, .excel-table td {
            padding: 10px;
            text-align: left;
            color: var(--dark);
        }
        .excel-table th {
            background: #e6e6e6;
            font-weight: 600;
            color: #333;
        }
        .excel-table tr:nth-child(even) {
            background-color: var(--grey);
        }
        .excel-table tr:hover {
            background: linear-gradient(135deg, var(--light), gray);
        }
        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .tab-container {
            margin-top: 20px;
            background: #f9f9f9;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .tab-buttons {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            background: #fff;
        }
        .tab-button {
            flex: 1;
            padding: 12px 20px;
            background-color: var(--light);
            color: var(--dark);
            cursor: pointer;
            border: none;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
        }
        .tab-button .bx {
            font-size: clamp(1rem, 3vw, 1.2rem);
        }
        .tab-button:hover,
        .tab-button.active:hover {
            background: #0056b3;
            color: white;
        }
        .tab-button.active {
            background: #007bff;
            color: white;
            border-bottom: 3px solid #007bff;
            font-weight: 600;
        }
        .tab-content {
            display: none;
            padding: 20px;
            background: var(--light);
            border-radius: 0 0 12px 12px;
        }
        .tab-content.active {
            display: block;
        }
        .log-history, .user-list, .help-guide {
            margin-top: 20px;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0px 0px 2px #ccc;
            overflow-x: auto;
        }
        .log-history h2, .user-list h2, .help-guide h2 {
            color: var(--dark);
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            font-size: clamp(1.2rem, 3vw, 2rem);
            margin-bottom: 15px;
        }
        .log-history table, .user-list table, .help-guide table {
            width: 100%;
            border-collapse: collapse;
            background: var(--light);
            border-radius: 8px;
            overflow: hidden;
        }
        .log-history th, .log-history td, .user-list th, .user-list td, .help-guide th, .help-guide td {
            padding: 12px;
            text-align: left;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }
        .log-history td, .user-list td, .help-guide td {
            color: var(--dark);
            border-bottom: 1px solid var(--dark-grey);
        }
        .log-history tr:nth-child(even), .user-list tr:nth-child(even), .help-guide tr:nth-child(even) {
            background-color: var(--grey);
        }
        .log-history th, .user-list th, .help-guide th {
            background: #0056b3;
            color: #fff;
            font-weight: 600;
        }
        .log-history .errors-cell {
            max-width: 300px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-history .action-cell a, .user-list a, .help-guide a {
            color: white;
            text-decoration: none;
            margin-right: 10px;
            transition: color 0.2s ease;
        }
        .log-history .action-cell a:hover, .user-list a:hover, .help-guide a:hover {
            text-decoration: none;
        }
        .archive-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: clamp(1rem, 3vw, 1.2rem);
            transition: background 0.2s ease;
        }
        .archive-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        .archive-all-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 10px 20px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            float: right;
            margin-bottom: 20px;
            transition: background 0.2s ease;
        }
        .archive-all-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        .error-view-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.2rem);
            transition: background 0.2s ease;
        }
        .error-view-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        .view-btn {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.3rem);
            transition: background 0.2s ease;
        }
        .view-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d82);
        }
        .view-btn i {
            font-size: clamp(1rem, 3vw, 1.3rem);
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
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .errorHeader {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 16px;
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .errorClose {
            cursor: pointer;
            font-size: clamp(1.2rem, 3vw, 2rem);
            transition: color 0.2s ease;
        }
        .errorClose:hover {
            color: #820a0a;
        }
        .errorContent {
            padding: 20px;
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
            font-size: clamp(1rem, 2vw, 1.2rem);
        }
        .errorGroup hr {
            border: 0;
            border-top: 1px solid #e0e0e0;
            margin: 8px 0;
        }
        .no-uploads {
            width: fit-content;
            margin: 0 auto;
            font-style: italic;
            color: var(--dark);
            font-size: clamp(1rem, 2vw, 1.5rem);
            text-align: center;
            padding: 20px;
        }
        .form-container {
            background: var(--light);
            padding: 20px;
            max-width: 500px;
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            margin-top: 10px;
            margin: auto;
        }
        .form-container h2 {
            margin-bottom: 20px;
			border-bottom: 1px solid #ccc;
			padding-bottom: 10px;
            font-size: clamp(1.2rem, 3vw, 2rem);
            color: var(--dark);
            text-align: left;
        }
        .form-container label {
            display: block;
            font-weight: 500;
            color: var(--dark);
            font-size: clamp(1rem, 2vw, 1.2rem) !important;
        }
        .form-container label span {
            color: #b21f2d;
        }
        .form-container input[type="file"] {
            display: block;
            width: 100%;
            padding: 10px;
            border: 2px dashed #aaa;
            border-radius: 8px;
            background: var(--grey);
            color: var(--dark);
            margin-bottom: 10px;
            margin-top: 10px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: border-color 0.3s ease;
            }

            .form-container input[type="file"]:hover {
            border-color: #007bff;
            }

            input[type="file"]::file-selector-button {
                padding: 10px 18px;
                border: none;
                border-radius: 6px;
                background: #007bff;
                color: white;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.3s ease;
                }

                input[type="file"]::file-selector-button:hover {
                background: #0056b3;
                }

        .form-container button {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 2vw, 1.2rem);
            transition: background 0.2s ease;
        }
        .form-container button:hover {
            background: linear-gradient(135deg, #218838, #1c7430);
        }
        .help-guide .step {
            margin-bottom: 20px;
            padding: 15px;
            background: var(--grey);
            border-radius: 8px;
            border-left: 4px solid #0056b3;
        }
        .help-guide .step h3 {
            color: #0056b3;
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            margin-bottom: 8px;
        }
        .help-guide .step p {
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.3rem);
            line-height: 1.5;
        }
        .template-download {
            margin: 25px 0;
        }
        .template-download a {
            color: white;
            background-color: #007bff;
            padding: 10px;
            text-decoration: none;
            font-size: clamp(1rem, 2vw, 1.2rem);
            border-radius: 10px;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .template-download a:hover {
            background-color: white;
            color: #007bff;
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

        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
             --transition: all 0.3s ease;
             --white: #FFFFFF;
             --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        @media screen and (max-width: 460px) {
            .tab-buttons {
                flex-direction: column;
                display: flex;
            }
        }

        .modal-content {
            max-height: 80vh;
            height: auto;
            overflow-y: auto;
            overflow-x: auto;
        }

        .bulk-upload-btn-container {
            display: flex;
            justify-content: right;
        }
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./adminDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./message_professor.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Teachers</span>
                </a>
            </li>
            <li class="active">
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

    <?php require_once './view/modal.php'; ?>
    
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

        <div id="previewModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span>CSV File Preview</span>
                    <span class="modal-close" onclick="closePreviewModal()">X</span>
                </div>
                <div class="modal-body">
                    <table id="previewTable" class="excel-table">
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
            </div>
        </div>

        <div id="viewCsvModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span>View CSV File</span>
                    <span class="modal-close" onclick="closeViewCsvModal()">X</span>
                </div>
                <div class="modal-body">
                    <table id="viewCsvTable" class="excel-table">
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
                        <tbody id="viewCsvBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <form id="logArchiveForm" method="POST" action="register-csv.php" style="display: none;">
            <input type="hidden" name="log_id" id="log_id">
            <input type="hidden" name="archive_log" value="1">
        </form>
        <form id="archiveAllLogsForm" method="POST" action="register-csv.php" style="display: none;">
            <input type="hidden" name="archive_all_logs" value="1">
        </form>
        <form id="userArchiveForm" method="POST" action="register-csv.php" style="display: none;">
            <input type="hidden" name="user_id" id="user_id">
            <input type="hidden" name="archive_user" value="1">
        </form>
        <form id="archiveAllUsersForm" method="POST" action="register-csv.php" style="display: none;">
            <input type="hidden" name="archive_all_users" value="1">
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
                <div class="error-notification">
                    <ul>
                        <?php foreach ($errors as $message): ?>
                            <li><?php echo htmlspecialchars($message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h2>Bulk Uploads</h2>
                <form action="register-csv.php" method="POST" enctype="multipart/form-data">
                    <label for="csvFile">Upload CSV File <span>*</span> </label>
                    <input type="file" name="csvFile" id="csvFile" accept=".csv" required>
                    <div class="bulk-upload-btn-container">
                        <button type="submit" name="submit">Upload</button>
                    </div>
                </form>
            </div>

            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-button <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'logs') ? 'active' : ''; ?>" onclick="showTab('logs')"><i class='bx bx-file'></i> CSV Upload History</button>
                    <button class="tab-button <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'users') ? 'active' : ''; ?>" onclick="showTab('users')"><i class='bx bx-user-plus'></i> Uploaded Users</button>
                    <button class="tab-button <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'help') ? 'active' : ''; ?>" onclick="showTab('help')"><i class='bx bx-help-circle'></i> Help</button>
                </div>
                <div class="tab-content <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'logs') ? 'active' : ''; ?>" id="logs">
                    <div class="log-history">
                        <h2>CSV Upload History</h2>
                        <button class="archive-all-btn" onclick="showArchiveAllLogsAlert()">Archive All Logs</button>
                        <?php if (!empty($logHistory)): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <!-- <th>Upload Timestamp</th> -->
                                        <!-- <th>Uploaded By</th> -->
                                        <th>File Name</th>
                                        <th>View</th>
                                        <th>Downlaod</th>
                                        <th>Successful Records</th>
                                        <th>Failed Records</th>
                                        <th>Errors</th>
                                        <th>Archive</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logHistory as $log): ?>
                                        <tr>
                                            <!-- <td><?php
                                                $date = new DateTime($log['upload_timestamp'], new DateTimeZone('UTC'));
                                                $date->setTimezone(new DateTimeZone('Asia/Manila'));
                                                echo htmlspecialchars($date->format('F j, Y \a\t h:i a'));
                                            ?></td> -->
                                            <!-- <td><?php echo htmlspecialchars($log['email']); ?></td> -->
                                            <td><?php echo htmlspecialchars($log['file_name']); ?></td>
                                            <td class="action-cell">
                                                <?php if (!empty($log['file_path']) && file_exists($log['file_path'])): ?>
                                                    <a href="javascript:void(0);" class="view-btn" onclick="viewCsvFile('<?php echo htmlspecialchars($log['file_path']); ?>')"><i class="bx bxs-show"></i></a>
                                                <?php else: ?>
                                                    Not Available
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-cell">
                                                <?php if (!empty($log['file_path']) && file_exists($log['file_path'])): ?>
                                                    <a href="download_csv.php?log_id=<?php echo (int)$log['log_id']; ?>" target="_blank" class="view-btn" ><i class="bx bxs-download"></i></a>
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
                </div>
                <div class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'users') ? 'active' : ''; ?>" id="users">
                    <div class="user-list">
                        <h2>Uploaded Users</h2>
                        <button class="archive-all-btn" onclick="showArchiveAllUsersAlert()">Archive All Users</button>
                        <?php if (!empty($uploadedUsers)): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>User Type</th>
                                        <th>LRN</th>
                                        <th>Date Created</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($uploadedUsers as $user): ?>
                                        <tr>
                                            <td><?php echo (int)$user['userID']; ?></td>
                                            <td><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['middleName'] . ' ' . $user['lastName']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['userType']); ?></td>
                                            <td><?php echo htmlspecialchars($user['lrn']); ?></td>
                                            <td><?php
                                                $date = new DateTime($user['dateCreated'], new DateTimeZone('UTC'));
                                                $date->setTimezone(new DateTimeZone('Asia/Manila'));
                                                echo htmlspecialchars($date->format('F j, Y \a\t h:i a'));
                                            ?></td>
                                            <td>
                                                <a href="javascript:void(0);" class="archive-btn" onclick="showUserArchiveAlert(<?php echo (int)$user['userID']; ?>)">Archive</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="no-uploads">No users available.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'help') ? 'active' : ''; ?>" id="help">
                    <div class="help-guide">
                        <h2>CSV Upload Walkthrough</h2>
                        <div class="step">
                            <h3>Step 1: Download the CSV Template</h3>
                            <p>Start by downloading the CSV template to ensure your data is formatted correctly. The template includes all required fields.</p>
                            <div class="template-download">
                                <a href="./files/sample_template.csv" download>Download CSV Template</a>
                            </div>
                        </div>
                        <div class="step">
                            <h3>Step 2: Prepare Your CSV File</h3>
                            <p>Fill the template with user data. Ensure the following:</p>
                            <table class="excel-table">
                                <tr>
                                    <th>#</th>
                                    <th>Fields</th>
                                    <th>Details</th>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Required Fields</td>
                                    <td> First Name, Middle Name, Last Name, Email, Address, Nationality, Contact Number, LRN, User Type.</td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>Sex</td>
                                    <td>Enter "Male" or "Female".</td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>Email</td>
                                    <td>Must end with @gmail.com or @yahoo.com.</td>
                                </tr>
                                <tr>
                                    <td>4</td>
                                    <td>Contact Number</td>
                                    <td>11 digits starting with 09 (e.g., 09123456789).</td>
                                </tr>
                                <tr>
                                    <td>5</td>
                                    <td>Nationality</td>
                                    <td>Specify the country (e.g., Filipino).</td>
                                </tr>
                                <tr>
                                    <td>6</td>
                                    <td>Image</td>
                                    <td>Optional, provide a file path or URL.</td>
                                </tr>
                                <tr>
                                    <td>7</td>
                                    <td>UserType</td>
                                    <td>Enter "Student" or "Professor". Admin or SuperAdmin are not allowed.</td>
                                </tr>
                                <tr>
                                    <td>8</td>
                                    <td>LRN <br> Teacher ID</td>
                                    <td>
                                        <strong>Student:</strong> Exactly <code>12 digits</code> (e.g., 123456789012)<br>
                                        <strong>Professor:</strong> Exactly <code>5 digits</code> (e.g., 12345)
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="step">
                            <h3>Step 3: Upload the CSV File</h3>
                            <p>Return to the upload form above, select your filled CSV file, and click "Upload". A preview will appear in a modal window to verify your data before processing.</p>
                        </div>
                        <div class="step">
                            <h3>Step 4: Review Upload Results</h3>
                            <p>After uploading, check the "CSV Upload History" tab to view the upload status, successful records, and any errors. Use the "Uploaded Users" tab to manage users added via CSV.</p>
                        </div>
                        <div class="step" style="width:100%; overflow-x:auto; margin-top:1rem;">
                            <h3>Example CSV Format</h3>
                            <p>Below is an example of how your CSV file should look:</p>
                            <table class="excel-table">
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
                                <tbody>
                                    <tr>
                                        <td>John</td>
                                        <td>A</td>
                                        <td>Doe</td>
                                        <td>2000-01-01</td>
                                        <td>Male</td>
                                        <td>123 Sample St</td>
                                        <td>johndoe@gmail.com</td>
                                        <td>09123456789</td>
                                        <td>Filipino</td>
                                        <td></td>
                                        <td>Student</td>
                                        <td>123456789012</td>
                                    </tr>
                                    <tr>
                                        <td>Jane</td>
                                        <td>B</td>
                                        <td>Smith</td>
                                        <td>1995-05-15</td>
                                        <td>Female</td>
                                        <td>456 Example Ave</td>
                                        <td>janesmith@yahoo.com</td>
                                        <td>09876543210</td>
                                        <td>Filipino</td>
                                        <td>path/to/image.jpg</td>
                                        <td>Professor</td>
                                        <td>12345</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                       <div class="step">
                            <h3>Notes</h3>
                            <p>
                                📌 The first row of your CSV must match the template headers.<br>
                                📌 Default password for new users is 'DIHS_12345'.<br>
                                📌 <strong>Student LRN must be 12 digits</strong>, <strong>Professor ID must be 5 digits</strong>.<br>
                                📌 If errors occur, review them in the "CSV Upload History" tab under "Errors".<br>
                                📌 Only users with userType 'Student' or 'Professor' can be uploaded via CSV.<br>
                                📌 You can archive logs or users to keep your interface clean.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.tab-button[onclick="showTab('${tabId}')"]`).classList.add('active');
            window.history.pushState({}, '', `register-csv.php?tab=${tabId}`);
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
            document.getElementById('errorDisplay').style.display = 'none';
        }

        function showPreviewModal() {
            document.getElementById('previewModal').style.display = 'flex';
        }

        function closePreviewModal() {
            document.getElementById('previewModal').style.display = 'none';
        }

        function showViewCsvModal() {
            document.getElementById('viewCsvModal').style.display = 'flex';
        }

        function closeViewCsvModal() {
            document.getElementById('viewCsvModal').style.display = 'none';
        }

        function showLogArchiveAlert(logId) {
            if (confirm('Are you sure you want to archive this log?')) {
                document.getElementById('log_id').value = logId;
                document.getElementById('logArchiveForm').submit();
            }
        }

        function showArchiveAllLogsAlert() {
            if (confirm('Are you sure you want to archive all logs?')) {
                document.getElementById('archiveAllLogsForm').submit();
            }
        }

        function showUserArchiveAlert(userId) {
            if (confirm('Are you sure you want to archive this user?')) {
                document.getElementById('user_id').value = userId;
                document.getElementById('userArchiveForm').submit();
            }
        }

        function showArchiveAllUsersAlert() {
            if (confirm('Are you sure you want to archive all users?')) {
                document.getElementById('archiveAllUsersForm').submit();
            }
        }

        window.addEventListener('click', function(event) {
            const errorDisplay = document.getElementById('errorDisplay');
            const previewModal = document.getElementById('previewModal');
            const viewCsvModal = document.getElementById('viewCsvModal');
            if (event.target === errorDisplay) {
                closeErrorDisplay();
            }
            if (event.target === previewModal) {
                closePreviewModal();
            }
            if (event.target === viewCsvModal) {
                closeViewCsvModal();
            }
        });

        document.getElementById('csvFile').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file && file.type === 'text/csv') {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const text = e.target.result;
                    const rows = text.split('\n').map(row => row.split(',').map(cell => cell.trim()));
                    
                    const previewBody = document.getElementById('previewBody');
                    previewBody.innerHTML = '';

                    for (let i = 1; i < rows.length && i <= 50; i++) { // Limit to 50 rows for performance
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

                    if (previewBody.children.length > 0) {
                        showPreviewModal();
                    } else {
                        alert('No valid data found in the CSV file.');
                    }
                };
                reader.readAsText(file);
            } else {
                alert('Please select a valid CSV file.');
            }
        });

        function viewCsvFile(filePath) {
            fetch(filePath)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('File not found or inaccessible.');
                    }
                    return response.text();
                })
                .then(text => {
                    const rows = text.split('\n').map(row => row.split(',').map(cell => cell.trim()));
                    const viewCsvBody = document.getElementById('viewCsvBody');
                    viewCsvBody.innerHTML = '';

                    for (let i = 1; i < rows.length && i <= 50; i++) { 
                        const row = rows[i];
                        if (row.length >= 12) {
                            const tr = document.createElement('tr');
                            for (let j = 0; j < 12; j++) {
                                const td = document.createElement('td');
                                td.textContent = row[j] || '';
                                tr.appendChild(td);
                            }
                            viewCsvBody.appendChild(tr);
                        }
                    }

                    if (viewCsvBody.children.length > 0) {
                        showViewCsvModal();
                    } else {
                        alert('No valid data found in the CSV file.');
                    }
                })
                .catch(error => {
                    alert('Error loading CSV file: ' + error.message);
                });
        }
    </script>
</body>
</html>