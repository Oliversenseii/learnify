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
    // User counts
    $userCounts = [
        'admins' => 'Admin',
        'professors' => 'Professor',
        'students' => 'Student',
        'superadmins' => 'SuperAdmin',
    ];

    $counts = [];
    $sexCounts = [
        'admins' => ['male' => 0, 'female' => 0],
        'professors' => ['male' => 0, 'female' => 0],
        'students' => ['male' => 0, 'female' => 0],
        'superadmins' => ['male' => 0, 'female' => 0],
    ];

    foreach ($userCounts as $key => $userType) {
        $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE userType = :userType AND archived = 0");
        $stmt->bindParam(':userType', $userType, PDO::PARAM_STR);
        $stmt->execute();
        $counts[$key] = (int)$stmt->fetchColumn();

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

    // Other counts
    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM track_strands WHERE archived = 0");
    $stmt->execute();
    $strandCount = (int)$stmt->fetchColumn();

    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM sections WHERE archived = 0");
    $stmt->execute();
    $sectionCount = (int)$stmt->fetchColumn();

    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'modules'");
    $stmt->execute();
    $moduleTableExists = (int)$stmt->fetchColumn() > 0;
    $moduleCount = 0;
    if ($moduleTableExists) {
        $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM modules WHERE archived = 0");
        $stmt->execute();
        $moduleCount = (int)$stmt->fetchColumn();
    }

    $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM subjects WHERE archived = 0");
    $stmt->execute();
    $subjectCount = (int)$stmt->fetchColumn();

    // Total users
    $totalUsers = array_sum($counts);

    // Sanitize admin name for filename
    $safeAdminName = preg_replace('/[^A-Za-z0-9_-]/', '_', $_SESSION['firstName']);
    $reportDate = date('F j, Y');

    // Generate narrative
    $narrative = "This report provides a comprehensive overview of Learnify's user base and academic structure.\n";
    $narrative .= "Total Active Users: $totalUsers\n";
    $narrative .= "- Admins: {$counts['admins']} (Male: {$sexCounts['admins']['male']}, Female: {$sexCounts['admins']['female']})\n";
    $narrative .= "- Professors: {$counts['professors']} (Male: {$sexCounts['professors']['male']}, Female: {$sexCounts['professors']['female']})\n";
    $narrative .= "- Students: {$counts['students']} (Male: {$sexCounts['students']['male']}, Female: {$sexCounts['students']['female']})\n";
    $narrative .= "- SuperAdmins: {$counts['superadmins']} (Male: {$sexCounts['superadmins']['male']}, Female: {$sexCounts['superadmins']['female']})\n";
    $narrative .= "Academic Structure:\n";
    $narrative .= "- Track & Strands: $strandCount\n";
    $narrative .= "- Sections: $sectionCount\n";
    $narrative .= "- Modules: $moduleCount\n";
    $narrative .= "- Subjects: $subjectCount";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnify Dashboard Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&family=Cedarville+Cursive&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            font-size: 8px;
            line-height: 1.1;
        }
        .report-wrapper {
            width: 210mm;
            margin: 0 auto;
            box-sizing: border-box;
        }
        .report-container {
            width: 200mm;
            min-height: 277mm;
            background-color: #f5f5f5;
            border: 1mm solid #2c3e50;
            border-radius: 2mm;
            padding: 3mm;
            box-sizing: border-box;
            position: relative;
        }
        .report-border {
            border: 0.3mm double #0056b3;
            padding: 2mm;
            min-height: calc(100% - 4mm);
            box-sizing: border-box;
        }
        .report-header h1 {
            font-size: 1.6rem;
            color: #2c3e50;
            margin: 0 0 2mm 0;
            text-align: center;
        }
        .report-content h2 {
            font-size: 1.2rem;
            color: #0056b3;
            margin: 1.5mm 0;
        }
        .report-content h3 {
            font-size: 1.1rem;
            color: #2c3e50;
            margin: 1mm 0;
            text-decoration: underline;
        }
        .report-content p {
            font-size: 0.9rem;
            color: #34495e;
            margin: 0.5mm 0;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1mm 0;
            font-size: 0.9rem;
            color: #34495e;
            page-break-inside: avoid;
        }
        .report-table th, .report-table td {
            border: 0.15mm solid #0056b3;
            padding: 0.8mm;
            text-align: left;
        }
        .report-table th {
            background: #0056b3;
            color: white;
            font-weight: 500;
        }
        .report-table tr:nth-child(even) {
            background: #f8f1e9;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 2mm;
            width: 100%;
            max-width: 60mm;
            margin-left: auto;
            margin-right: auto;
            font-size: 0.9rem;
        }
        .signature-line {
            text-align: center;
            flex: 1;
        }
        .signature-line div {
            width: 25mm;
            margin: 0 auto;
            font-size: 0.9rem;
            color: #34495e;
        }
        .signature-graphic {
            border-bottom: 0.15mm solid #2c3e50;
            font-family: 'Cedarville Cursive', cursive;
            font-size: 0.9rem;
            color: #2c3e50;
            margin-bottom: 0.3mm;
        }
        .signature-graphic .name {
            text-transform: uppercase;
            font-family: 'Poppins', sans-serif;
        }
        .report-footer {
            position: absolute;
            bottom: 1mm;
            width: 100%;
            text-align: center;
            font-size: 6px;
            color: #7f8c8d;
        }
        .btn-container {
            margin-top: 1mm;
            text-align: center;
        }
        .btn {
            padding: 2mm 4mm;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border-radius: 1mm;
            font-size: 0.9rem;
            margin: 0.5mm;
            cursor: pointer;
            display: inline-block;
        }
        .btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        #downloadBtn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
        }
        #downloadBtn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        @media print {
            body {
                background: none;
                margin: 0;
            }
            .report-wrapper {
                padding: 0;
                margin: 0;
            }
            .report-container {
                box-shadow: none;
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                padding: 3mm;
            }
            .report-border {
                margin: 0;
            }
            .btn-container {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-wrapper">
        <div class="report-container" id="report">
            <div class="report-border">
                <div class="report-header">
                    <h1>Learnify Dashboard Report</h1>
                </div>
                <div class="report-content">
                    <h2>Summary Report</h2>
                    <p>Generated on: <?php echo htmlspecialchars($reportDate); ?></p>
                    <p>Generated by: <?php echo htmlspecialchars($_SESSION['firstName']); ?> (SuperAdmin)</p>
                    
                    <h3>User Distribution</h3>
                    <table class="report-table">
                        <tr>
                            <th>User Type</th>
                            <th>Count</th>
                        </tr>
                        <?php foreach ($counts as $key => $count): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(ucfirst($key)); ?></td>
                                <td><?php echo htmlspecialchars($count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><strong>Total Users</strong></td>
                            <td><strong><?php echo htmlspecialchars($totalUsers); ?></strong></td>
                        </tr>
                    </table>

                    <h3>Sex Distribution</h3>
                    <table class="report-table">
                        <tr>
                            <th>User Type</th>
                            <th>Male</th>
                            <th>Female</th>
                        </tr>
                        <tr>
                            <td>SuperAdmins</td>
                            <td><?php echo htmlspecialchars($sexCounts['superadmins']['male']); ?></td>
                            <td><?php echo htmlspecialchars($sexCounts['superadmins']['female']); ?></td>
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
                    </table>

                    <h3>Academic Structure</h3>
                    <table class="report-table">
                        <tr>
                            <th>Category</th>
                            <th>Count</th>
                        </tr>
                        <tr>
                            <td>Track & Strands</td>
                            <td><?php echo htmlspecialchars($strandCount); ?></td>
                        </tr>
                        <tr>
                            <td>Sections</td>
                            <td><?php echo htmlspecialchars($sectionCount); ?></td>
                        </tr>
                        <tr>
                            <td>Modules</td>
                            <td><?php echo htmlspecialchars($moduleCount); ?></td>
                        </tr>
                        <tr>
                            <td>Subjects</td>
                            <td><?php echo htmlspecialchars($subjectCount); ?></td>
                        </tr>
                    </table>

                    <h3>Summary</h3>
                    <p style="text-align: left; white-space: pre-wrap;"><?php echo htmlspecialchars($narrative); ?></p>

                    <div class="signature-section">
                        <div class="signature-line">
                            <div class="signature-graphic">SuperAdmin <br><span class="name"><?php echo htmlspecialchars(strtoupper($_SESSION['firstName'])); ?></span></div>
                            <div>Super Administrator</div>
                        </div>
                        <div class="signature-line">
                            <div class="signature-graphic">System <br><span class="name">LEARNIFY SYSTEM</span></div>
                            <div>System Authority</div>
                        </div>
                    </div>
                </div>
                <div class="report-footer">
                    Generated by Learnify System | <?php echo htmlspecialchars($reportDate); ?>
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
                            filename: 'Learnify_Report_<?php echo $safeAdminName; ?>_<?php echo date('Ymd_His'); ?>.pdf',
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