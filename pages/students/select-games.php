<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';
require_once './check_enrollment_status.php';

$query = "SELECT * FROM login_history WHERE userID = :userID ORDER BY login_time DESC";
$stmt = $dbConnection->prepare($query);
$stmt->bindParam(':userID', $_SESSION['userID'], PDO::PARAM_INT);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// subjects
$userID = $_SESSION['userID'];

$sql = "
    SELECT s.subjectName, s.subjectID, m.moduleID, u.userID AS professorID, u.firstName AS professorFirstName, 
    u.lastName AS professorLastName, u.image AS professorImage, i.backgroundImg
    FROM enrollments e
    JOIN subjects s ON e.subjectID = s.subjectID
    JOIN modules m ON e.moduleID = m.moduleID
    JOIN users u ON e.professorID = u.userID
    LEFT JOIN images i ON e.imageID = i.imageID
    WHERE e.userID = :userID AND e.archived = 0
    ORDER BY e.dateEnrolled DESC
";

$stmt = $dbConnection->prepare($sql);
$stmt->execute([':userID' => $userID]);
$enrollmentsSub = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <!-- My CSS -->
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/subjects_sidebar.css">
    <link rel="stylesheet" href="./utils/feedback.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <script src="./logout.js"></script>
    <title>Learnify</title>
    <style>
        #sidebar.hide .submenu-title {
            display: none;
        }

        #sidebar.hide .subject-item .subject-texts {
            display: none;
        }

        #sidebar.hide .subject-item .subject-icon {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 16px;
            color: white;
            margin-right: 1px;
        }

        .subject-icon {
            width: 35px;
            height: 35px;
        }
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .games-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 40px;
            padding: 30px 20px;
            max-width: 600px;
            border-radius: 10px;
            margin: 0 auto;
            background-color: var(--light);
        }

        .game-item {
            display: flex;
            flex-direction: column;
            align-items: left;
            text-align: left;
            width: 100%;
        }

        .game-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .game-description {
            font-size: 1.1rem;
            color: var(--dark-grey);
            margin-bottom: 15px;
            max-width: 600px;
            line-height: 1.6;
        }

        .game-image {
            width: 100%;
            max-width: 600px;
            height: 450px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .game-button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(to right, #48bb78, #38b2ac);
            color: #fff;
            font-size: 1rem;
            width: 30%;
            margin: 0 auto;
            text-align: center;
            font-weight: 600;
            text-decoration: none;
            border-radius: 50px;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .game-button:hover {
            background: linear-gradient(to right, #3c9f63, #2c9288);
            transform: scale(1.05);
        }

        .video-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
        }

        .video-modal-container .modal-content {
            background-color: var(--light);
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            position: relative;
        }

        .modal-video {
            width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .demo-btn {
            cursor: pointer;
            border: none;
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 30px;
            font-weight: bold;
            color: #333;
            cursor: pointer;
            display: none;
        }

        .close-modal:hover {
            color: #ff0000;
        }

        .fullscreen-btn {
            display: inline-block;
            margin-top: 20px;
            border: none;
            padding: 8px 20px;
            background: linear-gradient(to right, #48bb78, #38b2ac);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 50px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .fullscreen-btn:hover {
            background: linear-gradient(to right, #3c9f63, #2c9288);
        }

        @media (max-width: 600px) {
            .game-title {
                font-size: 1.5rem;
            }

            .game-description {
                font-size: 1rem;
            }

            .game-image {
                height: 200px;
                max-width: 100%;
            }

            .game-button {
                padding: 10px 20px;
                font-size: 0.9rem;
                width: 100%;
            }

            .games-container {
                padding: 20px 15px;
                gap: 30px;
            }

            .video-modal-container .modal-content {
                width: 95%;
                padding: 10px;
            }

            .modal-video {
                height: 200px;
            }

            .close-modal {
                font-size: 24px;
                top: 5px;
                right: 15px;
            }

            .fullscreen-btn {
                font-size: 0.8rem;
                padding: 6px 15px;
            }
        }

        details {
            margin-bottom: 15px;
            width: 100%;
            max-width: 600px;
        }

        summary {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            background-color: var(--light-grey);
            border-bottom: 1px solid var(--dark);
            width: 27%;
        }

        .game-details-list {
            list-style: none;
            padding: 10px 15px;
            margin: 0;
        }

        .game-details-list li {
            font-size: 1.1rem;
            color: var(--dark-grey);
            line-height: 1.5;
            margin-bottom: 8px;
            padding-left: 12px;
            position: relative;
        }

        .game-details-list li::before {
            content: "•";
            color: #48bb78;
            position: absolute;
            left: 0;
            top: -7px;
            font-size: 2rem;
        }

        .game-details-list li strong {
            color: var(--dark);
            margin-left: 5px;
        }

        @media (max-width: 600px) {
            details {
                margin-bottom: 10px;
            }

            summary {
                font-size: 1rem;
                padding: 6px;
                width: 60%;
            }

            .game-details-list li::before {
                top: -11px;
            }

            .game-details-list li {
                font-size: 0.9rem;
            }
        }
          @import url('https://fonts.googleapis.com/css2?family=Caveat:wght@700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');

        .brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            padding: 10px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #007bff, #0056b3);
            position: relative; 
        }

        .logo_img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
            transition: transform 0.3s ease;
        }

        #logo_text {
            font-family: 'Caveat', cursive;
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1;
        }

        .brand::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: 1px; 
            left: 0;
            background-color: var(--dark); 
            transition: width 0.4s ease-in-out; 
        }

        .brand:hover {
            transform: translateY(-2px);
        }

        .brand:hover::after {
            width: 100%; 
        }

        .brand:hover .logo_img {
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .brand {
                padding: 8px 15px;
            }

            .logo_img {
                width: 32px;
                height: 32px;
            }

            #logo_text {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <a href="./studentDash.php" class="brand">
            <img class="logo_img" src="./img/darky-1.png" alt="">
            <span class="text" id="logo_text">Learnify</span>
        </a>
        
        <ul class="side-menu top">
            <li>
                <a href="./studentDash.php">
                    <i class='bx bxs-home'></i>
                    <span class="text">Home</span>
                </a>
            </li>
            <li class="active">
                <a href="./select-games.php">
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
                <a href="./location.php">
                    <i class='bx bxs-map'></i>
                    <span class="text">Geolocation</span>
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
                    <input type="search" name="query" placeholder="Search users..." required hidden>
                    <button type="submit" class="search-btn" style="display: none;"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="notifications.php" class="notification">
                <i class='bx bxs-bell'></i>
                <span class="num"></span>
            </a>
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
                    <h1>Select Games</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Home</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="active" href="./select-games.php">Games</a>
                        </li>
                    </ul>
                </div>
            </div>
            <br>
            <div class="games-container">
                <!-- Game 1 -->
                <div class="game-item">
                    <h2 class="game-title">1. Word Search</h2>
                    <p class="game-description">Word Search is an engaging game where students search for words in a grid across multiple levels, earning coins, stars, and a certificate for completing all levels, unlocking educational lessons after each level, while teachers generate Game Codes for custom puzzles.</p>
                    <details>
                        <summary>Game Details</summary>
                        <ul class="game-details-list">
                            <li><strong>Gameplay</strong>: Find words in a grid (horizontal, vertical, or diagonal). Earn stars based on speed: ⭐⭐⭐ (under 1 minute 30 seconds), ⭐⭐ (under 3 minutes), ⭐ (over 3 minutes).</li>
                            <li><strong>Rewards</strong>: Earn coins per word, stars for speed, and a certificate for all levels.</li>
                            <li><strong>Levels & Lessons</strong>: Explore topics like landforms or holidays, unlocking lessons after each level.</li>
                            <li><strong>Badges</strong>: Earn badges every 7 levels, from Beginner (1-21) to King Master (190-210).</li>
                        </ul>
                    </details>
                    <img src="./img/word-serach-01.png" alt="Word Search" class="game-image">
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <a href="http://localhost/learnify_game/" class="game-button">Play Now</a>
                        <button class="game-button demo-btn" data-video="./videos/Word_Serach.mp4">Add Demo</button>
                    </div>
                </div>
                <!-- Game 2 -->
                <div class="game-item">
                    <h2 class="game-title">2. Learnify Quiz</h2>
                    <p class="game-description">LearnifyQuiz is a fun, engaging platform where students can test their knowledge with exciting multiple-choice quizzes under a thrilling time limit. Teachers create unique Game Codes, allowing students to join and compete in real-time, making learning interactive and dynamic!</p>
                    <img src="./img/learnify_logo.jpg" alt="Word Puzzle" class="game-image">
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <a href="http://localhost/new-gmae/index.php" class="game-button">Play Now</a>
                        <button class="game-button demo-btn" data-video="./videos/Learnify_Quiz.mp4">Add Demo</button>
                    </div>
                </div>
            </div>

            <!-- Video Modal -->
            <div id="videoModal" class="video-modal video-modal-container">
                <div class="modal-content">
                    <span class="close-modal">×</span>
                    <video id="demoVideo" class="modal-video" controls>
                        <source id="videoSource" src="" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    <button class="fullscreen-btn" onclick="toggleFullScreen()">Full Screen</button>
                </div>
            </div>

            <a href="feedback.php" class="feedback-btn">
                <i class="bx bxs-comment-detail"></i>
            </a>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Get modal and video elements
        const modal = document.getElementById('videoModal');
        const video = document.getElementById('demoVideo');
        const videoSource = document.getElementById('videoSource');
        const closeModal = document.querySelector('.close-modal');
        const demoButtons = document.querySelectorAll('.demo-btn');

        // Open modal and set video source
        demoButtons.forEach(button => {
            button.addEventListener('click', () => {
                const videoPath = button.getAttribute('data-video');
                videoSource.setAttribute('src', videoPath);
                video.load(); // Reload video with new source
                modal.style.display = 'flex';
                video.play(); // Auto-play the video
            });
        });

        // Close modal
        closeModal.addEventListener('click', () => {
            modal.style.display = 'none';
            video.pause(); // Pause video when closing
            video.currentTime = 0; // Reset video to start
        });

        // Close modal when clicking outside
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
                video.pause();
                video.currentTime = 0;
            }
        });

        // Full-screen functionality
        function toggleFullScreen() {
            if (video.requestFullscreen) {
                video.requestFullscreen();
            } else if (video.mozRequestFullScreen) { /* Firefox */
                video.mozRequestFullScreen();
            } else if (video.webkitRequestFullscreen) { /* Chrome, Safari, Opera */
                video.webkitRequestFullscreen();
            } else if (video.msRequestFullscreen) { /* IE/Edge */
                video.msRequestFullscreen();
            }
        }
    </script>
</body>
</html>
