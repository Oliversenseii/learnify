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
    // User Creation Over Time
    $stmt = $dbConnection->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
        FROM users 
        WHERE archived = 0 AND userType != 'SuperAdmin'
        GROUP BY month
        ORDER BY month
    ");
    $stmt->execute();
    $creationData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total creations
    $totalCreations = array_sum(array_column($creationData, 'count'));

    // Sanitize admin name for filename
    $safeAdminName = preg_replace('/[^A-Za-z0-9_-]/', '_', $_SESSION['firstName']);
    $reportDate = date('F j, Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnify User Creation Report</title>
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
            padding: 12px;
            box-sizing: border-box;
        }
        .report-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 16px;
            margin-bottom: 16px;
        }
        .report-border {
            border: 2px solid #2c3e50;
            padding: 12px;
            border-radius: 6px;
        }
        .report-header {
            text-align: center;
            margin-bottom: 12px;
        }
        .report-header h1 {
            color: #2c3e50;
            font-size: clamp(1.8rem, 4vw, 3rem);
            border-bottom: 1px solid #2c3e50;
            margin: 0;
        }
        .report-content {
            margin-bottom: 16px;
            width: 100%;
            overflow-x: auto;
        }
        .report-content h2 {
            color: #2c3e50;
            font-size: clamp(1.4rem, 3vw, 2.6rem);
            margin-bottom: 8px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            min-width: 550px; 
            font-size: clamp(1rem, 3vw, 1.2rem);
        }
        .report-table th,
        .report-table td {
            border: 1px solid #ddd;
            padding: 7px;
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
            margin-top: 24px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .signature-line {
            text-align: center;
            width: 48%;
            box-sizing: border-box;
        }
        .signature-graphic {
            font-family: 'Cedarville Cursive', cursive;
            font-size: clamp(1.1rem, 2.8vw, 1.3rem);
            color: #2c3e50;
            margin-bottom: 4px;
        }
        .signature-graphic .name {
            font-size: clamp(1.3rem, 3.2vw, 1.5rem);
            text-transform: uppercase;
        }
        .signature-line div:last-child {
            font-size: clamp(0.75rem, 1.8vw, 0.85rem);
            color: #555;
        }
        .report-footer {
            text-align: center;
           font-size: clamp(1rem, 3vw, 1.2rem);
            color: #777;
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px solid #ddd;
        }
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .btn {
            padding: 7px 12px;
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
            min-width: 120px; 
        }
        .btn:hover {
            background-color: #34495e;
        }
        @media screen and (max-width: 768px) {
            .report-wrapper {
                padding: 8px;
            }
            .report-container {
                padding: 12px;
                border-radius: 6px;
            }
            .report-border {
                padding: 8px;
            }
            .report-table {
                min-width: 450px;
            }
            .report-table th,
            .report-table td {
                padding: 5px;
                font-size: clamp(0.75rem, 1.8vw, 0.85rem);
            }
            .report-header h1 {
                font-size: clamp(1.4rem, 3.2vw, 1.8rem);
            }
            .report-content h2 {
                font-size: clamp(1.2rem, 2.5vw, 1.4rem);
            }
            .signature-section {
                flex-direction: column;
                align-items: center;
                gap: 12px;
            }
            .signature-line {
                width: 100%;
                margin-bottom: 8px;
            }
            .btn-container {
                flex-direction: column;
                align-items: stretch;
            }
            .btn {
                padding: 6px;
                font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            }
        }
        @media screen and (max-width: 460px) {
            .report-table {
                min-width: 350px;
            }
            .report-table th,
            .report-table td {
                padding: 4px;
                font-size: clamp(0.7rem, 1.6vw, 0.8rem);
            }
            .report-header h1 {
                font-size: clamp(1.3rem, 3vw, 1.6rem);
            }
            .report-content h2 {
                font-size: clamp(1.1rem, 2.2vw, 1.3rem);
            }
            .report-footer {
                font-size: clamp(0.75rem, 1.8vw, 0.85rem);
            }
            .signature-graphic {
                font-size: clamp(1rem, 2.5vw, 1.2rem);
            }
            .signature-graphic .name {
                font-size: clamp(1.2rem, 2.8vw, 1.4rem);
            }
            .signature-line div:last-child {
                font-size: clamp(0.7rem, 1.6vw, 0.8rem);
            }
            .btn {
                padding: 5px;
                font-size: clamp(0.75rem, 1.6vw, 0.85rem);
                min-width: 100px;
            }
        }
        @media print {
            .btn-container {
                display: none;
            }
            .report-wrapper {
                padding: 0;
            }
            .report-container {
                box-shadow: none;
                margin: 0;
                padding: 8px;
            }
            .report-border {
                padding: 6px;
            }
            .report-table {
                min-width: 100%;
            }
            .report-table th,
            .report-table td {
                font-size: 0.85rem;
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="report-wrapper">
        <div class="report-container" id="report">
            <div class="report-border">
                <div class="report-header">
                    <h1>Learnify User Creation Report</h1>
                </div>
                <div class="report-content">
                    <h2>User Creation Over Time</h2>
                    <table class="report-table">
                        <tr>
                            <th>Month</th>
                            <th>Count</th>
                        </tr>
                        <?php foreach ($creationData as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['month']); ?></td>
                                <td><?php echo htmlspecialchars($row['count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><strong>Total Creations</strong></td>
                            <td><strong><?php echo htmlspecialchars($totalCreations); ?></strong></td>
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
                            filename: 'Learnify_Creation_Report_<?php echo $safeAdminName; ?>_<?php echo date('Ymd_His'); ?>.pdf',
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