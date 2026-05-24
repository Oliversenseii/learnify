<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './check_status.php';

// Validate session
if (!isset($_SESSION['userID']) || !isset($_GET['strandID'])) {
    header("Location: gameDash.php");
    exit;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
$strandID = filter_var($_GET['strandID'], FILTER_VALIDATE_INT);
if ($userID === false || $strandID === false) {
    session_destroy();
    header("Location: gameDash.php");
    exit;
}

// Get user details and verify certificate
try {
    $stmt = $dbConnection->prepare("SELECT firstName, lastName FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header("Location: gameDash.php");
        exit;
    }
    // Combine and capitalize full name
    $studentName = strtoupper(htmlspecialchars(trim($user['firstName'] . ' ' . $user['lastName'])));

    // Get strand details
    $strandStmt = $dbConnection->prepare("SELECT strandName FROM track_strands WHERE strandID = :strandID AND archived = 0");
    $strandStmt->bindParam(':strandID', $strandID, PDO::PARAM_INT);
    $strandStmt->execute();
    $strand = $strandStmt->fetch(PDO::FETCH_ASSOC);
    if (!$strand) {
        header("Location: gameDash.php");
        exit;
    }
    $strandName = htmlspecialchars($strand['strandName']);

    // Verify certificate exists
    $certStmt = $dbConnection->prepare("SELECT certificateID, issueDate FROM game_certificates WHERE userID = :userID AND strandID = :strandID");
    $certStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $certStmt->bindParam(':strandID', $strandID, PDO::PARAM_INT);
    $certStmt->execute();
    $certificate = $certStmt->fetch(PDO::FETCH_ASSOC);
    if (!$certificate) {
        $_SESSION['error_message'] = "No certificate found for this strand.";
        header("Location: gameDash.php");
        exit;
    }
    $certificateDate = date('F j, Y', strtotime($certificate['issueDate']));

    // Count completed strands for badge
    $completedStmt = $dbConnection->prepare("SELECT COUNT(*) as completed FROM game_certificates WHERE userID = :userID");
    $completedStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $completedStmt->execute();
    $completedStrands = $completedStmt->fetch(PDO::FETCH_ASSOC)['completed'];

    // Fetch badge based on completed strands
    $badgeStmt = $dbConnection->prepare("
        SELECT level, badgeName, icon, description 
        FROM game_badges 
        WHERE level = :level
    ");
    $badgeLevel = min($completedStrands, 7); // Cap at level 7
    $badgeStmt->bindParam(':level', $badgeLevel, PDO::PARAM_INT);
    $badgeStmt->execute();
    $currentBadge = $badgeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentBadge) {
        $currentBadge = [
            'level' => 0,
            'badgeName' => 'No Badge',
            'icon' => '❌',
            'description' => 'Complete strands to earn your first badge!'
        ];
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred.";
    header("Location: gameDash.php");
    exit;
}

$safeStudentName = preg_replace('/[^A-Za-z0-9_-]/', '_', $studentName);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Great+Vibes&family=Cedarville+Cursive&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #ece9e6, #ffffff);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .certificate-wrapper {
            width: 100%;
            max-width: 297mm;
            margin: 0 auto;
            text-align: center;
            box-sizing: border-box;
        }
        .certificate-container {
            width: 270mm;
            min-height: 190mm;
            background: linear-gradient(to bottom, #f8f1e9, #f5f5f5);
            border: 4mm solid #2c3e50;
            border-radius: 5mm;
            box-shadow: 0 0 10mm rgba(0, 0, 0, 0.2);
            padding: 10mm;
            margin: 0 auto;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        .certificate-border {
            border: 1mm double #3498db;
            padding: 8mm;
            height: calc(100% - 16mm);
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-sizing: border-box;
        }
        .certificate-header h1 {
            font-family: 'Great Vibes', cursive;
            font-size: 14mm;
            color: #2c3e50;
            margin: 0;
            text-shadow: 0.3mm 0.3mm 0.5mm rgba(0, 0, 0, 0.1);
        }
        .certificate-content h2 {
            font-family: 'Great Vibes', cursive;
            font-size: 10mm;
            color: #3498db;
            margin: 4mm 0;
        }
        .certificate-content h3 {
            font-size: 8mm;
            color: #2c3e50;
            margin: 3mm 0;
            text-decoration: underline;
        }
        .certificate-content p {
            font-size: 4.5mm;
            color: #34495e;
            margin: 3mm 0;
        }
        .badge-emoji {
            font-size: 15mm;
            margin: 4mm 0;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 8mm;
            width: 100%;
            max-width: 120mm;
            margin-left: auto;
            margin-right: auto;
        }
        .signature-line {
            text-align: center;
            flex: 1;
        }
        .signature-line div {
            width: 50mm;
            margin: 0 auto;
            padding-top: 1mm;
            font-size: 4mm;
            color: #34495e;
        }
        .signature-graphic {
            border-bottom: 0.3mm solid #2c3e50;
            font-family: 'Cedarville Cursive', cursive;
            font-size: 6mm;
            color: #2c3e50;
            margin-bottom: 1mm;
        }
        .signature-graphic .name {
            text-transform: uppercase;
            font-family: 'Poppins';
        }
        .certificate-footer {
            position: absolute;
            bottom: 3mm;
            width: 100%;
            text-align: center;
            font-size: 3mm;
            color: #7f8c8d;
        }
        .btn-container {
            margin-top: 5mm;
        }
        .btn {
            display: inline-block;
            padding: 3mm 6mm;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border-radius: 1.5mm;
            font-size: 4.5mm;
            max-width: 600px;
            width: fit-content;
            border: none;
            margin: 1.5mm;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        .btn-pdf {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        .btn-pdf:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        @media print {
            body { background: none; }
            .certificate-wrapper { padding: 0; margin: 0; }
            .certificate-container { box-shadow: none; }
            .btn-container { display: none; }
        }
        @media (max-width: 768px) {
            .certificate-wrapper { padding: 4mm; }
            .certificate-container { width: 100%; padding: 6mm; min-height: 150mm; }
            .certificate-border { padding: 5mm; }
            .certificate-header h1 { font-size: 10mm; }
            .certificate-content h2 { font-size: 8mm; }
            .certificate-content h3 { font-size: 6mm; }
            .certificate-content p { font-size: 4mm; }
            .badge-emoji { font-size: 12mm; }
            .signature-section { flex-direction: column; align-items: center; gap: 4mm; max-width: 100%; }
            .signature-line div { width: 40mm; }
        }
        @media (max-width: 480px) {
            .certificate-container { padding: 4mm; min-height: 120mm; }
            .certificate-border { padding: 3mm; }
            .btn { padding: 2mm 4mm; font-size: 4mm; }
        }
    </style>
</head>
<body>
    <div class="certificate-wrapper">
        <div class="certificate-container" id="certificate">
            <div class="certificate-border">
                <div class="certificate-header">
                    <h1>Certificate of Completion</h1>
                </div>
                <div class="certificate-content">
                    <h2>Congratulations!</h2>
                    <p>This certificate is proudly awarded to:</p>
                    <h3><?php echo $studentName; ?></h3>
                    <p>For successfully completing the <?php echo $strandName; ?> strand.</p>
                    <p>Achieved Rank: <?php echo htmlspecialchars($currentBadge['badgeName']); ?></p>
                    <div class="badge-emoji"><?php echo htmlspecialchars($currentBadge['icon']); ?></div>
                    <p>Date: <?php echo $certificateDate; ?></p>
                    <div class="signature-section">
                        <div class="signature-line">
                            <div class="signature-graphic">Samson <br><span class="name">BENCH SAMSON</span></div>
                            <div>Game Administrator</div>
                        </div>
                        <div class="signature-line">
                            <div class="signature-graphic">Oliversenseii <br><span class="name">JOHN NEMUEL MARTILLOS</span></div>
                            <div>Game Ambassador</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="btn-container">
            <button class="btn btn-pdf" onclick="downloadPDF()">Download PDF</button>
            <button class="btn btn-back" onclick="goBack()">Back to Game Dashboard</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof html2pdf === 'undefined') {
                console.error('html2pdf.js failed to load.');
                alert('Error: PDF generation library not loaded.');
                return;
            }

            window.downloadPDF = function() {
                const element = document.getElementById('certificate');
                if (!element) {
                    console.error('Certificate element not found.');
                    alert('Error: Certificate content not found.');
                    return;
                }

                try {
                    html2pdf()
                        .set({
                            margin: [5, 5, 5, 5],
                            filename: 'Learnify_Certificate_<?php echo $safeStudentName; ?>_<?php echo $strandID; ?>.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2, dpi: 300, logging: true },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
                            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                        })
                        .from(element)
                        .toPdf()
                        .save()
                        .catch(function(error) {
                            console.error('PDF generation failed:', error);
                            alert('Error generating PDF: ' + error.message);
                        });
                } catch (error) {
                    console.error('Error initiating PDF generation:', error);
                    alert('Error initiating PDF generation: ' + error.message);
                }
            };
        });

        function goBack() {
            window.location.href = 'gameDash.php';
        }
    </script>
</body>
</html>