<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

if (isset($_POST['registerSubject'])) {
    $subjectName = $_POST['subjectName'] ?? '';
    $professorIDs = $_POST['professorID'] ?? [];

    if (empty($subjectName) || empty($professorIDs)) {
        $_SESSION['error_message'] = "Please select a subject and at least one teacher.";
    } else {
        try {
            // Get subjectID from subjectName
            $sql = "SELECT subjectID FROM subjects WHERE subjectName = ?";
            $stmt = $dbConnection->prepare($sql);
            $stmt->execute([$subjectName]);
            $subject = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subject) {
                $_SESSION['error_message'] = "Selected subject does not exist.";
            } else {
                $subjectID = $subject['subjectID'];
                $success = true;

                // Insert each professor assignment into subject_professor
                $sqlInsert = "INSERT INTO subject_professor (subjectID, professorID) VALUES (?, ?)";
                $stmtInsert = $dbConnection->prepare($sqlInsert);

                foreach ($professorIDs as $professorID) {
                    // Check if assignment already exists
                    $sqlCheck = "SELECT COUNT(*) FROM subject_professor WHERE subjectID = ? AND professorID = ?";
                    $stmtCheck = $dbConnection->prepare($sqlCheck);
                    $stmtCheck->execute([$subjectID, $professorID]);
                    if ($stmtCheck->fetchColumn() == 0) {
                        if (!$stmtInsert->execute([$subjectID, $professorID])) {
                            $success = false;
                            break;
                        }
                    }
                }

                if ($success) {
                    $_SESSION['success_message'] = "Teacher(s) assigned to subject successfully!";
                } else {
                    $_SESSION['error_message'] = "Error assigning teacher(s) to subject. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }

    header("Location: enroll_prof_sub.php");
    exit;
}

// Fetch track-strands for dropdown
$strandSQL = "SELECT strandID, trackName, strandName FROM track_strands WHERE archived = 0 ORDER BY trackName, strandName";
$strandStmt = $dbConnection->prepare($strandSQL);
$strandStmt->execute();
$strands = $strandStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/track_strand.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify</title>
    <style>
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --light-blue: #CFE8FF;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --red: #a90c0c;
            --yellow: #FFCE26;
            --light-yellow: #FFF2C6;
            --orange: #FD7238;
            --light-orange: #FFE0D3;
        }

        .form-container {
            max-width: 600px;
            margin: 20px auto;
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .form-container h2 {
            margin-bottom: 20px;
            color: var(--dark);
            font-size: 24px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--dark);
        }

        #subjectName {
            background-color: var(--grey);
            color: var(--dark);
        }

        .form-group select,
        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            background-color: var(--grey);
            border: 1px solid var(--dark-grey);
            color: var(--dark);
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }

        .form-group select:focus,
        .form-group input[type="text"]:focus {
            border-color: var(--blue);
            outline: none;
        }

        .form-group select:disabled {
            background-color: #d3d3d3;
            cursor: not-allowed;
        }

        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: var(--grey);
            border: 1px solid var(--dark-grey);
            border-radius: 4px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--dark);
            cursor: pointer;
        }

        .checkbox-group input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid var(--dark-grey);
            border-radius: 4px;
            margin-right: 10px;
            position: relative;
            transition: all 0.3s ease;
        }

        .checkbox-group input[type="checkbox"]:checked {
            background-color: var(--blue);
            border-color: var(--blue);
        }

        .checkbox-group input[type="checkbox"]:checked::after {
            content: '\2713';
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
        }

        .checkbox-group label:hover input[type="checkbox"] {
            border-color: var(--blue);
        }

        .form-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .form-actions button {
            background-color: var(--blue);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-actions button:hover {
            background-color: #0056b3;
        }

        .success-notification, .error-notification {
            padding: 10px;
            margin: 20px auto;
            max-width: 600px;
            border-radius: 5px;
            color: white;
            text-align: center;
        }

        .success-notification {
            background-color: #28a745;
        }

        .error-notification {
            background-color: #dc3545;
        }

        .btn-download {
            background-color: var(--blue);
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s ease;
        }

        .btn-download:hover {
            background-color: #0056b3;
        }

        .loading {
            display: none;
            font-size: 14px;
            color: var(--dark);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./adminDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./message_professor.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Teachers</span>
                </a>
            </li>
            <li>
                <a href="./registration.php">
                    <i class='bx bx-user-plus'></i>
                    <span class="text">Registration</span>
                </a>
            </li>
            <li>
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li>
                <a href="./enroll_student_section.php">
                    <i class='bx bxs-user-check'></i>
                    <span class="text">Enroll Student Section</span>
                </a>
            </li>
            <li>
                <a href="./enroll_teacher_section.php">
                    <i class='bx bxs-user-check'></i>
                    <span class="text">Assign Teacher Schedule</span>
                </a>
            </li>
            <li>
                <a href="./game.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Games</span>
                </a>
            </li>
            <li>
                <a href="./admin_calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Academic Calendar</span>
                </a>
            </li>
            <li>
                <a href="./grading.php">
                    <i class='bx bxs-book-content'></i>
                    <span class="text">Student Grades</span>
                </a>
            </li>
        </ul>
        <ul class="side-menu">
            <li>
                <a href="./settings.php">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0);" class="logout" onclick="showLogoutModal()">
                    <i class='bx bxs-log-out-circle'></i>
                    <span class="text">Logout</span>
                </a>
            </li>
        </ul>
    </section>
    <!-- SIDEBAR -->

    <?php require_once './view/modal.php' ?>    

    <!-- CONTENT -->
    <section id="content">
        <!-- NAVBAR -->
        <nav>
            <i class='bx bx-menu'></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
                <img src="<?php echo isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <p><?php echo $_SESSION['firstName']; ?></p>
                    <small><?php echo $_SESSION['userType']; ?></small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Assign Teacher Subject</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./enroll_prof_sub.php">Assign</a></li>
                    </ul>
                </div>
                <a href="data_teacher_subjects.php" class="btn-download">
                    <i class="bx bxs-show"></i>
                    <span class="text">View Records</span>
                </a>
            </div>

            <!-- Success or Error Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']); 
                    ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']); 
                    ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h2>Assign Teacher(s) to Subject</h2>
                <form action="enroll_prof_sub.php" method="POST">
                    <div class="form-group">
                        <label for="strandID">Track and Strand</label>
                        <select name="strandID" id="strandID" required>
                            <option value="" disabled selected>- Select Track and Strand -</option>
                            <?php foreach ($strands as $strand): ?>
                                <option value="<?php echo $strand['strandID']; ?>">
                                    <?php echo htmlspecialchars($strand['trackName'] . ' - ' . $strand['strandName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subjectName">Assigned Subject</label>
                        <select name="subjectName" id="subjectName" required disabled>
                            <option value="" disabled selected>- Select Track and Strand First -</option>
                        </select>
                        <div class="loading" id="subjectLoading">Loading subjects...</div>
                    </div>

                    <div class="form-group">
                        <label>Assigned Teacher(s)</label>
                        <div class="checkbox-group">
                            <?php
                            $sql = "SELECT userID, firstName, lastName FROM users WHERE userType = 'Professor' ORDER BY firstName, lastName";
                            $stmt = $dbConnection->prepare($sql);
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<label><input type="checkbox" name="professorID[]" value="' . $row['userID'] . '"> ' . htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) . '</label>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="registerSubject">Assign Professor(s)</button>
                    </div>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script>
        document.getElementById('strandID').addEventListener('change', function() {
            const strandID = this.value;
            const subjectSelect = document.getElementById('subjectName');
            const loadingDiv = document.getElementById('subjectLoading');

            // Disable subject dropdown and show loading
            subjectSelect.disabled = true;
            subjectSelect.innerHTML = '<option value="" disabled selected>Loading subjects...</option>';
            loadingDiv.style.display = 'block';

            if (strandID === '') {
                subjectSelect.innerHTML = '<option value="" disabled selected>- Select Track and Strand First -</option>';
                loadingDiv.style.display = 'none';
                return;
            }

            // Fetch subjects via AJAX
            fetch('fetch_subjects.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'strandID=' + encodeURIComponent(strandID)
            })
            .then(response => response.json())
            .then(data => {
                subjectSelect.innerHTML = '<option value="" disabled selected>- Select Subject -</option>';
                if (data.subjects.length === 0) {
                    subjectSelect.innerHTML = '<option value="" disabled selected>No subjects available</option>';
                } else {
                    let lastSubjectType = '';
                    data.subjects.forEach(subject => {
                        if (lastSubjectType !== subject.subjectType) {
                            if (lastSubjectType !== '') {
                                subjectSelect.innerHTML += '</optgroup>';
                            }
                            subjectSelect.innerHTML += `<optgroup label="${subject.subjectType}">`;
                            lastSubjectType = subject.subjectType;
                        }
                        subjectSelect.innerHTML += `<option value="${subject.subjectName}">${subject.subjectName}</option>`;
                    });
                    if (lastSubjectType !== '') {
                        subjectSelect.innerHTML += '</optgroup>';
                    }
                }
                subjectSelect.disabled = false;
                loadingDiv.style.display = 'none';
            })
            .catch(error => {
                console.error('Error fetching subjects:', error);
                subjectSelect.innerHTML = '<option value="" disabled selected>Error loading subjects</option>';
                subjectSelect.disabled = true;
                loadingDiv.style.display = 'none';
            });
        });
    </script>
</body>
</html>