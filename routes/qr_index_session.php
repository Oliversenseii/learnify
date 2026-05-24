<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the uploaded file information
    $uploadedQRCode = $_FILES['qr_code']['name']; // Name of the uploaded QR code image

    if ($uploadedQRCode) {
        // Get the file path for the uploaded QR code
        $uploadedQRCodePath = "../lib/qr_code_img/" . $uploadedQRCode;

        // Query to check if the QR code exists in the database (matching the path)
        $query = "SELECT * FROM users WHERE qr_code_image = :qr_code_image";
        $stmt = $dbConnection->prepare($query);
        $stmt->bindParam(':qr_code_image', $uploadedQRCodePath, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // User found, check archived status
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user['archived'] == 1) {
                $error = "Your account has been archived.";
            } elseif ($user['status'] === 'Active') {
                // Start the session and set user session variables
                $_SESSION['userID'] = $user['userID'];
                $_SESSION['qr_code_image'] = $user['qr_code_image'];
                $_SESSION['userType'] = $user['userType'];
                $_SESSION['status'] = $user['status'];

                // Redirect user based on their type
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
            $error = "Invalid QR code.";
        }
    } else {
        $error = "Please upload a QR code.";
    }
}
?>