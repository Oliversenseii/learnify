<?php $currentYear = date("Y"); ?> 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Learnify</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../components/css/normal_text.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 93%;
            margin: 0;
        }

        .container {
            min-height: 100%; 
            display: flex;
            flex-direction: column;
        }

        .content {
            flex-grow: 1; 
            display: flex;
            justify-content: center;
            margin-top: 95px;
            align-items: center;
        }

        .contact-info {
            padding: 10px;
            border-radius: 5px;
            text-align: left;
        }

        .contact-info h2 {
            font-size: 3rem;
            margin-top: 0;
            margin-bottom: 50px;
        }

        .contact-info p {
            font-size: 1.8rem;
        }

        .map-frame {
            height: 500px;
            width: 100%;
            border: 0;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="./contact.php">Learnify</a>

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

    <div class="container">
        <div class="content">
            <div class="row w-100">
                <!-- Left: Contact Information -->
                <div class="col-md-6 contact-info mx-auto">
                    <h2><mark>Contact Us</mark></h2>
                    <p>
                        <strong>Location</strong><br>
                        Dasmariñas Integrated High School,<br> 
                        Congressional South Avenue,<br> 
                        Burol I,<br> 
                        Dasmariñas City, Cavite
                    </p><br>
                    <p>
                        <strong>Contact Numbers:</strong><br> 
                        506-1208 / 416-0498
                    </p>
                    <p><br>
                        <strong>Email</strong> your concern/s to:<br>
                        <a href="mailto:dasmarinas.ihs@depeddasma.edu.ph">dasmarinas.ihs@depeddasma.edu.ph</a></p>
                </div>

                <!-- Right: Map Frame -->
                <div class="col-md-6">
                    <iframe class="map-frame" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3865.776815956978!2d120.95816377469178!3d14.324392686129942!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397d44b2503aec7%3A0x2544b95ff5c0c5fa!2sDasmari%C3%B1as%20Integrated%20High%20School%20Main!5e0!3m2!1sen!2sph!4v1734702699374!5m2!1sen!2sph" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-success text-white text-center py-3 mt-5">
        <p>&copy; <?php echo $currentYear; ?> Learnify - Dasmariñas Integrated High School. All Rights Reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
