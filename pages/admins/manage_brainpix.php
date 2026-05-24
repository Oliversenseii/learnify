<?php
session_start();
require_once '../../config/db_connection.php';

if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php");
    exit;
}

// Initialize session message
$_SESSION['success_message'] = '';
$_SESSION['error_message'] = '';

try {
    // Determine action
    $action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

    switch ($action) {
        case 'add_map':
            $mapName = filter_input(INPUT_POST, 'mapName', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
            $orderNum = filter_input(INPUT_POST, 'orderNum', FILTER_VALIDATE_INT);

            if (!$mapName || $orderNum === false) {
                $_SESSION['error_message'] = "Invalid map name or order number.";
                header("Location: brainpix_controller.php?tab=maps");
                exit;
            }

            try {
                $stmt = $dbConnection->prepare("INSERT INTO brainpix_maps (mapName, description, orderNum) VALUES (:mapName, :description, :orderNum)");
                $stmt->bindParam(':mapName', $mapName);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':orderNum', $orderNum, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = "Map added successfully.";
            } catch (PDOException $e) {
                error_log("Error adding map: " . $e->getMessage());
                $_SESSION['error_message'] = "Failed to add map: " . $e->getMessage();
            }
            header("Location: brainpix_controller.php?tab=maps");
            exit;

        case 'edit_map':
            $mapID = filter_input(INPUT_POST, 'mapID', FILTER_VALIDATE_INT);
            $mapName = filter_input(INPUT_POST, 'mapName', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
            $orderNum = filter_input(INPUT_POST, 'orderNum', FILTER_VALIDATE_INT);

            if (!$mapID || !$mapName || $orderNum === false) {
                $_SESSION['error_message'] = "Invalid map ID, name, or order number.";
                header("Location: brainpix_controller.php?tab=maps");
                exit;
            }

            $stmt = $dbConnection->prepare("UPDATE brainpix_maps SET mapName = :mapName, description = :description, orderNum = :orderNum WHERE mapID = :mapID");
            $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
            $stmt->bindParam(':mapName', $mapName);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':orderNum', $orderNum, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['success_message'] = "Map updated successfully.";
            header("Location: brainpix_controller.php?tab=maps");
            exit;

        case 'delete_map':
            $mapID = filter_input(INPUT_GET, 'mapID', FILTER_VALIDATE_INT);

            if (!$mapID) {
                $_SESSION['error_message'] = "Invalid map ID.";
                header("Location: brainpix_controller.php?tab=maps");
                exit;
            }

            // Delete related badges, levels, and user badges first
            $dbConnection->beginTransaction();
            try {
                // Delete user badges associated with badges of this map
                $stmt = $dbConnection->prepare("DELETE FROM brainpix_user_badges WHERE badgeID IN (SELECT badgeID FROM brainpix_badges WHERE mapID = :mapID)");
                $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
                $stmt->execute();

                // Delete badge images and badge records
                $stmt = $dbConnection->prepare("SELECT imageURL FROM brainpix_badges WHERE mapID = :mapID");
                $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
                $stmt->execute();
                $badgeImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($badgeImages as $image) {
                    if ($image['imageURL'] && file_exists('../../uploads/BrainPix/' . $image['imageURL'])) {
                        unlink('../../uploads/BrainPix/' . $image['imageURL']);
                    }
                }
                $stmt = $dbConnection->prepare("DELETE FROM brainpix_badges WHERE mapID = :mapID");
                $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
                $stmt->execute();

                // Delete user progress associated with levels of this map
                $stmt = $dbConnection->prepare("DELETE FROM brainpix_user_progress WHERE levelID IN (SELECT levelID FROM brainpix_levels WHERE mapID = :mapID)");
                $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
                $stmt->execute();

                // Delete level images and level records
                $stmt = $dbConnection->prepare("SELECT imageURL FROM brainpix_levels WHERE mapID = :mapID");
                $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
                $stmt->execute();
                $levelImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($levelImages as $image) {
                    if ($image['imageURL'] && file_exists('../../uploads/BrainPix/' . $image['imageURL'])) {
                        unlink('../../uploads/BrainPix/' . $image['imageURL']);
                    }
                }
                $stmt = $dbConnection->prepare("DELETE FROM brainpix_levels WHERE mapID = :mapID");
                $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
                $stmt->execute();

                // Delete the map
                $stmt = $dbConnection->prepare("DELETE FROM brainpix_maps WHERE mapID = :mapID");
                $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
                $stmt->execute();

                $dbConnection->commit();
                $_SESSION['success_message'] = "Map and associated data deleted successfully.";
            } catch (PDOException $e) {
                $dbConnection->rollBack();
                $_SESSION['error_message'] = "Error deleting map: " . $e->getMessage();
            }
            header("Location: brainpix_controller.php?tab=maps");
            exit;

        case 'add_level':
            $mapID = filter_input(INPUT_POST, 'mapID', FILTER_VALIDATE_INT);
            $levelNum = filter_input(INPUT_POST, 'levelNum', FILTER_VALIDATE_INT);
            $correctAnswer = filter_input(INPUT_POST, 'correctAnswer', FILTER_SANITIZE_STRING);
            $hint = filter_input(INPUT_POST, 'hint', FILTER_SANITIZE_STRING);

            if (!$mapID || !$levelNum || $levelNum < 1 || $levelNum > 30 || !$correctAnswer || !isset($_FILES['imageURL']) || $_FILES['imageURL']['error'] == UPLOAD_ERR_NO_FILE) {
                $_SESSION['error_message'] = "Invalid map ID, level number, correct answer, or image file.";
                header("Location: brainpix_controller.php?tab=levels");
                exit;
            }

            // Validate image file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            $fileType = mime_content_type($_FILES['imageURL']['tmp_name']);
            $fileSize = $_FILES['imageURL']['size'];

            if (!in_array($fileType, $allowedTypes) || $fileSize > $maxFileSize) {
                $_SESSION['error_message'] = "Invalid image file type or size. Allowed types: JPEG, PNG, GIF. Max size: 5MB.";
                header("Location: brainpix_controller.php?tab=levels");
                exit;
            }

            // Check if level number already exists for this map
            $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM brainpix_levels WHERE mapID = :mapID AND levelNum = :levelNum");
            $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
            $stmt->bindParam(':levelNum', $levelNum, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Level number $levelNum already exists for this map.";
                header("Location: brainpix_controller.php?tab=levels");
                exit;
            }

            // Handle image upload
            $uploadDir = '../../uploads/BrainPix/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExtension = pathinfo($_FILES['imageURL']['name'], PATHINFO_EXTENSION);
            $imagePath = '../../uploads/BrainPix/' . uniqid('level_') . '.' . $fileExtension;
            $destination = '../../uploads/BrainPix/' . $imagePath;

            if (!move_uploaded_file($_FILES['imageURL']['tmp_name'], $destination)) {
                $_SESSION['error_message'] = "Failed to upload image.";
                header("Location: brainpix_controller.php?tab=levels");
                exit;
            }

            $stmt = $dbConnection->prepare("INSERT INTO brainpix_levels (mapID, levelNum, imageURL, correctAnswer, hint) VALUES (:mapID, :levelNum, :imageURL, :correctAnswer, :hint)");
            $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
            $stmt->bindParam(':levelNum', $levelNum, PDO::PARAM_INT);
            $stmt->bindParam(':imageURL', $imagePath);
            $stmt->bindParam(':correctAnswer', $correctAnswer);
            $stmt->bindParam(':hint', $hint);
            $stmt->execute();

            $_SESSION['success_message'] = "Level added successfully.";
            header("Location: brainpix_controller.php?tab=levels");
            exit;

        case 'edit_level':
            $levelID = filter_input(INPUT_POST, 'levelID', FILTER_VALIDATE_INT);
            $mapID = filter_input(INPUT_POST, 'mapID', FILTER_VALIDATE_INT);
            $levelNum = filter_input(INPUT_POST, 'levelNum', FILTER_VALIDATE_INT);
            $correctAnswer = filter_input(INPUT_POST, 'correctAnswer', FILTER_SANITIZE_STRING);
            $hint = filter_input(INPUT_POST, 'hint', FILTER_SANITIZE_STRING);

            if (!$levelID || !$mapID || !$levelNum || $levelNum < 1 || $levelNum > 30 || !$correctAnswer) {
                $_SESSION['error_message'] = "Invalid level ID, map ID, level number, or correct answer.";
                header("Location: brainpix_controller.php?tab=levels");
                exit;
            }

            $imagePath = null;
            if (isset($_FILES['imageURL']) && $_FILES['imageURL']['error'] != UPLOAD_ERR_NO_FILE) {
                // Validate image file
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                $fileType = mime_content_type($_FILES['imageURL']['tmp_name']);
                $fileSize = $_FILES['imageURL']['size'];

                if (!in_array($fileType, $allowedTypes) || $fileSize > $maxFileSize) {
                    $_SESSION['error_message'] = "Invalid image file type or size. Allowed types: JPEG, PNG, GIF. Max size: 5MB.";
                    header("Location: brainpix_controller.php?tab=levels");
                    exit;
                }

                // Delete old image
                $stmt = $dbConnection->prepare("SELECT imageURL FROM brainpix_levels WHERE levelID = :levelID");
                $stmt->bindParam(':levelID', $levelID, PDO::PARAM_INT);
                $stmt->execute();
                $oldImage = $stmt->fetchColumn();
                if ($oldImage && file_exists('../../uploads/BrainPix/' . $oldImage)) {
                    unlink('../../uploads/BrainPix/' . $oldImage);
                }

                // Upload new image
                $uploadDir = '../../uploads/BrainPix/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileExtension = pathinfo($_FILES['imageURL']['name'], PATHINFO_EXTENSION);
                $imagePath = '../../uploads/BrainPix/' . uniqid('level_') . '.' . $fileExtension;
                $destination = '../../uploads/BrainPix/' . $imagePath;

                if (!move_uploaded_file($_FILES['imageURL']['tmp_name'], $destination)) {
                    $_SESSION['error_message'] = "Failed to upload image.";
                    header("Location: brainpix_controller.php?tab=levels");
                    exit;
                }
            }

            $stmt = $dbConnection->prepare("UPDATE brainpix_levels SET mapID = :mapID, levelNum = :levelNum" . ($imagePath ? ", imageURL = :imageURL" : "") . ", correctAnswer = :correctAnswer, hint = :hint WHERE levelID = :levelID");
            $stmt->bindParam(':levelID', $levelID, PDO::PARAM_INT);
            $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
            $stmt->bindParam(':levelNum', $levelNum, PDO::PARAM_INT);
            if ($imagePath) {
                $stmt->bindParam(':imageURL', $imagePath);
            }
            $stmt->bindParam(':correctAnswer', $correctAnswer);
            $stmt->bindParam(':hint', $hint);
            $stmt->execute();

            $_SESSION['success_message'] = "Level updated successfully.";
            header("Location: brainpix_controller.php?tab=levels");
            exit;

        case 'delete_level':
            $levelID = filter_input(INPUT_GET, 'levelID', FILTER_VALIDATE_INT);

            if (!$levelID) {
                $_SESSION['error_message'] = "Invalid level ID.";
                header("Location: brainpix_controller.php?tab=levels");
                exit;
            }

            $dbConnection->beginTransaction();
            try {
                // Delete user progress for this level
                $stmt = $dbConnection->prepare("DELETE FROM brainpix_user_progress WHERE levelID = :levelID");
                $stmt->bindParam(':levelID', $levelID, PDO::PARAM_INT);
                $stmt->execute();

                // Delete level image
                $stmt = $dbConnection->prepare("SELECT imageURL FROM brainpix_levels WHERE levelID = :levelID");
                $stmt->bindParam(':levelID', $levelID, PDO::PARAM_INT);
                $stmt->execute();
                $imageURL = $stmt->fetchColumn();
                if ($imageURL && file_exists('../../uploads/BrainPix/' . $imageURL)) {
                    unlink('../../uploads/BrainPix/' . $imageURL);
                }

                // Delete level
                $stmt = $dbConnection->prepare("DELETE FROM brainpix_levels WHERE levelID = :levelID");
                $stmt->bindParam(':levelID', $levelID, PDO::PARAM_INT);
                $stmt->execute();

                $dbConnection->commit();
                $_SESSION['success_message'] = "Level deleted successfully.";
            } catch (PDOException $e) {
                $dbConnection->rollBack();
                $_SESSION['error_message'] = "Error deleting level: " . $e->getMessage();
            }
            header("Location: brainpix_controller.php?tab=levels");
            exit;

        case 'add_badge':
            $mapID = filter_input(INPUT_POST, 'mapID', FILTER_VALIDATE_INT);
            $badgeName = filter_input(INPUT_POST, 'badgeName', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

            if (!$mapID || !$badgeName || !isset($_FILES['imageURL']) || $_FILES['imageURL']['error'] == UPLOAD_ERR_NO_FILE) {
                $_SESSION['error_message'] = "Invalid map ID, badge name, or image file.";
                header("Location: brainpix_controller.php?tab=badges");
                exit;
            }

            // Validate image file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            $fileType = mime_content_type($_FILES['imageURL']['tmp_name']);
            $fileSize = $_FILES['imageURL']['size'];

            if (!in_array($fileType, $allowedTypes) || $fileSize > $maxFileSize) {
                $_SESSION['error_message'] = "Invalid image file type or size. Allowed types: JPEG, PNG, GIF. Max size: 5MB.";
                header("Location: brainpix_controller.php?tab=badges");
                exit;
            }

            // Handle image upload
            $uploadDir = '../../uploads/BrainPix/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExtension = pathinfo($_FILES['imageURL']['name'], PATHINFO_EXTENSION);
            $imagePath = '../../uploads/BrainPix/' . uniqid('badge_') . '.' . $fileExtension;
            $destination = '../../uploads/BrainPix/' . $imagePath;

            if (!move_uploaded_file($_FILES['imageURL']['tmp_name'], $destination)) {
                $_SESSION['error_message'] = "Failed to upload image.";
                header("Location: brainpix_controller.php?tab=badges");
                exit;
            }

            $stmt = $dbConnection->prepare("INSERT INTO brainpix_badges (mapID, badgeName, description, imageURL) VALUES (:mapID, :badgeName, :description, :imageURL)");
            $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
            $stmt->bindParam(':badgeName', $badgeName);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':imageURL', $imagePath);
            $stmt->execute();

            $_SESSION['success_message'] = "Badge added successfully.";
            header("Location: brainpix_controller.php?tab=badges");
            exit;

        case 'edit_badge':
            $badgeID = filter_input(INPUT_POST, 'badgeID', FILTER_VALIDATE_INT);
            $mapID = filter_input(INPUT_POST, 'mapID', FILTER_VALIDATE_INT);
            $badgeName = filter_input(INPUT_POST, 'badgeName', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

            if (!$badgeID || !$mapID || !$badgeName) {
                $_SESSION['error_message'] = "Invalid badge ID, map ID, or badge name.";
                header("Location: brainpix_controller.php?tab=badges");
                exit;
            }

            $imagePath = null;
            if (isset($_FILES['imageURL']) && $_FILES['imageURL']['error'] != UPLOAD_ERR_NO_FILE) {
                // Validate image file
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                $fileType = mime_content_type($_FILES['imageURL']['tmp_name']);
                $fileSize = $_FILES['imageURL']['size'];

                if (!in_array($fileType, $allowedTypes) || $fileSize > $maxFileSize) {
                    $_SESSION['error_message'] = "Invalid image file type or size. Allowed types: JPEG, PNG, GIF. Max size: 5MB.";
                    header("Location: brainpix_controller.php?tab=badges");
                    exit;
                }

                // Delete old image
                $stmt = $dbConnection->prepare("SELECT imageURL FROM brainpix_badges WHERE badgeID = :badgeID");
                $stmt->bindParam(':badgeID', $badgeID, PDO::PARAM_INT);
                $stmt->execute();
                $oldImage = $stmt->fetchColumn();
                if ($oldImage && file_exists('../../uploads/BrainPix/' . $oldImage)) {
                    unlink('../../uploads/BrainPix/' . $oldImage);
                }

                // Upload new image
                $uploadDir = '../../uploads/BrainPix/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileExtension = pathinfo($_FILES['imageURL']['name'], PATHINFO_EXTENSION);
                $imagePath = '../../uploads/BrainPix/' . uniqid('badge_') . '.' . $fileExtension;
                $destination = '../../uploads/BrainPix/' . $imagePath;

                if (!move_uploaded_file($_FILES['imageURL']['tmp_name'], $destination)) {
                    $_SESSION['error_message'] = "Failed to upload image.";
                    header("Location: brainpix_controller.php?tab=badges");
                    exit;
                }
            }

            $stmt = $dbConnection->prepare("UPDATE brainpix_badges SET mapID = :mapID, badgeName = :badgeName, description = :description" . ($imagePath ? ", imageURL = :imageURL" : "") . " WHERE badgeID = :badgeID");
            $stmt->bindParam(':badgeID', $badgeID, PDO::PARAM_INT);
            $stmt->bindParam(':mapID', $mapID, PDO::PARAM_INT);
            $stmt->bindParam(':badgeName', $badgeName);
            $stmt->bindParam(':description', $description);
            if ($imagePath) {
                $stmt->bindParam(':imageURL', $imagePath);
            }
            $stmt->execute();

            $_SESSION['success_message'] = "Badge updated successfully.";
            header("Location: brainpix_controller.php?tab=badges");
            exit;

        case 'delete_badge':
            $badgeID = filter_input(INPUT_GET, 'badgeID', FILTER_VALIDATE_INT);

            if (!$badgeID) {
                $_SESSION['error_message'] = "Invalid badge ID.";
                header("Location: brainpix_controller.php?tab=badges");
                exit;
            }

            $dbConnection->beginTransaction();
            try {
                // Delete user badges associated with this badge
                $stmt = $dbConnection->prepare("DELETE FROM brainpix_user_badges WHERE badgeID = :badgeID");
                $stmt->bindParam(':badgeID', $badgeID, PDO::PARAM_INT);
                $stmt->execute();

                // Delete badge image
                $stmt = $dbConnection->prepare("SELECT imageURL FROM brainpix_badges WHERE badgeID = :badgeID");
                $stmt->bindParam(':badgeID', $badgeID, PDO::PARAM_INT);
                $stmt->execute();
                $imageURL = $stmt->fetchColumn();
                if ($imageURL && file_exists('../../uploads/BrainPix/' . $imageURL)) {
                    unlink('../../uploads/BrainPix/' . $imageURL);
                }

                // Delete badge
                $stmt = $dbConnection->prepare("DELETE FROM brainpix_badges WHERE badgeID = :badgeID");
                $stmt->bindParam(':badgeID', $badgeID, PDO::PARAM_INT);
                $stmt->execute();

                $dbConnection->commit();
                $_SESSION['success_message'] = "Badge deleted successfully.";
            } catch (PDOException $e) {
                $dbConnection->rollBack();
                $_SESSION['error_message'] = "Error deleting badge: " . $e->getMessage();
            }
            header("Location: brainpix_controller.php?tab=badges");
            exit;

        case 'add_user_badge':
            $userID = filter_input(INPUT_POST, 'userID', FILTER_VALIDATE_INT);
            $badgeID = filter_input(INPUT_POST, 'badgeID', FILTER_VALIDATE_INT);
            $awardedDate = filter_input(INPUT_POST, 'awardedDate', FILTER_SANITIZE_STRING);

            if (!$userID || !$badgeID || !$awardedDate) {
                $_SESSION['error_message'] = "Invalid user ID, badge ID, or awarded date.";
                header("Location: brainpix_controller.php?tab=user_badges");
                exit;
            }

            // Validate awarded date
            try {
                $date = new DateTime($awardedDate);
                $awardedDate = $date->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Invalid awarded date format.";
                header("Location: brainpix_controller.php?tab=user_badges");
                exit;
            }

            // Check if user already has this badge
            $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM brainpix_user_badges WHERE userID = :userID AND badgeID = :badgeID");
            $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $stmt->bindParam(':badgeID', $badgeID, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "This user already has this badge.";
                header("Location: brainpix_controller.php?tab=user_badges");
                exit;
            }

            $stmt = $dbConnection->prepare("INSERT INTO brainpix_user_badges (userID, badgeID, awardedDate) VALUES (:userID, :badgeID, :awardedDate)");
            $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $stmt->bindParam(':badgeID', $badgeID, PDO::PARAM_INT);
            $stmt->bindParam(':awardedDate', $awardedDate);
            $stmt->execute();

            $_SESSION['success_message'] = "User badge added successfully.";
            header("Location: brainpix_controller.php?tab=user_badges");
            exit;

        case 'edit_user_badge':
            $userBadgeID = filter_input(INPUT_POST, 'userBadgeID', FILTER_VALIDATE_INT);
            $userID = filter_input(INPUT_POST, 'userID', FILTER_VALIDATE_INT);
            $badgeID = filter_input(INPUT_POST, 'badgeID', FILTER_VALIDATE_INT);
            $awardedDate = filter_input(INPUT_POST, 'awardedDate', FILTER_SANITIZE_STRING);

            if (!$userBadgeID || !$userID || !$badgeID || !$awardedDate) {
                $_SESSION['error_message'] = "Invalid user badge ID, user ID, badge ID, or awarded date.";
                header("Location: brainpix_controller.php?tab=user_badges");
                exit;
            }

            // Validate awarded date
            try {
                $date = new DateTime($awardedDate);
                $awardedDate = $date->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Invalid awarded date format.";
                header("Location: brainpix_controller.php?tab=user_badges");
                exit;
            }

            $stmt = $dbConnection->prepare("UPDATE brainpix_user_badges SET userID = :userID, badgeID = :badgeID, awardedDate = :awardedDate WHERE userBadgeID = :userBadgeID");
            $stmt->bindParam(':userBadgeID', $userBadgeID, PDO::PARAM_INT);
            $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $stmt->bindParam(':badgeID', $badgeID, PDO::PARAM_INT);
            $stmt->bindParam(':awardedDate', $awardedDate);
            $stmt->execute();

            $_SESSION['success_message'] = "User badge updated successfully.";
            header("Location: brainpix_controller.php?tab=user_badges");
            exit;

        case 'delete_user_badge':
            $userBadgeID = filter_input(INPUT_GET, 'userBadgeID', FILTER_VALIDATE_INT);

            if (!$userBadgeID) {
                $_SESSION['error_message'] = "Invalid user badge ID.";
                header("Location: brainpix_controller.php?tab=user_badges");
                exit;
            }

            $stmt = $dbConnection->prepare("DELETE FROM brainpix_user_badges WHERE userBadgeID = :userBadgeID");
            $stmt->bindParam(':userBadgeID', $userBadgeID, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['success_message'] = "User badge deleted successfully.";
            header("Location: brainpix_controller.php?tab=user_badges");
            exit;

        default:
            $_SESSION['error_message'] = "Invalid action.";
            header("Location: brainpix_controller.php");
            exit;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred: " . $e->getMessage();
    header("Location: brainpix_controller.php");
    exit;
}
?>