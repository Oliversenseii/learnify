<?php
require_once './config/db_connection.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'errors' => []];

    if (isset($_POST['form_type']) && $_POST['form_type'] === 'contact_modal') {
        // Contact Modal Form Handling
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name)) {
            $response['errors']['name'] = 'This field is required.';
        }
        if (!empty($email) && !preg_match('/@(?:gmail\.com|dihs\.edu\.ph)$/', $email)) {
            $response['errors']['email'] = 'Email must end with @gmail.com or @dihs.edu.ph.';
        }
        if (empty($message)) {
            $response['errors']['message'] = 'This field is required.';
        }

        if (empty($response['errors'])) {
            try {
                $stmt = $dbConnection->prepare("INSERT INTO contact_feedback (name, email, message, created_at) VALUES (:name, :email, :message, NOW())");
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email ?: null,
                    ':message' => $message
                ]);
                $response['success'] = true;
            } catch (PDOException $e) {
                $response['errors']['general'] = 'Failed to submit contact message. Please try again later.';
                error_log('Contact form submission error: ' . $e->getMessage());
            }
        }
    } else {
        // Feedback Form Handling
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

        if (empty($name)) {
            $response['errors']['name'] = 'This field is required.';
        }
        if (empty($email)) {
            $response['errors']['email'] = 'This field is required.';
        } elseif (!preg_match('/@(?:gmail\.com|dihs\.edu\.ph)$/', $email)) {
            $response['errors']['email'] = 'Email must end with @gmail.com or @dihs.edu.ph.';
        }
        if (empty($message)) {
            $response['errors']['message'] = 'This field is required.';
        }
        if ($rating < 1 || $rating > 5) {
            $response['errors']['rating'] = 'Please select a rating between 1 and 5 stars.';
        }

        if (empty($response['errors'])) {
            try {
                $stmt = $dbConnection->prepare("INSERT INTO feedback_message (name, email, message, rating, created_at) VALUES (:name, :email, :message, :rating, NOW())");
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':message' => $message,
                    ':rating' => $rating
                ]);
                $response['success'] = true;
            } catch (PDOException $e) {
                $response['errors']['general'] = 'Failed to submit feedback. Please try again later.';
                error_log('Feedback form submission error: ' . $e->getMessage());
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'feedback') {
    try {
        $stmt = $dbConnection->query("SELECT name, message, rating, created_at FROM feedback_message WHERE archived = 0 ORDER BY created_at DESC");
        $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'feedback' => $feedback]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to fetch feedback.']);
    }
    exit;
}

$subjectsStmt = $dbConnection->query("SELECT COUNT(*) as total FROM subjects WHERE archived = 0");
$subjectsCount = $subjectsStmt->fetch(PDO::FETCH_ASSOC);
$subjectsCount = $subjectsCount ? $subjectsCount['total'] : 0;

$tracksStmt = $dbConnection->query("SELECT COUNT(*) as total FROM track_strands WHERE archived = 0");
$tracksCount = $tracksStmt->fetch(PDO::FETCH_ASSOC);
$tracksCount = $tracksCount ? $tracksCount['total'] : 0;

$studentsStmt = $dbConnection->query("SELECT COUNT(*) as total FROM users WHERE userType = 'Student' AND archived = 0");
$studentsCountResult = $studentsStmt->fetch(PDO::FETCH_ASSOC);
$studentsCount = $studentsCountResult ? $studentsCountResult['total'] : 0;

$tracks = [
    "Academic" => [
        ["strandCode" => "STEM", "strandName" => "Science, Technology, Engineering and Mathematics", "subjectsFile" => "docs/STEM.docx", "image" => "img/STEM.jpg"],
        ["strandCode" => "ABM", "strandName" => "Accountancy, Business, and Management", "subjectsFile" => "docs/ABM.docx", "image" => "img/ABM.jpg"],
        ["strandCode" => "HUMSS", "strandName" => "Humanities and Social Sciences", "subjectsFile" => "docs/HUMSS.docx", "image" => "img/HUMSS.png"],
    ],
    "TVL" => [
        ["strandCode" => "FLTH", "strandName" => "Front Office, Tourism Promotion, Local Tour Guiding, Housekeeping", "subjectsFile" => "docs/FLTH.docx", "image" => "img/FLTH.jpg"],
        ["strandCode" => "CBF", "strandName" => "Cookery, Bread and Pastry Production, Food and Beverage Services", "subjectsFile" => "docs/CBF.docx", "image" => "img/CBF.png"],
        ["strandCode" => "AS", "strandName" => "Automotive Servicing", "subjectsFile" => "docs/AS.docx", "image" => "img/AS.jpg"],
        ["strandCode" => "EIM", "strandName" => "Electrical Installation and Maintenance", "subjectsFile" => "docs/EIM.docx", "image" => "img/EIM.jpg"],
    ],
];

$guides = [
    [
        "title" => "Forgot Password",
        "description" => "Learn how to reset your password if you’ve forgotten it",
        "category" => "Password",
        "steps" => [
            ["stepTitle" => "Step 1: Email Address", "image" => "img/guides/step-1.png", "description" => "Please enter your email to search for your account."],
            ["stepTitle" => "Step 2: Email Authentication", "image" => "img/guides/step-2.png", "description" => "Click the Continue button and check your email for the OTP."],
            ["stepTitle" => "Step 3: OTP Verification", "image" => "img/guides/step-3.png", "description" => "Once you receive it, enter your OTP and click Verify button."],
            ["stepTitle" => "Step 4: Reset Password", "image" => "img/guides/step-4.png", "description" => "Enter your new password and confirm it. Make sure you meet the password requirements."],
            ["stepTitle" => "Step 5: Successfully Changed", "image" => "img/guides/step-5.png", "description" => "After that, you will see a success message. Click the 'Back to Log In' button. On the login page, use your new password to log in."]
        ]
    ],
    [
        "title" => "QR Code Authentication",
        "description" => "Understand how to use QR codes for quick login and downloading module files.",
        "category" => "QR Code",
        "steps" => [
            ["stepTitle" => "Step 1: QR Code Detector", "image" => "img/guides/step1-qr.png", "description" => "Open your QR code and face it towards the scanner to log in. Make sure the QR code is clearly visible."],
            ["stepTitle" => "Step 2: Login", "image" => "img/guides/step2-qr.png", "description" => "Log in with your password. We've added a password for additional security."]
        ]
    ],
];

$faqs = [
    ["question" => "What is the QR Code feature used for?", "answer" => "The QR Code feature provides an alternative way to log in to Learnify, in addition to manual login with a username and password."],
    ["question" => "Does Learnify work offline?", "answer" => "No, Learnify requires a stable and fast internet connection to function, as it does not support offline access."],
    ["question" => "What is gamification in Learnify?", "answer" => "Gamification in Learnify includes various interactive games for students. These games engage students and stimulate their thinking, making learning more fun."],
    ["question" => "How can I access Learnify modules?", "answer" => "Learnify modules are accessible online only, with no offline feature. Users can view or download modules individually or download all files at once as a ZIP file."]
];

$help = [
    [
        "title" => "Technical Support",
        "description" => "For issues with login, QR code scanning, or system errors, please contact our support team via the Contact Us form or email us at dasmarinas.ihs@depeddasma.edu.ph.",
        "icon" => "fas fa-headset"
    ],
    [
        "title" => "User Guides",
        "description" => "Explore our detailed guides for step-by-step instructions on using Learnify, including password resets, QR code authentication, and accessing modules.",
        "icon" => "fas fa-book-open"
    ],
    [
        "title" => "Feedback",
        "description" => "We value your feedback! Use the Feedback & Suggestions form in the student dashboard or the Contact Us section to share your thoughts.",
        "icon" => "fas fa-comment-dots"
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnify</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="./img/learnify-logo.png" type="image/x-icon">
    <style>
        ::-webkit-scrollbar {
            width: 25px; 
            height: 25px;
        }

        ::-webkit-scrollbar-track {
            background: var(--grey); 
            border-radius: 10px; 
        }

        ::-webkit-scrollbar-thumb {
            background: var(--dark);
            border-radius: 10px; 
            border: 5px solid var(--grey); 
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555; 
        }

        * {
            scrollbar-width: thin; 
            scrollbar-color: var(--dark) var(--grey); 
        }
       
        :root {
            --primary-green: #155724;
            --secondary-green: #2ca02c;
            --whitish: #f9fafb;
            --light-grayish: #e5e5e5;
            --dark-green: #0d3c15;
            --accent-gold: #FFD700;
        }

        :root {
            --dark: #342E37;
            --grey: #eee;
            --light: #F9F9F9;
        }

        section {
            display: none;
            align-items: center;
            padding-top: 10rem;
            justify-content: center;
            color: var(--dark);
        }

        section.active {
            display: flex;
        }
        #home, #contact, #help {
            background-color: #eee;
            background-image: url("https://www.transparenttextures.com/patterns/white-wall-3.png");
        }

        #faqs, #guides {
            background: linear-gradient(135deg, #f9fafb, #e5e5e5);
            background-image: url("https://www.transparenttextures.com/patterns/white-wall-3.png");
        }
        nav {
            background-color: var(--light);
            padding: 1.5rem 2rem;
            position: fixed;
            width: 100%;
            border-bottom: 3px solid #003d80;
            z-index: 100000;
            top: 0;
        }
        .nav-link {
            position: relative;
            font-size: clamp(1rem, 3vw, 1.4rem);
            font-weight: 500;
            color: var(--dark);
        }
        .nav-link:hover {
            border-bottom: 3px solid #003d80;
        }
        .nav-link.active {
            color: #007bff;
            font-weight: bold;
        }
        .nav-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            padding: 12px;
            color: white;
            border-radius: 5px;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .nav-button i {
            margin-right: 4px;
        }

        .nav-button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px) !important;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 100000000000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: var(--light);
            padding: 2rem;
            border-radius: 1rem;
            max-width: 800px;
            max-height: 80vh;
            width: 70%;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        .modal-close {
            position: absolute;
            top: 10px;
            right: 20px;
            cursor: pointer;
            font-size: 2rem;
            color: var(--dark);
            transition: color 0.3s ease;
        }
        .modal-close:hover {
           color: red;
        }

        .modal-content input,.modal-content textarea {
            border: 1px solid #ccc; 
            border-radius: 6px;
            font-size: clamp(1rem, 3vw, 1.2rem) !important;
            padding: 0.75rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .modal-content input:focus,.modal-content textarea:focus {
        border-color: #3b82f6; 
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); 
        }

        .guide-modal .modal-content {
            max-width: 1000px;
            width: 70%;
            padding: 0;
            overflow: hidden;
        }

        .guide-modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1.5rem;
            text-align: center;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .guide-modal-header h2 {
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2rem);
            font-weight: 600;
        }
        .guide-modal-body {
            display: flex;
            flex-direction: row;
            gap: 2rem;
            padding: 2rem;
            overflow-y: auto;
        }
        .guide-modal-image-container {
            flex: 0 0 40%;
            max-width: 40%;
        }
        .guide-modal-image {
            width: 100%;
            object-fit: cover;
            border-radius: 0.5rem;
            display: none;
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .guide-modal-image.active {
            display: block;
            transform: scale(1.02);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .guide-modal-steps {
            flex: 1;
            padding: 1rem;
        }
        .guide-modal-steps details {
            margin-bottom: 1.5rem;
            border: 1px solid #0056b3;
            border-radius: 0.5rem;
            transition: border-color 0.3s ease;
        }
        .guide-modal-steps details.active {
            border-color: #0056b3;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .guide-modal-steps summary {
            cursor: pointer;
            padding: 1rem;
            font-weight: 700;
            color: var(--dark);
            background: var(--light);
            border-radius: 0.5rem;
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            transition: background 0.3s ease, color 0.3s ease;
        }
        .guide-modal-steps summary:hover {
            background: var(--grey);
        }
        .guide-modal-steps details.active summary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        .guide-modal-steps details p {
            padding: 1rem;
            color: var(--dark);
            line-height: 1.6;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }
        .guide-modal.fullscreen {
            background: #000;
            padding: 0;
            transition: all 0.3s ease-in-out;
        }
        .guide-modal {
            transition: all 0.3s ease-in-out; 
        }

        .guide-modal .modal-content {
            transition: all 0.3s ease-in-out; 
        }

        .guide-modal .guide-modal-body {
            transition: all 0.3s ease-in-out; 
        }

        .guide-modal .guide-modal-image-container {
            transition: all 0.3s ease-in-out;
        }

        .guide-modal .guide-modal-steps {
            transition: all 0.3s ease-in-out; 
        }
        .guide-modal.fullscreen .modal-content {
            width: 100vw;
            height: 100vh;
            max-width: none;
            max-height: none;
            border-radius: 0;
            border: none;
            margin: 0;
            display: flex;
            transition: all 0.3s ease-in-out;
            flex-direction: column;
        }
        .guide-modal.fullscreen .guide-modal-header {
            position: relative;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .guide-modal.fullscreen .guide-modal-header h2 {
            font-size: 2.5rem;
        }
        .guide-modal.fullscreen .guide-modal-body {
            flex: 1;
            max-height: none;
            padding: 1rem;
            display: flex;
            flex-direction: row;
            gap: 1rem;
            overflow-y: auto;
        }
        .guide-modal.fullscreen .guide-modal-image-container {
            flex: 0 0 50%;
            max-width: 100%;
        }
        .guide-modal.fullscreen .guide-modal-image {
            max-height: 80vh;
            object-fit: contain;
        }
        .guide-modal.fullscreen .guide-modal-steps {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .fullscreen-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            transition: color 0.3s ease;
            margin-left: 1rem;
        }
        .fullscreen-toggle:hover {
            color: var(--accent-gold);
        }
        .fullscreen-toggle .fa-compress {
            display: none;
        }
        .guide-modal.fullscreen .fullscreen-toggle .fa-expand {
            display: none;
        }
        .guide-modal.fullscreen .fullscreen-toggle .fa-compress {
            display: inline;
        }
        body.modal-fullscreen {
            overflow: hidden;
        }
        body.modal-fullscreen > *:not(.modal.fullscreen) {
            display: none;
        }

        #cookies-modal .modal-content {
            background: var(--light);
            max-width: 700px;
            border: none;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        #cookies-modal h2 {
            color: var(--dark);
            font-size: 2.2rem;
            margin-bottom: 1rem;
            font-weight: 600;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        #cookies-modal p {
            color: var(--dark);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        #cookies-modal .buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        #cookies-modal button {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            width: 50%;
            font-weight: 500;
            font-size: 1.2rem;
            transition: background 0.3s ease;
        }
        #cookies-modal .accept-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }
        #cookies-modal .accept-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }
        #cookies-modal .reject-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        #cookies-modal .reject-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }
        #cookies-modal details {
            margin-bottom: 1rem;
            text-align: left;
        }
        #cookies-modal summary {
            cursor: pointer;
            font-weight: 600;
            font-size: 1.5rem;
            color: var(--dark);
            padding: 0.5rem;
            border-bottom: 1px solid #ccc;
            border-top: 1px solid #ccc;
        }
        #cookies-modal details p, #cookies-modal details ul {
            margin-top: 0.5rem;
            padding-left: 1rem;
            font-size: 1.5rem;
            color: var(--dark);
        }
        #cookies-modal details ul {
            list-style-type: disc;
            padding-left: 2rem;
        }
        .cookies-accept-icon {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .cookies-accept-icon:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: scale(1.05);
        }
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 15px 20px;
            font-size: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            display: none;
            z-index: 100000000000000000000000000000002;
            min-width: 200px;
        }
        .toast-container.show {
            display: block;
        }
        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background: linear-gradient(135deg, #ffc107, #e0a800);
            animation: progress 5s linear forwards;
        }
        @keyframes progress {
            from { width: 100%; }
            to { width: 0; }
        }
        .card {
            background: var(--light);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            color: var(--dark);
            text-align: center;
        }
        /* ===== Guides ===== */
        #guides .grid-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        #guides img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            border: 2px solid #ccc;
            transition: transform 0.3s ease;
        }
        #guides .upcoming-card:hover img {
            transform: scale(1.05);
        }


        /* ===== FAQs ===== */
        .faq-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        .faq-item {
            border: 1px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
            background-color: var(--light);
            transition: all 0.3s ease;
        }

        .faq-item summary {
            padding: 1rem;
            font-size: 1.5rem;
            background-color: var(--light);
            color: var(--dark);
            cursor: pointer;
            list-style: none;
        }

    
        .faq-item[open] > summary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            font-weight: 600;
            color: white;
        }
        
        .faq-item:not([open]) > summary:hover {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .faq-item[open] > summary:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .faq-item summary::marker {
            display: none;
        }

        .faq-item p {
            padding: 1rem;
            font-size: 1.5rem;
            color: var(--dark);
            border-radius: 10px;
            background-color: var(--light);
            box-shadow: rgba(0, 0, 0, 0.15);
            line-height: 1.6;
        }

        /* ===== FAQS =====  */

        /* ===== HELP ===== */
        #help .grid-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        #help .upcoming-card {
            background: var(--light);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        #help .upcoming-card .card-icon {
            background-color: var(--grey);
            border-radius: 50%;
            width: 170px;
            height: 170px;
            font-size: 7rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        #help .upcoming-card .card-title {
            font-size: 2rem;
            color: var(--dark);
        }
        #help .upcoming-card p {
            font-size: 1.5rem;
            text-align: center;
            margin-top: 20px;
        }
        .guide-description {
            font-size: 1.5rem;
            text-align: center;
        }
        #help .upcoming-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        /* ===== HELP ===== */

        /* ===== FOOTER ===== */
        .footer {
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            flex-direction: column;
            border-top: 3px solid #0056b3;
            align-items: center;
            gap: 20px;
            padding: 40px 8vw;
        }
        .footer-content {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 30px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 20px;
        }
        .footer-content-left, .footer-content-center, .footer-content-right {
            display: flex;
            flex-direction: column;
            align-items: start;
            gap: 10px;
            font-size: 1.5rem;
        }
        .footer-content-left li, .footer-content-center li {
            list-style: none;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .footer-content-center li:hover {
            color: #007bff;
        }
        .footer-content-right h2, .footer-content-center h2 {
            color: var(--dark);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .footer-logo {
            display: none;
        }
        .footer-logo:hover {
            transform: scale(1.05);
        }
        .footer-content-left p {
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .footer-social-icons {
            display: flex;
            gap: 15px;
        }
        .social-icon {
            color: white;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 50%;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            gap: 8px;
        }

        .social-icon i {
            font-size: 1.5rem;
        }
        .social-icon:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: scale(1.05);
        }
        
        .footer-copyright {
            font-size: 1.5rem;
        }
        /* ===== Footer ===== */

        /* ===== Scroll to Top ===== */
        .scroll-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            color: white;
            background: linear-gradient(135deg, #007bff, #0056b3);
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease, background 0.3s ease;
        }
        .scroll-to-top:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: scale(1.05);
        }
        .scroll-to-top.visible {
            opacity: 1;
        }
         /* ===== Scroll to Top ===== */


        /* ===== Stats ===== */
        .upcoming-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
            border-radius: 10px;
        }
        .upcoming-header {
            text-align: center;
            margin-bottom: 50px;
        }
        .upcoming-title {
            font-size: 4rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 16px;
            letter-spacing: -0.025em;
        }
        .upcoming-subtitle {
            font-size: 1.8rem;
            color: var(--dark);
            max-width: 900px;
            margin: 0 auto;
            line-height: 1.2;
            font-style: italic;
        }
        .upcoming-content, .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        .upcoming-card, .custom-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
            border: 2px solid #e2e8f0;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .upcoming-card:hover, .custom-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border-color: #007bff;
        }
        .upcoming-card::before, .custom-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            transition: height 0.3s ease;
        }
        .upcoming-card:hover::before, .custom-card:hover::before {
            height: 10px;
        }
        .card-icon {
            font-size: 6rem;
            color: #007bff;
            background-color: var(--grey);
            border-radius: 50%;
            width: 160px;
            height: 160px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: transform 0.3s ease;
        }
        .upcoming-card:hover .card-icon, .custom-card:hover .card-icon {
            transform: scale(1.2);
        }
        .upcoming-card .font-bold {
            color: #003d80;
            font-size: 4rem;
        }
        .card-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            text-align: center;
        }
        /* ===== Stats ===== */

        /* ===== marquee ===== */
        .banner {
            width: 100%;
            height: 450px;
            object-fit: cover;
        }
        .marquee {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 10px 0;
            font-size: 1.5rem;
            text-align: center;
        }
        #contact .custom-card h3, #contact .custom-card label, #contact .custom-card input, #contact .custom-card textarea {
            text-align: left;
        }
        
        .error-text {
            color: #e53e3e;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }

        /* ===== Contact Modal ===== */
        #contact-modal .modal-content {
            height: auto;
            max-width: 500px;
            width: 80%;
        }

        .modal-close:hover {
            transform: scale(1.5);
        }
        
        #contact-modal .modal-content h2 {
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            text-align: left;
        }

        /* ===== Contact Modal ===== */

        /* ===== tracks modal ===== */
        #tracks-modal .modal-content {
            height: 100vh;
            max-width: 1200px;
            width: 80%;
        }

        .track-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
            margin-top: 2rem;
        }
        .card .strand-name {
            color: var(--dark);
            font-size: 1.5rem;
        }
        .track-btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            font-weight: 500;
            transition: background 0.3s ease;
            font-size: 1.2rem;
            cursor: pointer;
        }
        .track-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            color: white;
        }
        .track-btn.active, .btn-tracks {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        .track-btn.inactive {
            background-color: var(--grey);
        }
        .track-btn.inactive:hover, .btn-tracks:hover {
             background: linear-gradient(135deg, #007bff, #0056b3);
             transform: scale(1.1);
        }
        .track-btn:hover:not(.active) {
            background-color: var(--grey);
        }
        .track-section {
            display: none;
        }
        .track-section.active {
            display: block;
        }
        .track-section .card img {
            width: 100%;
            height: 30vh;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .card {
            background: linear-gradient(135deg, #f9fafb, #e5e5e5);
            border-top: 4px solid #007bff !important;
        }
        .card:hover {
            border-top: 10px solid #007bff;
            border-left: 2px solid #007bff;
            border-bottom: 2px solid #007bff;
            border-right: 2px solid #007bff;
            transform: translateY(-5px);
        }
        .card p {
            padding-bottom: 10px;
            margin: 0 auto;
        }
        .__track__ {
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            margin-bottom: 10px;
            font-size: 2rem;
            margin: 0 auto;
            text-align: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .logo-container img {
            height: 3.5rem;
            margin-right: 0.5rem;
            border-radius: 5px;
            padding: 3px;
            background: white;
        }
        .logo-container span {
            font-family: 'Caveat', cursive;
            font-size: clamp(1.6rem, 3vw, 2.4rem);
            font-weight: 700;
            background: linear-gradient(135deg, #007bff, #0056b3);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1;
        }
        .logo-container span:hover {
            color: var(--accent-gold);
        }

        .top-contact-info {
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .top-contact-info p {
            display: flex;
            align-items: center;
            color: var(--dark);
            margin-top: -15px;
            font-size: 1.2rem;
            white-space: nowrap;
        }
        .top-contact-info i {
            color: #007bff;
            margin-right: 0.5rem;
        }
        #banner-marquee {
            display: block;
        }

        .send-message {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        .send-message:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: scale(1.1);
        }
        .custom-card h3 {
            text-align: center;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        .mb-4 label {
            color: var(--dark);
            font-size: 1.2rem;
            font-weight: 600;
        }
        .mb-4 textarea {
            border: 1px solid #ccc;
        }
        .mb-4 textarea:focus {
            border: 1px solid #007bff;
        }

        input:focus, textarea:focus {
            border-color: #0056b3 !important;
            outline: none;
        }

        #mobile-menu {
            border-top: 1px solid #ccc;
            font-size: 1.5rem !important;
        }
        .top-contact-info p {
            font-size: 1.5rem;
        }

        /* ===== feedback ===== */
        #feedback-container-bg {
            position: relative;
            overflow: hidden;
            max-width: 1250px;
            height: auto;
        }

        .feedback-slider {
            display: flex;
            transition: transform 0.5s ease-in-out;
            gap: 1.5rem;
        }

        .feedback-card {
            flex: 0 0 calc(33.33% - 1.3rem);
            border-radius: 1.2rem;
            padding: 1.5rem;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
            border: none;
            background-color: var(--light);
            width: fit-content;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeIn 0.5s ease-out;
            border-top: 4px solid #007bff;
        }

        .feedback-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
            border-top: 10px solid #007bff;
            border-right: 2px solid #007bff;
            border-left: 2px solid #007bff;
            border-bottom: 2px solid #007bff;
        }

        .feedback-card .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #4b5e6f !important;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
            border: 2px solid #e5e7eb;
        }

        .feedback-card .stars {
            color: #facc15;
            font-size: 2.4rem;
            margin-bottom: 0.75rem;
            letter-spacing: 2px;
        }

        .feedback-card h4 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .feedback-card p {
            font-size: 1.1rem;
            color: var(--dark);
            line-height: 1.6;
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .feedback-card .feedback-date {
            font-size: 1rem;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            color: #6b7280;
            margin-top: 0.75rem;
            text-align: left;
            font-style: italic;
        }

        .slider-controls {
            position: absolute;
            top: 97%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-70%);
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .feedback-container:hover .slider-controls {
            opacity: 1;
        }

        .slider-controls button {
            background: #1f2937;
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            cursor: pointer;
            border-radius: 50%;
            font-size: 1.2rem;
            transition: background 0.3s ease;
        }

        .slider-controls button:hover {
            background: #374151;
        }

        .slider-controls button:focus {
            outline: 2px solid #facc15;
            outline-offset: 2px;
        }

        .error-text {
            color: #ef4444;
            font-size: 0.8rem;
            display: none;
            margin-top: 0.25rem;
        }

        .stars {
            display: flex;
            flex-direction: row-reverse;
            gap: 0.5rem;
        }

        .stars input {
            display: none;
        }

        .stars label {
            font-size: 4rem;
            color: #d1d5db;
            cursor: pointer;
            transition: color 0.2s ease;
            margin: 0 auto;
        }

        .stars input:checked ~ label,
        .stars label:hover,
        .stars label:hover ~ label {
            color: #facc15;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== feedback end ===== */

        /* ===== banner start ===== */
        #banner-marquee {
            background: linear-gradient(135deg, #f9fafb, #e5e5e5);
            background-image: url("https://www.transparenttextures.com/patterns/white-wall-3.png");
            position: relative;
            overflow: hidden;
            min-height: 90vh;
            padding-top: 10rem;
            height: auto;
        }

        #banner-marquee .container {
            max-width: 1400px; 
            padding-left: 2rem;
            padding-right: 2rem;
        }

        #banner-marquee h1 {
            font-size: 5rem !important; 
            line-height: 1.2;
        }

        #banner-marquee p {
            font-size: 1.75rem !important; 
            line-height: 1.6;
            max-width: 900px; 
        }

        #banner-marquee .flex button, #banner-marquee .flex a {
            padding: clamp(0.75rem, 1.25rem, 1.5rem) clamp(1.5rem, 3vw + 1rem, 3.5rem) !important;
            font-size: clamp(1.2rem, 3vw, 1.5rem) !important; 
        }

        /* ===== banner end ===== */

        /* ===== user start ===== */
        #users {
            background-color: #eee;
            background-image: url("https://www.transparenttextures.com/patterns/white-wall-3.png");
        }

        #users .container {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
            border: 2px solid #e2e8f0;
        }

        .tab-button {
            border-bottom: 4px solid transparent;
            transition: all 0.3s ease;
            font-size: 1.5rem !important;
            width: 90%;
            max-width: 250px;
        }

        .tab-button:hover {
            transform: translateY(-5px);
            border-bottom-color: #3b82f6;
        }

        .tab-button.active {
            border-bottom-color: #3b82f6;
            background: #dbeafe; 
            font-weight: bold;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .tab-pane-content {
            display: none;
        }

        .tab-pane-content.active {
            display: block;
        }

        #users h2 {
            font-size: 4rem !important; 
            line-height: 1.2;
        }

        #users img {
            width: 100%;
            max-width: 320px;
            height: auto;
            max-height: 320px; 
            object-fit: contain; 
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1); 
            border-radius: 50%;
        }

        #it-admin-content h3, #admin-content h3,
        #teacher-content h3, #student-content h3 {
            font-size: 3rem !important;
            font-weight: 600 !important;
        }

         #it-admin-content p, #admin-content p,
         #teacher-content p, #student-content p {
            font-size: 1.7rem !important;
         }
       
        /* ===== user end ===== */

        #games {
            background: linear-gradient(135deg, #f9fafb, #e5e5e5);
            background-image: url("https://www.transparenttextures.com/patterns/white-wall-3.png");
        }

        #games .container {
            max-width: 1000px;
            width: 100%;
        }

        #games .container h2 {
            font-size: 4rem !important;
        }

        #games .container p {
            font-size: 1.8rem;
        }

        #game-img img{
            max-width: 500px;
            width: 100%;
            height: auto;
            object-fit: contain; 
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1); 
            border-radius: 10px;
        }

        .game-title h3 {
            font-size: 3rem !important;
        }

        .game-title p{
            font-size: 1.5rem !important;
        }

        .game-title ul li  {
            font-size: 1.5rem !important;
        }

        .category .inline-block  {
            font-size: 1.5rem !important;
            padding: 15px;
        }

        .slide-in {
            opacity: 0;
            transform: translateX(-100px); 
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .slide-in:nth-child(even) {
            transform: translateX(100px); 
        }

        .slide-in.visible {
            opacity: 1;
            transform: translateX(0); 
        }

        .fade-scale {
            opacity: 0;
            transform: scale(0.8); 
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .fade-scale.visible {
            opacity: 1;
            transform: scale(1); 
        }

        .slide-up-fade {
            opacity: 0;
            transform: translateY(50px); 
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .slide-up-fade.visible {
            opacity: 1;
            transform: translateY(0); 
        }

        .fade-slide-left {
            opacity: 0;
            transform: translateX(-50px); 
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .fade-slide-left.visible {
            opacity: 1;
            transform: translateX(0); 
        }

        /* Media Queries */
        @media screen and (max-width: 1024px) {
            #banner-marquee {
                min-height: 900px;
                padding-top: 10rem;
                padding-bottom: 10rem;
            }

            #banner-marquee h1 {
                font-size: 6.5rem;
            }

            #banner-marquee p {
                font-size: 2.25rem;
            }

            #banner-marquee .flex button,
            #banner-marquee .flex a {
                padding: 1.5rem 3.5rem;
                font-size: 1.75rem;
            }

            .upcoming-card,
            .custom-card {
                padding: 30px;
            }

            .banner {
                height: 300px;
            }

            #guides .grid-container,
            #help .grid-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .guide-modal-body {
                flex-direction: column;
            }

            .guide-modal-image-container {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .guide-modal-steps {
                padding-top: 0;
            }
        }

        @media screen and (max-width: 768px) {
            #banner-marquee {
                min-height: 800px;
                padding-top: 8rem;
                padding-bottom: 8rem;
            }

            #banner-marquee h1 {
                font-size: 4rem; 
            }

            #banner-marquee p {
                font-size: 2rem;
            }

            #help .container h2,
            #faqs .container h2,
            #guides .container h2,
            #contact .container h2,
            #games .container h2,
            #terms .container h2,
            #privacy .container h2 {
                margin-top: 100px;
                height: auto;
            }

            .guide-modal .modal-content {
                width: 95%;
                max-height: 100vh;
            }

            .fullscreen-toggle {
                display: none;
            }

            .upcoming-container {
                padding: 40px 16px;
            }

            .upcoming-card,
            .custom-card {
                padding: 20px;
            }

            .banner {
                height: 200px;
            }

            .marquee {
                font-size: 1rem;
            }

            #guides .grid-container,
            #help .grid-container {
                grid-template-columns: 1fr;
            }

            .footer-content {
                display: flex;
                flex-direction: column;
                gap: 35px;
            }

            .footer-copyright {
                text-align: center;
            }

            #mobile-menu {
                margin-top: 2rem;
            }

            section {
                padding: 2rem 1rem;
            }

            #guides img {
                height: 150px;
            }

            .guide-modal-header h2 {
                font-size: 1.5rem;
            }

            .guide-modal-body {
                padding: 1rem;
            }

            .top-contact-info {
                flex-direction: column;
                gap: 0.5rem;
            }

            .top-contact-info p {
                font-size: 1rem;
                justify-content: center;
            }

            .feedback-card {
                flex: 0 0 calc(50% - 0.75rem);
                min-height: 280px;
            }

            .feedback-slider {
                gap: 1rem;
            }
        }

        @media screen and (max-width: 640px) {
            #banner-marquee {
                min-height: 500px;
                padding-top: 5rem;
                padding-bottom: 5rem;
            }

            #banner-marquee h1 {
                font-size: 2.5rem;
            }

            #banner-marquee p {
                font-size: 1.25rem;
            }

            #banner-marquee .flex button,
            #banner-marquee .flex a {
                padding: 1rem 2rem;
                font-size: 1.25rem;
            }
        }

        @media screen and (max-width: 480px) {
            .feedback-card {
                flex: 0 0 calc(100% - 0.5rem);
                min-height: 260px;
            }

            .slider-controls button {
                padding: 0.5rem 1rem;
            }

            .nav-button {
                display: none;
            }
        }

    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <div class="container mx-auto flex justify-between items-center">
            <div class="logo-container" onclick="window.location.href='./index.php'">
                <img src="./img/learnify-logo.png" alt="Learnify Logo">
                <span>Learnify</span>
            </div>

            <div class="hidden md:flex space-x-8 items-center">
                <a href="./portal.php" class="nav-link active">Home</a>
                <a href="#help" class="nav-link" onclick="showSection('help')">Helpdesk</a>
                <a href="#contact" class="nav-link" onclick="showSection('contact')">Feedback</a>
                <a href="#games" class="nav-link" onclick="showSection('games')">Games</a>
                <a href="#users" class="nav-link" onclick="showSection('users')">Roles</a>

                <!-- 🧾 LEGAL DROPDOWN -->
                <div class="relative group">
                    <button class="nav-link flex items-center gap-1">
                        Legal <i class="fas fa-chevron-down text-sm mt-1"></i>
                    </button>
                    <div class="absolute hidden group-hover:block bg-white shadow-lg rounded-md mt-2 w-48 z-50 text-xl">
                        <a href="#privacy" onclick="showSection('privacy')" class="block px-7 py-4 text-gray-700 hover:bg-gray-100">Data Privacy</a>
                        <a href="#terms" onclick="showSection('terms')" class="block px-7 py-4 text-gray-700 hover:bg-gray-100">Terms & Conditions</a>
                    </div>
                </div>
            </div>

            <div>
                <a href="./index.php" class="nav-button"><i class="fas fa-sign-in-alt"></i> Log In</a>
            </div>

            <div class="md:hidden">
                <button id="mobile-menu-btn" class="focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden">
            <a href="./portal.php" class="block px-4 py-2 nav-link active">Home</a>
            <a href="#help" class="block px-4 py-2 nav-link" onclick="showSection('help')">Helpdesk</a>
            <a href="#contact" class="block px-4 py-2 nav-link" onclick="showSection('contact')">Feedback</a>
            <a href="#games" class="block px-4 py-2 nav-link" onclick="showSection('games')">Games</a>
            <a href="#users" class="block px-4 py-2 nav-link" onclick="showSection('users')">Roles</a>

            <!-- 🧾 LEGAL DROPDOWN FOR MOBILE -->
            <div class="border-t border-gray-200 mt-2">
                <span class="block px-4 py-2 font-semibold text-gray-700 text-xl">Legal</span>
                <a href="#privacy" onclick="showSection('privacy')" class="block px-6 py-2 text-gray-700 hover:bg-gray-100 text-lg">Data Privacy</a>
                <a href="#terms" onclick="showSection('terms')" class="block px-6 py-2 text-gray-700 hover:bg-gray-100 text-lg">Terms & Conditions</a>
            </div>

            <br>
            <a href="./index.php" class="block px-4 py-2 nav-link"><i class="fas fa-sign-in-alt"></i> Log In</a>
        </div>
    </nav>

    <!-- Banner Section -->
    <div id="banner-marquee" class="pt-20 bg-gray-100 relative overflow-hidden">
        <div class="container mx-auto px-4 flex flex-col md:flex-row items-center justify-between py-16 md:py-24 relative z-10">
            <!-- Left Side: Text Content -->
            <div class="md:w-1/2 text-center md:text-left animate-slide-in-left">
            <div class="bg-blue-200 text-blue-800 font-semibold text-4xl px-4 py-3 rounded mb-4 inline-block border-2">Empowering Education Every Day</div>
                <h1 class="font-bold text-gray-800 mb-6 drop-shadow-lg banner-title">Learn Together Grow Together</h1>
                <p class="text-gray-600 mb-8 leading-relaxed max-w-3xl mx-auto md:mx-0">Learnify makes teaching learning and staying organized easy for Dasmariñas Integrated High School.</p>
                <div class="flex justify-center md:justify-start space-x-4">
                    <button onclick="openModal('contact-modal')" class="inline-block bg-gradient-to-r from-blue-500 to-blue-700 text-white px-8 py-4 rounded-lg font-medium text-lg hover:from-blue-600 hover:to-blue-800 transition-transform transform hover:scale-105 shadow-lg hover:shadow-xl"><i class="fas fa-envelope mr-2"></i>Contact</button>
                    <a href="./index.php" class="inline-block bg-white text-blue-700 border-2 border-blue-700 px-8 py-4 rounded-lg font-medium text-lg hover:bg-blue-100 hover:text-blue-800 transition-transform transform hover:scale-105 shadow-lg hover:shadow-xl"><i class="fas fa-sign-in-alt mr-2"></i>Sign In</a>
                </div>
            </div>
            <!-- Right Side: Image -->
            <div class="md:w-1/2 mt-8 md:mt-0 flex items-center">
                <img src="./uploads/lms-banner-2.png" alt="Learnify Banner" class="w-auto h-[80vh] object-contain rounded-lg shadow-lg">
            </div>
        </div>
        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-blue-700/10 z-0 animate-pulse-slow"></div>
    </div>
    
    <!-- Contact Modal -->
    <div id="contact-modal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('contact-modal')">x</span>
            <h2 class="text-4xl font-bold text-center mb-6">Get in touch</h2>
            <div>
                <div class="mb-4">
                    <!-- <label class="block text-gray-700 font-medium mb-1">Name <span class="text-red-500">*</span></label> -->
                    <input type="text" id="contact-name" placeholder="Name" class="w-full p-3 border rounded-lg" required>
                    <p id="contact-name-error" class="error-text">This field is required.</p>
                </div>
                <div class="mb-4">
                    <!-- <label class="block text-gray-700 font-medium mb-1">Email (optional)</label> -->
                    <input type="email" id="contact-email" placeholder="Email (optional)" class="w-full p-3 border rounded-lg">
                    <p id="contact-email-error" class="error-text">Email must end with @gmail.com or @dihs.edu.ph.</p>
                </div>
                <div class="mb-4">
                    <!-- <label class="block text-gray-700 font-medium mb-1">Message <span class="text-red-500">*</span></label> -->
                    <textarea id="contact-message" placeholder="Message" class="w-full p-3 border rounded-lg" rows="5" required></textarea>
                    <p id="contact-message-error" class="error-text">This field is required.</p>
                </div>
                <button onclick="submitContactForm()" class="text-white px-5 py-3 rounded-lg send-message">Send Message</button>
            </div>
        </div>
    </div>

    <!-- Home Section -->
    <section id="home" class="active">
        <div class="container mx-auto px-4 text-center">
            <div class="upcoming-container">
                <div class="upcoming-header">
                    <h2 class="upcoming-title text-5xl md:text-6xl lg:text-7xl font-bold text-gray-800 text-center mb-16">Academic Overview</h2>
                    <p class="upcoming-subtitle font-semibold text-center mb-16 text-gray-600 italic max-w-4xl mx-auto">Explore the key statistics and resources of our learning management <br> system.</p>
                </div>
                <div class="upcoming-content">
                    <div class="upcoming-card slide-up-fade">
                        <div class="card-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3 class="card-title">Subjects</h3>
                        <p class="text-4xl font-bold"><?php echo $subjectsCount; ?></p>
                    </div>
                    <div class="upcoming-card slide-up-fade" onclick="openModal('tracks-modal')" style="cursor: pointer;">
                        <div class="card-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h3 class="card-title">Tracks & Strands</h3>
                        <p class="text-4xl font-bold"><?php echo $tracksCount; ?></p>
                    </div>
                    <div class="upcoming-card slide-up-fade">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="card-title">No. of Students</h3>
                        <p class="text-4xl font-bold"><?php echo $studentsCount; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Data Privacy Notice Section -->
    <section id="privacy">
    <div class="container mx-auto px-6 md:px-12 lg:px-20">
        <h2 class="text-5xl md:text-6xl font-bold text-gray-800 text-center mb-10">Data Privacy Notice</h2>

        <p class="text-3xl text-gray-700 leading-relaxed mb-8 text-center max-w-7xl mx-auto">
        <strong>Learnify Learning Management System</strong> is committed to protecting the privacy and security of your
        personal data in compliance with Republic Act No. 10173, the Data Privacy Act of 2012 of the Philippines.
        This notice details how we collect, use, store, and protect your personal information when you interact with our platform.
        </p>

        <div class="space-y-10">
        <!-- 1 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">1. Information We Collect</h3>
            <p class="text-gray-700 mb-3 text-2xl">We collect data to provide a personalized and efficient learning experience. The categories of data include:</p>
            <ul class="list-disc list-inside text-gray-700 space-y-2 text-2xl">
            <li><strong>Personal Identifiable Information (PII):</strong> Full name, LRN, email, contact number, date of birth, sex, address, nationality, profile image.</li>
            <li><strong>Account Information:</strong> User type, account status, hashed passwords, OTPs, authentication codes.</li>
            <li><strong>Usage Data:</strong> Quiz results, assignments, gamified activities, login history.</li>
            <li><strong>Educational Data:</strong> Enrollment details, academic progress, grades, and records.</li>
            </ul>
        </div>

        <!-- 2 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">2. Purpose of Data Collection</h3>
            <p class="text-gray-700 mb-3 text-2xl">We process personal data for the following purposes:</p>
            <ul class="list-disc list-inside text-gray-700 space-y-2 text-2xl">
            <li>User authentication and account management.</li>
            <li>Delivering educational content and tracking academic progress.</li>
            <li>Communicating updates, notifications, and support services.</li>
            <li>Enhancing performance, security, and user experience.</li>
            <li>Complying with legal obligations under the Data Privacy Act of 2012.</li>
            </ul>
        </div>

        <!-- 3 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">3. Data Storage and Security</h3>
            <p class="text-gray-700 mb-3 text-2xl">We implement robust security measures, including:</p>
            <ul class="list-disc list-inside text-gray-700 space-y-2 text-2xl">
            <li><strong>Encryption:</strong> Passwords hashed with bcrypt, data encrypted in transit and at rest.</li>
            <li><strong>Access Controls:</strong> Restricted to authorized personnel only.</li>
            <li><strong>Audits:</strong> Regular security assessments for system integrity.</li>
            <li><strong>Retention:</strong> Data retained only as long as necessary or legally required.</li>
            </ul>
        </div>

        <!-- 4 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">4. Data Sharing and Disclosure</h3>
            <ul class="list-disc list-inside text-gray-700 space-y-2 text-2xl">
            <li><strong>Educational Institutions:</strong> For academic purposes like enrollment and grading.</li>
            <li><strong>Service Providers:</strong> Such as InfinityFree (hosting), under strict protection agreements.</li>
            <li><strong>Legal Authorities:</strong> When required by law or to protect Learnify’s rights or safety.</li>
            </ul>
        </div>

        <!-- 5 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">5. Your Rights</h3>
            <p class="text-gray-700 mb-3 text-2xl">Under the Data Privacy Act, you have the right to:</p>
            <ul class="list-disc list-inside text-gray-700 space-y-2 text-2xl">
            <li><strong>Access:</strong> View your personal data.</li>
            <li><strong>Rectification:</strong> Correct inaccuracies.</li>
            <li><strong>Erasure/Blocking:</strong> Request deletion or restriction.</li>
            <li><strong>Object:</strong> Oppose specific processing.</li>
            <li><strong>Data Portability:</strong> Obtain your data in structured form.</li>
            <li><strong>Complain:</strong> File complaints with the National Privacy Commission.</li>
            </ul>
        </div>

        <!-- 6 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">6. Cookies and Tracking</h3>
            <p class="text-gray-700 text-2xl">
            We use cookies to maintain sessions and track usage. You can manage your preferences in browser settings, 
            but disabling cookies may affect functionality.
            </p>
        </div>

        <!-- 7 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">7. Updates to This Notice</h3>
            <p class="text-gray-700 text-2xl">
            Updates will be posted on the platform, and major changes will be notified via email or in-app alerts.
            </p>
        </div>

        <!-- 8 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">8. Contact Us</h3>
            <p class="text-gray-700 mb-2 text-2xl">For inquiries, contact our Data Protection Officer:</p>
            <ul class="list-disc list-inside text-gray-700 space-y-2 text-2xl">
            <li><strong>Email:</strong> <a href="mailto:dpo@learnify.com" class="text-indigo-600 underline">dpo@learnify.com</a></li>
            <li><strong>Phone:</strong> +63 9103 833 991</li>
            <li><strong>Address:</strong> Learnify Data Protection Office, DIHS Building, Manila, Philippines</li>
            </ul>
        </div>
        </div>

        <p class="text-center text-2xl text-gray-700 mt-12 italic mb-8">
        By using Learnify, you acknowledge and consent to this Data Privacy Notice.
        </p>
    </div>
    </section>

    <!-- Terms and Conditions Section -->
    <section id="terms">
    <div class="container mx-auto px-6 md:px-12 lg:px-20">
        <h2 class="text-5xl md:text-6xl font-bold text-gray-800 text-center mb-10">
        Terms and Conditions
        </h2>

        <p class="text-3xl text-gray-700 leading-relaxed mb-8 text-center max-w-6xl mx-auto">
        Welcome to <strong>Learnify Learning Management System</strong>. By using our platform, you agree to these Terms and Conditions. Please read carefully.
        </p>

        <div class="space-y-10">
        <!-- 1 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">1. Acceptance of Terms</h3>
            <p class="text-gray-700 text-2xl">
            By accessing or registering on Learnify, you agree to these Terms, our Data Privacy Notice, and applicable laws, including the Data Privacy Act of 2012. 
            If you do not agree, you must discontinue using the platform.
            </p>
        </div>

        <!-- 2 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">2. User Accounts</h3>
            <p class="text-gray-700 text-2xl">
            You must provide accurate registration details (LRN, email address). You are responsible for maintaining the confidentiality of your credentials and all activities under your account.
            </p>
        </div>

        <!-- 3 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">3. Platform Usage</h3>
            <p class="text-gray-700 mb-3 text-2xl">
            Learnify supports educational activities such as quizzes, assignments, and gamified learning. You agree not to:
            </p>
            <ul class="list-disc list-inside text-gray-700 space-y-2 text-2xl">
            <li>Engage in unlawful or unauthorized activities.</li>
            <li>Attempt to access systems or data without permission.</li>
            <li>Share inappropriate, harmful, or infringing content.</li>
            </ul>
        </div>

        <!-- 4 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">4. Intellectual Property</h3>
            <p class="text-gray-700 text-2xl">
            All platform content (including text, quizzes, and images) is owned by Learnify or its licensors and protected by intellectual property laws. 
            Reproduction, modification, or distribution requires prior written consent.
            </p>
        </div>

        <!-- 5 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">5. User Conduct</h3>
            <p class="text-gray-700 mb-3 text-2xl">Users are expected to behave respectfully and must not:</p>
            <ul class="list-disc list-inside text-gray-700 space-y-2 text-2xl">
            <li>Engage in harassment or inappropriate behavior.</li>
            <li>Cheat or manipulate academic activities.</li>
            <li>Exploit or misuse platform features, such as the coin system.</li>
            </ul>
        </div>

        <!-- 6 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">6. Termination</h3>
            <p class="text-gray-700 text-2xl">
            Learnify reserves the right to suspend or terminate accounts for violations, including unauthorized access or non-compliance with these Terms.
            </p>
        </div>

        <!-- 7 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">7. Limitation of Liability</h3>
            <p class="text-gray-700 text-2xl">
            Learnify is provided “as is” without warranties of any kind. We are not liable for damages arising from use, data loss, or technical issues, except as required by law.
            </p>
        </div>

        <!-- 8 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">8. Changes to Terms</h3>
            <p class="text-gray-700 text-2xl">
            Terms may be updated from time to time. Continued use of the platform constitutes acceptance of any revisions.
            </p>
        </div>

        <!-- 9 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">9. Governing Law</h3>
            <p class="text-gray-700 text-2xl">
            These Terms are governed by the laws of the Republic of the Philippines. Disputes shall be resolved exclusively in the courts of Manila.
            </p>
        </div>

        <!-- 10 -->
        <div>
            <h3 class="text-4xl font-semibold text-indigo-700 mb-3">10. Contact Us</h3>
            <p class="text-gray-700 mb-2 text-2xl">For questions or support, reach us at:</p>
            <ul class="list-disc list-inside text-gray-700 space-y-2 text-2xl">
            <li><strong>Email:</strong> <a href="mailto:support@learnify.com" class="text-indigo-600 underline">support@learnify.com</a></li>
            <li><strong>Phone:</strong> +63 9103 833 991</li>
            </ul>
        </div>
        </div>

        <p class="text-center text-gray-700 mt-12 italic text-2xl mb-8">
        By using Learnify, you acknowledge that you have read, understood, and agreed to these Terms and Conditions.
        </p>
    </div>
    </section>

    <!-- Games Section -->
    <section id="games" class="active">
        <div class="container mx-auto px-4">
            <h2 class="font-bold text-center mb-2 text-gray-800">Game Features</h2>
            <p class="text-center mb-10 text-gray-600 italic max-w-4xl mx-auto">Engage in fun, educational games designed to enhance learning and make education interactive!</p>
            
            <div class="space-y-16">
                <!-- Game 1: Pixora -->
                <div class="fade-scale flex flex-col md:flex-row items-center gap-8 bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl hover:border-4 hover:border-blue-500">
                    <div class="md:w-1/2" id="game-img">
                        <img src="./pages/students/image/pix-1.jpg" alt="Pixora" class="w-full h-64 object-cover rounded-lg border-2 border-gray-200">
                    </div>
                    <div class="md:w-1/2 game-title">
                        <h3 class="font-semibold text-gray-800 mb-4">Pixora</h3>
                        <p class="text-lg md:text-xl text-gray-600 leading-relaxed">Pixora is an interactive, image-based puzzle game where players solve 30 levels by guessing words or phrases from 1-3 images, earning coins, stars, and a certificate.</p>
                        <ul class="text-gray-600 list-disc list-inside mt-4">
                            <li>Solve puzzles using 1-3 images to identify a word or phrase</li>
                            <li>Unlock badges (Beginner, Intermediate, Expert, Master) and a certificate after 30 levels</li>
                            <li>Claim daily coin bonuses (10-150 coins based on the day)</li>
                            <li>Enjoy zoomable images and speech feedback for hints and results</li>
                            <li>Review completed levels for educational insights</li>
                        </ul>
                        <div class="mt-2 category mt-6">
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full mb-2">Educational</span>
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full ml-2">Image-Based</span>
                            <!-- <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full ml-2">Reward-based</span> -->
                        </div>
                    </div>
                </div>

                <!-- Game 2: Brainzap -->
                <div class="fade-scale flex flex-col md:flex-row-reverse items-center gap-8 bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl hover:border-4 hover:border-blue-500">
                    <div class="md:w-1/2" id="game-img">
                        <img src="./pages/students/image/brainzap.jpg" alt="Brainzap" class="w-full h-64 object-cover rounded-lg border-2 border-gray-200">
                    </div>
                    <div class="md:w-1/2 game-title">
                        <h3 class="font-semibold text-gray-800 mb-4">Brainzap</h3>
                        <p class="text-gray-600 leading-relaxed">Brainzap is an engaging, interactive quiz game where players join sessions using a game code or QR scan, answer timed multiple-choice questions, and compete for top spots on a leaderboard while earning achievements.</p>
                        <ul class="text-gray-600 list-disc list-inside mt-4">
                            <li>Join quizzes by entering a game code or scanning a QR code</li>
                            <li>Answer multiple-choice questions (A, B, C, D) within a time limit</li>
                            <li>Earn points for correct answers based on question difficulty</li>
                            <li>Climb the leaderboard, with rankings based on total points and tiebreakers by name</li>
                            <li>Unlock achievements for 1st, 2nd, and 3rd place finishes</li>
                        </ul>
                        <div class="mt-2 category mt-6 mb-6">
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full mb-2">Quiz-Based</span>
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full ml-2">Timed Challenges</span>
                            <!-- <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full ml-2">Interactive</span> -->
                        </div>
                    </div>
                </div>

                <!-- Game 3: Thinking-It-Out -->
                <div class="fade-scale flex flex-col md:flex-row items-center gap-8 bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl hover:border-4 hover:border-blue-500">
                    <div class="md:w-1/2" id="game-img">
                        <img src="./pages/students/image/Thinking-It-Out.jpg" alt="Thinking-It-Out" class="w-full h-64 object-cover rounded-lg border-2 border-gray-200">
                    </div>
                    <div class="md:w-1/2 game-title">
                        <h3 class="font-semibold text-gray-800 mb-4">Thinking-It-Out</h3>
                        <p class="text-gray-600 leading-relaxed">Thinking-It-Out is an interactive, image-based puzzle game where players progress through 10 roadmaps, each with 30 levels, by guessing words or phrases from images, unlocking badges, and tracking completion progress.</p>
                        <ul class="text-gray-600 list-disc list-inside mt-4">
                            <li>Select from 10 roadmaps themed around different topics</li>
                            <li>Solve 30 levels per roadmap by guessing words or phrases based on a single image</li>
                            <li>Use provided hints and text-to-speech support to aid puzzle-solving</li>
                            <li>Earn and upgrade badges by completing all 30 levels in a roadmap and Track progress with completion percentages for each roadmap</li>
                            <li>Download earned badges as JPG images for keepsakes</li>
                        </ul>
                        <div class="mt-2 category mt-6">
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full mb-2">Puzzle</span>
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full ml-2">Sequential Progression</span>
                            <!-- <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full ml-2">Educational</span> -->
                        </div>
                    </div>
                </div>
            
                <!-- Coming Soon -->
                <div class="fade-scale flex flex-col items-center gap-8 bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl hover:border-4 hover:border-blue-500 text-center">
                    <h3 class="text-3xl md:text-4xl font-semibold text-gray-800 mb-4">More Games Coming Soon!</h3>
                    <p class="text-lg md:text-xl text-gray-600 leading-relaxed">Stay tuned for exciting new games that will make learning even more interactive and fun!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Users Section -->
    <section id="users" class="py-20 md:py-32 active">
        <div class="container mx-auto px-6 fade-scale">
            <h2 class="text-5xl md:text-6xl lg:text-7xl font-bold text-gray-800 text-center mb-16">How Learnify Can Make a Difference for You</h2>
            <!-- Tabs -->
            <div class="flex justify-center mb-12 flex-wrap gap-5">
                <button class="tab-button active px-8 py-4 font-medium text-gray-800 rounded-t-lg transition" data-tab="it-admin">IT Administrators</button>
                <button class="tab-button px-8 py-4  font-medium text-gray-800 bg-gray-100 rounded-t-lg transition" data-tab="admin">Administrators</button>
                <button class="tab-button px-8 py-4 font-medium text-gray-800 bg-gray-100 rounded-t-lg transition" data-tab="teacher">Teachers</button>
                <button class="tab-button px-8 py-4 font-medium text-gray-800 bg-gray-100 rounded-t-lg transition" data-tab="student">Students</button>
            </div>
            <!-- Tab Content -->
            <div class="tab-content flex flex-col md:flex-row items-start gap-12">

                <!-- IT Administrators -->
                <div id="it-admin" class="tab-pane active">
                    <img src="./img/users/Superadmin.png" alt="IT Administrators using Learnify">
                </div>
                <div id="it-admin-content" class="tab-pane-content active w-full md:w-1/2">
                    <h3 class="font-semibold text-gray-800 mb-6">IT Administrators</h3>
                    <p class="text-gray-600 mb-8 leading-relaxed">Super Admins control the system, managing admin accounts, registration, branding, reports, and data backups to keep everything organized and on track.</p>
                </div>

                <!-- Administrators -->
                <div id="admin" class="tab-pane hidden">
                    <img src="./img/users/Administrator.png" alt="Administrators using Learnify">
                </div>
                <div id="admin-content" class="tab-pane-content hidden w-full md:w-1/2">
                    <h3 class="font-semibold text-gray-800 mb-6">Administrators</h3>
                    <p class="text-gray-600 mb-8 leading-relaxed">Admins easily manage teachers, students, subjects, schedules, modules, enrollment, grades, reports, events, and messaging to keep everything organized and connected.</p>
                </div>

                <!-- Teachers -->
                <div id="teacher" class="tab-pane hidden">
                    <img src="./img/users/teacherss.png" alt="Teachers using Learnify">
                </div>
                <div id="teacher-content" class="tab-pane-content hidden w-full md:w-1/2">
                    <h3 class="font-semibold text-gray-800 mb-6">Teachers</h3>
                    <p class="text-gray-600 mb-8 leading-relaxed">Teachers manage attendance, quizzes, assignments, and announcements, view admin-uploaded modules, check the academic calendar, message admins, and create reports to keep classes engaging and organized.</p>
                </div>
                
                <!-- Students -->
                <div id="student" class="tab-pane hidden">
                    <img src="./img/users/Students.png" alt="Students using Learnify">
                </div>
                <div id="student-content" class="tab-pane-content hidden w-full md:w-1/2">
                    <h3 class="font-semibold text-gray-800 mb-6">Students</h3>
                    <p class="text-gray-600 mb-8 leading-relaxed">Students access lessons, quizzes, assignments, announcements, and the academic calendar, add comments to every module, and play engaging games like Think-It-Out and Brainzap to stay connected and motivated.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Help Section -->
    <section id="help" class="active">
        <div class="container mx-auto px-4">
            
            <h2 class="text-6xl font-bold text-center mb-12">Frequently Asked Questions</h2>
            <div class="faq-container">
                <?php foreach ($faqs as $index => $faq) { ?>
                    <details class="faq-item fade-slide-left" <?php echo $index === 0 ? 'open' : ''; ?>>
                        <summary><?php echo $faq['question']; ?></summary>
                        <p><?php echo $faq['answer']; ?></p>
                    </details>
                <?php } ?>
            </div>

            <h2 class="text-6xl font-bold text-center mb-12 mt-20">System Guides</h2>
            <div class="grid-container" id="guides-container">
                <?php foreach ($guides as $index => $guide) { ?>
                    <div class="upcoming-card cursor-pointer fade-scale" data-index="<?php echo $index; ?>" onclick="openGuideModal('guide<?php echo $index + 1; ?>')">
                        <img src="<?php echo $guide['steps'][0]['image']; ?>" alt="<?php echo $guide['title']; ?>" class="w-full h-48 object-cover rounded-lg mb-4">
                        <h3 class="card-title"><?php echo $guide['title']; ?></h3>
                        <h2 class="guide-description"><?php echo isset($guide['description']) ? $guide['description'] : $guide['steps'][0]['description']; ?></h2>
                    </div>
                <?php } ?>
            </div>
        </div>

    </section>

    <!-- Feedack Section -->
    <section id="contact" class="active">
        <div class="container mx-auto px-4">
            <h2 class="text-6xl font-bold text-center mb-12">Feedback</h2>
            <div class="upcoming-content slide-up-fade">
                <div class="custom-card">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3865.776815956978!2d120.95816377469178!3d14.324392686129942!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397d44b2503aec7%3A0x2544b95ff5c0c5fa!2sDasmari%C3%B1as%20Integrated%20High%20School%20Main!5e0!3m2!1sen!2sph!4v1734702699374!5m2!1sen!2sph" width="100%" height="400" style="border:0; border-radius: 0.5rem;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <div class="custom-card">
                    <h3 class="text-2xl font-semibold mb-4">Share Your Thoughts</h3>
                    <div>
                        <div class="mb-4">
                            <!-- <label class="block text-gray-700 font-medium mb-1">Name <span class="text-red-500">*</span></label> -->
                            <input type="text" id="name" placeholder="Name" class="w-full p-3 border rounded-lg" required>
                            <p id="name-error" class="error-text">This field is required.</p>
                        </div>
                        <div class="mb-4">
                            <!-- <label class="block text-gray-700 font-medium mb-1">Email <span class="text-red-500">*</span></label> -->
                            <input type="email" id="email" placeholder="Email" class="w-full p-3 border rounded-lg" required>
                            <p id="email-error" class="error-text">This field is required and must end with @gmail.com or @dihs.edu.ph.</p>
                        </div>
                        <div class="mb-4">
                            <!-- <label class="block text-gray-700 font-medium mb-1">Message <span class="text-red-500">*</span></label> -->
                            <textarea id="message" placeholder="Message" class="w-full p-3 border rounded-lg" rows="5" required></textarea>
                            <p id="message-error" class="error-text">This field is required.</p>
                        </div>
                        <div class="mb-4">
                            <!-- <label class="block text-gray-700 font-medium mb-1">Rating <span class="text-red-500">*</span></label> -->
                            <div class="stars">
                                <input type="radio" name="rating" value="5" id="star5"><label for="star5">★</label>
                                <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                                <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                                <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                                <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                            </div>
                            <p id="rating-error" class="error-text">Please select a rating.</p>
                        </div>
                        <button onclick="submitForm()" class="text-white px-5 py-3 rounded-lg send-message">Send Message</button>
                    </div>
                </div>
            </div> <br><br>
            <div class="mt-12" id="feedback-container-bg">
                <div class="upcoming-header">
                    <h2 class="upcoming-title">User Feedback</h2>
                    <p class="upcoming-subtitle">See what our users are saying about Learnify!</p>
                </div>
                <div class="upcoming-content" id="feedback-container">
                </div>
            </div>
            <br>
            <br>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-content-left">
                <img src="img/DIHS_logo.png" alt="Learnify Logo" class="footer-logo" onclick="showSection('home')">
                <p>Learnify is the official Learning Management System of Dasmariñas Integrated High School, dedicated to fostering quality education.</p>
                <div class="footer-social-icons">
                    <a href="https://facebook.com" target="_blank" class="social-icon"><i class="fab fa-facebook"></i></a>
                    <a href="https://twitter.com" target="_blank" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="https://instagram.com" target="_blank" class="social-icon"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-content-center">
                <h2>Quick Links</h2>
                <ul>
                    <li><a href="./portal.php">Home</a></li>
                    <li><a href="#help" onclick="showSection('help')">Helpdesk</a></li>
                    <li><a href="#contact" onclick="showSection('contact')">Feedback</a></li>
                    <li><a href="#games" onclick="showSection('games')">Games</a></li>
                    <li><a href="#roles" onclick="showSection('roles')">Roles</a></li>
                    <li><a href="#privacy" onclick="showSection('privacy')">Data Privacy</a></li>
                    <li><a href="#terms" onclick="showSection('terms')">Terms & Conditions</a></li>
                </ul>
            </div>
            <div class="footer-content-right">
                <h2>Quick Contact</h2>
                <ul>
                    <li>+63 123 456 7890</li>
                    <li>+63 123 456 7890</li>
                </ul>
                <h2>Email</h2>
                <ul>
                    <li>dasmarinas.ihs@depeddasma.edu.ph</li>
                </ul>
            </div>
            <div class="footer-content-right">
                <h2>Office Hours</h2>
                <ul>
                    <li>7:00 AM - 5:00 PM, Philippines</li>
                </ul>
            </div>
        </div>
        <div class="footer-copyright">
            Copyright © 2025 Learnify - Dasmariñas Integrated High School. All Rights Reserved.
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <div class="scroll-to-top" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- Toast Container -->
    <div id="toast" class="toast-container">
        Feedback insert successfully!
        <div class="toast-progress"></div>
    </div>

    <!-- Tracks & Strands Modal -->
    <div id="tracks-modal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('tracks-modal')">×</span>
            <h2 class="text-3xl font-bold mb-6 __track__">Tracks & Strands</h2>
            <div class="track-buttons">
                <button class="track-btn active" onclick="showTrack('Academic')">Academic</button>
                <button class="track-btn inactive" onclick="showTrack('TVL')">TVL</button>
            </div>
            <?php foreach ($tracks as $trackName => $strands) { ?>
                <div class="track-section <?php echo $trackName === 'Academic' ? 'active' : 'inactive'; ?>" id="track-<?php echo $trackName; ?>">
                    <div class="mb-8">
                        <h3 class="text-2xl font-semibold mb-4"><?php echo $trackName; ?></h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <?php foreach ($strands as $strand) { ?>
                                <div class="card">
                                    <img src="<?php echo $strand['image']; ?>" alt="<?php echo $strand['strandCode']; ?>">
                                    <p class="text-gray-600 strand-name"><strong><?php echo $strand['strandCode']; ?> - <?php echo $strand['strandName']; ?></strong></p>
                                    <button class="mt-3 text-white px-4 py-2 rounded-lg btn-tracks" onclick="downloadFile('<?php echo $strand['subjectsFile']; ?>')">Download Track Form</button>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Guide Modals -->
    <?php foreach ($guides as $index => $guide) { ?>
        <div id="guide<?php echo $index + 1; ?>-modal" class="modal guide-modal">
            <div class="modal-content">
                <div class="guide-modal-header">
                    <h2><?php echo $guide['title']; ?></h2>
                    <button class="fullscreen-toggle" onclick="toggleFullscreen('guide<?php echo $index + 1; ?>-modal')" title="Toggle Fullscreen">
                        <i class="fas fa-expand"></i>
                        <i class="fas fa-compress"></i>
                    </button>
                </div>
                <div class="guide-modal-body">
                    <div class="guide-modal-image-container">
                        <?php foreach ($guide['steps'] as $stepIndex => $step) { ?>
                            <img src="<?php echo $step['image']; ?>" alt="<?php echo $step['stepTitle']; ?>" class="guide-modal-image <?php echo $stepIndex === 0 ? 'active' : ''; ?>" data-step="<?php echo $stepIndex; ?>">
                        <?php } ?>
                    </div>
                    <div class="guide-modal-steps">
                        <?php foreach ($guide['steps'] as $stepIndex => $step) { ?>
                            <details class="<?php echo $stepIndex === 0 ? 'active' : ''; ?>" data-step="<?php echo $stepIndex; ?>" <?php echo $stepIndex === 0 ? 'open' : ''; ?>>
                                <summary><?php echo $step['stepTitle']; ?></summary>
                                <p><?php echo $step['description']; ?></p>
                            </details>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <!-- Cookies Modal -->
    <div id="cookies-modal" class="modal">
        <div class="modal-content text-center">
            <span class="modal-close" onclick="closeModal('cookies-modal')">×</span>
            <h2>We Value Your Privacy</h2>
            <p>
                Learnify uses cookies to improve your experience and maintain session security. 
                Cookies keep you logged in even if you close your browser without logging out. 
                For your protection, Learnify includes an automatic logout feature that activates after 
                <strong>5 minutes of inactivity</strong> (or your configured timeout duration).
            </p>

            <details>
                <summary>Learn More About Cookies</summary>
                <p>Learnify uses the following types of cookies:</p>
                <ul>
                    <li><strong>Essential Cookies:</strong> Required for login sessions and basic platform functions.</li>
                    <li><strong>Performance Cookies:</strong> Help optimize loading speed and system responsiveness.</li>
                    <li><strong>Analytics Cookies:</strong> Collect usage data to improve platform performance and usability.</li>
                </ul>
                <p>
                    Note: Rejecting non-essential cookies may limit some functionality, but essential cookies will still be used 
                    to keep Learnify secure and functional.
                </p>
            </details>

            <div class="buttons">
                <button class="reject-btn" onclick="rejectCookies()">Reject</button>
                <button class="accept-btn" onclick="acceptCookies()">Accept All Cookies</button>
            </div>
        </div>
    </div>

    <!-- Cookies Accept Icon -->
    <div class="cookies-accept-icon" onclick="openModal('cookies-modal')">
        <i class="fas fa-shield-alt"></i>
    </div>

    <script>

    let scrollPosition = 0;

    function showSection(sectionId) {
        document.querySelectorAll('section').forEach(section => {
            section.classList.remove('active');
        });
        const section = document.getElementById(sectionId);
        if (section) {
            section.classList.add('active');
        }
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${sectionId}`) {
                link.classList.add('active');
            }
        });
        const bannerMarquee = document.getElementById('banner-marquee');
        if (sectionId === 'home') {
            bannerMarquee.style.display = 'block';
        } else {
            bannerMarquee.style.display = 'none';
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
        const mobileMenu = document.getElementById('mobile-menu');
        if (!mobileMenu.classList.contains('hidden')) {
            mobileMenu.classList.add('hidden');
        }
    }

    document.getElementById('mobile-menu-btn').addEventListener('click', () => {
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenu.classList.toggle('hidden');
    });

    function openModal(modalId) {
        scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        const modal = document.getElementById(modalId);
        modal.style.display = 'flex';
        if (modal.classList.contains('guide-modal')) {
            const firstDetails = modal.querySelector('.guide-modal-steps details');
            if (firstDetails) {
                const stepIndex = firstDetails.getAttribute('data-step');
                showSingleImage(modal, stepIndex);
            }
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.style.display = 'none';
        if (modal.classList.contains('fullscreen')) {
            modal.classList.remove('fullscreen');
            document.body.classList.remove('modal-fullscreen');
        }
        window.scrollTo({ top: scrollPosition, behavior: 'instant' });
    }

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    function toggleFullscreen(modalId) {
        const modal = document.getElementById(modalId);
        const body = document.body;
        if (modal.classList.contains('fullscreen')) {
            modal.classList.remove('fullscreen');
            body.classList.remove('modal-fullscreen');
        } else {
            modal.classList.add('fullscreen');
            body.classList.add('modal-fullscreen');
        }
    }

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    window.addEventListener('scroll', () => {
        const scrollToTop = document.querySelector('.scroll-to-top');
        if (window.pageYOffset > 100) {
            scrollToTop.classList.add('visible');
        } else {
            scrollToTop.classList.remove('visible');
        }
    });

    function downloadFile(file) {
        const link = document.createElement('a');
        link.href = file;
        link.download = file;
        link.click();
    }

    function openGuideModal(guideId) {
        openModal(guideId + '-modal');
    }

    function showSingleImage(modal, stepIndex) {
        const images = modal.querySelectorAll('.guide-modal-image');
        const details = modal.querySelectorAll('.guide-modal-steps details');
        images.forEach(img => {
            img.classList.remove('active');
            img.style.display = 'none';
        });
        details.forEach(det => {
            det.classList.remove('active');
            det.removeAttribute('open');
        });
        const targetImage = modal.querySelector(`.guide-modal-image[data-step="${stepIndex}"]`);
        const targetDetails = modal.querySelector(`.guide-modal-steps details[data-step="${stepIndex}"]`);
        if (targetImage) {
            targetImage.classList.add('active');
            targetImage.style.display = 'block';
        }
        if (targetDetails) {
            targetDetails.classList.add('active');
            targetDetails.setAttribute('open', '');
        }
    }

    document.querySelectorAll('.guide-modal-steps summary').forEach(summary => {
        summary.addEventListener('click', (e) => {
            e.preventDefault();
            const modal = e.target.closest('.modal');
            const stepIndex = e.target.parentElement.getAttribute('data-step');
            showSingleImage(modal, stepIndex);
        });
    });

    document.querySelectorAll('#faqs .faq-item summary').forEach(summary => {
        summary.addEventListener('click', function (e) {
            const clickedDetails = summary.parentElement;

            // Delay execution to allow toggle to happen first
            setTimeout(() => {
                document.querySelectorAll('#faqs .faq-item').forEach(item => {
                    if (item !== clickedDetails) {
                        item.removeAttribute('open');
                    }
                });
            }, 50);
        });
    });

    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('home').classList.contains('active')) {
            loadFeedback();
        }
    });

    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (link.getAttribute('href') === '#home') {
                setTimeout(loadFeedback, 100);
            }
        });
    });

    function loadFeedback() {
        fetch('portal.php?action=feedback')
            .then(response => response.json())
            .then(data => {
                const feedbackContainer = document.getElementById('feedback-container');
                feedbackContainer.innerHTML = `
                    <div class="feedback-slider" id="feedback-slider" role="region" aria-label="Feedback slider"></div>
                    <div class="slider-controls">
                        <button onclick="prevSlide()" aria-label="Previous slide">←</button>
                        <button onclick="nextSlide()" aria-label="Next slide">→</button>
                    </div>
                `;
                const slider = document.getElementById('feedback-slider');
                
                if (data.success && data.feedback.length > 0) {
                    const feedbackItems = [...data.feedback, ...data.feedback];
                    feedbackItems.forEach((fb, index) => {
                        const stars = '★'.repeat(fb.rating) + '☆'.repeat(5 - fb.rating);
                        const firstLetter = fb.name.charAt(0).toUpperCase();
                        const avatarColor = `hsl(${firstLetter.charCodeAt(0) % 360}, 60%, 50%)`;
                        const feedbackCard = document.createElement('div');
                        feedbackCard.classList.add('feedback-card');
                        feedbackCard.setAttribute('role', 'article');
                        feedbackCard.setAttribute('aria-labelledby', `feedback-${index}`);
                        feedbackCard.innerHTML = `
                            <div class="avatar" style="background-color: ${avatarColor}">${firstLetter}</div>
                            <div class="stars" aria-label="${fb.rating} out of 5 stars">${stars}</div>
                            <h4 id="feedback-${index}">${fb.name}</h4>
                            <p>${fb.message}</p>
                            <p class="feedback-date">${new Date(fb.created_at).toLocaleString('en-US', {
                                month: 'long',
                                day: 'numeric',
                                year: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            })}</p>
                        `;
                        slider.appendChild(feedbackCard);
                    });
                    
                    let currentIndex = 0;
                    const slides = slider.children;
                    const slideCount = data.feedback.length;
                    let autoSlideInterval = null;
                    
                    function showSlide(index) {
                        if (index >= slideCount) {
                            currentIndex = index % slideCount;
                            slider.style.transition = 'none';
                            slider.style.transform = `translateX(-${currentIndex * (100 / 3)}%)`;
                            setTimeout(() => {
                                slider.style.transition = 'transform 0.5s ease-in-out';
                            }, 50);
                        } else if (index < 0) {
                            currentIndex = slideCount + (index % slideCount);
                            slider.style.transition = 'none';
                            slider.style.transform = `translateX(-${currentIndex * (100 / 3)}%)`;
                            setTimeout(() => {
                                slider.style.transition = 'transform 0.5s ease-in-out';
                            }, 50);
                        } else {
                            currentIndex = index;
                        }
                        slider.style.transform = `translateX(-${currentIndex * (100 / 3)}%)`;
                    }
                    
                    function nextSlide() {
                        showSlide(currentIndex + 1);
                    }
                    
                    function prevSlide() {
                        showSlide(currentIndex - 1);
                    }
                    
                    function startAutoSlide() {
                        if (!autoSlideInterval) {
                            autoSlideInterval = setInterval(nextSlide, 5000);
                        }
                    }
                    
                    function stopAutoSlide() {
                        if (autoSlideInterval) {
                            clearInterval(autoSlideInterval);
                            autoSlideInterval = null;
                        }
                    }
                    
                    slider.addEventListener('mouseenter', stopAutoSlide);
                    slider.addEventListener('mouseleave', startAutoSlide);
                    slider.addEventListener('focusin', stopAutoSlide);
                    slider.addEventListener('focusout', startAutoSlide);
                    
                    startAutoSlide();
                    showSlide(0);
                    
                    window.nextSlide = nextSlide;
                    window.prevSlide = prevSlide;
                } else {
                    feedbackContainer.innerHTML = '<p class="text-gray-600 text-center" role="alert">No feedback available yet.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching feedback:', error);
                document.getElementById('feedback-container').innerHTML = '<p class="text-red-500 text-center" role="alert">Failed to load feedback.</p>';
            });
    }

    function submitForm() {
        const formData = new FormData();
        formData.append('name', document.getElementById('name').value.trim());
        formData.append('email', document.getElementById('email').value.trim());
        formData.append('message', document.getElementById('message').value.trim());
        formData.append('rating', document.querySelector('input[name="rating"]:checked')?.value || 0);

        const nameError = document.getElementById('name-error');
        const emailError = document.getElementById('email-error');
        const messageError = document.getElementById('message-error');
        const ratingError = document.getElementById('rating-error');
        
        nameError.style.display = 'none';
        emailError.style.display = 'none';
        messageError.style.display = 'none';
        ratingError.style.display = 'none';
        
        let isValid = true;

        if (!formData.get('name')) {
            nameError.style.display = 'block';
            isValid = false;
        }
        if (!formData.get('email')) {
            emailError.style.display = 'block';
            isValid = false;
        } else if (!formData.get('email').match(/@(?:gmail\.com|dihs\.edu\.ph)$/)) {
            emailError.textContent = 'Email must end with @gmail.com or @dihs.edu.ph.';
            emailError.style.display = 'block';
            isValid = false;
        }
        if (!formData.get('message')) {
            messageError.style.display = 'block';
            isValid = false;
        }
        if (!formData.get('rating') || formData.get('rating') == 0) {
            ratingError.textContent = 'Please select a rating.';
            ratingError.style.display = 'block';
            isValid = false;
        }

        if (isValid) {
            fetch('portal.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const toast = document.getElementById('toast');
                    toast.classList.add('show');
                    setTimeout(() => {
                        toast.classList.remove('show');
                        document.getElementById('name').value = '';
                        document.getElementById('email').value = '';
                        document.getElementById('message').value = '';
                        document.querySelector('input[name="rating"]:checked').checked = false;
                        if (document.getElementById('home').classList.contains('active')) {
                            loadFeedback();
                        }
                    }, 5000);
                } else {
                    if (data.errors.name) nameError.textContent = data.errors.name;
                    if (data.errors.email) emailError.textContent = data.errors.email;
                    if (data.errors.message) messageError.textContent = data.errors.message;
                    if (data.errors.rating) ratingError.textContent = data.errors.rating;
                    if (data.errors.general) alert(data.errors.general);
                    nameError.style.display = data.errors.name ? 'block' : 'none';
                    emailError.style.display = data.errors.email ? 'block' : 'none';
                    messageError.style.display = data.errors.message ? 'block' : 'none';
                    ratingError.style.display = data.errors.rating ? 'block' : 'none';
                }
            })
            .catch(error => {
                console.error('Error submitting feedback:', error);
                alert('Failed to submit feedback. Please try again later.');
            });
        }
    }

    function showTrack(trackName) {
        document.querySelectorAll('.track-section').forEach(section => {
            section.classList.remove('active');
            section.classList.add('inactive');
        });
        document.querySelectorAll('.track-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.classList.add('inactive');
        });
        const section = document.getElementById(`track-${trackName}`);
        section.classList.remove('inactive');
        section.classList.add('active');
        const button = document.querySelector(`button[onclick="showTrack('${trackName}')"]`);
        button.classList.remove('inactive');
        button.classList.add('active');
    }

    function acceptCookies() {
        localStorage.setItem('cookiesAccepted', 'true');
        closeModal('cookies-modal');
    }

    function rejectCookies() {
        localStorage.setItem('cookiesAccepted', 'false');
        closeModal('cookies-modal');
    }

    // Initialize cookies modal with 30-second delay
    document.addEventListener('DOMContentLoaded', () => {
        const cookiesAccepted = localStorage.getItem('cookiesAccepted');
        if (!cookiesAccepted) {
            setTimeout(() => {
                openModal('cookies-modal');
            }, 30000); // 30-second delay
        }
    });


    // Tab Switching Functionality
    document.addEventListener('DOMContentLoaded', () => {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabPanes = document.querySelectorAll('.tab-pane');
        const tabContents = document.querySelectorAll('.tab-pane-content');

        // Ensure IT Administrators is default
        document.getElementById('it-admin').classList.add('active');
        document.getElementById('it-admin-content').classList.add('active');
        document.querySelector('.tab-button[data-tab="it-admin"]').classList.add('active', 'bg-blue-100');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons
                tabButtons.forEach(btn => {
                    btn.classList.remove('active', 'bg-blue-100');
                    btn.classList.add('bg-gray-100');
                });
                // Add active class to clicked button
                button.classList.add('active', 'bg-blue-100');
                button.classList.remove('bg-gray-100');

                // Hide all tab panes and content
                tabPanes.forEach(pane => pane.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                // Show the selected tab pane and content
                const tabId = button.getAttribute('data-tab');
                const pane = document.getElementById(tabId);
                const content = document.getElementById(`${tabId}-content`);
                if (pane && content) {
                    pane.classList.add('active');
                    content.classList.add('active');
                } else {
                    console.error(`Tab pane or content not found for tabId: ${tabId}`);
                }
            });
        });
    });

    function submitForm() {
        console.log('submitForm called (Feedback Form)'); 
        const formData = new FormData();
        formData.append('name', document.getElementById('name').value.trim());
        formData.append('email', document.getElementById('email').value.trim());
        formData.append('message', document.getElementById('message').value.trim());
        formData.append('rating', document.querySelector('input[name="rating"]:checked')?.value || 0);

        const nameError = document.getElementById('name-error');
        const emailError = document.getElementById('email-error');
        const messageError = document.getElementById('message-error');
        const ratingError = document.getElementById('rating-error');
        
        nameError.style.display = 'none';
        emailError.style.display = 'none';
        messageError.style.display = 'none';
        ratingError.style.display = 'none';
        
        let isValid = true;

        if (!formData.get('name')) {
            nameError.style.display = 'block';
            isValid = false;
        }
        if (!formData.get('email')) {
            emailError.style.display = 'block';
            isValid = false;
        } else if (!formData.get('email').match(/@(?:gmail\.com|dihs\.edu\.ph)$/)) {
            emailError.textContent = 'Email must end with @gmail.com or @dihs.edu.ph.';
            emailError.style.display = 'block';
            isValid = false;
        }
        if (!formData.get('message')) {
            messageError.style.display = 'block';
            isValid = false;
        }
        if (!formData.get('rating') || formData.get('rating') == 0) {
            ratingError.textContent = 'Please select a rating.';
            ratingError.style.display = 'block';
            isValid = false;
        }

        if (isValid) {
            console.log('Submitting feedback form data:', Object.fromEntries(formData)); 
            fetch('portal.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Feedback form response status:', response.status); 
                return response.json();
            })
            .then(data => {
                console.log('Feedback form response data:', data); 
                if (data.success) {
                    const toast = document.getElementById('toast');
                    toast.textContent = 'Feedback sent successfully!'; 
                    toast.classList.add('show');
                    setTimeout(() => {
                        toast.classList.remove('show');
                        document.getElementById('name').value = '';
                        document.getElementById('email').value = '';
                        document.getElementById('message').value = '';
                        document.querySelector('input[name="rating"]:checked').checked = false;
                        if (document.getElementById('home').classList.contains('active')) {
                            loadFeedback();
                        }
                    }, 5000);
                } else {
                    if (data.errors.name) nameError.textContent = data.errors.name;
                    if (data.errors.email) emailError.textContent = data.errors.email;
                    if (data.errors.message) messageError.textContent = data.errors.message;
                    if (data.errors.rating) ratingError.textContent = data.errors.rating;
                    if (data.errors.general) alert(data.errors.general);
                    nameError.style.display = data.errors.name ? 'block' : 'none';
                    emailError.style.display = data.errors.email ? 'block' : 'none';
                    messageError.style.display = data.errors.message ? 'block' : 'none';
                    ratingError.style.display = data.errors.rating ? 'block' : 'none';
                }
            })
            .catch(error => {
                console.error('Error submitting feedback form:', error); 
                alert('Failed to submit feedback. Please try again later.');
            });
        } else {
            console.log('Feedback form validation failed'); 
        }
    }

    function submitContactForm() {
        console.log('submitContactForm called (Contact Modal)'); 
        const formData = new FormData();
        formData.append('form_type', 'contact_modal');
        formData.append('name', document.getElementById('contact-name').value.trim());
        formData.append('email', document.getElementById('contact-email').value.trim());
        formData.append('message', document.getElementById('contact-message').value.trim());

        const nameError = document.getElementById('contact-name-error');
        const emailError = document.getElementById('contact-email-error');
        const messageError = document.getElementById('contact-message-error');

        nameError.style.display = 'none';
        emailError.style.display = 'none';
        messageError.style.display = 'none';

        let isValid = true;

        if (!formData.get('name')) {
            nameError.style.display = 'block';
            isValid = false;
        }
        if (formData.get('email') && !formData.get('email').match(/@(?:gmail\.com|dihs\.edu\.ph)$/)) {
            emailError.textContent = 'Email must end with @gmail.com or @dihs.edu.ph.';
            emailError.style.display = 'block';
            isValid = false;
        }
        if (!formData.get('message')) {
            messageError.style.display = 'block';
            isValid = false;
        }

        if (isValid) {
            console.log('Submitting contact form data:', Object.fromEntries(formData)); 
            fetch('portal.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Contact form response status:', response.status); 
                return response.json();
            })
            .then(data => {
                console.log('Contact form response data:', data); 
                if (data.success) {
                    const toast = document.getElementById('toast');
                    toast.textContent = 'Contact message sent successfully!'; 
                    toast.classList.add('show');
                    setTimeout(() => {
                        toast.classList.remove('show');
                        document.getElementById('contact-name').value = '';
                        document.getElementById('contact-email').value = '';
                        document.getElementById('contact-message').value = '';
                        closeModal('contact-modal');
                    }, 5000);
                } else {
                    if (data.errors.name) nameError.textContent = data.errors.name;
                    if (data.errors.email) emailError.textContent = data.errors.email;
                    if (data.errors.message) messageError.textContent = data.errors.message;
                    if (data.errors.general) alert(data.errors.general);
                    nameError.style.display = data.errors.name ? 'block' : 'none';
                    emailError.style.display = data.errors.email ? 'block' : 'none';
                    messageError.style.display = data.errors.message ? 'block' : 'none';
                }
            })
            .catch(error => {
                console.error('Error submitting contact form:', error); 
                alert('Failed to submit contact form. Please try again later.');
            });
        } else {
            console.log('Contact form validation failed'); 
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const elements = document.querySelectorAll('.slide-in');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                } else {
                    entry.target.classList.remove('visible'); 
                }
                });
            }, {
            threshold: 0.1 
            });
            elements.forEach(element => observer.observe(element));
            });

    document.addEventListener('DOMContentLoaded', () => {
            const elements = document.querySelectorAll('.fade-scale');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    } else {
                        entry.target.classList.remove('visible'); 
                    }
                });
            }, {
                threshold: 0.1 
            });

            elements.forEach(element => observer.observe(element));
        });

        document.addEventListener('DOMContentLoaded', () => {
            const elements = document.querySelectorAll('.slide-up-fade');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    } else {
                        entry.target.classList.remove('visible'); 
                    }
                });
            }, {
                threshold: 0.1 
            });

            elements.forEach(element => observer.observe(element));
        });

        document.addEventListener('DOMContentLoaded', () => {
            const elements = document.querySelectorAll('.fade-slide-left');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    } else {
                        entry.target.classList.remove('visible'); 
                    }
                });
            }, {
                threshold: 0.1 
            });

            elements.forEach(element => observer.observe(element));
        });
    </script>

    <script>
  // Dropdown functionality for the "Legal" item (works with your existing markup)
  (function () {
    // find all dropdown wrappers (your markup: <div class="relative group"> ... <button class="nav-link ..."> ... </button> <div class="absolute ..."> ... </div> </div>)
    const dropdownWrappers = Array.from(document.querySelectorAll('.relative.group'));

    function closeAllDropdowns(except = null) {
      dropdownWrappers.forEach(wrapper => {
        const menu = wrapper.querySelector('div.absolute');
        const button = wrapper.querySelector('button');
        if (!menu || !button) return;
        if (wrapper === except) return;
        menu.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
      });
    }

    dropdownWrappers.forEach(wrapper => {
      const button = wrapper.querySelector('button');
      const menu = wrapper.querySelector('div.absolute');

      if (!button || !menu) return;

      if (!menu.classList.contains('hidden')) menu.classList.add('hidden');

      button.setAttribute('aria-haspopup', 'true');
      button.setAttribute('aria-expanded', 'false');

      button.addEventListener('click', (e) => {
        e.stopPropagation();
        const isHidden = menu.classList.contains('hidden');
        closeAllDropdowns(isHidden ? wrapper : null); 
        menu.classList.toggle('hidden');
        button.setAttribute('aria-expanded', String(isHidden));
      });

      // Optional: close the dropdown if focus leaves (keyboard accessibility)
      button.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          menu.classList.add('hidden');
          button.setAttribute('aria-expanded', 'false');
          button.blur();
        }
      });

      menu.addEventListener('click', (ev) => ev.stopPropagation());
    });

    document.addEventListener('click', () => closeAllDropdowns());

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAllDropdowns();
    });

  })();
</script>

</body>
</html>