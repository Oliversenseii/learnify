<?php
ob_start();
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

// Validate session
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'Admin') {
    ob_end_clean();
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false) {
    ob_end_clean();
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

try {
    // User counts
    $userCounts = [
        'admins' => 'Admin',
        'professors' => 'Professor',
        'students' => 'Student',
    ];

    $sexCounts = [
        'admins' => ['male' => 0, 'female' => 0],
        'professors' => ['male' => 0, 'female' => 0],
        'students' => ['male' => 0, 'female' => 0],
    ];

    foreach ($userCounts as $key => $userType) {
        $stmt = $dbConnection->prepare("SELECT sex, COUNT(*) as count FROM users WHERE userType = :userType AND archived = 0 GROUP BY sex");
        $stmt->bindParam(':userType', $userType, PDO::PARAM_STR);
        $stmt->execute();
        $sexData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sexData as $row) {
            if (strtolower($row['sex']) === 'male') {
                $sexCounts[$key]['male'] = (int)$row['count'];
            } elseif (strtolower($row['sex']) === 'female') {
                $sexCounts[$key]['female'] = (int)$row['count'];
            }
        }
    }

    // Calculate totals
    $totalMale = $sexCounts['admins']['male'] + $sexCounts['professors']['male'] + $sexCounts['students']['male'];
    $totalFemale = $sexCounts['admins']['female'] + $sexCounts['professors']['female'] + $sexCounts['students']['female'];

    // Sanitize admin name for filename
    $safeAdminName = preg_replace('/[^A-Za-z0-9_-]/', '_', $_SESSION['firstName']);
    $reportDate = date('F j, Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnify Sex Distribution Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&family=Cedarville+Cursive&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .report-wrapper {
            max-width: 900px;
            width: 100%;
            padding: clamp(10px, 2vw, 15px);
            box-sizing: border-box;
        }
        .report-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: clamp(15px, 3vw, 20px);
            margin-bottom: 15px;
        }
        .report-border {
            border: 2px solid #2c3e50;
            padding: clamp(10px, 2vw, 15px);
            border-radius: 6px;
        }
        .report-header {
            text-align: center;
            margin-bottom: clamp(10px, 2vw, 15px);
        }
        .report-header h1 {
            color: #2c3e50;
            font-size: clamp(1.8rem, 4vw, 3rem);
            border-bottom: 1px solid #2c3e50;
            margin: 0;
        }
        .report-content {
            margin-bottom: clamp(15px, 3vw, 20px);
            width: 100%;
            overflow-x: auto;
        }
        .report-content h2 {
            color: #2c3e50;
            font-size: clamp(1.4rem, 3vw, 2.6rem);
            margin-bottom: clamp(6px, 1.5vw, 8px);
        }
        .report-content h3 {
            color: #34495e;
            font-size: clamp(1.1rem, 2.5vw, 1.3rem);
            margin-top: clamp(10px, 2vw, 15px);
            margin-bottom: clamp(6px, 1.5vw, 8px);
        }
        .report-content p {
            color: #555;
            font-size: clamp(1rem, 3vw, 1.2rem);
            margin: clamp(3px, 0.8vw, 5px) 0;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: clamp(8px, 2vw, 10px);
            min-width: 300px;
        }
        .report-table th,
        .report-table td {
            border: 1px solid #ddd;
            padding: clamp(6px, 1.5vw, 8px);
            text-align: left;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }
        .report-table th {
            background-color: #2c3e50;
            color: #fff;
            font-weight: 500;
        }
        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .report-table tr:last-child {
            font-weight: bold;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: clamp(20px, 4vw, 25px);
            flex-wrap: wrap;
            gap: clamp(8px, 2vw, 12px);
        }
        .signature-line {
            text-align: center;
            width: 48%;
            min-width: 120px;
        }
        .signature-graphic {
            font-family: 'Cedarville Cursive', cursive;
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            color: #2c3e50;
            margin-bottom: clamp(3px, 0.8vw, 5px);
        }
        .signature-graphic .name {
            font-size: clamp(1.4rem, 3.5vw, 1.6rem);
            text-transform: uppercase;
        }
        .signature-line div:last-child {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            color: #555;
        }
        .report-footer {
            text-align: center;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: #777;
            margin-top: clamp(10px, 2vw, 15px);
            padding-top: clamp(6px, 1.5vw, 8px);
            border-top: 1px solid #ddd;
        }
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: clamp(10px, 2vw, 15px);
            flex-wrap: wrap;
            gap: clamp(8px, 2vw, 12px);
        }
        .btn {
            padding: clamp(6px, 1.5vw, 8px) clamp(12px, 2.5vw, 15px);
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-family: 'Poppins', sans-serif;
            color: #fff;
            background-color: #2c3e50;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s;
            flex: 1;
            text-align: center;
            min-width: 100px;
        }
        .btn:hover {
            background-color: #34495e;
        }
        @media screen and (max-width: 768px) {
            .report-wrapper {
                padding: clamp(8px, 1.5vw, 10px);
            }
            .report-container {
                padding: clamp(10px, 2vw, 15px);
                border-radius: 6px;
            }
            .report-border {
                padding: clamp(8px, 1.5vw, 10px);
            }
            .report-table {
                min-width: 250px;
            }
            .report-table th,
            .report-table td {
                padding: clamp(4px, 1vw, 6px);
                font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            }
            .report-header h1 {
                font-size: clamp(1.4rem, 3.5vw, 1.8rem);
            }
            .report-content h2 {
                font-size: clamp(1.2rem, 2.8vw, 1.4rem);
            }
            .report-content h3 {
                font-size: clamp(1rem, 2.2vw, 1.2rem);
            }
            .report-content p {
                font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            }
            .signature-section {
                flex-direction: column;
                align-items: center;
                gap: clamp(6px, 1.5vw, 8px);
            }
            .signature-line {
                width: 100%;
                min-width: 100px;
                margin-bottom: clamp(6px, 1.5vw, 8px);
            }
            .btn-container {
                flex-direction: column;
                align-items: stretch;
            }
            .btn {
                padding: clamp(5px, 1.2vw, 7px) clamp(10px, 2vw, 12px);
                font-size: clamp(0.85rem, 1.8vw, 0.95rem);
                min-width: 80px;
            }
        }
        @media screen and (max-width: 460px) {
            .report-table {
                min-width: 200px;
            }
            .report-table th,
            .report-table td {
                padding: clamp(3px, 0.8vw, 4px);
                font-size: clamp(0.75rem, 1.6vw, 0.85rem);
            }
            .report-header h1 {
                font-size: clamp(1.3rem, 3vw, 1.6rem);
            }
            .report-content h2 {
                font-size: clamp(1.1rem, 2.5vw, 1.3rem);
            }
            .report-content h3 {
                font-size: clamp(0.9rem, 2vw, 1.1rem);
            }
            .report-content p {
                font-size: clamp(0.75rem, 1.6vw, 0.85rem);
            }
            .report-footer {
                font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            }
            .signature-graphic {
                font-size: clamp(1.1rem, 2.5vw, 1.3rem);
            }
            .signature-graphic .name {
                font-size: clamp(1.2rem, 3vw, 1.4rem);
            }
            .signature-line div:last-child {
                font-size: clamp(0.75rem, 1.6vw, 0.85rem);
            }
            .btn {
                padding: clamp(4px, 1vw, 6px) clamp(8px, 1.8vw, 10px);
                font-size: clamp(0.8rem, 1.6vw, 0.9rem);
                min-width: 70px;
            }
        }
        @media print {
            .btn-container {
                display: none;
            }
            .report-wrapper {
                padding: 0;
                max-width: 210mm;
            }
            .report-container {
                box-shadow: none;
                margin: 0;
                padding: 3mm;
                max-width: 210mm;
                min-height: 297mm;
            }
            .report-border {
                padding: 2mm;
            }
            .report-table {
                min-width: 100%;
            }
            .report-table th,
            .report-table td {
                font-size: 8pt;
                padding: 0.8mm;
            }
            .report-header h1 {
                font-size: 14pt;
            }
            .report-content h2 {
                font-size: 12pt;
            }
            .report-content h3 {
                font-size: 10pt;
            }
            .report-content p {
                font-size: 8pt;
            }
            .signature-section {
                max-width: 60mm;
            }
            .signature-graphic {
                font-size: 8pt;
            }
            .signature-graphic .name {
                font-size: 8pt;
            }
            .signature-line div:last-child {
                font-size: 8pt;
            }
            .report-footer {
                font-size: 6pt;
            }
        }
    </style>
</head>
<body>
    <div class="report-wrapper">
        <div class="report-container" id="report">
            <div class="report-border">
                <div class="report-header">
                    <h1>Learnify Sex Distribution Report</h1>
                </div>
                <div class="report-content">
                    <h2>Sex Distribution</h2>
                    <table class="report-table">
                        <tr>
                            <th>User Type</th>
                            <th>Male</th>
                            <th>Female</th>
                        </tr>
                        <tr>
                            <td>Admins</td>
                            <td><?php echo htmlspecialchars($sexCounts['admins']['male']); ?></td>
                            <td><?php echo htmlspecialchars($sexCounts['admins']['female']); ?></td>
                        </tr>
                        <tr>
                            <td>Professors</td>
                            <td><?php echo htmlspecialchars($sexCounts['professors']['male']); ?></td>
                            <td><?php echo htmlspecialchars($sexCounts['professors']['female']); ?></td>
                        </tr>
                        <tr>
                            <td>Students</td>
                            <td><?php echo htmlspecialchars($sexCounts['students']['male']); ?></td>
                            <td><?php echo htmlspecialchars($sexCounts['students']['female']); ?></td>
                        </tr>
                        <tr>
                            <td>Total</td>
                            <td><?php echo htmlspecialchars($totalMale); ?></td>
                            <td><?php echo htmlspecialchars($totalFemale); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="report-footer">
                    Generated by Learnify System (Admin) | <?php echo htmlspecialchars($reportDate); ?>
                </div>
            </div>
        </div>
        <div class="btn-container">
            <button id="downloadBtn" class="btn">Download PDF</button>
            <a href="adminDash.php" class="btn">Back to Dashboard</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof html2pdf === 'undefined') {
                console.error('html2pdf.js failed to load.');
                alert('Error: PDF generation library not loaded.');
                return;
            }

            const downloadBtn = document.getElementById('downloadBtn');
            if (!downloadBtn) {
                console.error('Download button not found.');
                alert('Error: Download button not found.');
                return;
            }

            downloadBtn.addEventListener('click', function() {
                const element = document.getElementById('report');
                if (!element) {
                    console.error('Report element not found.');
                    alert('Error: Report content not found.');
                    return;
                }

                try {
                    html2pdf()
                        .set({
                            margin: [3, 3, 3, 3],
                            filename: 'Learnify_Sex_Report_<?php echo $safeAdminName; ?>_<?php echo date('Ymd_His'); ?>.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2, dpi: 300, useCORS: true },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                            pagebreak: { mode: ['css', 'legacy'], avoid: ['h1', 'h2', 'h3', 'table', '.signature-section'] }
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
            });
        });
    </script>
</body>
</html>
<?php
    ob_end_flush();
    exit;
} catch (PDOException $e) {
    ob_end_clean();
    error_log("Report Generation Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error: Unable to generate report.";
    header("Location: adminDash.php");
    exit;
} catch (Exception $e) {
    ob_end_clean();
    error_log("Report Generation Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error generating report: " . $e->getMessage();
    header("Location: adminDash.php");
    exit;
}
?>