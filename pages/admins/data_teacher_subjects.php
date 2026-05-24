<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Handle delete action
if (isset($_GET['delete']) && isset($_GET['subjectID']) && isset($_GET['professorID'])) {
    $subjectID = $_GET['subjectID'];
    $professorID = $_GET['professorID'];

    try {
        $sql = "DELETE FROM subject_professor WHERE subjectID = ? AND professorID = ?";
        $stmt = $dbConnection->prepare($sql);
        if ($stmt->execute([$subjectID, $professorID])) {
            $_SESSION['success_message'] = "Professor removed from subject successfully!";
        } else {
            $_SESSION['error_message'] = "Error removing professor from subject.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }

    header("Location: data_teacher_subjects.php");
    exit;
}
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
    <style>
        @media screen and (max-width: 768px) {
            .admin-table {
                overflow-x: auto; 
                display: block; 
            }

            .admin-table table {
                min-width: 600px;
            }

            .admin-table th,
            .admin-table td {
                padding: 8px; 
                font-size: 0.9em; 
            }

            .admin-table .profile-img {
                width: 40px; 
                height: 40px;
            }

            .admin-table th:nth-child(3),
            .admin-table td:nth-child(3) {
                display: none;
            }
        }

        @media screen and (max-width: 480px) {
            .admin-table th,
            .admin-table td {
                padding: 6px; 
                font-size: 0.8em; 
            }

            .admin-table .profile-img {
                width: 30px; 
                height: 30px;
            }

            .admin-table th:nth-child(4), 
            .admin-table td:nth-child(4),
            .admin-table th:nth-child(5), 
            .admin-table td:nth-child(5) {
                display: none;
            }
        }

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
            --blue: #007bff;
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

        .table-wrapper {
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 1000px;
        }

        .table-wrapper h2 {
            color: var(--dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--dark-grey);
        }

        th {
            background-color: var(--blue);
            font-weight: bold;
            color: white;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
        }

        .btn.edit-btn {
            background-color: var(--yellow);
            color: #342E37;
        }

        .btn.delete-btn {
            background-color: var(--red);
            color: white;
        }

        .btn.delete-btn:hover {
            background-color: #8b0000;
        }

        .btn-back {
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

        .btn-back:hover {
            background-color: #0056b3;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }

        .pagination a.btn {
            background-color: var(--blue);
            color: white;
        }

        .pagination a.btn:hover {
            background-color: #0056b3;
        }

        .pagination span {
            font-size: 14px;
            color: var(--dark);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: var(--light);
            margin: 15% auto;
            padding: 40px;
            border-radius: 8px;
            width: 80%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .modal-content h3 {
            font-size: 1.7rem;
            margin-bottom: 20px;
            color: var(--dark);
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-buttons .btn {
            padding: 10px 20px;
        }
    </style>
    <title>Learnify</title>
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
                    <h1>View Assigned Teacher Subjects</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">View Records</a></li>
                    </ul>
                </div>
                <a href="enroll_prof_sub.php" class="btn-download">
                    <i class="bx bxs-left-arrow"></i>
                    <span class="text">Back to Assign</span>
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

            <?php
            $limit = 8; 
            $page = isset($_GET['page']) ? $_GET['page'] : 1;
            $offset = ($page - 1) * $limit;

            // Fetch the total number of subjects with assigned professors
            $sqlTotal = "SELECT COUNT(DISTINCT subjectID) FROM subject_professor";
            $stmtTotal = $dbConnection->prepare($sqlTotal);
            $stmtTotal->execute();
            $totalSubjects = $stmtTotal->fetchColumn();
            $totalPages = ceil($totalSubjects / $limit);

            // Fetch assigned subjects with all professors
            $sql = "
                SELECT s.subjectID, s.subjectName, sp.professorID, CONCAT(u.firstName, ' ', u.lastName) AS professor_name
                FROM subjects s
                JOIN subject_professor sp ON s.subjectID = sp.subjectID
                JOIN users u ON sp.professorID = u.userID
                ORDER BY s.subjectName, u.firstName, u.lastName
                LIMIT :limit OFFSET :offset";
            $stmt = $dbConnection->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $subjects = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $subjects[$row['subjectID']]['subjectName'] = $row['subjectName'];
                $subjects[$row['subjectID']]['professors'][] = [
                    'professorID' => $row['professorID'],
                    'name' => $row['professor_name']
                ];
            }
            ?>

            <!-- View Assigned Subjects with Professors -->
            <div class="table-wrapper">
                <h2>Teacher Assigned Subjects</h2>
                <br>
                <table>
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Professor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody style="color: var(--dark);">
                        <?php foreach ($subjects as $subjectID => $subject): ?>
                            <?php 
                            $rowspan = count($subject['professors']);
                            $first = true;
                            foreach ($subject['professors'] as $professor): 
                            ?>
                                <tr>
                                    <?php if ($first): ?>
                                        <td rowspan="<?php echo $rowspan; ?>">
                                            <?php echo htmlspecialchars($subject['subjectName']); ?>
                                        </td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($professor['name']); ?></td>
                                    <td>
                                        <form action="edit_enroll_prof_sub.php" method="GET" style="display: inline;">
                                            <button type="submit" name="subjectID" value="<?php echo $subjectID; ?>" class="btn edit-btn">Edit</button>
                                        </form>
                                        <button class="btn delete-btn" onclick="confirmDelete(<?php echo $subjectID; ?>, <?php echo $professor['professorID']; ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php $first = false; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1" class="btn">« First</a>
                    <a href="?page=<?php echo $page - 1; ?>" class="btn">< Previous</a>
                <?php endif; ?>
                <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="btn">Next ></a>
                    <a href="?page=<?php echo $totalPages; ?>" class="btn">Last »</a>
                <?php endif; ?>
            </div>

            <!-- Delete Confirmation Modal -->
            <div id="deleteModal" class="modal">
                <div class="modal-content">
                    <h3>Are you sure you want to remove this teacher from the subject?</h3>
                    <div class="modal-buttons">
                        <a id="confirmDeleteLink" class="btn delete-btn">Yes</a>
                        <button class="btn edit-btn" onclick="closeModal()">Cancel</button>
                    </div>
                </div>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script>
        function confirmDelete(subjectID, professorID) {
            const modal = document.getElementById('deleteModal');
            const confirmLink = document.getElementById('confirmDeleteLink');
            confirmLink.href = `data_teacher_subjects.php?delete=1&subjectID=${subjectID}&professorID=${professorID}`;
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
