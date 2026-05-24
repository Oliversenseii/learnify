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
    // Age Distribution by User Type
    $ageGroups = ['0-17', '18-24', '25-34', '35-44', '45+'];
    $ageDataByType = [
        'SuperAdmins' => array_fill_keys($ageGroups, 0),
        'Admins' => array_fill_keys($ageGroups, 0),
        'Professors' => array_fill_keys($ageGroups, 0),
        'Students' => array_fill_keys($ageGroups, 0),
    ];
    $userCounts = [
        'Admins' => 'Admin',
        'Professors' => 'Professor',
        'Students' => 'Student',
        'SuperAdmins' => 'SuperAdmin',
    ];

    foreach ($userCounts as $displayKey => $userType) {
        $stmt = $dbConnection->prepare("
            SELECT 
                CASE 
                    WHEN age < 18 THEN '0-17'
                    WHEN age BETWEEN 18 AND 24 THEN '18-24'
                    WHEN age BETWEEN 25 AND 34 THEN '25-34'
                    WHEN age BETWEEN 35 AND 44 THEN '35-44'
                    ELSE '45+'
                END as age_group,
                COUNT(*) as count
            FROM (
                SELECT FLOOR(DATEDIFF(CURDATE(), birthday)/365) as age
                FROM users 
                WHERE userType = :userType AND archived = 0 AND birthday IS NOT NULL
            ) as ages
            GROUP BY age_group
            ORDER BY FIELD(age_group, '0-17', '18-24', '25-34', '35-44', '45+')
        ");
        $stmt->bindParam(':userType', $userType, PDO::PARAM_STR);
        $stmt->execute();
        $ageData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ageData as $row) {
            $ageDataByType[$displayKey][$row['age_group']] = (int)$row['count'];
        }
    }

    // Total users by type
    $totals = [];
    foreach ($ageDataByType as $key => $data) {
        $totals[$key] = array_sum($data);
    }

    // Sanitize admin name for filename
    $safeAdminName = preg_replace('/[^A-Za-z0-9_-]/', '_', $_SESSION['firstName']);
    $reportDate = date('F j, Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnify Age Distribution Report</title>
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
            padding: 15px;
            box-sizing: border-box;
            height: auto;
        }
        .report-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .report-border {
            border: 2px solid #2c3e50;
            padding: 15px;
            border-radius: 8px;
        }
        .report-header {
            text-align: center;
            margin-bottom: 15px;
        }
        .report-header h1 {
            color: #2c3e50;
            font-size: clamp(1.8rem, 4vw, 3rem);
            border-bottom: 1px solid #2c3e50;
            margin: 0;
        }
        .report-content {
            margin-bottom: 20px;
            width: 100%;
            overflow-x: auto;
        }
        .report-content h2 {
            color: #2c3e50;
            font-size: clamp(1.4rem, 3vw, 2.6rem);
            margin-bottom: 10px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            min-width: 600px; 
        }
        .report-table th,
        .report-table td {
            border: 1px solid #ddd;
            padding: 8px;
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
            margin-top: 30px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .signature-line {
            text-align: center;
            width: 45%;
        }
        .signature-graphic {
            font-family: 'Cedarville Cursive', cursive;
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            color: #2c3e50;
            margin-bottom: 5px;
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
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
        }
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn {
            padding: 8px 15px;
              font-size: clamp(1rem, 3vw, 1.2rem);
            font-family: 'Poppins', sans-serif;
            color: #fff;
            background-color: #2c3e50;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s;
            flex: 1;
            text-align: center;
        }
        .btn:hover {
            background-color: #34495e;
        }
        @media screen and (max-width: 768px) {
            .report-wrapper {
                padding: 10px;
            }
            .report-container {
                padding: 15px;
            }
            .report-border {
                padding: 10px;
            }
            .report-table {
                min-width: 500px; /* Adjusted for smaller screens */
            }
            .report-table th,
            .report-table td {
                padding: 6px;
                font-size: clamp(0.8rem, 2vw, 0.9rem);
            }
            .signature-section {
                flex-direction: column;
                align-items: center;
            }
            .signature-line {
                width: 100%;
                margin-bottom: 10px;
            }
            .btn-container {
                flex-direction: column;
                align-items: stretch;
            }
            .btn {
                padding: 8px;
                font-size: clamp(0.85rem, 2vw, 0.95rem);
            }
        }
        @media screen and (max-width: 460px) {
            .report-table {
                min-width: 400px;
            }
            .report-table th,
            .report-table td {
                padding: 5px;
                font-size: clamp(0.75rem, 1.8vw, 0.85rem);
            }
            .report-header h1 {
                font-size: clamp(1.6rem, 3.5vw, 1.8rem);
            }
            .report-content h2 {
                font-size: clamp(1.2rem, 2.5vw, 1.4rem);
            }
            .report-footer {
                font-size: clamp(0.8rem, 2vw, 0.9rem);
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
                padding: 10px;
            }
            .report-table {
                min-width: 100%;
            }
            .report-table th,
            .report-table td {
                font-size: 0.9rem;
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="report-wrapper">
        <div class="report-container" id="report">
            <div class="report-border">
                <div class="report-header">
                    <h1>Learnify Age Distribution Report</h1>
                </div>
                <div class="report-content">
                    <h2>Age Distribution Across All User Types</h2>
                    <table class="report-table">
                        <tr>
                            <th>Age Group</th>
                            <th>SuperAdmins</th>
                            <th>Admins</th>
                            <th>Professors</th>
                            <th>Students</th>
                            <th>Total</th>
                        </tr>
                        <?php foreach ($ageGroups as $group): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($group); ?></td>
                                <td><?php echo htmlspecialchars($ageDataByType['SuperAdmins'][$group]); ?></td>
                                <td><?php echo htmlspecialchars($ageDataByType['Admins'][$group]); ?></td>
                                <td><?php echo htmlspecialchars($ageDataByType['Professors'][$group]); ?></td>
                                <td><?php echo htmlspecialchars($ageDataByType['Students'][$group]); ?></td>
                                <td><?php echo htmlspecialchars(
                                    $ageDataByType['SuperAdmins'][$group] +
                                    $ageDataByType['Admins'][$group] +
                                    $ageDataByType['Professors'][$group] +
                                    $ageDataByType['Students'][$group]
                                ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><strong>Total</strong></td>
                            <td><strong><?php echo htmlspecialchars($totals['SuperAdmins']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($totals['Admins']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($totals['Professors']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($totals['Students']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars(array_sum($totals)); ?></strong></td>
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
                            filename: 'Learnify_Age_Report_<?php echo $safeAdminName; ?>_<?php echo date('Ymd_His'); ?>.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2, dpi: 300, useCORS: true },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                            pagebreak: { mode: ['css', 'legacy'], avoid: ['h1', 'h2', 'table'] }
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