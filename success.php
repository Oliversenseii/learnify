<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/index.css">
    <link rel="icon" href="./img/learnify-logo.png" type="image/x-icon">
    <title>Successful</title>
    <style>
        .login-form-container .message-success h3{
            font-size: clamp(2rem, 3vw, 2.5rem);
            margin-top: -5px;
        }
        .login-form-container .message-success p {
            font-size: clamp(1.5rem, 3vw, 2rem);
            margin-top: -20px;
        }
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
        }

        .title-right img {
            max-width: 100px; 
            height: auto;
        }

        .title-left h2 {
            margin-bottom: 10px;
            font-size: clamp(1.1rem, 3vw, 2rem);
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

        .login-btn {
            width: 100%;
            padding: 12px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: white;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .login-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);

        }
        input:focus {
            border-color: #0056b3 !important;
            outline: none;
        }
        input:valid {
            border-color: #342E37;
        }

       .footer-container {
            text-align: center;
            font-weight: 100;
            font-size: clamp(0.7rem, 3vw, 0.9rem);
            margin-top: 30px;
       }

        .footer-left,
        .footer-left h3 a span {
            margin-top: 10px;
            color: #342E37;
        }

        .footer-left h3 a {
            color: #1877F2;
            text-decoration: none;
        }

        .footer-left h3 a:hover {
            text-decoration: underline;
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
            background-color: #f8f9fa;
            border-left: 4px solid #218838;
            padding: 15px;
            margin-bottom: 20px;
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
            <div class="step-wrapper-green">
                <div class="step active">5</div>
            </div>
        </div>
        
        <svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 24 24" fill="none" stroke="#218838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2L3 6v6c0 5 4 9 9 9s9-4 9-9V6l-9-4z" fill="white"></path>
            <path d="M9 12l2 2l4-4" fill="white"></path>
        </svg>

        <div class="message-success">
            <h3>Password Changed!</h3>
            <p>Your password has been successfully changed.</p>
        </div>

        <form action="index.php" method="get">
            <button type="submit" class="login-btn">Back to Log In</button>
        </form>

    </div>

</body>
</html>
