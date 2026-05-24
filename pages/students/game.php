<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';
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
    // Fetch current (non-archived) branding data
    $stmt = $dbConnection->prepare("SELECT id, logo_image_path, logo_text, updated_at FROM branding WHERE archived = 0 ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $currentBranding = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set defaults if no branding data exists
    $currentLogoImage = $currentBranding['logo_image_path'] ?? './img/darky-1.png';
    $currentLogoText = $currentBranding['logo_text'] ?? 'Learnify';
    $currentBrandingId = $currentBranding['id'] ?? null;
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "An error occurred: " . htmlspecialchars($e->getMessage()) . ". Please try again later.";
}

// Include notification count
require_once './get_notification_count.php';
$unreadCount = getUnreadAnnouncementCount($dbConnection, $userID);
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
    <title>Game Dashboard - Learnify</title>
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
            --warning: #ED8936;
            --white: #FFFFFF;
            --accent: #EDF2F7;
            --transition: all 0.3s ease;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
            --highlight-bg: #FFE4B5;
            --highlight-border: #F6AD55;
        }

        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
            --background: #1A202C;
            --text: #F7FAFC;
            --text-secondary: #A0AEC0;
            --border: #4A5568;
            --accent: #2D3748;
            --white: #2D3748;
            --highlight-bg: #FFD700;
            --highlight-border: #DAA520;
        }

        /* Pending Tasks Badge in Header */
        .dashboard-header-badge {
            background: var(--warning);
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.55rem;
            font-size: 0.9rem;
            font-weight: 600;
            min-width: 15px;
            text-align: center;
            margin-left: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .game-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 3fr));
            gap: 2rem;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .game-card {
            display: flex;
            flex-direction: column; 
            background-color: var(--light);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .game-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        .game-image {
            width: 100%; 
            height: 290px; 
            object-fit: cover;
        }

        .game-content {
            width: 100%; 
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .game-title {
            font-size: clamp(1.5rem, 3vw, 2.5rem);
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .game-tagline {
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 500;
            color: #1A202C;
            background-color: var(--highlight-bg);
            border-left: 4px solid var(--highlight-border);
            padding: 0.3rem 0.8rem;
            border-radius: 10px;
            margin-bottom: 0.8rem;
            display: inline-block;
        }

        .game-description {
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--dark);
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .play-button {
            background-color: var(--primary);
            color: white;
            font-size: clamp(1rem, 3vw, 1.3rem);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: inline-block;
        }

        .play-button:hover {
            background-color: var(--primary-dark);
            transform: scale(1.05);
        }

        @media screen and (max-width: 768px) {
            .game-container {
                margin-top: 10px;
                grid-template-columns: 1fr;
                padding: 0;
            }

            .game-card {
                flex-direction: column;
            }

            .game-image {
                width: 100%;
                height: 200px;
            }

            .game-content {
                width: 100%;
            }

            .game-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <?php require_once './dashboard_nav_item.php' ?>
            <li>
                <a href="./announcements.php?viewed=true">
                    <i class='bx bxs-bell'></i>
                    <span class="text">Announcements</span>
                    <!-- <?php if ($unreadCount > 0 && !$_SESSION['announcements_viewed']): ?>
                        <span class="notification-badge"><?php echo htmlspecialchars($unreadCount); ?></span>
                    <?php endif; ?> -->
                </a>
            </li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li class="active">
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
                    <input type="search" name="query" placeholder="Search Teachers and Students" required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
                <img src="<?php echo htmlspecialchars($_SESSION['image']); ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Game Dashboard</h1>
                    <ul class="breadcrumb">
                        <li><a href="./superAdminDash.php" style="color: var(--dark);">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./game.php">Games</a></li>
                    </ul>
                </div>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class='error-notification' style="background-color: var(--error); color: var(--white); padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class='success-notification' style="background-color: var(--success); color: var(--white); padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <div class="game-container">
                <!-- Game Card 1 -->
                <div class="game-card">
                    <img src="./image/pix-1.jpg" alt="Pixora" class="game-image">
                    <div class="game-content">
                        <div>
                            <h2 class="game-title">Pixora</h2>
                            <p class="game-tagline">3 Images. 1 Answer. Up to 3 Words</p>
                            <p class="game-description"> Test your brain with fun picture puzzles. Can you find the hidden word or phrase?</p>
                        </div>
                        <a href="./imageGame.php" class="play-button">Play Now</a>
                    </div>
                </div>

                <!-- Game Card 2 -->
                <div class="game-card">
                    <img src="./image/brainzap.jpg" alt="BrainZap" class="game-image">
                    <div class="game-content">
                        <div>
                            <h2 class="game-title">BrainZap</h2>
                            <p class="game-tagline">Ready, set, zap!</p>
                            <p class="game-description">Race against the clock, pick the right answer, and spark your brainpower.</p>
                        </div>
                        <a href="./learn_quizziz.php" class="play-button">Play Now</a>
                    </div>
                </div>

                <!-- Game Card 3 -->
                 <div class="game-card">
                    <img src="./image/Thinking-It-Out.jpg" alt="Think-It-Out" class="game-image">
                    <div class="game-content">
                        <div>
                            <h2 class="game-title">Think-It-Out</h2>
                            <p class="game-tagline">Pictures + Words. 1 Answer.</p>
                            <p class="game-description">Crack the clues and test your brain. Can you Think-It-Out?</p>
                        </div>
                        <a href="./brainpix.php" class="play-button">Play Now</a>
                    </div>
                </div>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>