<?php
require_once '../../config/db_connection.php';

$cookie_name = 'last_activity_time';

$userID = isset($_SESSION['userID']) ? filter_var($_SESSION['userID'], FILTER_VALIDATE_INT) : null;
$timeout_duration = 300; 

if ($userID) {
    try {
        $query = "SELECT timeout_duration FROM user_timeout_settings WHERE userID = :userID";
        $stmt = $dbConnection->prepare($query);
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['timeout_duration'])) {
            $timeout_duration = (int)$result['timeout_duration'];
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    }
}

if (isset($_COOKIE[$cookie_name])) {
    $elapsed_time = time() - $_COOKIE[$cookie_name];

    if ($elapsed_time >= $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: logout.php");
        exit();
    }
}

setcookie($cookie_name, time(), time() + $timeout_duration, "/");
?>

<script>
    let duration = <?php echo $timeout_duration; ?>;

    function startTimer() {
        const timerElement = document.getElementById("timer");
        let countdown = setInterval(() => {
            duration--;

            if (duration < 0) {
                clearInterval(countdown);
                window.location = "logout.php";
            } else if (timerElement) {
                let hours = Math.floor(duration / 3600);
                let minutes = Math.floor((duration % 3600) / 60);
                let seconds = duration % 60;
                seconds = seconds < 10 ? "0" + seconds : seconds;
                minutes = minutes < 10 && hours > 0 ? "0" + minutes : minutes;
                timerElement.innerText = (hours > 0 ? hours + ":" : "") + minutes + ":" + seconds;
            }
        }, 1000);

        document.addEventListener("mousemove", resetTimer);
        document.addEventListener("keydown", resetTimer);

        function resetTimer() {
            duration = <?php echo $timeout_duration; ?>;
        }
    }

    window.onload = startTimer;
</script>