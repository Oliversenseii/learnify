<?php
if (isset($_POST['registerStrand'])) {
    $strandCode = $_POST['strandCode'];
    $strandName = $_POST['strandName'];
    $trackName = $_POST['trackName'];
    $description = $_POST['description'];  

    $sql = "INSERT INTO track_strands (strandCode, strandName, trackName, description, dateCreated) 
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(1, $strandCode);
    $stmt->bindParam(2, $strandName);
    $stmt->bindParam(3, $trackName);
    $stmt->bindParam(4, $description); 

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Track and Strand registered successfully!";
    } else {
        $_SESSION['error_message'] = "Error registering track and strand. Please try again.";
    }
    
    $stmt->closeCursor();
    header("Location: track-strands.php"); 
    exit;
}
?>
