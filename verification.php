<?php
require_once './config/db_connection.php';

$error = '';  
$firstName = '';
$lastName = '';
$otp = '';
$image = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $otp = $_POST['otp'];

    $stmt = $dbConnection->prepare("SELECT otp, otp_expiration, firstName, lastName, image FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['otp'] === $otp) {
        $otp_expiration = $user['otp_expiration'];
        if (strtotime($otp_expiration) > time()) {
            header('Location: validation.php?email=' . urlencode($email));
            exit();
        } else {
            $error = 'OTP has expired.';
        }
    } else {
        $error = 'Invalid OTP.';
    }
} else {
    $email = isset($_GET['email']) ? $_GET['email'] : '';
    if ($email) {
        $stmt = $dbConnection->prepare("SELECT otp, firstName, lastName, image FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $otp = $user['otp'];
            $firstName = $user['firstName'];
            $lastName = $user['lastName'];
            $image = $user['image']; 
        } else {
            $error = 'Email not found.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/index.css">
    <link rel="icon" href="./img/learnify-logo.png" type="image/x-icon">
    <title>Authentication - <?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></title>
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
            max-width: 530px;
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
            text-align: center; 
            display: flex;
            justify-content: center;
            align-items: center; 
            border-top: 1px solid #ccc;
            padding-top: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 7px;
        }
        .input-group label {
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: #342E37;
            display: flex;
            align-items: center;
            font-weight: bold;
        }
        .input-group label span {
            color: red;
        }
        .input-group input {
            width: 100%;
            padding: 12px;
            font-size: clamp(1rem, 3vw, 1.2rem); 
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .input-group input[type="checkbox"] {
            display: none;
        }
        .checkbox-label {
            position: relative;
            padding-left: 30px;
            cursor: pointer;
            user-select: none;
        }
        .custom-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid #342E37;
            border-radius: 50%;
            background-color: #fff;
            transition: all 0.3s;
            margin-right: 10px; 
            font-size: clamp(1.1rem, 3vw, 1.2rem); 
        }
        .input-group input[type="checkbox"]:checked + .custom-checkbox {
            background-color: #fff;
            border-color: #0056b3;
        }
        .custom-checkbox::after {
            content: '';
            display: block;
            width: 10px;
            height: 10px;
            background-color: transparent;
            border-radius: 50%;
            margin: 5px auto; 
        }
        .input-group input[type="checkbox"]:checked + .custom-checkbox::after {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        .error-message {
            color: #fff; 
            font-size: clamp(1rem, 3vw, 1.2rem); 
            background: linear-gradient(135deg, #dc3545, #c82333);
            padding: 10px 15px; 
            border-radius: 5px;
            border: 1px solid #e53935;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); 
            font-weight: bold; 
        }
        .back {
            width: 90px; 
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
            width: 90px; 
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
        }
        input:focus {
            border-color: #0056b3 !important;
            outline: none;
        }
        input:valid {
            border-color: #342E37;
        }
        .loading-bar-container {
            display: none;
            width: 100%;
            height: 4px;
            background-color: #f3f3f3;
            margin: 10px 0;
        }
        .loading-bar {
            width: 0;
            height: 100%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            animation: loading 3s infinite;
        }
        @keyframes loading {
            0% { width: 0; }
            50% { width: 50%; }
            100% { width: 100%; }
        }
        .note {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            width: 80%;
            background: linear-gradient(135deg, #ffffff, #e6e6e6);
            border-radius: 10px;
            margin: 0 auto;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); 
            padding: 10px;
            margin-bottom: 20px;
        }
        .note h2 {
            background-color: red;
            color: white;
            padding: 5px;
            border-radius: 10px;
            width: 20%;
            margin: 0 auto;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            margin-top: 5px;
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
            height: 4px;
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

        .user-img {
            max-width: 150px; 
            height: auto; 
            border-radius: 50%; 
            margin-bottom: 20px;
        }

        .user-email {
            font-size: clamp(1.1rem, 3vw, 1.3rem); 
            font-weight: 600;
            margin-top: -15px;
        }

        .learnify-user {
            font-size: clamp(1.1rem, 3vw, 1.3rem); 
            font-weight: 100;
            margin-top: -12px;
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
                <h2>Email Authentication</h2>
                <h3><strong>Step 2:</strong> Click the Continue button and check your email for the OTP.</h3>
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
            <div class="step-wrapper">
                <div class="step active">2</div>
            </div>
            <div class="step">3</div>
            <div class="step">4</div>
            <div class="step">5</div>
        </div>

        <form name="submit-to-google-sheet" onsubmit="showLoadingBar()">
            <br>
            <?php if (isset($error)): ?>
                <div class="error-message" hidden><?php echo $error; ?></div>
            <?php endif; ?>
            <input type="text" id="Name" name="Name" value="<?php echo htmlspecialchars($firstName . ' ' . $lastName); ?>" required readonly hidden>
            
            <input type="text" id="Otp" name="Otp" value="<?php echo htmlspecialchars($otp); ?>" required hidden>

            <input type="email" id="Email" name="Email" value="<?php echo htmlspecialchars($email); ?>" required readonly hidden>
            <br>
           <?php if ($image && $image !== './img/noprofile.png' && file_exists("lib/file_Uploads/" . $image)): ?>
                <div class="user-image">
                    <img class="user-img" src="lib/file_Uploads/<?php echo htmlspecialchars($image); ?>" alt="User Image">
                </div>
            <?php else: ?>
                <div class="user-image">
                    <img class="user-img" src="./img/noprofile.png" alt="Default User Image">
                </div>
            <?php endif; ?>
            <p class="user-email"><?php echo htmlspecialchars($email); ?></p>
            <p class="learnify-user">Learnify user</p>

            <div class="input-group">
                <label for="sendEmail" class="checkbox-label">
                    <input type="checkbox" id="sendEmail" name="sendEmail" checked>
                    <span class="custom-checkbox"></span> Send code via email
                </label>
            </div>

            <div class="button-wrapper">
                <a href="./forgot_password.php" class="back">Not you?</a>
                <button type="submit" class="login-btn">Continue</button>
            </div>
        </form>

         <!-- Loading Bar -->
         <div class="loading-bar-container" id="loadingBarContainer">
            <div class="loading-bar" id="loadingBar"></div>
        </div>
    </div>

    <script>
        const scriptURL = 'https://script.google.com/macros/s/AKfycbyPMBy_eIh4Rq2t79LqpqvMxeIgpg9Xj_NYYj-ushEUPrxsU1fiQbZ3Y2WxDDA3wuYgZQ/exec';
        const form = document.forms['submit-to-google-sheet'];

        form.addEventListener('submit', e => {
            e.preventDefault();

            const formData = new FormData(form);
            const Email = formData.get('Email'); 

            if (!Email) {
                alert('Email is missing in the form. Please check the form fields.');
                return;
            }

            fetch(scriptURL, { method: 'POST', body: formData })
                .then(response => {
                    if (response.ok) {
                        console.log('Form submitted successfully!');
                        window.location.href = `validation.php?email=${encodeURIComponent(Email)}`;
                    } else {
                        alert('Form submission failed. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error during form submission:', error.message);
                    alert('An error occurred. Please try again.');
                });
        });

        function showLoadingBar() {
            document.getElementById("loadingBarContainer").style.display = "block";
        }
    </script>
</body>
</html>
