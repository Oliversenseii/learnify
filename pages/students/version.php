<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './check_enrollment_status.php';

// Validate session
if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php");
    exit;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

try {
    // Fetch user data
    $stmt = $dbConnection->prepare("SELECT firstName, userType, image FROM users WHERE userID = :userID AND userType = 'Student'");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
    } else {
        session_destroy();
        header("Location: ../../index.php");
        exit;
    }

    // Version information
    $changelog = [
        "1.0.0" => [
            "date" => "October 20, 2025",
            "new_features" => [
                "Initial release of Learnify LMS for Student",
                "Access quizzes and assignments for enrolled classes",
                "View announcements posted by teachers",
                "Send and receive comments and private messages",
                "Access course modules online via QR code scanner",
                "Download course modules for offline access (requires a secure token)",
                "QR Code Login for secure and convenient access (requires fast internet connection; poor connectivity may lead to login delays or failures)"
            ],
            "bug_fixes" => [
                "Fixed logout session timeout bug"
            ],
            "removed_features" => [],
            "security_patches" => [
                "Implemented secure session handling for Student",
                "Upgraded password hashing using bcrypt",
                "Enhanced cookie management to persist sessions across browser reopen",
                "Implemented auto-logout for improved security"
            ],
            "release_notes" => "Initial release with core Student features, including access to quizzes, assignments, announcements, messages, and modules (online via QR code and offline with token requirement). Enhanced security via bcrypt hashing, session persistence, and auto-logout."
        ]
    ];

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "An error occurred: " . htmlspecialchars($e->getMessage()) . ". Please try again later.";
}

require_once './get_notification_count.php';

// Ensure userID is set and validated
$userID = isset($_SESSION['userID']) ? filter_var($_SESSION['userID'], FILTER_VALIDATE_INT) : null;
$unreadCount = $userID ? getUnreadAnnouncementCount($dbConnection, $userID) : 0;

// Ensure announcements_viewed session flag is set
if (!isset($_SESSION['announcements_viewed'])) {
    $_SESSION['announcements_viewed'] = false;
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
    <script src="./logout.js" defer></script>
    <title>Learnify - Version Information</title>
    <style>
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .version-content {
            max-width: 700px;
            margin-top: 10px;
            margin: 0 auto;
            padding: 20px;
            background-color: var(--light);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .version-content h3 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            text-align: left;
            text-transform: uppercase;
            border-bottom: 1px solid #ccc;
            letter-spacing: 1px;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        .version-info {
            max-width: 1000px;
            margin: 0 auto;
            margin-bottom: 40px;
        }

        .version-info h4,
        .version-content .changelog h4 {
            font-size: 1.8rem;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            font-weight: 600;
            text-align: center;
            color: var(--dark);
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .version-content .changelog .category h5 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin: 20px 0 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-transform: uppercase;
        }

        .version-content .changelog .category .feature-card {
            display: block;
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 10px;
            padding: 15px 20px 15px 40px;
            border-left: 4px solid #0056b3;
            background: rgba(255, 255, 255, 0.1); 
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }

        .version-content .changelog .category .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .version-content .changelog .category .feature-card::before {
            content: "";
            position: absolute;
            left: 15px;
            top: 15px;
            font-size: 1.2rem;
        }

        .version-content .changelog .category .feature-card.gamification {
            background: rgba(255, 255, 255, 0.1); 
           
        }

        .version-content .changelog .category .feature-card.gamification-item {
            background: rgba(255, 255, 255, 0.1); 
        }

        .version-content .changelog .category.new-features h5::before {
            content: "✅";
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .version-content .changelog .category.bug-fixes h5::before {
            content: "🐞";
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .version-content .changelog .category.removed-features h5::before {
            content: "❌";
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .version-content .changelog .category.security-patches h5::before {
            content: "🔐";
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .version-content .changelog .category.release-notes h5::before {
            content: "📝";
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .version-table {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border-collapse: separate;
            border-spacing: 0;
            background-color: var(--light);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .version-table th,
        .version-table td {
            padding: 16px;
            text-align: left;
            font-size: 1rem;
            color: var(--dark);
            border-bottom: 1px solid rgba(0, 0, 0, 0.12);
        }

        .version-table th {
            background-color: #0056b3;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            color: white;
        }

        .version-table tr:hover {
            background-color: rgba(0, 0, 0, 0.04);
            transition: background-color 0.3s ease;
        }

        .version-table tr:last-child td {
            border-bottom: none;
        }

        .version-table td .highlight {
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .version-content h3 {
                font-size: 1.8rem;
            }

            .version-info h4,
            .version-content .changelog h4 {
                font-size: 1.6rem;
            }

            .version-content .changelog .category h5 {
                font-size: 1.3rem;
            }

            .version-content .changelog .category .feature-card {
                font-size: 0.95rem;
                padding: 12px 18px 12px 35px;
            }

            .version-table th,
            .version-table td {
                font-size: 0.9rem;
                padding: 12px;
            }
        }

        .notification-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border-radius: 50%;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php'; ?>
        <ul class="side-menu top">
            <li>
                <a href="./studentDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./announcements.php?viewed=true">
                    <i class='bx bxs-bell'></i>
                    <span class="text">Announcements</span>
                    <?php if ($unreadCount > 0 && !$_SESSION['announcements_viewed']): ?>
                        <span class="notification-badge"><?php echo htmlspecialchars($unreadCount); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li>
                <a href="./game.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Games</span>
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
            <li class="active">
                <a href="./version.php">
                    <i class='bx bxs-info-circle'></i>
                    <span class="text">Version</span>
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

    <?php require_once './view/modal.php'; ?>

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
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image">
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
                    <h1>Version Information (v1.0.0)</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./version.php">Version</a></li>
                    </ul>
                </div>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class='error-notification'><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="version-content">
                <div class="changelog">
                    <?php foreach ($changelog as $version => $details): ?>
                        <h4>Changelog for Version <?php echo $version; ?> (<?php echo $details['date']; ?>)</h4>
                        <?php if (!empty($details['new_features'])): ?>
                            <div class="category new-features">
                                <h5>New Features</h5>
                                <?php foreach ($details['new_features'] as $feature): ?>
                                    <div class="feature-card"><?php echo htmlspecialchars($feature); ?></div>
                                <?php endforeach; ?>
                                <h5>GAMIFICATION</h5>
                                <div class="feature-card gamification-item"><strong>LEARNIFY BRAINIACS:</strong> A strand-based educational game with lectures, tests, badges, and certificates to enhance student engagement</div>
                                <div class="feature-card gamification-item"><strong>VISUAL SPEAK CHALLENGE:</strong> An image-based guessing game with many levels, speech-to-text and text-to-speech features, coin rewards, lifelines, and a certificate for completion</div>
                                <div class="feature-card gamification-item"><strong>WORD HUNT:</strong> A drag-and-drop word formation game through themed boxes, categories, and hints. Players earn stars, badges, and certificates based on time and accuracy.</div>
                                <div class="feature-card gamification-item"><strong>LEARNIFY QUIZZIZ:</strong> An interactive quiz game where teachers create quizzes with unique codes, and students compete in timed multiple-choice challenges, earning points and ranking on a leaderboard with medals for top performers.</div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($details['bug_fixes'])): ?>
                            <div class="category bug-fixes">
                                <h5>Bug Fixes</h5>
                                <?php foreach ($details['bug_fixes'] as $fix): ?>
                                    <div class="feature-card"><?php echo htmlspecialchars($fix); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($details['removed_features'])): ?>
                            <div class="category removed-features">
                                <h5>Removed Features</h5>
                                <?php foreach ($details['removed_features'] as $removed): ?>
                                    <div class="feature-card"><?php echo htmlspecialchars($removed); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($details['security_patches'])): ?>
                            <div class="category security-patches">
                                <h5>Security Patches</h5>
                                <?php foreach ($details['security_patches'] as $patch): ?>
                                    <div class="feature-card"><?php echo htmlspecialchars($patch); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="category release-notes">
                            <h5>Release Notes / Summary</h5>
                            <div class="feature-card"><?php echo htmlspecialchars($details['release_notes']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="version-info">
                    <h4>Version List</h4>
                    <table class="version-table">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($changelog as $version => $details): ?>
                                <tr>
                                    <td><span class="highlight">Version Number</span></td>
                                    <td>v<?php echo $version; ?></td>
                                </tr>
                                <tr>
                                    <td><span class="highlight">Released Date</span></td>
                                    <td><?php echo $details['date']; ?></td>
                                </tr>
                                <tr>
                                    <td><span class="highlight">Status</span></td>
                                    <td>Active</td>
                                </tr>
                                <tr>
                                    <td><span class="highlight">Author/Developer</span></td>
                                    <td>Oliver M.</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>