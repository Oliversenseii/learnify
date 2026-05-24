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
    // Validate sectionID
    $sectionID = isset($_GET['sectionID']) ? filter_var($_GET['sectionID'], FILTER_VALIDATE_INT) : false;
    if ($sectionID === false) {
        throw new Exception("Invalid section ID.");
    }

    // Fetch section details
    $sectionStmt = $dbConnection->prepare("
        SELECT sectionName 
        FROM sections 
        WHERE sectionID = :sectionID AND archived = 0
    ");
    $sectionStmt->bindParam(':sectionID', $sectionID, PDO::PARAM_INT);
    $sectionStmt->execute();
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        throw new Exception("Section not found or archived.");
    }
    $sectionName = $section['sectionName'];

    // Fetch student data for the section
    $studentStmt = $dbConnection->prepare("
        SELECT u.firstName, u.lastName, u.sex, ss.enrollmentDate, ss.status
        FROM student_section ss
        JOIN users u ON ss.userID = u.userID
        WHERE ss.sectionID = :sectionID AND ss.archived = 0 AND ss.status = 'Enrolled'
        ORDER BY u.lastName, u.firstName
    ");
    $studentStmt->bindParam(':sectionID', $sectionID, PDO::PARAM_INT);
    $studentStmt->execute();
    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Separate male and female students
    $maleStudents = [];
    $femaleStudents = [];
    foreach ($students as $student) {
        if ($student['sex'] === 'Male') {
            $maleStudents[] = $student;
        } elseif ($student['sex'] === 'Female') {
            $femaleStudents[] = $student;
        }
    }
    $maleCount = count($maleStudents);
    $femaleCount = count($femaleStudents);
    $totalCount = $maleCount + $femaleCount;

    // Sanitize section name and admin name for filename
    $safeSectionName = preg_replace('/[^A-Za-z0-9_-]/', '_', $sectionName);
    $safeAdminName = preg_replace('/[^A-Za-z0-9_-]/', '_', $_SESSION['firstName']);
    $reportDate = date('F j, Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnify Section Enrollment Report</title>
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
            height: 100vh;
            box-sizing: border-box;
        }
        .report-container {
            background-color: #fff;
            overflow-y: auto;
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
        .gender-container {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .gender-box {
            flex: 1;
            min-width: 280px;
        }
        .gender-box h3 {
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            color: #2c3e50;
            margin-bottom: 10px;
            text-align: center;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .report-table th,
        .report-table td {
            padding: 8px;
            text-align: left;
            font-size: clamp(0.9rem, 2.5vw, 1.1rem);
        }
        .report-table th {
            background-color: #2c3e50;
            color: #fff;
            font-weight: 500;
        }
        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .summary-section {
            margin-top: 20px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: #2c3e50;
            text-align: center;
        }
        .summary-section p {
            margin: 5px 0;
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
            .gender-container {
                flex-direction: column;
            }
            .gender-box {
                min-width: 100%;
            }
            .report-table {
                min-width: 100%;
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
            .gender-container {
                flex-direction: row;
                gap: 10px;
            }
            .gender-box {
                min-width: 45%;
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
                    <h1>Learnify Section Enrollment Report</h1>
                </div>
                <div class="report-content">
                    <h2>Section: <?php echo htmlspecialchars($sectionName); ?></h2>
                    <div class="gender-container">
                        <div class="gender-box">
                            <h3>Male Students</h3>
                            <table class="report-table">
                                <tr>
                                    <th>#</th>
                                    <th>Student Name</th>
                                    <!-- <th>Enrollment Date</th>
                                    <th>Status</th> -->
                                </tr>
                                <?php if (empty($maleStudents)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No male students enrolled.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $rowNumber = 1; ?>
                                    <?php foreach ($maleStudents as $student): ?>
                                        <tr>
                                            <td><?php echo $rowNumber; ?></td>
                                            <td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
                                            <!-- <td><?php echo htmlspecialchars(date("F d, Y", strtotime($student['enrollmentDate']))); ?></td>
                                            <td><?php echo htmlspecialchars($student['status']); ?></td> -->
                                        </tr>
                                        <?php $rowNumber++; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="gender-box">
                            <h3>Female Students</h3>
                            <table class="report-table">
                                <tr>
                                    <th>#</th>
                                    <th>Student Name</th>
                                    <!-- <th>Enrollment Date</th>
                                    <th>Status</th> -->
                                </tr>
                                <?php if (empty($femaleStudents)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No female students enrolled.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $rowNumber = 1; ?>
                                    <?php foreach ($femaleStudents as $student): ?>
                                        <tr>
                                            <td><?php echo $rowNumber; ?></td>
                                            <td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
                                            <!-- <td><?php echo htmlspecialchars(date("F d, Y", strtotime($student['enrollmentDate']))); ?></td>
                                            <td><?php echo htmlspecialchars($student['status']); ?></td> -->
                                        </tr>
                                        <?php $rowNumber++; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    <div class="summary-section">
                        <p><strong>Male Students:</strong> <?php echo $maleCount; ?> / 25</p>
                        <p><strong>Female Students:</strong> <?php echo $femaleCount; ?> / 25</p>
                        <p><strong>Total Students:</strong> <?php echo $totalCount; ?> / 50</p>
                    </div>
                </div>
                <div class="report-footer">
                    Generated by Learnify System (Admin) | <?php echo htmlspecialchars($reportDate); ?>
                </div>
            </div>
        </div>
        <div class="btn-container">
            <button id="downloadBtn" class="btn">Download PDF</button>
            <a href="data_student_section.php" class="btn">Back to Enrolled Students</a>
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
                            filename: 'Learnify_Section_Report_<?php echo $safeSectionName; ?>_<?php echo $safeAdminName; ?>_<?php echo date('Ymd_His'); ?>.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2, dpi: 300, useCORS: true },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                            pagebreak: { mode: ['css', 'legacy'], avoid: ['h1', 'h2', 'table', '.gender-box'] }
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
    error_log("Section Report Generation Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error: Unable to generate section report.";
    header("Location: data_student_section.php");
    exit;
} catch (Exception $e) {
    ob_end_clean();
    error_log("Section Report Generation Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error generating section report: " . $e->getMessage();
    header("Location: data_student_section.php");
    exit;
}
?>