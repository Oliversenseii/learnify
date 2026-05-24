<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../../img/learnify-logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <title>Enrollment Status - Learnify</title>
    <style>
        :root {
            --primary: #1a73e8; /* Google Classroom blue */
            --primary-dark: #174ea6;
            --success: #34a853; /* Google green */
            --success-dark: #2c8d42;
            --error: #EF4444; /* Matches requested red */
            --error-dark: #B91C1C;
            --pending: #F59E0B; /* Matches requested yellow */
            --pending-dark: #D97706;
            --background: #f0f4f8; /* Light gray-blue background */
            --surface: #FFFFFF;
            --text-primary: #202124; /* Google dark text */
            --text-secondary: #5f6368; /* Google secondary text */
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            --shadow-hover: 0 4px 12px rgba(0, 0, 0, 0.2);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #e8f0fe 0%, #f0f4f8 100%); /* Subtle Google-like gradient */
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }

        .container {
            max-width: 32rem;
            width: 100%;
            background: var(--surface);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 2rem;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .container .icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            color: var(--pending);
            transition: var(--transition);
        }

        .container .icon.dropped {
            color: var(--error);
        }

        .container h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .container h1.pending {
            color: var(--pending);
        }

        .container h1.dropped {
            color: var(--error);
        }

        .container p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            color: white;
            width: 100%;
            max-width: 20rem;
            justify-content: center;
            margin: 0.5rem auto;
            box-shadow: var(--shadow);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .btn-login:hover {
            background: linear-gradient(135deg, var(--primary-dark), #135a9c);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-contact {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
        }

        .btn-contact:hover {
            background: linear-gradient(135deg, var(--success-dark), #2c7a3f);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .bx {
            font-size: 1.25rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }

            .container .icon {
                font-size: 3rem;
            }

            .container h1 {
                font-size: 1.75rem;
            }

            .container p {
                font-size: 1rem;
            }

            .btn {
                font-size: 0.9rem;
                padding: 0.65rem 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
            }

            .container .icon {
                font-size: 2.5rem;
            }

            .container h1 {
                font-size: 1.5rem;
            }

            .container p {
                font-size: 0.9rem;
            }

            .btn {
                font-size: 0.85rem;
                padding: 0.6rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        $status = isset($_GET['status']) ? urldecode($_GET['status']) : 'Unknown';
        $statusClass = '';
        $header = '';
        $message = '';

        if ($status === 'Pending') {
            $statusClass = 'pending';
            $header = 'Enrollment Pending';
            $message = 'Your enrollment is currently pending approval. Please contact the administrator to complete your enrollment process.';
        } elseif ($status === 'Dropped') {
            $statusClass = 'dropped';
            $header = 'Enrollment Dropped';
            $message = 'Your enrollment has been dropped. Please contact the administrator to resolve this issue.';
        } else {
            $statusClass = 'dropped';
            $header = 'Enrollment Status Unknown';
            $message = 'There was an issue determining your enrollment status. Please contact the administrator for assistance.';
        }
        ?>

        <i class='bx bx-error-circle icon <?php echo htmlspecialchars($statusClass); ?>'></i>
        <h1 class="<?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($header); ?></h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        
        <a href="/capstone-lms/index.php" class="btn btn-login">
            <i class='bx bx-log-in'></i>
            <span>Back to Login</span>
        </a>
        <a href="mailto:admin@learnify.com?subject=Enrollment%20Status%20Inquiry" class="btn btn-contact">
            <i class='bx bx-envelope'></i>
            <span>Contact Admin via Email</span>
        </a>
        <a href="tel:+1234567890" class="btn btn-contact">
            <i class='bx bx-phone'></i>
            <span>Contact Admin via Phone</span>
        </a>
    </div>
</body>
</html>