<?php
require_once './routes/session.php';
require_once './routes/qr_index_session.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIHS - Login</title>
    <link rel="stylesheet" href="./assets/css/index.css">
    <link rel="stylesheet" href="./assets/css/qr_code.css">
</head>
<body>
    <div class="login-form-container">
        <header class="title-container">
            <!-- left -->
            <div class="title-left">
                <h2>Login</h2>
                <h3>Upload your QR Code to log in:</h3>
            </div>
            <!-- right -->
            <div class="title-right">
                <img src="./img/DIHS_logo.png" alt="">
            </div>
        </header>
        <hr>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="qr_index.php" method="POST" enctype="multipart/form-data" id="login-form">
            <div class="input-group">
                <!-- <label for="qr_code" class="input-label">Upload QR Code</label> -->
                <input type="file" name="qr_code" id="qr_code" accept="image/*" required onchange="previewQRCode()">
                <span class="file-name" id="file-name"></span> 
            </div>


            <div class="qr-code-preview" id="qr-code-preview-container">
                <img id="qr-code-preview" src="#" alt="QR Code Preview"/>
                <div class="scan-line"></div>
            </div>

            <button type="submit" class="login-btn" id="login-btn" onclick="startScanEffect(event)">Login</button>
        </form>
        <footer class="footer-container">
            <!-- left -->
            <div class="footer-left">
                <h3><a href="forgot_password.php">Forgot Password?</a></h3>
                <h3>for <a href="index.php">LRN login <span>click here</span> </a></h3>
            </div>
            <!-- right -->
            <div class="footer-right">
                <h3>
                    <a href="./components/faq.php">FAQs</a>&nbsp; • &nbsp;
                    <a href="./components/help.php">Help</a>&nbsp; • &nbsp;
                    <a href="./components/contact.php">Contact Us</a>
                </h3>
            </div>
        </footer>

    </div>

    <script>

        function previewQRCode() {
            const fileInput = document.getElementById('qr_code');
            const previewImage = document.getElementById('qr-code-preview');
            const previewContainer = document.getElementById('qr-code-preview-container');

            const file = fileInput.files[0];
            const reader = new FileReader();

            reader.onload = function(event) {
                previewImage.src = event.target.result;
                previewContainer.style.display = 'block'; 
            }

            if (file) {
                reader.readAsDataURL(file); 
            }
        }

        function startScanEffect(event) {
            event.preventDefault(); 

            document.getElementById('qr-code-preview-container').classList.add('start-scanning');
            
            setTimeout(function() {

                document.getElementById('login-form').submit();
            }, 8000); 
        }
    </script>
</body>
</html>