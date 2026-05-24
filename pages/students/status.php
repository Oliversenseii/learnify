<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './academic_events.php';

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
    // Get user details and enrollment status
    $stmt = $dbConnection->prepare("
        SELECT u.firstName, u.lastName, u.middleName, u.userType, u.image, ss.status
        FROM users u
        LEFT JOIN student_section ss ON u.userID = ss.userID
        WHERE u.userID = :userID
        LIMIT 1
    ");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['lastName'] = htmlspecialchars($user['lastName']);
        $_SESSION['middleName'] = htmlspecialchars($user['middleName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
        $status = $user['status'];
       
        // If status is Enrolled, redirect to dashboard
        if ($status === 'Enrolled') {
            header("Location: ./studentDash.php");
            exit;
        }
    } else {
        header("Location: ../../index.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - SHS Track and Strand Status</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #4285f4;
            --primary-green: #34a853;
            --light-bg: #fafbfc;
            --white: #ffffff;
            --text-primary: #202124;
            --text-secondary: #5f6368;
            --border-color: #dadce0;
            --shadow-light: 0 1px 2px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --border-radius: 8px;
            --light: #F9F9F9;
            --blue: #3C91E6;
            --light-blue: #CFE8FF;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --red: #DB504A;
            --light-red: #F5A5A0;
            --yellow: #FFCE26;
            --light-yellow: #FFF2C6;
            --orange: #FD7238;
            --light-orange: #FFE0D3;
        }
        body {
            font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background: var(--white);
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--shadow-light);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar-tabs {
            display: flex;
            gap: 1.5rem;
        }
        .navbar-tabs a {
            text-decoration: none;
            color: var(--text-secondary);
            font-size: clamp(1.2rem, 2vw, 1.4rem);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        .navbar-tabs a:hover,
        .navbar-tabs a.active {
            color: var(--primary-blue);
            background: var(--light-bg);
        }
        .navbar .logout-btn {
            padding: 0.8rem 1.5rem;
            font-size: clamp(1.2rem, 2vw, 1.4rem);
            font-weight: 500;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .navbar .logout-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        #content {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .status-container, .faq-container, .profile-container {
            max-width: 600px;
            width: 100%;
            padding: 3rem 2.5rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            text-align: center;
            margin-bottom: 2rem;
        }
        .status-header, .faq-header, .profile-header {
            font-size: clamp(2.5rem, 3vw, 3.5rem);
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 1rem;
            line-height: 1;
        }
        .status-subtitle, .faq-subtitle, .profile-subtitle {
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
            font-weight: 400;
        }
        .profile-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 3rem;
            padding: 1.5rem;
            background: var(--light-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        .profile-info img {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--white);
            box-shadow: var(--shadow-md);
        }
        .profile-details {
            text-align: left;
        }
        .profile-details p {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.5rem 0;
            text-transform: uppercase;
        }
        .profile-details small {
            font-size: clamp(1.3rem, 3vw, 1.5rem);
            color: var(--text-secondary);
            font-weight: 400;
        }
        .status-icon {
            font-size: 6rem;
            margin-bottom: 1.5rem;
        }
        .status-icon.pending {
            color: #fbbc04;
        }
        .status-icon.dropped {
            color: #c82333;
        }
        .status-icon.completed {
            color: var(--primary-green);
        }
        .status-message {
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        .status-message.pending,
        .status-message.dropped,
        .status-message.completed {
            color: var(--text-primary);
        }
        .status-message strong {
            color: var(--dark);
            font-weight: 500;
        }
        .faq-item, .profile-item {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .faq-item h3, .profile-item h3 {
            font-size: clamp(1.4rem, 2vw, 1.6rem);
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .faq-item p, .profile-item p {
            font-size: clamp(1.2rem, 2vw, 1.4rem);
            color: var(--text-secondary);
        }
        .contact-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-blue);
            color: var(--white);
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            z-index: 1000;
        }
        .contact-button:hover {
            background: #3267d6;
            transform: scale(1.1);
        }
        .contact-button i {
            font-size: 2rem;
        }
        .contact-container {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 350px;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            display: none;
            z-index: 1000;
            overflow: hidden;
        }
        .contact-container.active {
            display: block;
        }
        .contact-header {
            background: var(--primary-blue);
            color: var(--white);
            padding: 1rem;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 500;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .contact-header .close-btn {
            cursor: pointer;
            font-size: clamp(1.5rem, 3vw, 2rem);
        }
        .contact-body {
            padding: 1.5rem;
        }
        .contact-body p {
            margin: 0.5rem 0;
            color: var(--text-secondary);
            font-size: clamp(1.3rem, 3vw, 1.5rem);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .contact-body a {
            color: var(--primary-blue);
            text-decoration: none;
        }
        .contact-body a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }
            .navbar-tabs {
                flex-wrap: wrap;
                justify-content: center;
            }
            .status-container, .faq-container, .profile-container {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }
            .status-header, .faq-header, .profile-header {
                font-size: 2.5rem;
            }
            .status-subtitle, .faq-subtitle, .profile-subtitle {
                font-size: 1.3rem;
            }
            .status-message {
                font-size: 1.5rem;
            }
            .profile-info img {
                width: 100px;
                height: 100px;
            }
            .profile-details p {
                font-size: 1.8rem;
            }
            .profile-details small {
                font-size: 1.2rem;
            }
            .status-icon {
                font-size: 4.5rem;
            }
            .contact-container {
                width: 250px;
            }
        }
        @media (max-width: 480px) {
            .status-header, .faq-header, .profile-header {
                font-size: 2.2rem;
            }
            .status-message {
                font-size: 1.3rem;
            }
            .profile-info {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            .profile-details p {
                font-size: 1.6rem;
            }
            .profile-details small {
                font-size: 1.1rem;
            }
            .contact-container {
                width: 200px;
            }
            .contact-button {
                width: 50px;
                height: 50px;
            }
            .contact-button i {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <?php require_once './view/modal.php' ?>
    <nav class="navbar">
        <div class="navbar-tabs">
            <a href="#status" class="active">Status</a>
            <a href="#faqs">FAQs</a>
            <a href="#profile">Profile</a>
        </div>
        <button class="logout-btn" onclick="showLogoutModal()" aria-label="Logout">
            Sign out
        </button>
    </nav>
    <section id="content">
        <main>
            <div class="status-container" id="status">
                <h1 class="status-header">SHS Track and Strand Status</h1>
                <p class="status-subtitle">Welcome back to Learnify</p>
                <div class="profile-info">
                    <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image">
                    <div class="profile-details">
                        <p><?php echo htmlspecialchars($_SESSION['lastName'] . ', ' . $_SESSION['firstName'] . ' ' . $_SESSION['middleName']); ?></p>
                        <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                    </div>
                </div>
                <div class="status-icon <?php echo strtolower($status); ?>">
                    <i class='bx <?php 
                        if ($status === 'Pending') {
                            echo 'bx-hourglass';
                        } elseif ($status === 'Dropped') {
                            echo 'bx-x-circle';
                        } elseif ($status === 'Completed') {
                            echo 'bx-check-circle';
                        }
                    ?>'></i>
                </div>
                <p class="status-message <?php echo strtolower($status); ?>" 
                   aria-label="Your SHS track and strand status is <?php echo htmlspecialchars($status); ?>. <?php 
                        if ($status === 'Pending') {
                            echo 'Please wait for admin approval to access your track and strand.';
                        } elseif ($status === 'Dropped') {
                            echo 'Your enrollment has been dropped. Please contact the administrator for more details.';
                        } elseif ($status === 'Completed') {
                            echo 'You have successfully completed your SHS track and strand.';
                        }
                   ?>">
                    Your SHS track and strand status is currently <strong><?php echo htmlspecialchars($status); ?></strong>.
                    <?php if ($status === 'Pending'): ?>
                        Please wait for admin approval to access your track and strand.
                    <?php elseif ($status === 'Dropped'): ?>
                        Your enrollment has been dropped. Please contact the administrator for more details.
                    <?php elseif ($status === 'Completed'): ?>
                        You have successfully completed your SHS track and strand. Contact the administrator for certificate issuance or further details.
                    <?php endif; ?>
                </p>
            </div>
            <div class="faq-container" id="faqs">
                <h1 class="faq-header">Frequently Asked Questions</h1>
                <p class="faq-subtitle">Common questions about SHS track and strand</p>
                <div class="faq-item">
                    <h3>What does "Pending" status mean?</h3>
                    <p>A "Pending" status means your SHS track and strand application is under review by the administrator. You will be notified once it is approved.</p>
                </div>
                <div class="faq-item">
                    <h3>What does "Completed" status mean?</h3>
                    <p>A "Completed" status means you have successfully finished your SHS track and strand. Contact the administrator for certificate issuance or further details.</p>
                </div>
                <div class="faq-item">
                    <h3>What should I do if my status is "Dropped"?</h3>
                    <p>If your status is "Dropped," please contact the administrator via email or phone for further details and assistance.</p>
                </div>
                <div class="faq-item">
                    <h3>How long does it take to get approved?</h3>
                    <p>Approval times vary, but typically it takes 1-3 business days. Contact the administrator if you have urgent questions.</p>
                </div>
            </div>
            <div class="profile-container" id="profile">
                <h1 class="profile-header">Your Profile</h1>
                <p class="profile-subtitle">Manage your personal information</p>
                <div class="profile-item">
                    <h3>Update Profile Information</h3>
                    <p>You can update your name, email, and other details by contacting the administrator or through your dashboard once enrolled.</p>
                </div>
                <div class="profile-item">
                    <h3>Change Profile Picture</h3>
                    <p>Upload a new profile picture to personalize your account. This feature is available once your enrollment is approved.</p>
                </div>
            </div>
        </main>
    </section>
    <button class="contact-button" onclick="toggleContact()">
        <i class='bx bx-message-square-detail'></i>
    </button>
    <div class="contact-container" id="contactContainer">
        <div class="contact-header">
            <span>Contact Admin</span>
            <span class="close-btn" onclick="toggleContact()">&times;</span>
        </div>
        <div class="contact-body">
            <p><i class='bx bx-envelope'></i> Email: <a href="mailto:support@learnify.co">support@learnify.co</a></p>
            <p><i class='bx bx-phone'></i> Phone: <a href="tel:+15551234567">+1 (555) 123-4567</a></p>
        </div>
    </div>
    <script src="./utils/script.js"></script>
    <script>
        function toggleContact() {
            const contactContainer = document.getElementById('contactContainer');
            contactContainer.classList.toggle('active');
        }
        // Handle tab switching
        document.querySelectorAll('.navbar-tabs a').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.navbar-tabs a').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.status-container, .faq-container, .profile-container').forEach(container => {
                    container.style.display = 'none';
                });
                document.querySelector(this.getAttribute('href')).style.display = 'block';
            });
        });
        // Initially show status and hide other sections
        document.getElementById('status').style.display = 'block';
        document.getElementById('faqs').style.display = 'none';
        document.getElementById('profile').style.display = 'none';
    </script>
</body>
</html>