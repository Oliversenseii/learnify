<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

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
    <title>Learnify - Cookies Policy</title>
    <style>
        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --background: #F7FAFC;
            --text: #1A202C;
            --text-secondary: #4A5568;
            --border: #E2E8F0;
            --white: #FFFFFF;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition: all 0.3s ease;
        }

        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .policy-section {
            background: var(--light);
            border-radius: 8px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
        }

        .policy-section h1 {
            font-size: clamp(1.8rem, 4vw, 4rem);
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .policy-section h1 i {
            font-size: 2rem;
            color: var(--primary);
        }

        .policy-section h2 {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 600;
            color: var(--dark);
            margin: 1.5rem 0 1rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
        }

        .policy-section p {
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .policy-section ul {
            list-style-type: disc;
            padding-left: 2rem;
            margin-bottom: 1rem;
        }

        .policy-section li {
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .policy-section li strong {
            color: var(--dark);
            font-weight: 600;
        }

        .policy-section a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .policy-section a:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }

        .back-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
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
            font-size: clamp(1.1rem, 3vw, 1.3rem);
        }

        @media (max-width: 768px) {
            .container {
                margin: 1rem;
            }

            .policy-section {
                padding: 1.5rem;
            }

            .policy-section h1 {
                font-size: 1.6rem;
            }

            .policy-section h2 {
                font-size: 1.3rem;
            }

            .policy-section p,
            .policy-section li {
                font-size: 0.95rem;
            }

            .back-button {
                font-size: 0.95rem;
                padding: 0.5rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .policy-section {
                padding: 1rem;
            }

            .policy-section h1 {
                font-size: 1.4rem;
            }

            .policy-section h2 {
                font-size: 1.2rem;
            }

            .policy-section p,
            .policy-section li {
                font-size: 0.9rem;
            }

            .back-button {
                font-size: 0.9rem;
                padding: 0.5rem 0.8rem;
                width: 100%;
                justify-content: center;
            }
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
                    <h1>Cookies Policy</h1>
                    <ul class="breadcrumb">
                        <li><a href="./superAdminDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./settings.php">Settings</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./cookies_policy.php">Cookies Policy</a></li>
                    </ul>
                </div>
                <a href="./settings.php" class="back-button"><i class='bx bx-arrow-back'></i> Back to Settings</a>
            </div>

            <div class="container">
                <div class="policy-section">
                    <h2>Overview</h2>
                    <p>We use cookies to make our website work smoothly, keep it secure, and give you a personalized experience. This policy explains what cookies are, why we use them, and how you can manage them.</p>

                    <h2>Cookies We Use</h2>
                    <p>Cookies are small files that help our website recognize you and work properly. We only use cookies that are necessary for the site to function. Here's what they do:</p>
                    <ul>
                        <li><strong>userID:</strong> Helps us know it's you when you're logged in, keeping your session secure.</li>
                        <li><strong>lrn:</strong> Saves your learner reference number to personalize your experience.</li>
                        <li><strong>userType:</strong> Checks if you're a student, teacher, or another type of user to customize what you see.</li>
                        <li><strong>status:</strong> Keeps track of your account's status to make sure everything runs smoothly.</li>
                        <li><strong>last_activity_time:</strong> Monitors session activity to enhance security, such as detecting inactivity.</li>
                    </ul>
                    <p>These cookies are deleted when you log out of our platform.</p>

                    <h2>Purpose of Cookies</h2>
                    <p>Our essential cookies enable core website functionality, including user authentication, session management, and security. They do not collect personal data beyond what is necessary for these purposes.</p>

                    <h2>Your Consent</h2>
                    <p>By continuing to use our site, you consent to our use of these cookies. You can withdraw consent at any time by managing your cookie preferences through your browser settings.</p>

                    <h2>Managing Cookies</h2>
                    <p>You can control cookies through your browser settings. Most browsers allow you to block or delete cookies. Note that disabling essential cookies may affect the functionality of our website.</p>

                    <h2>Have Questions?</h2>
                    <p>If you want to know more about our cookies or how we use them, email us at <a href="mailto:support@learnify.com">support@learnify.com</a>.</p>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
</body>
</html>