<?php $currentYear = date("Y"); ?> 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Learnify</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../components/css/normal_text.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="./faq.php">Learnify</a>

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
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="text-left mb-4">Frequently Asked Questions (FAQ)</h1>
        <hr>
        <div class="mt-5">
            <h2><mark>Ⅰ. General Questions</mark></h2>
            <div class="p-3">
                <h4>1. What is Learnify?</h4>
                <p>Learnify is a learning management system (LMS) designed for senior high school students, teachers, and administrators. It helps manage student records, course materials, and communication between teachers and students.</p>
            </div>
            <div class="p-3">
                <h4>2. What are the modules in Learnify?</h4>
                <p>The modules in Learnify include Dark Mode, Search Engine, Notifications, Sidebar, and Modal, each serving a unique function to enhance user experience and system navigation.</p>
            </div>
            <div class="p-3">
                <h4>3. What benefit could I get from Learnify?</h4>
                <p>Learnify is easy to use, helps you quickly find course materials, and makes it simple for students and teachers to communicate with each other.</p>
            </div>
            <div class="p-3">
                <h4>4. Can I access Learnify offline?</h4>
                <p>Currently, Learnify requires an internet connection to access its full features.</p>
            </div>
        </div>

        <div class="mt-5">
            <h2><mark>Ⅱ. Account Related Questions</mark></h2>
            <div class="p-3">
                <h4>5. How do I create an account?</h4>
                <p>Accounts are created by the Admin Department. Once your account is registered, you will receive your login credentials via email.</p>
            </div>
            <div class="p-3">
                <h4>6. How do I change my password?</h4>
                <p>To change your password, log in to your account, navigate to your profile settings, and click the "Update Password" button. Follow the on-screen instructions to set a new password.</p>
            </div>
            <div class="p-3">
                <h4>7. What should I do if I forget my password?</h4>
                <p>If you forget your password, go to the "Forgot Password" page, enter your email, and follow the steps to reset it.</p>
            </div>
        </div>

        <div class="mt-5">
            <h2><mark>Ⅲ. System Functionality</mark></h2>
            <div class="p-3">
                <h4>8. How do I access course materials?</h4>
                <p>Once logged in, navigate to the "Subjects" section where you will see all your enrolled subjects. On the right side of the professor's details, click on the three dots and select the "View Module" option. Once you're in the module, you'll be able to see all the course materials uploaded for that subject.</p>
            </div>
        </div>

        <div class="mt-5">
            <h2><mark>Ⅳ. Technical Support</mark></h2>
            <div class="p-3">
                <h4>9. How do I report technical issues?</h4>
                <p>If you encounter any issues with the system, please contact the IT department at <a href="mailto:itadmin@DIHS.edu">itadmin@DIHS.edu</a>.</p>
            </div>
            <div class="p-3">
                <h4>10. What should I do if the system is not working on my device?</h4>
                <p>Ensure that you are using a modern browser and an updated operating system. If the issue persists, please contact the IT department for further assistance.</p>
            </div>
        </div>

        <div class="mt-5">
            <h2><mark>Ⅴ. User Guidelines</mark></h2>
            <div class="p-3">
                <h4>11. Can I share my Learnify account?</h4>
                <p>No, sharing accounts is prohibited as it violates the terms of use. Each user must have their own account.</p>
            </div>
            <div class="p-3">
                <h4>12. How do I update my profile information?</h4>
                <p>Log in to your account, navigate to "Edit Profile" on the sidebar, or click your profile icon on the top-right corner of the navbar. Clicking either option will take you directly to the Edit Profile page to update your information.</p>
            </div>
        </div>

        <div class="mt-5">
            <h2><mark>Ⅵ. Feedback and Suggestions</mark></h2>
            <div class="p-3">
            <h4>13. How can I provide feedback about Learnify?</h4>
                <p>You can provide feedback through the "Contact Us" section or by filling out our Feedback and Suggestions Form</a>. We appreciate your input to improve Learnify!</p>
            </div>
            <div class="p-3">
                <h4>14. Will my suggestions be considered for future updates?</h4>
                <p>Yes, we value user feedback and strive to incorporate useful suggestions in future updates.</p>
            </div>
        </div>

        <div class="mt-5">
            <h2><mark>Ⅶ. Miscellaneous</mark></h2>
            <div class="p-3">
                <h4>15. Are there any mobile apps for Learnify?</h4>
                <p>Currently, Learnify is only available as a web application.</p>
            </div>
        </div>
    </div>

    <footer class="bg-success text-white text-center py-3 mt-5">
        <p>&copy; <?php echo $currentYear; ?> Learnify - Dasmariñas Integrated High School. All Rights Reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
