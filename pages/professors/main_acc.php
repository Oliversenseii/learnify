<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';

$userID = $_SESSION['userID'];

try {
    $stmt = $dbConnection->prepare("SELECT firstName, middleName, lastName, birthday, sex, address, email, contactNumber, nationality, image, status, generated_code, lrn FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = $user['firstName'];
        $_SESSION['userType'] = 'Professor';  
        $_SESSION['image'] = $user['image'] ? $user['image'] : './img/noprofile.png'; 

        // Calculate age from birthday
        $birthday = new DateTime($user['birthday']);
        $today = new DateTime();
        $age = $today->diff($birthday)->y;
    } else {
        header("Location: ../../index.php");
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/subjects_sidebar.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <style>
        :root {
            --dark: #333;
            --grey: #f5f5f5;
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --background: #F7FAFC;
            --text: #1A202C;
            --text-secondary: #4A5568;
            --border: #E2E8F0;
            --success: #38A169;
            --error: #E53E3E;
            --white: #FFFFFF;
            --accent: #EDF2F7;
            --transition: all 0.3s ease;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .modal {
            z-index: 10000;
        }

        .profile-container {
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem;
            background-color: var(--grey);
            max-width: 1200px;
            margin: 0 auto;
            margin-top: 10px;
            flex-wrap: wrap;
            box-sizing: border-box;
        }

        .profile-left, .profile-right {
            background: var(--grey);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            box-sizing: border-box;
        }

        .profile-left {
            flex: 1;
            min-width: 280px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .profile-left img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            transition: var(--transition);
        }

        .profile-left img:hover {
            transform: scale(1.05);
        }

        .qr-code-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .qr-code-container img {
            width: 120px;
            height: 120px;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .download-btn {
            background: var(--primary);
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: clamp(1rem, 3vw, 1rem);
            cursor: pointer;
            transition: var(--transition);
            touch-action: manipulation;
            min-height: 44px;
        }

        .download-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .profile-right {
            flex: 2;
            min-width: 280px;
        }

        .tab-container {
            margin-bottom: 1rem;
        }

        .tab-buttons {
            display: flex;
            border-bottom: 2px solid var(--border);
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .tab-button {
            padding: 0.6rem 1.2rem;
            background: none;
            border: none;
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 500;
            color: var(--dark);
            cursor: pointer;
            transition: var(--transition);
            touch-action: manipulation;
            min-height: 44px;
        }

        .tab-button.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            font-weight: 600;
        }

        .tab-button:hover {
            color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .profile-right table {
            width: 100%;
            border-collapse: collapse;
        }

        .profile-right tr {
            transition: var(--transition);
        }

        .profile-right tr:hover {
            background: var(--accent);
        }

        .profile-right td {
            padding: 0.8rem;
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--text-secondary);
        }

        .profile-right td:first-child {
            font-weight: 500;
            color: var(--dark);
            width: 40%;
        }

        .profile-right td i {
            margin-right: 0.5rem;
            color: var(--primary);
        }

        .status-row.active td:last-child {
            color: var(--success);
            font-weight: 500;
        }

        .status-row.inactive td:last-child {
            color: var(--error);
            font-weight: 500;
        }

        .status-row.suspended td:last-child {
            color: var(--suspended);
            font-weight: 500;
        }

        .full-name {
            text-transform: uppercase;
        }

        @media screen and (max-width: 768px) {
            .profile-container {
                flex-direction: column;
                padding: 1rem;
                gap: 1rem;
            }

            .profile-left, .profile-right {
                width: 100%;
                min-width: 0;
                padding: 1rem;
            }

            .profile-left img, .qr-code-container img {
                width: 100px;
                height: 100px;
            }

            .tab-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .tab-button {
                width: 100%;
                text-align: left;
                min-height: 40px;
            }

            .tab-button.active {
                border-bottom: 1px solid var(--primary);
            }

            .profile-right td:first-child {
                width: 100%;
            }
        }

        @media screen and (max-width: 460px) {
            .tab-content table tr{
                display: flex;
                flex-direction: column;
            }
        }

        .profile-container {
            overflow-x: hidden;
        }


        #content main .head-title .left .breadcrumb li a {
            color: var(--text-secondary);
            pointer-events: none;
        }

        .back-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .back-button:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1A4971);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .back-button i {
            font-size: clamp(1rem, 3vw, 1.3rem);
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
                <a href="./professor_main_dash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li>
                <a href="./message_admin.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Admin</span>
                </a>
            </li>
            <li>
                <a href="./game_controller.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Game</span>
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
                    <input type="search" name="query" placeholder="Search users..." required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
                <img src="<?php echo isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <p><?php echo $_SESSION['firstName']; ?></p>
                    <small>Teacher</small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>View Account Details</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./main_acc.php">Account</a></li>
                    </ul>
                </div>
                <a href="./userAcc2.php" class="back-button">
                    <i class='bx bxs-key'></i>
                    <span class="text">Edit Account</span>
                </a>
            </div>

            <div class="profile-container">
                <div class="profile-left">
                    <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Picture">
                    <div class="qr-code-container">
                        <img id="qrImg" src="generate_qr.php?code=<?php echo $user['generated_code']; ?>" alt="QR Code">
                        <button onclick="downloadQrCode()" class="download-btn">View QR Code</button> 
                    </div>
                </div>

                <div class="profile-right">
                    <div class="tab-container">
                        <div class="tab-buttons">
                            <button class="tab-button active" data-tab="personal">Personal Information</button>
                            <button class="tab-button" data-tab="contact">Contact Information</button>
                        </div>

                        <div class="tab-content active" id="personal">
                            <table>
                                <tr>
                                    <td><i class='bx bx-user'></i> Full Name:</td>
                                    <td class="full-name"><?php echo $user['lastName']; ?>, <?php echo $user['firstName']; ?> <?php echo $user['middleName'] ?: 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><i class='bx bx-user'></i> Age:</td>
                                    <td><?php echo $age; ?></td>
                                </tr>
                                <tr>
                                    <td><i class='bx bx-flag'></i> Nationality:</td>
                                    <td><?php echo $user['nationality'] ?: 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><i class='bx bx-id-card'></i> Teacher ID:</td>
                                    <td><?php echo $user['lrn']; ?></td>
                                </tr>
                                <tr class="status-row <?php 
                                        if ($user['status'] == 'Active') {
                                            echo 'active';
                                        } elseif ($user['status'] == 'Inactive') {
                                            echo 'inactive';
                                        } elseif ($user['status'] == 'Suspended') {
                                            echo 'suspended';
                                        }
                                    ?>">
                                    <td><i class='bx bx-check-circle'></i> Status:</td>
                                    <td><?php echo $user['status']; ?></td>
                                </tr>
                            </table>
                        </div>

                        <div class="tab-content" id="contact">
                            <table>
                                <tr>
                                    <td><i class='bx bx-envelope'></i> Email:</td>
                                    <td><?php echo $user['email']; ?></td>
                                </tr>
                                <tr>
                                    <td><i class='bx bx-phone'></i> Contact:</td>
                                    <td><?php echo $user['contactNumber'] ?: 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><i class='bx bx-home'></i> Address:</td>
                                    <td><?php echo $user['address'] ?: 'N/A'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script>
        // QR Code 
        function generateQrCodeUrl(text) {
            return `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(text)}`;
        }

        function downloadQrCode() {
            const qrImg = document.getElementById('qrImg');
            const generatedCode = "<?php echo $user['generated_code']; ?>";

            if (!qrImg.src || !generatedCode) {
                alert("No QR code generated yet.");
                return;
            }

            const link = document.createElement('a');
            link.href = qrImg.src;
            link.download = `QR_Code_${generatedCode}.png`; 
            document.body.appendChild(link);
            link.click(); 
            document.body.removeChild(link); 
        }

        window.onload = function() {
            const generatedCode = "<?php echo $user['generated_code']; ?>";
            if (generatedCode) {
                const qrImg = document.getElementById('qrImg');
                qrImg.src = generateQrCodeUrl(generatedCode);
            }

            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    button.classList.add('active');
                    const tabId = button.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        };
    </script>
</body>
</html>