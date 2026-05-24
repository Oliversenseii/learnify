<?php 
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userID = $_SESSION['userID'];
    $feedback = trim($_POST['feedback']);
    $rating = $_POST['rating'];
    $user_name = trim($_POST['user_name']) !== '' ? trim($_POST['user_name']) : '';

    if (!empty($userID) && !empty($feedback) && !empty($rating)) {
        $query = "INSERT INTO feedbacks (userID, feedback, rating, user_name) VALUES (:userID, :feedback, :rating, :user_name)";
        $stmt = $dbConnection->prepare($query);
        $stmt->bindParam(':userID', $userID);
        $stmt->bindParam(':feedback', $feedback);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':user_name', $user_name);

        if ($stmt->execute()) {
            $successMessage = "Feedback submitted successfully!";
        } else {
            $errorMessage = "Something went wrong. Please try again.";
        }
    } else {
        $errorMessage = "Please fill all required fields.";
    }
}

// Fetch ratings for distribution
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

// Fetch recent feedbacks with user details
$query = "SELECT u.firstName, u.lastName, f.feedback, f.rating, f.created_at, f.user_name 
          FROM feedbacks f
          JOIN users u ON f.userID = u.userID
          ORDER BY f.id DESC LIMIT 5";
$stmt = $dbConnection->prepare($query);
$stmt->execute();
$recentFeedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's name for display in form
if (!isset($_SESSION['firstName']) || !isset($_SESSION['lastName'])) {
    $query = "SELECT firstName, lastName FROM users WHERE userID = :userID";
    $stmt = $dbConnection->prepare($query);
    $stmt->bindParam(':userID', $_SESSION['userID']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $sessionName = $user['firstName'] . ' ' . $user['lastName'];
} else {
    $sessionName = $_SESSION['firstName'] . ' ' . $_SESSION['lastName'];
}

$userID = $_SESSION['userID'];

try {
    $stmt = $dbConnection->prepare("SELECT firstName, middleName, lastName, birthday, sex, address, email, contactNumber, nationality, image, status, generated_code, lrn FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = $user['firstName'];
        $_SESSION['userType'] = 'Student';  

        $_SESSION['image'] = $user['image'] ? $user['image'] : './img/noprofile.png'; 

        // Calculate age from birthday
        $birthday = new DateTime($user['birthday']);
        $today = new DateTime();
        $age = $today->diff($birthday)->y;
    } else {
        header("Location: ../../index.php");
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<?php require_once './feedback_head.php'; ?>

<body>


    <!-- SIDEBAR -->
    <section id="sidebar">
		<a href="./studentDash.php" class="brand">
            <img class="logo_img" src="./img/darky-1.png" alt="">
            <span class="text" id="logo_text">Learnify</span>
        </a>

		<ul class="side-menu top">
			<li>
				<a href="./studentDash.php">
					<i class='bx bxs-home' ></i>
					<span class="text">Home</span>
				</a>
			</li>
			
		<!-- Dynamically Display Enrolled Subjects -->
			<?php if (!empty($enrollmentsSub)): ?>
				<li class="submenu-title">Enrolled</li>
				<?php foreach ($enrollmentsSub as $subject): ?>
					<?php
					// Extract initials (First Letter of Each Word)
					$words = explode(" ", $subject['subjectName']);
					$initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ""));

					// Define a color palette similar to Google Classroom's icons
					$colors = ['#4CAF50', '#F44336', '#FFEB3B', '#8BC34A', '#9C27B0', '#FF9800'];
					$bgColor = $colors[array_rand($colors)];
					?>
					<li class="subject-item">
						<a href="show-module.php?subjectID=<?php echo $subject['subjectID']; ?>&moduleID=<?php echo $subject['moduleID']; ?>&profFirstName=<?php echo urlencode($subject['professorFirstName']); ?>&profLastName=<?php echo urlencode($subject['professorLastName']); ?>">
							<!-- Subject Icon -->
							<span class="subject-icon" style="background-color: <?php echo $bgColor; ?>;">
								<?php echo $initials; ?>
							</span>
							<!-- Truncated Subject Name -->
							<span class="subject-texts"><?php echo htmlspecialchars($subject['subjectName']); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>

            <li class="active">
                <a href="./feedback.php">
                    <i class='bx bxs-comment-detail'></i>
                    <span class="text">Feedback</span>
                </a>
            </li>
               <!-- Quizzes Section -->
			 <li class="submenu-title">Quizzes & Assignments</li>
            <li>
                <a href="./all_quizzes.php">
                    <i class='bx bx-edit'></i>
                    <span class="text">View All Quizzes</span>
                </a>
            </li>
            <li>
                <a href="./all_assignments.php">
                    <i class='bx bx-upload'></i>
                    <span class="text">View All Assignments</span>
                </a>
            </li>
			
		</ul>

		<ul class="side-menu">
			<li>
				<a href="./select-games.php">
					<i class='bx bxs-game'></i>
					<span class="text">Games</span>
				</a>
			</li>
			<li>
				<a href="./settings.php">
					<i class='bx bxs-cog'></i>
					<span class="text">Settings</span>
				</a>
			</li>
			<li>
				<a href="./location.php">
					<i class='bx bxs-map'></i>
					<span class="text">Geolocation</span>
				</a>
			</li>
			<li>
				<a href="javascript:void(0);" class="logout" onclick="showLogoutModal()">
					<i class='bx bxs-log-out-circle'></i>
					<span class="text">Logout</span>
				</a>
			</li>
		</ul>
	</section>
    <!-- SIDEBAR -->

    <?php require_once './view/modal.php' ?>	

    <!-- CONTENT -->
    <section id="content">
        <!-- NAVBAR -->
        <nav>
            <i class='bx bx-menu' ></i>
                <form action="search.php" method="get">
                    <div class="form-input">
                        <input type="search" name="query" placeholder="Search users..." required hidden>
                        <button type="submit" class="search-btn" style="display: none;"><i class='bx bx-search'></i></button>
                    </div>
                </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
				<img src="<?php echo isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'; ?>" alt="Profile Image">
				<div>
					<p><?php echo $_SESSION['firstName']; ?></p>
					<small><?php echo $_SESSION['userType']; ?></small>
				</div>
			</a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Feedback & Suggestions</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./feedback.php">Feedback & Suggestions</a></li>
                    </ul>
                </div>
                <a href="./studentDash.php" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
					<span class="text">Back</span>
				</a>
            </div>

            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
            <?php elseif (isset($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <div class="container">
                <!-- <h1>Feedback and Suggestions</h1> -->
                <div class="feedback-container">
                    <!-- Feedback Form (Left) -->
                    <div class="feedback-form">
                        <h4>Submit Your Feedback</h4>
                        <hr class="hr">
                        <form action="feedback.php" method="POST">
                            <div class="form-group">
                                <label for="feedback">Your Feedback:</label>
                                <textarea id="feedback" name="feedback" rows="4" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="rating">Rating:</label>
                                <select id="rating" name="rating" required>
                                    <option value="">- Select Rating -</option>
                                    <option value="1">1 Star ⭐</option>
                                    <option value="2">2 Star ⭐⭐</option>
                                    <option value="3">3 Star ⭐⭐⭐</option>
                                    <option value="4">4 Star ⭐⭐⭐⭐</option>
                                    <option value="5">5 Star ⭐⭐⭐⭐⭐</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="user_name">Display Name (optional):</label>
                                <input type="text" id="user_name" name="user_name" 
                                    placeholder="Leave blank to use your real name" maxlength="50">
                                <small class="small">If left blank, your real name (<?php echo htmlspecialchars($sessionName); ?>) will be shown</small>
                            </div>
                            <button type="submit">Submit Feedback</button>
                        </form>
                    </div>

                    <!-- Rating Distribution (Right) -->
                    <div class="rating-distribution">
                        <h2>Rating Distribution</h2>
                        <hr class="hr">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="progress-label">
                                <strong><?php echo $i . ($i === 1 ? ' Star' : ' Stars'); ?>:</strong> 
                                <span><?php echo round($ratingPercentages[$i], 2); ?>%</span>
                            </div>
                            <div class="rating-bar">
                                <div style="width: <?php echo $ratingPercentages[$i]; ?>%;"></div>
                            </div>
                        <?php endfor; ?>
                        <div class="average-rating"><?php echo round($averageRating, 1); ?></div>
                        <p class="total-feedback">(Total of <?php echo $totalRatings; ?> Feedbacks)</p>
                    </div>
                </div>

                <!-- Recent Feedbacks (Below) -->
                <div class="recent-feedbacks">
                    <h2>Recent Feedbacks</h2>
                    <hr class="hr">
                    <div class="feedback-list">
                        <?php foreach ($recentFeedbacks as $feedback): ?>
                            <div class="feedback-item">
                                <div class="user-info">
                                    <div class="user-image">
                                        <?php 
                                        $displayName = !empty($feedback['user_name']) ? $feedback['user_name'] : 
                                            $feedback['firstName'] . ' ' . $feedback['lastName'];
                                        echo strtoupper($displayName[0]); 
                                        ?>
                                    </div>
                                    <strong><?php echo htmlspecialchars($displayName); ?></strong>
                                </div>
                                <p><?php echo htmlspecialchars($feedback['feedback']); ?></p>
                                <p><strong>Rating:</strong> 
                                    <?php 
                                    // Display star icons based on rating
                                    $rating = (int)$feedback['rating'];
                                    for ($i = 0; $i < $rating; $i++) {
                                        echo '<span class="star">⭐</span>';
                                    }
                                    ?>
                                </p>
                                <p><small><?php echo date("F j, Y, g:i a", strtotime($feedback['created_at'])); ?></small></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->
    
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="./utils/script.js"></script>
</body>
</html>