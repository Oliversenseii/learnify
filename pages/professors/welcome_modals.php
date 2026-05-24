<!-- welcome_modals.php -->
<?php if (!$hasSeenModals): ?>
    <!-- Welcome Overlays -->
    <div id="welcomeOverlay1" class="welcome-overlay" style="display: flex;">
        <div class="overlay-container">
            <!-- <button class="close-button" onclick="closeOverlay()">×</button> -->
            <img src="./welcome/dashboard/learnify.jpg" alt="Welcome Image" class="overlay-image">
            <div class="content-section">
                <h2>🎉 Welcome to Learnify! 🎉</h2>
                <p>Welcome to your teacher dashboard. Let's take a quick tour to get you started.</p>
            </div>
            <button class="action-button primary-button" onclick="showOverlay(2, 'right')">Next</button>
            <div class="progress-dots">
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        </div>
    </div>
    <div id="welcomeOverlay2" class="welcome-overlay" style="display: none;">
        <div class="overlay-container">
            <!-- <button class="close-button" onclick="closeOverlay()">×</button> -->
            <img src="./welcome/dashboard/teacher-dash.png" alt="Dashboard Image" class="overlay-image">
            <div class="content-section">
                <h2>📒 Dashboard Overview 📒</h2>
                <p>Your dashboard is your central hub for managing classes, schedules, quizzes, assignments, attendance, and announcements. Click the 'View' button to access and manage all these features.</p>
            </div>
            <button class="action-button primary-button" onclick="showOverlay(3, 'left')">Next</button>
            <div class="progress-dots">
                <span class="dot"></span>
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        </div>
    </div>
    <div id="welcomeOverlay3" class="welcome-overlay" style="display: none;">
        <div class="overlay-container">
            <!-- <button class="close-button" onclick="closeOverlay()">×</button> -->
            <img src="./welcome/dashboard/pass-change.png" alt="Security Image" class="overlay-image">
            <div class="content-section">
                <h2>🔐 Password Security 🔐</h2>
                <p>Keep your account secure by regularly updating your password in the Settings section.</p>
            </div>
            <button class="action-button primary-button" onclick="showOverlay(4, 'top')">Next</button>
            <div class="progress-dots">
                <span class="dot"></span>
                <span class="dot"></span>
                <span class="dot active"></span>
                <span class="dot"></span>
            </div>
        </div>
    </div>
    <div id="welcomeOverlay4" class="welcome-overlay" style="display: none;">
        <div class="overlay-container">
            <!-- <button class="close-button" onclick="closeOverlay()">×</button> -->
            <img src="./welcome/dashboard/pass-sec.png" alt="Urgent Password Change Image" class="overlay-image">
            <div class="content-section">
                <h2>📝 Change Password Now 📝</h2>
                <p>We strongly recommend updating your password immediately for security. Do it now?</p>
            </div>
            <form method="post" action="./professor_main_dash.php" class="overlay-actions">
                <button type="submit" name="complete_modals" value="yes" class="action-button success-button">Yes</button>
                <button type="submit" name="complete_modals" value="got_it" class="action-button secondary-button">Got It</button>
            </form>
            <div class="progress-dots">
                <span class="dot"></span>
                <span class="dot"></span>
                <span class="dot"></span>
                <span class="dot active"></span>
            </div>
        </div>
    </div>

    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --success-dark: #059669;
            --secondary-color: #6b7280;
            --secondary-dark: #4b5563;
            --text-color: #111827;
            --text-secondary: #4b5563;
            --background-color: #ffffff;
            --gradient: linear-gradient(135deg, #f5f7fa 0%, #e4e7eb 100%);
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }

        .welcome-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000001;
            backdrop-filter: blur(6px);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .overlay-container {
            background: var(--gradient);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 850px;
            width: 90%;
            height: 650px;
            margin: auto;
            text-align: center;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            overflow-y: auto;
            transform: translateY(0);
            opacity: 1;
            transition: transform 0.5s ease, opacity 0.5s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .overlay-image {
            display: block;
            width: 100%;
            max-width: 100%;
            max-height: 60%;
            margin: 0 auto 1rem;
            border-radius: 12px;
            object-fit: contain;
            border: 2px solid #ccc;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            transition: transform 0.3s ease;
        }

        .overlay-image:hover {
            transform: scale(1.02);
        }

        .content-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin-bottom: 1rem;
        }

        .overlay-container h2 {
            font-size: clamp(2rem, 3vw, 4rem);
            margin: 0.5rem 0;
            color: var(--text-color);
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.025em;
        }

        .overlay-container p {
            font-size: clamp(1.2rem, 3vw, 1.6rem);
            margin: 0 0 1rem;
            color: var(--text-secondary);
            line-height: 1.6;
            font-weight: 500;
            max-width: 90%;
        }

        .action-button {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin: 0.5rem;
        }

        .action-button:hover {
            transform: scale(1.05);
        }

        .primary-button {
            background: var(--primary-color);
            color: #ffffff;
        }

        .primary-button:hover {
            background: var(--primary-dark);
        }

        .success-button {
            background: var(--success-color);
            color: #ffffff;
        }

        .success-button:hover {
            background: var(--success-dark);
        }

        .secondary-button {
            background: var(--secondary-color);
            color: #ffffff;
        }

        .secondary-button:hover {
            background: var(--secondary-dark);
        }

        .overlay-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .close-button {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .close-button:hover {
            color: var(--text-color);
            transform: scale(1.2);
        }

        .progress-dots {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .dot {
            width: 10px;
            height: 10px;
            background: var(--secondary-color);
            border-radius: 50%;
            opacity: 0.3;
        }

        .dot.active {
            background: var(--primary-color);
            opacity: 1;
        }

        /* Slide Animations */
        .slide-in-right {
            transform: translateX(100%);
            opacity: 0;
        }

        .slide-in-left {
            transform: translateX(-100%);
            opacity: 0;
        }

        .slide-in-top {
            transform: translateY(-100%);
            opacity: 0;
        }

        .slide-in-bottom {
            transform: translateY(100%);
            opacity: 0;
        }

        .slide-out-right {
            transform: translateX(100%);
            opacity: 0;
        }

        .slide-out-left {
            transform: translateX(-100%);
            opacity: 0;
        }

        .slide-out-top {
            transform: translateY(-100%);
            opacity: 0;
        }

        .slide-out-bottom {
            transform: translateY(100%);
            opacity: 0;
        }

        @media (max-width: 768px) {
            .overlay-container {
                padding: 1.5rem;
                max-width: 500px;
                height: 450px;
            }

            .overlay-image {
                max-height: 40%;
            }

            .overlay-container h2 {
                font-size: clamp(1.25rem, 2.5vw, 1.75rem);
            }

            .overlay-container p {
                font-size: clamp(0.75rem, 1.8vw, 1rem);
            }

            .action-button {
                padding: 0.6rem 1.5rem;
                font-size: clamp(0.75rem, 1.8vw, 1rem);
            }
        }

        @media (max-width: 480px) {
            .overlay-container {
                padding: 1.25rem;
                max-width: 340px;
                height: 400px;
            }

            .overlay-image {
                max-height: 35%;
            }

            .overlay-container h2 {
                font-size: clamp(1rem, 2vw, 1.5rem);
            }

            .overlay-container p {
                font-size: clamp(0.7rem, 1.5vw, 0.875rem);
            }

            .action-button {
                padding: 0.5rem 1.2rem;
                font-size: clamp(0.7rem, 1.5vw, 0.875rem);
            }

            .overlay-actions {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
    </style>

    <script>
        function showOverlay(step, direction) {
            const currentOverlay = document.querySelector('.welcome-overlay[style*="display: flex"]');
            const nextOverlay = document.getElementById(`welcomeOverlay${step}`);

            if (currentOverlay && nextOverlay) {
                let slideOutClass;
                switch (direction) {
                    case 'right': slideOutClass = 'slide-out-left'; break;
                    case 'left': slideOutClass = 'slide-out-right'; break;
                    case 'top': slideOutClass = 'slide-out-bottom'; break;
                    case 'bottom': slideOutClass = 'slide-out-top'; break;
                    default: slideOutClass = 'slide-out-right';
                }

                currentOverlay.querySelector('.overlay-container').classList.add(slideOutClass);
                setTimeout(() => {
                    currentOverlay.style.display = 'none';
                    currentOverlay.querySelector('.overlay-container').classList.remove(slideOutClass);

                    let slideInClass;
                    switch (direction) {
                        case 'right': slideInClass = 'slide-in-right'; break;
                        case 'left': slideInClass = 'slide-in-left'; break;
                        case 'top': slideInClass = 'slide-in-top'; break;
                        case 'bottom': slideInClass = 'slide-in-bottom'; break;
                        default: slideInClass = 'slide-in-right';
                    }

                    nextOverlay.style.display = 'flex';
                    nextOverlay.querySelector('.overlay-container').classList.add(slideInClass);
                    setTimeout(() => {
                        nextOverlay.querySelector('.overlay-container').classList.remove(slideInClass);
                    }, 500);
                }, 500);
            } else if (nextOverlay) {
                nextOverlay.style.display = 'flex';
            }
        }

        function closeOverlay() {
            const currentOverlay = document.querySelector('.welcome-overlay[style*="display: flex"]');
            if (currentOverlay) {
                currentOverlay.querySelector('.overlay-container').classList.add('slide-out-top');
                setTimeout(() => {
                    currentOverlay.style.display = 'none';
                    currentOverlay.querySelector('.overlay-container').classList.remove('slide-out-top');
                }, 500);
            }
        }
    </script>
<?php endif; ?>