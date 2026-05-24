<?php
date_default_timezone_set('Asia/Manila');
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Validate session
if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php");
    exit;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false || $userID <= 0) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

try {
    // Fetch current timeout duration
    $query = "SELECT timeout_duration FROM user_timeout_settings WHERE userID = :userID";
    $stmt = $dbConnection->prepare($query);
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $timeout_duration = $result ? (int)$result['timeout_duration'] : 300; // Default to 5 minutes

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: Unable to fetch settings.";
    header("Location: superAdminDash.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timeout_duration'])) {
    $new_timeout = filter_var($_POST['timeout_duration'], FILTER_VALIDATE_INT);
    if ($new_timeout !== false && $new_timeout > 0) {
        try {
            $query = "INSERT INTO user_timeout_settings (userID, timeout_duration) VALUES (:userID, :timeout_duration)
                      ON DUPLICATE KEY UPDATE timeout_duration = :timeout_duration, last_updated = CURRENT_TIMESTAMP";
            $stmt = $dbConnection->prepare($query);
            $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $stmt->bindParam(':timeout_duration', $new_timeout, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Timeout duration updated successfully.";
            header("Location: settings.php");
            exit;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            $_SESSION['error_message'] = "Failed to update timeout duration.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid timeout duration selected.";
    }
}

$timeout_duration_json = json_encode((int)$timeout_duration);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Settings</title>
    <style>
        :root {
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

        .settings-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .settings-section {
            margin-bottom: 1rem;
            background: var(--light);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .settings-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            color: var(--dark);
            cursor: pointer;
            background-color: var(--light);
            transition: var(--transition);
        }

        .settings-section-header:hover {
            background: linear-gradient(135deg, var(--light), var(--primary-dark));
        }

        .settings-section-header h2 {
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .settings-section-header i {
            font-size: 1.5rem;
            color: var(--dark);
            background-color: var(--light);
            transition: var(--transition);
        }

        .settings-section-header.active i {
            transform: rotate(180deg);
        }

        .settings-section-content {
            max-height: 0;
            overflow: hidden;
            padding: 0 1.5rem;
            transition: max-height 0.4s ease, padding 0.4s ease;
        }

        .settings-section-content.active {
            padding: 1.5rem;
            border-top: 1px solid var(--text-secondary);
        }

        .settings-section-content p {
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: #5f6368;
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }

        .timeout-form {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            background: var(--grey);
            padding: 1rem;
            border-radius: 6px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .timeout-form label {
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 500;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .timeout-form select,
        #dashboard_view {
            margin-top: 10px;
            margin-bottom: 10px;
            padding: 0.6rem 1.2rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            background: var(--white);
            color: var(--text);
            cursor: pointer;
            transition: var(--transition);
        }

        .timeout-form select:focus,
        #dashboard_view:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.2);
        }

        .timeout-form button,
        .settings-section-content button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .timeout-form button:hover,
        .settings-section-content button:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1A4971);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .timeout-form button i,
        .settings-section-content button i {
            font-size: clamp(1.1rem, 3vw, 1.3rem);
        }
        
        .password-restriction {
            color: red !important;
            font-style: italic;
            font-size: 1.1rem !important;
            font-weight: 500;
        }

        .password-approved {
            color: #38A169 !important;
            font-style: italic;
            font-size: 1.1rem !important;
            font-weight: 500;
        }

        #timer {
            font-size: clamp(1.5rem, 3vw, 3rem);
            color: var(--dark);
            border-left: 4px solid var(--primary-dark);
            font-weight: 500;
            background: transparent;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        #timer i {
            color: var(--primary);
        }

        #timer-expired {
            color: var(--error);
            font-weight: 600;
        }

        .success-notification, .error-notification {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            color: var(--white);
            font-size: clamp(1rem, 3vw, 1.2rem);
            z-index: 1000;
            box-shadow: var(--shadow);
            transition: opacity 0.3s ease;
        }

        .success-notification {
            background: var(--success);
        }

        .error-notification {
            background: var(--error);
        }

        .cookies-policy a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .cookies-policy a:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }

        @media (max-width: 768px) {
            .settings-container {
                margin: 1rem;
            }

            .settings-section-header h2 {
                font-size: 1.2rem;
            }

            .settings-section-content p {
                font-size: 0.95rem;
            }

            .timeout-form select,
            .timeout-form button,
            .settings-section-content button {
                font-size: 0.95rem;
                padding: 0.5rem 1rem;
            }

            .timer {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            .settings-section-header h2 {
                font-size: 1.1rem;
            }

            .settings-section-content p {
                font-size: 0.9rem;
            }

            .timeout-form {
                flex-direction: column;
                align-items: flex-start;
            }

            .timeout-form select,
            .timeout-form button,
            .settings-section-content button {
                font-size: 0.9rem;
                padding: 0.5rem 0.8rem;
                width: 100%;
            }

            .timer {
                font-size: 0.9rem;
                width: 100%;
                justify-content: center;
            }

            .success-notification, .error-notification {
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
            }
        }

        .dashboard-view-form label {
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            color: var(--dark);
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
                    <i class='bx bxs-dashboard' ></i>
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
            <li class="active">
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

    <section id="content">
        <nav>
            <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required aria-label="Search">
                    <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile" aria-label="Profile">
                <img src="<?php echo isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Settings</h1>
                    <ul class="breadcrumb">
                        <li><a href="./adminDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./settings.php">Settings</a></li>
                    </ul>
                </div>
            </div>

            <div class="settings-container">
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h2><i class='bx bx-log-out' style="margin-right: 5px; background-color: var(--primary); color: white; padding: 7px; border-radius: 50%;"></i> Auto-Logout</h2>
                        <i class='bx bx-chevron-down'></i>
                    </div>
                    <div class="settings-section-content">
                        <p>Configure the duration of inactivity before the system automatically logs you out for security.</p>
                        <div class="timeout-form">
                            <form action="settings.php" method="post">
                                <label for="timeout_duration"><i class='bx bx-time'></i> Auto-Logout Duration:</label>
                                <select name="timeout_duration" id="timeout_duration">
                                    <option value="86400" <?php echo $timeout_duration == 86400 ? 'selected' : ''; ?>>24 Hours</option>
                                    <option value="64800" <?php echo $timeout_duration == 64800 ? 'selected' : ''; ?>>18 Hours</option>
                                    <option value="43200" <?php echo $timeout_duration == 43200 ? 'selected' : ''; ?>>12 Hours</option>
                                    <option value="21600" <?php echo $timeout_duration == 21600 ? 'selected' : ''; ?>>6 Hours</option>
                                    <option value="10800" <?php echo $timeout_duration == 10800 ? 'selected' : ''; ?>>3 Hours</option>
                                    <option value="3600" <?php echo $timeout_duration == 3600 ? 'selected' : ''; ?>>1 Hour</option>
                                    <option value="1800" <?php echo $timeout_duration == 1800 ? 'selected' : ''; ?>>30 Minutes</option>
                                    <option value="300" <?php echo $timeout_duration == 300 ? 'selected' : ''; ?>>5 Minutes</option>
                                    <option value="60" <?php echo $timeout_duration == 60 ? 'selected' : ''; ?>>1 Minute</option>
                                </select>
                                <button type="submit"><i class='bx bx-save'></i> Save</button>
                            </form>
                            <span id="timer" data-timeout="<?php echo htmlspecialchars($timeout_duration); ?>">
                                <i class='bx bx-timer'></i>
                                <?php 
                                    $hours = floor($timeout_duration / 3600);
                                    $minutes = floor(($timeout_duration % 3600) / 60);
                                    $seconds = $timeout_duration % 60;
                                    $seconds = $seconds < 10 ? "0" . $seconds : $seconds;
                                    $minutes = $minutes < 10 && $hours > 0 ? "0" . $minutes : $minutes;
                                    echo ($hours > 0 ? $hours . ":" : "") . $minutes . ":" . $seconds;
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <div class="settings-section-header">
                        <h2><i class='bx bxs-cookie' style="margin-right: 5px; background-color: var(--primary); color: white; padding: 7px; border-radius: 50%;"></i> Cookies Policy</h2>
                        <i class='bx bx-chevron-down'></i>
                    </div>
                    <div class="settings-section-content">
                        <p>We use cookies to enhance your experience on our platform. By continuing to use our site, you consent to our use of cookies. Learn more in our <a href="cookies_policy.php">Cookies Policy</a>.</p>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification" id="success-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Toggle sections
            const headers = document.querySelectorAll('.settings-section-header');
            headers.forEach(header => {
                header.addEventListener('click', () => {
                    const content = header.nextElementSibling;
                    const isActive = content.classList.contains('active');
                    
                    // Close all sections
                    document.querySelectorAll('.settings-section-content').forEach(c => {
                        c.classList.remove('active');
                        c.style.maxHeight = null;
                        c.style.padding = '0 1.5rem';
                    });
                    document.querySelectorAll('.settings-section-header').forEach(h => {
                        h.classList.remove('active');
                    });

                    // Open clicked section if it wasn't already open
                    if (!isActive) {
                        content.classList.add('active');
                        content.style.maxHeight = content.scrollHeight + 30 + 'px';
                        content.style.padding = '1.5rem';
                        header.classList.add('active');
                    }
                });
            });

            // Timer logic
            const timerSpan = document.getElementById('timer');
            let timeLeft = parseInt(timerSpan.dataset.timeout, 10);

            if (timeLeft > 0) {
                const updateTimer = () => {
                    if (timeLeft <= 0) {
                        timerSpan.textContent = "00:00";
                        timerSpan.classList.add('timer-expired');
                        window.location.href = "logout.php";
                        return;
                    }

                    const hours = Math.floor(timeLeft / 3600);
                    const minutes = Math.floor((timeLeft % 3600) / 60);
                    const seconds = timeLeft % 60;
                    const formattedTime = `${hours > 0 ? hours + ":" : ""}${minutes < 10 && hours > 0 ? "0" : ""}${minutes}:${seconds < 10 ? "0" : ""}${seconds}`;
                    timerSpan.textContent = formattedTime;
                    timeLeft--;
                };

                updateTimer();
                const intervalId = setInterval(updateTimer, 1000);
                window.addEventListener('unload', () => clearInterval(intervalId));
            } else {
                timerSpan.textContent = "00:00";
                timerSpan.classList.add('timer-expired');
            }

            // Notification fade-out
            const successNotification = document.getElementById('success-notification');
            if (successNotification) {
                setTimeout(() => {
                    successNotification.style.opacity = '0';
                    setTimeout(() => {
                        successNotification.remove();
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>