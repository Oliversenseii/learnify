<?php
try {
    $stmt = $dbConnection->prepare("SELECT firstName, middleName, lastName, birthday, sex, address, email, contactNumber, nationality, image, status FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = $user['firstName'];
        $_SESSION['userType'] = 'Professor';  

        $_SESSION['image'] = $user['image'] ? $user['image'] : './img/noprofile.png'; 
    } else {
        header("Location: ../../index.php");
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['saveChanges'])) {
        $firstName = $_POST['firstName'] ?? '';
        $middleName = $_POST['middleName'] ?? '';
        $lastName = $_POST['lastName'] ?? '';
        $birthday = $_POST['birthday'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $address = $_POST['address'] ?? '';
        $email = $_POST['email'] ?? '';
        $contactNumber = $_POST['contactNumber'] ?? '';
        $nationality = $_POST['nationality'] ?? '';
        $status = $_POST['status'] ?? '';

        $imagePath = $user['image']; 
        if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] == UPLOAD_ERR_OK) {
            $imageName = $_FILES['profileImage']['name'];
            $imageTmpName = $_FILES['profileImage']['tmp_name'];
            $imageSize = $_FILES['profileImage']['size'];
            $imageError = $_FILES['profileImage']['error'];

            $imageExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($imageExtension, $allowedExtensions)) {
                $imageNewName = uniqid('', true) . '.' . $imageExtension;
                $imagePath = '../../../lib/file_uploads/' . $imageNewName; 

                if (!move_uploaded_file($imageTmpName, $imagePath)) {
                    echo "Error uploading image.";
                    exit;
                }
            } else {
                echo "Invalid image format.";
                exit;
            }
        }

        try {
            $stmt = $dbConnection->prepare("UPDATE users SET firstName = :firstName, middleName = :middleName, lastName = :lastName, birthday = :birthday, sex = :sex, address = :address, email = :email, contactNumber = :contactNumber, nationality = :nationality, image = :image, status = :status WHERE userID = :userID");
            $stmt->bindParam(':firstName', $firstName);
            $stmt->bindParam(':middleName', $middleName);
            $stmt->bindParam(':lastName', $lastName);
            $stmt->bindParam(':birthday', $birthday);
            $stmt->bindParam(':sex', $sex);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':contactNumber', $contactNumber);
            $stmt->bindParam(':nationality', $nationality);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':image', $imagePath);  
            $stmt->bindParam(':userID', $_SESSION['userID']);
            $stmt->execute();

            $_SESSION['success_message'] = "Your account has been successfully updated.";
            header("Location: userAcc.php");
            exit;
        } catch (PDOException $e) {
            echo "Error updating user: " . $e->getMessage();
            exit;
        }
    }

    if (isset($_POST['archiveAccount'])) {
        try {
            $stmt = $dbConnection->prepare("UPDATE users SET status = 'Archived' WHERE userID = :userID");
            $stmt->bindParam(':userID', $_SESSION['userID']);
            $stmt->execute();

            session_destroy();

            header("Location: ../../index.php");
            exit;
        } catch (PDOException $e) {
            echo "Error archiving account: " . $e->getMessage();
            exit;
        }
    }
}
?>