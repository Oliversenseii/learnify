<?php
require_once './config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $stmt = $dbConnection->prepare("SELECT userID, email FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $otp = rand(100000, 999999);
            $otp_expiration = date('Y-m-d H:i:s', strtotime('+10 minutes')); 

            $updateStmt = $dbConnection->prepare("UPDATE users SET otp = :otp, otp_expiration = :otp_expiration WHERE email = :email");
            $updateStmt->execute(['otp' => $otp, 'otp_expiration' => $otp_expiration, 'email' => $email]);

            header('Location: verification.php?email=' . urlencode($email));
            exit();
        } else {
            $error = 'Email not found. Your search did not return any results. Please try again with other information.';
        }
    } else {
        $error = 'Invalid email address.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./assets/css/index.css">
    <link rel="icon" href="./img/learnify-logo.png" type="image/x-icon">
    <title>Forgot Password</title>
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
            max-width: 500px;
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
            font-size: clamp(1.5rem, 3vw, 2.5rem);
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
            padding: 15px;
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
            border: 1px solid #e53935;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); 
            font-weight: bold; 
        }

        .back {
            width: 85px; 
            text-decoration: none;
            padding: 7px;
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
            width: 85px; 
            padding: 7px;
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
            padding-top: 15px;
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
            height: 7px;
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
        
        .step-wrapper .active {
            font-weight: 900;
        }
 
        .step-wrapper {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 50%;
            padding: 5px;
            display: inline-flex;
        }

         @media screen and (max-width: 768px) {
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
        }
        
    </style>
</head>
<body>

    <div class="login-form-container">
        <header class="title-container">
            <!-- left -->
            <div class="title-left">
                <h2>Forgot Password</h2>
                <h3><strong>Step 1:</strong> Please enter your email to search for your account.</h3>
            </div>
            <!-- right -->
            <div class="title-right">
                <img src="./img/DIHS_logo.png" alt="">
            </div>
        </header>

        <div class="progress-bar">
            <div class="step-wrapper">
                <div class="step active">1</div>
            </div>
            <div class="step">2</div>
            <div class="step">3</div>
            <div class="step">4</div>
            <div class="step">5</div>
        </div>

        <form method="POST" action="forgot_password.php">
            <br>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="input-group">
                <label for="email">Email <span>*</span> </label>
                <input type="email" name="email" id="email" required placeholder="Enter your email">
            </div>
            <div class="button-wrapper">
                <a href="index.php" class="back">Cancel</a>
                <button type="submit" class="login-btn">Search</button>
            </div>  
        </form>
    </div>
</body>
</html>
