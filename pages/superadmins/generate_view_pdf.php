<?php
ob_start();
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

// Validate session
if (!isset($_SESSION['userID']) || $_SESSION['userType'] !== 'SuperAdmin') {
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
    // Dashboard View Statistics
    $dashboardViews = ['table' => 0, 'card' => 0];
    $stmt = $dbConnection->prepare("
        SELECT dashboard_view, COUNT(*) as count
        FROM users 
        WHERE archived = 0
        GROUP BY dashboard_view
    ");
    $stmt->execute();
    $viewData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($viewData as $row) {
        if (isset($dashboardViews[$row['dashboard_view']])) {
            $dashboardViews[$row['dashboard_view']] = (int)$row['count'];
        }
    }

    // Total views
    $totalViews = array_sum($dashboardViews);

    // Sanitize admin name for filename
    $safeAdminName = preg_replace('/[^A-Za-z0-9_-]/', '_', $_SESSION['firstName']);
    $reportDate = date('F j, Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnify Dashboard View Report</title>
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
        }

        .report-wrapper {
            max-width: 900px;
            width: 100%;
            padding: 20px;
            box-sizing: border-box;
        }

        .report-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 20px;
        }

        .report-border {
            border: 2px solid #2c3e50;
            padding: 20px;
            border-radius: 8px;
        }

        .report-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .report-header h1 {
            color: #2c3e50;
            font-size: 36px;
            border-bottom: 1px solid #2c3e50;
            margin: 0;
        }

        .report-content {
            margin-bottom: 30px;
        }

        .report-content h2 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .report-content h3 {
            color: #34495e;
            font-size: 20px;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .report-content p {
            color: #555;
            font-size: 16px;
            margin: 5px 0;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            font-size: clamp(1.2rem, 3vw, 1.3rem);
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
            margin-top: 40px;
        }

        .signature-line {
            text-align: center;
            width: 45%;
        }

        .signature-graphic {
            font-family: 'Cedarville Cursive', cursive;
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .signature-graphic .name {
            font-size: 28px;
            text-transform: uppercase;
        }

        .signature-line div:last-child {
            font-size: 14px;
            color: #555;
        }

        .report-footer {
            text-align: center;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: #777;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }

        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            font-size: clamp(1.4rem, 3vw, 1.5rem);
            font-family: 'Poppins', sans-serif;
            color: #fff;
            background-color: #2c3e50;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #34495e;
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
            }
        }
    </style>
</head>
<body>
    <div class="report-wrapper">
        <div class="report-container" id="report">
            <div class="report-border">
                <div class="report-header">
                    <h1>Learnify Dashboard View Report</h1>
                </div>
                <div class="report-content">
                    <h3>Dashboard View Preferences</h3>
                    <table class="report-table">
                        <tr>
                            <th>View Type</th>
                            <th>Count</th>
                        </tr>
                        <?php foreach ($dashboardViews as $view => $count): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(ucfirst($view)); ?></td>
                                <td><?php echo htmlspecialchars($count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><strong>Total</strong></td>
                            <td><strong><?php echo htmlspecialchars($totalViews); ?></strong></td>
                        </tr>
                    </table>
                </div>
                <div class="report-footer">
                    Generated by Learnify System (SuperAdmin) | <?php echo htmlspecialchars($reportDate); ?>
                </div>
            </div>
        </div>
        <div class="btn-container">
            <button id="downloadBtn" class="btn">Download PDF</button>
            <a href="superAdminDash.php" class="btn">Back to Dashboard</a>
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
                            filename: 'Learnify_View_Report_<?php echo $safeAdminName; ?>_<?php echo date('Ymd_His'); ?>.pdf',
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
    header("Location: superAdminDash.php");
    exit;
} catch (Exception $e) {
    ob_end_clean();
    error_log("Report Generation Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error generating report: " . $e->getMessage();
    header("Location: superAdminDash.php");
    exit;
}
?>