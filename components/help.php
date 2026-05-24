<?php $currentYear = date("Y"); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../components/css/normal_text.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="./help.php">Learnify</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="./faq.php">FAQs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="./help.php">Help</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="./contact.php">Contact Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../index.php">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    </li>
                    <!-- Add font size adjustment options -->
                    <!-- <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Font Size
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#">Normal</a></li>
                            <li><a class="dropdown-item" href="#">Large</a></li>
                            <li><a class="dropdown-item" href="#">Extra Large</a></li>
                        </ul>
                    </li> -->
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="text-left mb-4">Help Center</h1>
        <hr>
        <div class="mt-5">
            <h2><mark>Ⅰ. ACCOUNTS</mark></h2>
            <div class="p-3">
                <h4>Level of Users</h4>
            </div>
        </div>

        <div class="accordion" id="helpCenterAccordion">
            <!-- Students Section -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="studentsHeading">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#studentsSection" aria-expanded="true" aria-controls="studentsSection" data-bs-parent="#helpCenterAccordion">
                        A. Students
                    </button>
                </h2>
                <div id="studentsSection" class="accordion-collapse collapse show" aria-labelledby="studentsHeading">
                    <div class="accordion-body">
                        <p>LMS accounts will be created or registered by the Admin Department.</p>
                        <ul>
                            <li><strong>LRN:</strong> Learner Reference Number (12-digit unique ID)</li>
                            <li><strong>Default Password:</strong> <code>DIHS_12345</code></li>
                        </ul>
                        <p><strong>Steps to Change Password:</strong></p>
                        <ol>
                            <li>Log in using the default password <code>DIHS_12345</code>.</li>
                            <li>Click your profile on the top right or navigate to <em>Edit Account</em> on the left sidebar.</li>
                            <li>Click the <em>Update Password</em> button on the right side.</li>
                            <li>Set a strong password with a mix of uppercase, lowercase, numbers, and special characters, and enter the confirmation password.</li>
                        </ol>
                        <p><em>Note: Ensure to update your password regularly to maintain account security.</em></p>
                        <p><strong>Students can view the enrolled subjects and can see the modules uploaded.</strong></p>
                    </div>
                </div>
            </div>

            <!-- Teachers Section -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="teachersHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#teachersSection" aria-expanded="false" aria-controls="teachersSection" data-bs-parent="#helpCenterAccordion">
                        B. Teachers / Professors
                    </button>
                </h2>
                <div id="teachersSection" class="accordion-collapse collapse" aria-labelledby="teachersHeading">
                    <div class="accordion-body">
                        <p>LMS accounts for professors will be provided by the Admin Department.</p>
                        <p><strong>Professors:</strong> Responsible for enrolling students in their modules and subjects. They are the ones who enroll students for their subjects and modules.</p>
                    </div>
                </div>
            </div>

            <!-- Admin Section -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="adminHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adminSection" aria-expanded="false" aria-controls="adminSection" data-bs-parent="#helpCenterAccordion">
                        C. Admins and Super Admins
                    </button>
                </h2>
                <div id="adminSection" class="accordion-collapse collapse" aria-labelledby="adminHeading">
                    <div class="accordion-body">
                        <p><strong>Admins:</strong> Handle student related process, including registration and account management..</p>
                        <p><strong>Super Admins:</strong> Handle system configurations, codebase, and technical operations.</p>
                        <p>Contact <code>itadmin@DIHS.edu</code> for assistance.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5">
            <h2><mark>Ⅱ. ACCOUNTS LOGIN</mark></h2>
            <div class="p-3">
                <h4>How to Login</h4>
                <ol>
                    <li>Launch the web browser and navigate to <a href="../index.php" target="_blank">DIHS Learnify</a></li>
                    <li>Input your lrn and password.</li>
                    <li>Click login button.</li>
                </ol>
            </div>
        </div>

        <div class="mt-5">
            <h2><mark>Ⅲ. RETRIEVE ACCOUNT</mark></h2>
            <div class="p-3">
                <h4>How to Reset your Password</h4>
                <ol>
                    <li>Launch the web browser and navigate to <a href="../forgot_password.php" target="_blank">Forgot Password</a>.</li>
                    <li>Enter your email, then click "Next".</li>
                    <li>Click the "Send OTP" and "Proceed" buttons.</li>
                    <p><em>Note: The OTP will be sent to your email for verification. Go to your <a href="https://www.gmail.com" target="_blank">Gmail</a> account to retrieve your OTP.</em></p>
                    <li>Once you receive it, enter your OTP and click "Verify OTP".</li>
                    <li>Enter your new password and confirm the password, then click "Reset Password".</li>
                    <li>After that, you will see a success message. Click the "Back to Login" button.</li>
                    <li>On the login page, use your new password to log in.</li>
                </ol>
            </div>
        </div>
    </div>

    <footer class="bg-success text-white text-center py-3 mt-5">
        <p>&copy; <?php echo $currentYear; ?> Learnify - Dasmariñas Integrated High School. All Rights Reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
