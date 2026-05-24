<?php
if (isset($_POST['registerSection'])) {
    $sectionCode = $_POST['sectionCode'];
    $sectionName = $_POST['sectionName'];
    $strandID = $_POST['strandID'];  
    $gradeLevel = $_POST['gradeLevel']; 
    $semester = $_POST['semester'];  

    $sql = "INSERT INTO sections (sectionCode, sectionName, strandID, gradeLevel, semester, dateCreated) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $sectionCode);
    $stmt->bindParam(2, $sectionName);
    $stmt->bindParam(3, $strandID);
    $stmt->bindParam(4, $gradeLevel);
    $stmt->bindParam(5, $semester);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Section registered successfully!";
    } else {
        $_SESSION['error_message'] = "Error registering section. Please try again.";
    }

    $stmt->closeCursor();
    header("Location: section.php");  
    exit;
}
?>