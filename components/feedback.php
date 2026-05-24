<?php
require_once '../config/db_connection.php';  

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_name = $_POST['user_name'];
    $feedback = $_POST['feedback'];
    $rating = $_POST['rating'];

    if (!empty($user_name) && !empty($feedback) && !empty($rating)) {
        $query = "INSERT INTO feedbacks (user_name, feedback, rating) VALUES (:user_name, :feedback, :rating)";
        $stmt = $dbConnection->prepare($query);
        $stmt->bindParam(':user_name', $user_name);
        $stmt->bindParam(':feedback', $feedback);
        $stmt->bindParam(':rating', $rating);

        if ($stmt->execute()) {
            $successMessage = "Feedback submitted successfully!";
        } else {
            $errorMessage = "Something went wrong. Please try again.";
        }
    }
}

$query = "SELECT rating FROM feedbacks";
$stmt = $dbConnection->prepare($query);
$stmt->execute();
$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRatings = count($ratings);
$sumRatings = array_sum(array_column($ratings, 'rating'));

$averageRating = $totalRatings > 0 ? $sumRatings / $totalRatings : 0;

$ratingCount = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($ratings as $rating) {
    $ratingCount[$rating['rating']]++;
}

$ratingPercentages = [];
foreach ($ratingCount as $rating => $count) {
    $ratingPercentages[$rating] = $totalRatings > 0 ? ($count / $totalRatings) * 100 : 0;
}

$query = "SELECT user_name, feedback, rating, created_at FROM feedbacks ORDER BY id DESC LIMIT 5"; 
$stmt = $dbConnection->prepare($query);
$stmt->execute();
$recentFeedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Learnify</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../components/css/normal_text.css">
    <link rel="stylesheet" href="../components/css/feedback.css">
</head>

<body>
    <!-- Navbar -->
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
        <h1 class="text-center mb-4">Feedback and Suggestions</h1>
        <div class="row">
            <!-- Feedback Form Section (Left) -->
            <div class="col-md-6">
                <div class="feedback-form">
                    <h4>Submit Your Feedback</h4><hr class="hr">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php elseif (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>
                    <form action="feedback.php" method="POST">
                        <div class="form-group">
                            <label for="user_name">Your Name:</label>
                            <input type="text" class="form-control" id="user_name" name="user_name" required>
                        </div>

                        <div class="form-group">
                            <label for="feedback">Your Feedback:</label>
                            <textarea class="form-control" id="feedback" name="feedback" rows="4" required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="rating">Rating:</label>
                            <select class="form-control" id="rating" name="rating" required>
                                <option value="">- Select Rating -</option>
                                <option value="1">1 Star ⭐</option>
                                <option value="2">2 Star ⭐⭐</option>
                                <option value="3">3 Star ⭐⭐⭐</option>
                                <option value="4">4 Star ⭐⭐⭐⭐</option>
                                <option value="5">5 Star ⭐⭐⭐⭐⭐</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success">Submit Feedback</button>
                    </form>
                </div>
            </div>

            <!-- Rating Distribution (Right) -->
            <div class="col-md-6">
                <h2 class="rights text-center mt-4 fs-1">Rating Distribution</h2><hr class="hr">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <div class="progress-label">
                        <strong><?php echo $i; echo $i === 1 ? ' Star' : ' Stars'; ?>: </strong> <?php echo round($ratingPercentages[$i], 2); ?>%
                    </div>
                    <div class="rating-bar">
                        <div class="progress-bar" style="width: <?php echo $ratingPercentages[$i]; ?>%; background-color: #f8d64e;"></div>
                    </div>
                <?php endfor; ?>
                <h2 class="rights text-center mt-4 display-5"><?php echo round($averageRating, 1); ?></h2>
                <div class="stars">
                        <?php 
                        $fullStars = floor($averageRating);
                        $halfStar = ($averageRating - $fullStars) >= 0.5 ? true : false;
                        $emptyStars = 5 - ceil($averageRating);

                        for ($i = 1; $i <= $fullStars; $i++) {
                            echo '<i class="fas fa-star"></i>';
                        }

                        if ($halfStar) {
                            echo '<i class="fas fa-star-half-alt"></i>';
                        }

                        for ($i = 1; $i <= $emptyStars; $i++) {
                            echo '<i class="far fa-star"></i>';
                        }
                        ?>
                </div>
                <p class="text-center">(Total of <?php echo $totalRatings; ?> Feedbacks)</p>
            </div>
        </div>

        <!-- Recent Feedbacks Section -->
        <div class="mt-5">
            <h2 class="fs-1">Recent Feedbacks</h2><hr>
            <div class="row">
                <?php foreach ($recentFeedbacks as $feedback): ?>
                    <div class="col-md-6 mb-4">
                        <div class="feedback-item">
                            <!-- User Image and Name (First Row) -->
                            <div class="d-flex align-items-center">
                                <div class="user-image">
                                    <?php echo strtoupper($feedback['user_name'][0]); ?>
                                </div>
                                <strong class="ms-2 fs-4 mb-3"><?php echo $feedback['user_name']; ?></strong>
                            </div>

                            <!-- Stars (Second Row) -->
                            <div class="stars fs-5">
                                <?php for ($i = 1; $i <= $feedback['rating']; $i++): ?>
                                    <i class="fas fa-star"></i>
                                <?php endfor; ?><span></span>
                                <?php echo date('F j, Y, g:i a', strtotime($feedback['created_at'])); ?>
                            </div>

                            <!-- Feedback (Third Row) -->
                            <div class="feedback-text">
                                <hr>
                                <p><?php echo $feedback['feedback']; ?></p>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="bg-success text-white text-center py-3 mt-5">
        <p>&copy; <?php echo date('Y'); ?> Learnify - Dasmariñas Integrated High School. All Rights Reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
