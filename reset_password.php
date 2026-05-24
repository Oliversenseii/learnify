<?php
require_once './config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if 15 days have passed since last password change and get current password
    $stmt = $dbConnection->prepare("SELECT password_last_changed, password FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['password_last_changed']) {
        $last_changed = new DateTime($user['password_last_changed']);
        $now = new DateTime();
        $days_since_change = $now->diff($last_changed)->days;

        if ($days_since_change < 15) {
            $error = "You can only change your password every 15 days. Please try again after " . (15 - $days_since_change) . " days.";
        }
    }

    // Password validation
    if (!isset($error)) {
        // Check if new password matches current password
        if ($user && password_verify($new_password, $user['password'])) {
            $error = "New password cannot be the same as your current password.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif (preg_match('/[\*\>\<]/', $new_password)) {
            $error = "Password cannot contain *, <, or > characters.";
        } elseif (preg_match('/[\p{So}]/u', $new_password)) {
            $error = "Password cannot contain emojis or special symbols.";
        } elseif (!preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $new_password)) {
            $error = "Password must contain both letters and numbers.";
        }
    }

    if (!isset($error)) {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        $stmt = $dbConnection->prepare("UPDATE users SET password = :password, otp = NULL, otp_expiration = NULL, password_last_changed = NOW() WHERE email = :email");
        $stmt->execute(['password' => $hashed_password, 'email' => $email]);

        header('Location: success.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Reset Password</title>
    <link rel="icon" href="./img/learnify-logo.png" type="image/x-icon">
    <style>
         ::selection {
            color: #fff; 
            background-color: #1877F2; 
        }
        body {
             background-color:  #eee;
            background-image: url("https://www.transparenttextures.com/patterns/white-wall-3.png");
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-form-container {
            background-color: #F9F9F9;
            padding: 50px;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 550px;
            text-align: center;
            position: fixed;
        }
        .title-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-top: -30px;
            margin-bottom: 20px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ccc;
        }

        .title-left {
            text-align: left;
            color: #342E37;
            font-size: clamp(1.1rem, 3vw, 1.2rem); 
        }

        .title-right img {
            max-width: 100px; 
            height: auto;
        }

        .title-left h2 {
            margin-bottom: 10px;
            font-size: clamp(2rem, 3vw, 2.5rem);
            text-transform: uppercase;
            color: #1877F2;
            font-weight: 900;
        }
        .title-left h3 {
            font-weight: 300;
        }
        .title-left h3 strong {
            font-weight: bold;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group label {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: #342E37;
            margin-bottom: 5px;
            display: block;
            font-weight: bold;
        }
        .input-group label span {
            color: red;
        }
        .input-group input {
            width: 100%;
            padding: 12px;
            font-size: clamp(1.1rem, 3vw, 1.2rem); 
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .error-message {
            color: #fff; 
            font-size: clamp(1.1rem, 3vw, 1.2rem); 
            background: linear-gradient(135deg, #dc3545, #c82333);
            padding: 10px 15px; 
            border-radius: 5px;
            margin-bottom: 10px; 
            border: 1px solid #e53935;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); 
            font-weight: bold; 
        }

        .back {
            width: 90px; 
            text-decoration: none;
            padding: 10px;
            font-size: clamp(1.2rem, 3vw, 1.3rem);
            font-weight: 600;
            color: #050505; 
            background-color: #e4e6eb;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            display: inline-block;
            margin-right: 10px; 
        }
        .login-btn {
            width: 90px; 
            padding: 10px;
            font-size: clamp(1.2rem, 3vw, 1.3rem);
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-block;
        }
        .login-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        .back:hover {
            background-color: #d8dadf; 
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        .button-wrapper {
            display: flex;
            justify-content: flex-end; 
            margin-top: 10px;
            padding-right: 10px; 
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        input:focus {
            border-color: #0056b3 !important;
            outline: none;
        }
        input:valid {
            border-color: #342E37;
        }

        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            margin: 25px auto;
            width: 90%;
            height: 4px;
            background: linear-gradient(135deg, #007bff, #0056b3, #28a745, #218838);
            margin-top: 40px;
            }

        .progress-bar::before {
            content: '';
            position: absolute;
            height: 7px;
            width: 0%;
            background: linear-gradient(135deg, #007bff, #0056b3, #28a745, #218838);
            z-index: 1;
            }

        .step {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 34px;
            height: 34px;
            background-color: #F9F9F9;
            border: 4px solid #ddd;
            border-radius: 50%;
            font-size: clamp(1.1rem, 3vw, 1.5rem); 
            color: #342E37;
        }
 
        .step-wrapper {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 50%;
            padding: 5px;
            display: inline-flex;
        }

        .step-wrapper .active {
            font-weight: 900;
        }

        .step-wrapper .active, .step-wrapper-dif .active, .step-wrapper-green .active {
            font-weight: 900;
        }

        .step-wrapper-dif {
            background: linear-gradient(90deg, #007bff, #0056b3, #28a745, #218838);
            border-radius: 50%;
            padding: 5px;
            display: inline-flex;
        }

        .step-wrapper-green {
            background: linear-gradient(135deg, #28a745, #218838);
            border-radius: 50%;
            padding: 5px;
            display: inline-flex;
        }
        
        .password-requirements {
            margin-top: 20px;
            background-color: #f8f9fa;
            border-left: 4px solid #218838;
            padding: 15px;
            border-radius: 10px;
            text-align: left;
            color: #2c3e50;
        }
        .password-requirements h4 {
            margin: 0 0 10px 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 600;
            color: #2c3e50;
        }
        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .password-requirements li {
            position: relative;
            padding-left: 25px;
            margin-bottom: 8px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            line-height: 1.5;
        }
        .password-requirements li::before {
            content: '\f058';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            color: #4CAF50;
            position: absolute;
            left: 0;
            top: 2px;
        }
        

         @media screen and (max-width: 768px) {
            .login-form-container {
                height: 85vh;
                overflow-y: auto;
            }
            .login-form-container {
                padding: 30px;
                width: 75%;
            }       

            .title-container {
                margin-top: -10px;
            }
            .title-right {
                display: none;
            }
            .progress-bar {
                width: 100%;
            }
            .step {
                width: 24px;
                height: 24px;
            }
            .password-requirements {
                padding: 12px;
                font-size: 13px;
            }
            .password-requirements h4 {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="login-form-container">
        <header class="title-container">
            <div class="title-left">
                <h2>Reset your password</h2>
                <h3><strong>Step 4:</strong> Enter your new password and confirm it. Make sure you meet the password requirements.</h3>
            </div>
            <div class="title-right">
                <img src="./img/DIHS_logo.png" alt="">
            </div>
        </header>

        <div class="progress-bar">
             <div class="step-wrapper">
                <div class="step active">1</div>
            </div>
            <div class="step-wrapper">
                <div class="step active">2</div>
            </div>
            <div class="step-wrapper-dif">
                <div class="step active">3</div>
            </div>
            <div class="step-wrapper-green">
                <div class="step active">4</div>
            </div>
            <div class="step">5</div>
        </div>
        
        <div class="password-requirements">
            <h4>Password Requirements</h4>
            <ul>
                <li>Be at least 8 characters long</li>
                <li>Contain both letters and numbers</li>
                <li>Not contain *, <, >, or emojis</li>
            </ul>
        </div>

        <form method="POST" action="reset_password.php?email=<?php echo htmlspecialchars($_GET['email']); ?>">
            <br>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="input-group">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email']); ?>">
                <label for="password">New Password <span>*</span> </label>
                <input type="password" name="password" placeholder="Enter your password" id="password" required>
            </div>
            <div class="input-group">
                <label for="confirm_password">Confirm Password <span>*</span> </label>
                <input type="password" name="confirm_password" placeholder="Confirm your Password" id="confirm_password" required>
            </div>
            <div class="button-wrapper">
                <a href="./forgot_password.php" class="back">Cancel</a>
                <button type="submit" class="login-btn">Submit</button>
            </div>
        </form>
    </div>
</body>
</html>