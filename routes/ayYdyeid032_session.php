<?php
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = $_POST['login']; 
    $password = $_POST['password'];

    if (!empty($login) && !empty($password)) {
        $query = "SELECT * FROM users WHERE lrn = :login OR email = :login";
        $stmt = $dbConnection->prepare($query);
        $stmt->bindParam(':login', $login, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $user['password'])) {
                if ($user['archived'] == 1) {
                    $error = "Your account has been archived.";
                } elseif ($user['status'] === 'Active') {
                    $_SESSION['userID'] = $user['userID'];
                    $_SESSION['lrn'] = $user['lrn'];
                    $_SESSION['userType'] = $user['userType'];
                    $_SESSION['status'] = $user['status'];

                    // Set cookies for 30 days
                    setcookie('userID', $user['userID'], time() + (30 * 24 * 60 * 60), '/'); // 30 days
                    setcookie('lrn', $user['lrn'], time() + (30 * 24 * 60 * 60), '/');
                    setcookie('userType', $user['userType'], time() + (30 * 24 * 60 * 60), '/');
                    setcookie('status', $user['status'], time() + (30 * 24 * 60 * 60), '/');

                    $loginTime = date('Y-m-d H:i:s');
                    $ipAddress = $_SERVER['REMOTE_ADDR'];
                    $deviceInfo = $_SERVER['HTTP_USER_AGENT'];

                    $insertHistoryQuery = "INSERT INTO login_history (userID, login_time, ip_address, device_info) 
                                           VALUES (:userID, :loginTime, :ipAddress, :deviceInfo)";
                    $historyStmt = $dbConnection->prepare($insertHistoryQuery);
                    $historyStmt->bindParam(':userID', $user['userID'], PDO::PARAM_INT);
                    $historyStmt->bindParam(':loginTime', $loginTime, PDO::PARAM_STR);
                    $historyStmt->bindParam(':ipAddress', $ipAddress, PDO::PARAM_STR);
                    $historyStmt->bindParam(':deviceInfo', $deviceInfo, PDO::PARAM_STR);
                    $historyStmt->execute();

                    switch ($user['userType']) {
                        case 'Student':
                            header("Location: ./pages/students/studentDash.php");
                            break;
                        case 'Professor':
                            header("Location: ./pages/professors/professor_main_dash.php");
                            break;
                        case 'Admin':
                            header("Location: ./pages/admins/adminDash.php");
                            break;
                        case 'SuperAdmin':
                            header("Location: ./pages/superadmins/superAdminDash.php");
                            break;
                        default:
                            $error = "Invalid user type.";
                            break;
                    }
                    exit;
                } else {
                    $error = "Your account is not active.";
                }
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "No account found with that LRN or email.";
        }
    } else {
        $error = "Please fill in both fields.";
    }
}
?>