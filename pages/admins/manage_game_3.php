<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';

if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php");
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$_SESSION['error_message'] = '';
$_SESSION['success_message'] = '';

function uploadImage($tmpName, $fileName) {
    $uploadDir = '../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = uniqid() . '.' . $ext;
    $destination = $uploadDir . $newFileName;
    if (move_uploaded_file($tmpName, $destination)) {
        return $destination;
    }
    throw new Exception("Failed to upload image.");
}

try {
    $dbConnection->beginTransaction();

    switch ($action) {
        // Box Actions
        case 'add_multiple_boxes':
            foreach ($_POST['boxes'] as $index => $boxData) {
                $boxName = trim($boxData['boxName']);
                $description = trim($boxData['description']);
                $image = null;

                if (!$boxName || !$description) {
                    throw new Exception("Invalid input data for box at index $index.");
                }

                if (isset($_FILES['boxes']['name'][$index]['image']) && $_FILES['boxes']['error'][$index]['image'] === UPLOAD_ERR_OK) {
                    $image = uploadImage($_FILES['boxes']['tmp_name'][$index]['image'], $_FILES['boxes']['name'][$index]['image']);
                }

                $stmt = $dbConnection->prepare("
                    INSERT INTO word_boxes (boxName, description, image, archived)
                    VALUES (:boxName, :description, :image, 0)
                ");
                $stmt->bindParam(':boxName', $boxName, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':image', $image, PDO::PARAM_STR);
                $stmt->execute();
            }
            $_SESSION['success_message'] = "Boxes added successfully.";
            break;

        case 'edit_box':
            $boxID = filter_var($_POST['boxID'], FILTER_VALIDATE_INT);
            $boxName = trim($_POST['boxName']);
            $description = trim($_POST['description']);
            $archived = filter_var($_POST['archived'], FILTER_VALIDATE_INT);
            $image = null;

            if (!$boxID || !$boxName || !$description || !in_array($archived, [0, 1])) {
                throw new Exception("Invalid input data for box.");
            }

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image = uploadImage($_FILES['image']['tmp_name'], $_FILES['image']['name']);
            } else {
                $stmt = $dbConnection->prepare("SELECT image FROM word_boxes WHERE boxID = :boxID");
                $stmt->bindParam(':boxID', $boxID, PDO::PARAM_INT);
                $stmt->execute();
                $image = $stmt->fetchColumn();
            }

            $stmt = $dbConnection->prepare("
                UPDATE word_boxes
                SET boxName = :boxName, description = :description, image = :image, archived = :archived
                WHERE boxID = :boxID
            ");
            $stmt->bindParam(':boxID', $boxID, PDO::PARAM_INT);
            $stmt->bindParam(':boxName', $boxName, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':image', $image, PDO::PARAM_STR);
            $stmt->bindParam(':archived', $archived, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Box updated successfully.";
            break;

        case 'archive_box':
            $boxID = filter_var($_GET['boxID'], FILTER_VALIDATE_INT);
            if (!$boxID) {
                throw new Exception("Invalid box ID.");
            }

            $stmt = $dbConnection->prepare("UPDATE word_boxes SET archived = 1 WHERE boxID = :boxID");
            $stmt->bindParam(':boxID', $boxID, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Box archived successfully.";
            break;

        // Category Actions
        case 'add_multiple_categories':
            foreach ($_POST['categories'] as $index => $categoryData) {
                $boxID = filter_var($categoryData['boxID'], FILTER_VALIDATE_INT);
                $categoryName = trim($categoryData['categoryName']);
                $description = trim($categoryData['description']);
                $image = null;

                if (!$boxID || !$categoryName || !$description) {
                    throw new Exception("Invalid input data for category at index $index.");
                }

                if (isset($_FILES['categories']['name'][$index]['image']) && $_FILES['categories']['error'][$index]['image'] === UPLOAD_ERR_OK) {
                    $image = uploadImage($_FILES['categories']['tmp_name'][$index]['image'], $_FILES['categories']['name'][$index]['image']);
                }

                $stmt = $dbConnection->prepare("
                    INSERT INTO word_categories (boxID, categoryName, description, image, archived)
                    VALUES (:boxID, :categoryName, :description, :image, 0)
                ");
                $stmt->bindParam(':boxID', $boxID, PDO::PARAM_INT);
                $stmt->bindParam(':categoryName', $categoryName, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':image', $image, PDO::PARAM_STR);
                $stmt->execute();
            }
            $_SESSION['success_message'] = "Categories added successfully.";
            break;

        case 'edit_category':
            $categoryID = filter_var($_POST['categoryID'], FILTER_VALIDATE_INT);
            $boxID = filter_var($_POST['boxID'], FILTER_VALIDATE_INT);
            $categoryName = trim($_POST['categoryName']);
            $description = trim($_POST['description']);
            $archived = filter_var($_POST['archived'], FILTER_VALIDATE_INT);
            $image = null;

            if (!$categoryID || !$boxID || !$categoryName || !$description || !in_array($archived, [0, 1])) {
                throw new Exception("Invalid input data for category.");
            }

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image = uploadImage($_FILES['image']['tmp_name'], $_FILES['image']['name']);
            } else {
                $stmt = $dbConnection->prepare("SELECT image FROM word_categories WHERE categoryID = :categoryID");
                $stmt->bindParam(':categoryID', $categoryID, PDO::PARAM_INT);
                $stmt->execute();
                $image = $stmt->fetchColumn();
            }

            $stmt = $dbConnection->prepare("
                UPDATE word_categories
                SET boxID = :boxID, categoryName = :categoryName, description = :description, image = :image, archived = :archived
                WHERE categoryID = :categoryID
            ");
            $stmt->bindParam(':categoryID', $categoryID, PDO::PARAM_INT);
            $stmt->bindParam(':boxID', $boxID, PDO::PARAM_INT);
            $stmt->bindParam(':categoryName', $categoryName, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':image', $image, PDO::PARAM_STR);
            $stmt->bindParam(':archived', $archived, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Category updated successfully.";
            break;

        case 'archive_category':
            $categoryID = filter_var($_GET['categoryID'], FILTER_VALIDATE_INT);
            if (!$categoryID) {
                throw new Exception("Invalid category ID.");
            }

            $stmt = $dbConnection->prepare("UPDATE word_categories SET archived = 1 WHERE categoryID = :categoryID");
            $stmt->bindParam(':categoryID', $categoryID, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Category archived successfully.";
            break;

        // Word Actions
        case 'add_multiple_words':
            $selectedCategoryID = filter_var($_POST['selectedCategoryID'], FILTER_VALIDATE_INT);
            if (!$selectedCategoryID) {
                throw new Exception("Invalid or missing category selection.");
            }
            foreach ($_POST['words'] as $index => $wordData) {
                $word = trim($wordData['word']);
                $hint = trim($wordData['hint']);
                if (!$word || !$hint) {
                    throw new Exception("Invalid input data for word at index $index.");
                }
                $stmt = $dbConnection->prepare("
                    INSERT INTO word_words (categoryID, word, hint, archived)
                    VALUES (:categoryID, :word, :hint, 0)
                ");
                $stmt->bindParam(':categoryID', $selectedCategoryID, PDO::PARAM_INT);
                $stmt->bindParam(':word', $word, PDO::PARAM_STR);
                $stmt->bindParam(':hint', $hint, PDO::PARAM_STR);
                $stmt->execute();
            }
            $_SESSION['success_message'] = "Words added successfully.";
            break;

        case 'edit_word':
            $wordID = filter_var($_POST['wordID'], FILTER_VALIDATE_INT);
            $categoryID = filter_var($_POST['categoryID'], FILTER_VALIDATE_INT);
            $word = trim($_POST['word']);
            $hint = trim($_POST['hint']);
            $archived = filter_var($_POST['archived'], FILTER_VALIDATE_INT);

            if (!$wordID || !$categoryID || !$word || !$hint || !in_array($archived, [0, 1])) {
                throw new Exception("Invalid input data for word.");
            }

            $stmt = $dbConnection->prepare("
                UPDATE word_words
                SET categoryID = :categoryID, word = :word, hint = :hint, archived = :archived
                WHERE wordID = :wordID
            ");
            $stmt->bindParam(':wordID', $wordID, PDO::PARAM_INT);
            $stmt->bindParam(':categoryID', $categoryID, PDO::PARAM_INT);
            $stmt->bindParam(':word', $word, PDO::PARAM_STR);
            $stmt->bindParam(':hint', $hint, PDO::PARAM_STR);
            $stmt->bindParam(':archived', $archived, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Word updated successfully.";
            break;

        case 'archive_word':
            $wordID = filter_var($_GET['wordID'], FILTER_VALIDATE_INT);
            if (!$wordID) {
                throw new Exception("Invalid word ID.");
            }

            $stmt = $dbConnection->prepare("UPDATE word_words SET archived = 1 WHERE wordID = :wordID");
            $stmt->bindParam(':wordID', $wordID, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Word archived successfully.";
            break;

        // Badge Actions
        case 'add_multiple_badges':
            foreach ($_POST['badges'] as $index => $badgeData) {
                $level = filter_var($badgeData['level'], FILTER_VALIDATE_INT);
                $badgeName = trim($badgeData['badgeName']);
                $icon = trim($badgeData['icon']);
                $description = trim($badgeData['description']);

                if ($level === false || $level < 0 || !$badgeName || !$icon || !$description) {
                    throw new Exception("Invalid input data for badge at index $index.");
                }

                $stmt = $dbConnection->prepare("
                    INSERT INTO word_badges (level, badgeName, icon, description, archived)
                    VALUES (:level, :badgeName, :icon, :description, 0)
                ");
                $stmt->bindParam(':level', $level, PDO::PARAM_INT);
                $stmt->bindParam(':badgeName', $badgeName, PDO::PARAM_STR);
                $stmt->bindParam(':icon', $icon, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->execute();
            }
            $_SESSION['success_message'] = "Badges added successfully.";
            break;

        case 'edit_badge':
            $level = filter_var($_POST['level'], FILTER_VALIDATE_INT);
            $badgeName = trim($_POST['badgeName']);
            $icon = trim($_POST['icon']);
            $description = trim($_POST['description']);
            $archived = filter_var($_POST['archived'], FILTER_VALIDATE_INT);

            if ($level === false || $level < 0 || !$badgeName || !$icon || !$description || !in_array($archived, [0, 1])) {
                throw new Exception("Invalid input data for badge.");
            }

            $stmt = $dbConnection->prepare("
                UPDATE word_badges
                SET badgeName = :badgeName, icon = :icon, description = :description, archived = :archived
                WHERE level = :level
            ");
            $stmt->bindParam(':level', $level, PDO::PARAM_INT);
            $stmt->bindParam(':badgeName', $badgeName, PDO::PARAM_STR);
            $stmt->bindParam(':icon', $icon, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':archived', $archived, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Badge updated successfully.";
            break;

        case 'archive_badge':
            $level = filter_var($_GET['level'], FILTER_VALIDATE_INT);
            if ($level === false || $level < 0) {
                throw new Exception("Invalid badge level.");
            }

            $stmt = $dbConnection->prepare("UPDATE word_badges SET archived = 1 WHERE level = :level");
            $stmt->bindParam(':level', $level, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Badge archived successfully.";
            break;

        default:
            throw new Exception("Invalid action.");
    }

    $dbConnection->commit();
    header("Location: game_controller_3.php");
    exit;

} catch (Exception $e) {
    $dbConnection->rollBack();
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: game_controller_3.php");
    exit;
}
?>