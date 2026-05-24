<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';

date_default_timezone_set('Asia/Manila');

function formatPst(string $datetime, string $format = 'F d, Y - h:i A'): string {
    $dt = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    return $dt->format($format);
}

if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php"); exit;
}
$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false) {
    session_destroy(); header("Location: ../../index.php"); exit;
}

if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section ID.";
    header("Location: professorDash.php"); exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];

try {
    $checkStmt = $dbConnection->prepare("
        SELECT ts.sectionID, s.sectionName, s.sectionCode, sub.subjectName, sub.subjectCode, u.firstName, u.lastName
        FROM teacher_section ts
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        JOIN users u ON ts.teacherID = u.userID
        WHERE ts.teacherSectionID = :teacherSectionID AND ts.teacherID = :userID AND ts.archived = 0
        AND u.archived = 0 AND s.archived = 0 AND sub.archived = 0
    ");
    $checkStmt->execute([':teacherSectionID' => $teacherSectionID, ':userID' => $userID]);
    $section = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        $_SESSION['error_message'] = "Invalid section assignment.";
        header("Location: professorDash.php"); exit;
    }
    $professorName = $section['firstName'] . ' ' . $section['lastName'];
    $subjectName = $section['subjectName'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['createQuiz'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;
        $quizType = $_POST['quizType'] ?? '';
        $dueDateInput = trim($_POST['dueDate'] ?? '');
        $releaseDateInput = trim($_POST['releaseDate'] ?? '');
        $questions = $_POST['questions'] ?? [];

        if (empty($title) || !in_array($quizType, ['Multiple Choice', 'True/False', 'Essay'])) {
            $_SESSION['error_message'] = "Invalid title or quiz type.";
            header("Location: create_quiz.php?teacherSectionID=$teacherSectionID"); exit;
        }
        if (empty($dueDateInput) || empty($releaseDateInput)) {
            $_SESSION['error_message'] = "Both dates are required.";
            header("Location: create_quiz.php?teacherSectionID=$teacherSectionID"); exit;
        }
        if (empty($questions)) {
            $_SESSION['error_message'] = "Add at least one question.";
            header("Location: create_quiz.php?teacherSectionID=$teacherSectionID"); exit;
        }

        $dueDatePst = new DateTime($dueDateInput . ':00', new DateTimeZone('Asia/Manila'));
        $releaseDatePst = new DateTime($releaseDateInput . ':00', new DateTimeZone('Asia/Manila'));
        $nowPst = new DateTime('now', new DateTimeZone('Asia/Manila'));

        if ($releaseDatePst >= $dueDatePst) {
            $_SESSION['error_message'] = "Release date must be before due date.";
            header("Location: create_quiz.php?teacherSectionID=$teacherSectionID"); exit;
        }
        if ($dueDatePst <= $nowPst) {
            $_SESSION['error_message'] = "Due date must be in the future.";
            header("Location: create_quiz.php?teacherSectionID=$teacherSectionID"); exit;
        }

        $dueDateForDB = $dueDateInput . ':00';
        $releaseDateForDB = $releaseDateInput . ':00';

        $quizStmt = $dbConnection->prepare("
            INSERT INTO quizzes (teacherSectionID, title, description, quizType, dueDate, releaseDate, createdDate)
            VALUES (:teacherSectionID, :title, :description, :quizType, :dueDate, :releaseDate, NOW())
        ");
        $quizStmt->execute([
            ':teacherSectionID' => $teacherSectionID,
            ':title' => $title,
            ':description' => $description,
            ':quizType' => $quizType,
            ':dueDate' => $dueDateForDB,
            ':releaseDate' => $releaseDateForDB
        ]);
        $quizID = $dbConnection->lastInsertId();

        $qStmt = $dbConnection->prepare("
            INSERT INTO questions (quizID, questionText, option1, option2, option3, option4, correctOption, points)
            VALUES (:quizID, :questionText, :option1, :option2, :option3, :option4, :correctOption, :points)
        ");
        $allGood = true;
        foreach ($questions as $q) {
            $text = trim($q['text'] ?? '');
            $points = max(1, (int)($q['points'] ?? 1));
            if (empty($text)) { $allGood = false; continue; }

            $o1 = $o2 = $o3 = $o4 = $correct = null;
            if ($quizType !== 'Essay') {
                $o1 = trim($q['option1'] ?? '');
                $o2 = trim($q['option2'] ?? '');
                $correct = isset($q['correctOption']) ? (int)$q['correctOption'] : null;

                if ($quizType === 'Multiple Choice') {
                    $o3 = trim($q['option3'] ?? '');
                    $o4 = trim($q['option4'] ?? '');
                    if (empty($o1) || empty($o2) || empty($o3) || empty($o4) || !in_array($correct, [1,2,3,4])) {
                        $allGood = false; continue;
                    }
                } elseif ($quizType === 'True/False') {
                    if (!in_array($correct, [1,2])) { $allGood = false; continue; }
                }
            }

            $qStmt->execute([
                ':quizID' => $quizID,
                ':questionText' => $text,
                ':option1' => $o1,
                ':option2' => $o2,
                ':option3' => $o3,
                ':option4' => $o4,
                ':correctOption' => $correct,
                ':points' => $points
            ]);
        }

        if (!$allGood) {
            $_SESSION['error_message'] = "Quiz created, but some questions failed.";
            header("Location: create_quiz.php?teacherSectionID=$teacherSectionID"); exit;
        }

        $studentStmt = $dbConnection->prepare("
            SELECT u.email FROM student_section ss
            JOIN users u ON ss.userID = u.userID
            WHERE ss.sectionID = (SELECT sectionID FROM teacher_section WHERE teacherSectionID = :tsid)
            AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
        ");
        $studentStmt->execute([':tsid' => $teacherSectionID]);
        $emails = array_column($studentStmt->fetchAll(PDO::FETCH_ASSOC), 'email');

        $webhookUrl = 'https://script.google.com/macros/s/AKfycbwBpoaGoZe2ytsaiHoI9312MtTLVJyi0PqVMW7WstpYv8mJCobJioyszF57pZBXjyp2/exec';
        $payload = [
            'quizID' => $quizID,
            'teacherSectionID' => $teacherSectionID,
            'title' => $title,
            'description' => $description,
            'quizType' => $quizType,
            'dueDate' => $dueDateInput,
            'releaseDate' => $releaseDateInput,
            'dueDatePretty' => formatPst($dueDateInput . ':00'),
            'releaseDatePretty' => formatPst($releaseDateInput . ':00'),
            'numQuestions' => count($questions),
            'createdDate' => date('Y-m-d H:i:s'),
            'studentEmails' => $emails,
            'professorName' => $professorName,
            'subjectName' => $subjectName
        ];
        $context = stream_context_create([
            'http' => ['method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => json_encode($payload)]
        ]);
        @file_get_contents($webhookUrl, false, $context);

        $_SESSION['success_message'] = "Quiz created and notifications sent successfully.";
        header("Location: create_quiz.php?teacherSectionID=$teacherSectionID"); exit;
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Server error. Try again.";
    header("Location: create_quiz.php?teacherSectionID=$teacherSectionID"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/semi-dash.css">
    <link rel="stylesheet" href="./utils/subjects_sidebar.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <link rel="stylesheet" href="./css/createQuiz.css">
    <script src="./logout.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <title>Learnify - Create Quiz</title>
    <style>
        #description {
            width: 100%;
            min-height: 130px;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-family: inherit;
            font-size: 1rem;
            line-height: 1.6;
            resize: vertical;
            background: #fafbff;
            transition: all 0.3s ease;
        }
        #description:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            background: white;
        }
        #description::placeholder {
            color: #94a3b8;
            font-style: italic;
        }

        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
            font-size: 1.05rem;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        .summary-table td {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
        }
        .summary-table tr:first-child td {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .summary-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .correct-toggle {
            cursor: pointer;
            user-select: none;
            margin-top: 8px;
        }
        .correct-toggle input[type="checkbox"] {
            display: none;
        }
        .correct-toggle span {
            display: inline-block;
            padding: 8px 16px;
            background: #e2e8f0;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .correct-toggle.selected span {
            background: #10b981;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }
        .type-card h4 {
            font-size: clamp(1rem, 3vw, 1.7rem) !important;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        .type-card p {
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 0;
            font-size: clamp(1rem, 3vw, 1.4rem);
        }
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li><a href="./professor_main_dash.php"><i class='bx bxs-dashboard'></i><span class="text">Dashboard</span></a></li>
            <li><a href="./modules.php"><i class='bx bxs-bookmark'></i><span class="text">Modules</span></a></li>
            <li><a href="./calendar.php"><i class='bx bxs-calendar'></i><span class="text">Calendar</span></a></li>
            <li><a href="./message_admin.php"><i class='bx bxs-message'></i><span class="text">Message Admin</span></a></li>
            <li><a href="./game_controller.php"><i class='bx bxs-game'></i><span class="text">Game</span></a></li>
        </ul>
        <ul class="side-menu">
            <li><a href="./settings.php"><i class='bx bxs-cog'></i><span class="text">Settings</span></a></li>
            <li><a href="javascript:void(0);" class="logout" onclick="showLogoutModal()"><i class='bx bxs-log-out-circle'></i><span class="text">Logout</span></a></li>
        </ul>
    </section>
    <?php require_once './view/modal.php' ?>
    <section id="content">
        <nav>
            <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users..." required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
                <img src="<?php echo htmlspecialchars($_SESSION['image']); ?>" alt="Profile">
                <div><p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p><small>Teacher</small></div>
            </a>
        </nav>
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Create Quiz</h1>
                    <ul class="breadcrumb">
                        <li><a href="./professorDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./professorDash.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>">Quizzes</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active">Create</a></li>
                    </ul>
                </div>
                <a href="dashQuiz.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Quiz Dashboard
                </a>
            </div>
            <div class="quiz-container">
                <h2><?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h2>
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-message" style="margin: 0 2rem;">
                        <i class='bx bx-check-circle'></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="error-message" style="margin: 0 2rem;">
                        <i class='bx bx-error'></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>
                <div class="create-quiz-button-container">
                    <button type="button" class="btn btn-primary" onclick="showQuizModal()">
                        <i class='bx bx-plus'></i> Click this to create a quiz
                    </button>
                    <p class="create-quiz-description">
                        All dates and times are in <strong>Philippine Standard Time (PST)</strong>.
                    </p>
                </div>
            </div>

            <div id="quizModal" class="modal-overlay">
                <div class="modal-content-fullscreen">
                    <div class="modal-header">
                        <h2 class="modal-title">Create New Quiz</h2>
                        <button class="modal-close" onclick="closeQuizModal()">x</button>
                    </div>
                    <div class="modal-body">
                        <div class="quiz-header">
                            <div class="quiz-title-section">
                                <div class="quiz-icon"><i class='bx bx-edit-alt'></i></div>
                                <input type="text" class="quiz-title-input" id="dynamicTitle" placeholder="Untitled Quiz" maxlength="255" required>
                            </div>
                        </div>
                        <form id="quizForm" method="POST">
                            <div class="step-content active" id="step1">
                                <div class="form-section">
                                    <div class="section-header">
                                        <div class="section-icon" style="background: var(--gradient);"><i class='bx bx-list-check'></i></div>
                                        <h3 class="section-title">Choose Quiz Type</h3>
                                    </div>
                                    <div class="quiz-type-grid">
                                        <div class="type-card" data-type="Multiple Choice" onclick="selectQuizType(this, 'Multiple Choice')">
                                            <div class="type-card-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);"><i class='bx bxs-select-multiple'></i></div>
                                            <h4>Multiple Choice</h4>
                                            <p>Students select one correct answer from four options.</p>
                                        </div>
                                        <div class="type-card" data-type="True/False" onclick="selectQuizType(this, 'True/False')">
                                            <div class="type-card-icon" style="background: linear-gradient(135deg, #10B981, #059669);"><i class='bx bx-toggle-left'></i></div>
                                            <h4>True or False</h4>
                                            <p>Students choose between True or False.</p>
                                        </div>
                                        <div class="type-card" data-type="Essay" onclick="selectQuizType(this, 'Essay')">
                                            <div class="type-card-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);"><i class='bx bxs-pencil'></i></div>
                                            <h4>Essay</h4>
                                            <p>Students provide written responses.</p>
                                        </div>
                                    </div>
                                    <input type="hidden" name="quizType" id="quizType" required>
                                </div>
                                <div class="navigation">
                                    <div class="nav-right">
                                        <button type="button" class="btn btn-nav btn-primary" onclick="nextStep(2)">Continue</button>
                                    </div>
                                </div>
                            </div>

                            <div class="step-content" id="step2">
                                <div class="form-section">
                                    <div class="section-header">
                                        <div class="section-icon" style="background: var(--gradient);"><i class='bx bx-info-circle'></i></div>
                                        <h3 class="section-title">Quiz Details</h3>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">Instructions for students (Optional)</label>
                                        <textarea id="description" name="description" placeholder="e.g. Read carefully • No cheating • Good luck!"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="dueDate">Due Date <span class="required">*</span></label>
                                        <input id="dueDate" type="datetime-local" name="dueDate" required>
                                    </div>
                                </div>
                                <div class="navigation">
                                    <div class="nav-left">
                                        <button type="button" class="btn btn-secondary btn-nav" onclick="prevStep(1)">Back</button>
                                    </div>
                                    <div class="nav-right">
                                        <button type="button" class="btn btn-nav btn-primary" onclick="validateStep2AndNext(3)">Continue to Questions</button>
                                    </div>
                                </div>
                            </div>

                            <div class="step-content" id="step3">
                                <div class="form-section">
                                    <div class="section-header">
                                        <div class="section-icon" style="background: var(--gradient);"><i class='bx bx-question-mark'></i></div>
                                        <h3 class="section-title">Add Questions</h3>
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="addQuestion()">Add Question</button>
                                    <div class="questions-container" id="questionsContainer"></div>
                                    <div class="pagination-container" id="paginationContainer"></div>
                                </div>
                                <div class="navigation">
                                    <div class="nav-left">
                                        <button type="button" class="btn btn-secondary btn-nav" onclick="prevStep(2)">Back</button>
                                    </div>
                                    <div class="nav-right">
                                        <button type="button" class="btn btn-nav btn-primary" onclick="validateStep3AndNext(4)">Continue to Settings</button>
                                    </div>
                                </div>
                            </div>

                            <div class="step-content" id="step4">
                                <div class="form-section">
                                    <div class="section-header">
                                        <div class="section-icon" style="background: var(--gradient);"><i class='bx bx-cog'></i></div>
                                        <h3 class="section-title">Quiz Settings</h3>
                                    </div>
                                    <p>All times are in <strong>Philippine Standard Time</strong>.</p>
                                    <div class="form-group">
                                        <label>Due Date</label>
                                        <input type="text" id="dueDateDisplay" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="releaseDate">Release Date <span class="required">*</span></label>
                                        <input id="releaseDate" type="datetime-local" name="releaseDate" required>
                                    </div>
                                </div>
                                <div class="navigation">
                                    <div class="nav-left">
                                        <button type="button" class="btn btn-secondary btn-nav" onclick="prevStep(3)">Back</button>
                                    </div>
                                    <div class="nav-right">
                                        <button type="button" class="btn btn-nav btn-primary" onclick="validateStep4AndNext()">Review & Create</button>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="createQuiz" value="1">
                            <input type="hidden" name="title" id="hiddenTitle">
                        </form>

                        <div class="progress-container" id="progressContainer">
                            <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
                            <div class="progress-text">
                                <span id="progressLabel">0 of 0 questions completed</span>
                                <span id="progressPercentage">0%</span>
                            </div>
                        </div>
                        <div class="step-indicator-modern" id="stepIndicator">
                            <div class="step-dots">
                                <div class="step-dot active" data-step="1">1</div>
                                <div class="step-dot inactive" data-step="2">2</div>
                                <div class="step-dot inactive" data-step="3">3</div>
                                <div class="step-dot inactive" data-step="4">4</div>
                            </div>
                            <div class="step-labels">
                                <span class="step-label">Type</span>
                                <span class="step-label">Details</span>
                                <span class="step-label">Questions</span>
                                <span class="step-label">Settings</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="confirmationModal" class="modal-overlay">
                <div class="modal-content-modern">
                    <div class="modal-header">
                        <h2 class="modal-title">Confirm Quiz Creation</h2>
                        <button class="modal-close" onclick="closeConfirmation()">x</button>
                    </div>
                    <div id="summaryContent" style="padding: 24px; max-height: 70vh; overflow-y: auto;"></div>
                    <div class="modal-actions">
                        <button class="btn btn-secondary" onclick="closeConfirmation()">Cancel</button>
                        <button class="btn btn-primary" onclick="submitForm()">Create Quiz</button>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script>
        let currentStep = 1, quizType = '', questions = [], currentQuestionIndex = 0;
        const form = document.getElementById('quizForm');
        const titleInput = document.getElementById('dynamicTitle');
        const hiddenTitle = document.getElementById('hiddenTitle');
        titleInput.addEventListener('input', () => hiddenTitle.value = titleInput.value || 'Untitled Quiz');
        titleInput.value = 'Untitled Quiz'; hiddenTitle.value = 'Untitled Quiz';

        function formatPst(dateStr) {
            const d = new Date(dateStr + ':00');
            return d.toLocaleString('en-PH', {
                month: 'long', day: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit', hour12: true
            }).replace(',', ' -');
        }

        function showQuizModal() { document.getElementById('quizModal').classList.add('show'); }
        function closeQuizModal() { document.getElementById('quizModal').classList.remove('show'); resetForm(); }

        function resetForm() {
            form.reset(); titleInput.value = 'Untitled Quiz'; hiddenTitle.value = 'Untitled Quiz';
            currentStep = 1; quizType = ''; questions = []; currentQuestionIndex = 0;
            document.getElementById('quizType').value = '';
            document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
            document.getElementById('questionsContainer').innerHTML = '';
            document.getElementById('paginationContainer').innerHTML = '';
            updateStepIndicators(); updateProgress();
        }

        function updateStepIndicators() {
            document.querySelectorAll('.step-dot').forEach((dot, i) => {
                dot.className = i < currentStep - 1 ? 'step-dot completed' : i === currentStep - 1 ? 'step-dot active' : 'step-dot inactive';
            });
        }

        function selectQuizType(el, type) {
            document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected'); quizType = type; document.getElementById('quizType').value = type;
        }

        function nextStep(step) {
            if (step === 2 && !quizType) return showToast('Select a quiz type first.', 'error');
            document.querySelectorAll('.step-content').forEach(c => c.classList.remove('active'));
            document.getElementById(`step${step}`).classList.add('active');
            currentStep = step; updateStepIndicators();
            if (step === 4) updateDueDateDisplay();
        }

        function prevStep(step) {
            document.querySelectorAll('.step-content').forEach(c => c.classList.remove('active'));
            document.getElementById(`step${step}`).classList.add('active');
            currentStep = step; updateStepIndicators();
        }

        function updateDueDateDisplay() {
            const due = document.getElementById('dueDate').value;
            document.getElementById('dueDateDisplay').value = due ? formatPst(due) : '';
        }

        function validateStep2AndNext(step) {
            const due = document.getElementById('dueDate');
            if (!titleInput.value.trim()) return showToast('Enter a quiz title.', 'error');
            if (!due.value) return showToast('Set a due date.', 'error');
            if (new Date(due.value) <= new Date()) return showToast('Due date must be in the future.', 'error');
            nextStep(step);
        }

        function validateStep3AndNext(step) {
            if (questions.length === 0) return showToast('Add at least one question.', 'error');
            let valid = true;
            questions.forEach((_, i) => { if (!validateQuestionFields(i)) valid = false; });
            if (valid) nextStep(step); else showToast('Complete all questions.', 'error');
        }

        function validateStep4AndNext() {
            const release = document.getElementById('releaseDate').value;
            const due = document.getElementById('dueDate').value;
            if (!release || !due) return showToast('Both dates required.', 'error');
            if (new Date(release) >= new Date(due)) return showToast('Release must be before due.', 'error');
            showConfirmation();
        }

        function showToast(msg, type = 'error') {
            Toastify({
                text: `<i class='bx bx-error'></i> ${msg}`,
                duration: 3000,
                gravity: "top",
                position: "right",
                style: { background: type === 'error' ? '#FEE2E2' : '#D1FAE5', color: type === 'error' ? '#991B1B' : '#166534' }
            }).showToast();
        }

        function addQuestion() {
            if (questions.length > 0 && !validateQuestionFields(questions.length - 1)) {
                showToast('Complete the current question first.', 'error');
                showQuestion(questions.length - 1);
                return;
            }
            const idx = questions.length;
            const container = document.getElementById('questionsContainer');
            const fieldset = document.createElement('fieldset');
            fieldset.classList.add('question-fieldset');
            fieldset.id = `question-${idx}`;
            let optionsHTML = '';
            if (quizType === 'Multiple Choice') {
                optionsHTML = `
                    <div class="options-grid">
                        <div class="option-item" data-option="1">
                            <span class="option-label">A</span>
                            <input type="text" class="option-input" name="questions[${idx}][option1]" placeholder="Option A" required>
                            <div class="correct-toggle" onclick="toggleCorrectOption(${idx}, 1)">
                                <input type="checkbox" class="correct-checkbox" name="questions[${idx}][correctOption]" value="1">
                                <span>Correct Answer</span>
                            </div>
                        </div>
                        <div class="option-item" data-option="2">
                            <span class="option-label">B</span>
                            <input type="text" class="option-input" name="questions[${idx}][option2]" placeholder="Option B" required>
                            <div class="correct-toggle" onclick="toggleCorrectOption(${idx}, 2)">
                                <input type="checkbox" class="correct-checkbox" name="questions[${idx}][correctOption]" value="2">
                                <span>Correct Answer</span>
                            </div>
                        </div>
                        <div class="option-item" data-option="3">
                            <span class="option-label">C</span>
                            <input type="text" class="option-input" name="questions[${idx}][option3]" placeholder="Option C" required>
                            <div class="correct-toggle" onclick="toggleCorrectOption(${idx}, 3)">
                                <input type="checkbox" class="correct-checkbox" name="questions[${idx}][correctOption]" value="3">
                                <span>Correct Answer</span>
                            </div>
                        </div>
                        <div class="option-item" data-option="4">
                            <span class="option-label">D</span>
                            <input type="text" class="option-input" name="questions[${idx}][option4]" placeholder="Option D" required>
                            <div class="correct-toggle" onclick="toggleCorrectOption(${idx}, 4)">
                                <input type="checkbox" class="correct-checkbox" name="questions[${idx}][correctOption]" value="4">
                                <span>Correct Answer</span>
                            </div>
                        </div>
                    </div>
                `;
            } else if (quizType === 'True/False') {
                optionsHTML = `
                    <div class="true-false-options">
                        <label class="true-false-option" onclick="selectTrueFalse(this, ${idx}, '1')">
                            <input type="radio" class="correct-radio" name="questions[${idx}][correctOption]" value="1" required>
                            <span class="true-false-label">True</span>
                        </label>
                        <label class="true-false-option" onclick="selectTrueFalse(this, ${idx}, '2')">
                            <input type="radio" class="correct-radio" name="questions[${idx}][correctOption]" value="2" required>
                            <span class="true-false-label">False</span>
                        </label>
                    </div>
                    <input type="hidden" name="questions[${idx}][option1]" value="True">
                    <input type="hidden" name="questions[${idx}][option2]" value="False">
                `;
            }
            fieldset.innerHTML = `
                <legend><i class='bx bx-hash'></i> Question ${idx + 1}</legend>
                <div class="question-header">
                    <div class="question-actions">
                        <input type="number" class="points-input" name="questions[${idx}][points]" value="1" min="1" max="10" required>
                        <span>points</span>
                        <button type="button" class="btn btn-danger btn-small" onclick="removeQuestion(${idx})"><i class='bx bx-trash'></i></button>
                    </div>
                </div>
                <div class="question-content">
                    <textarea class="question-text-input" name="questions[${idx}][text]" placeholder="Enter your question here..." required></textarea>
                    ${optionsHTML}
                </div>
            `;
            container.appendChild(fieldset);
            questions.push({ index: idx, completed: false });
            currentQuestionIndex = idx;
            updatePagination(); showQuestion(idx); updateProgress();
        }

        function showQuestion(idx) {
            document.querySelectorAll('.question-fieldset').forEach((fs, i) => fs.classList.toggle('active', i === idx));
            currentQuestionIndex = idx; updatePagination();
        }

        function updatePagination() {
            const cont = document.getElementById('paginationContainer');
            cont.innerHTML = '';
            questions.forEach((q, i) => {
                const box = document.createElement('div');
                box.className = `pagination-box ${i === currentQuestionIndex ? 'active' : ''} ${q.completed ? 'completed' : ''}`;
                box.textContent = i + 1;
                box.onclick = () => showQuestion(i);
                cont.appendChild(box);
            });
        }

        function toggleCorrectOption(qIdx, opt) {
            const fieldset = document.getElementById(`question-${qIdx}`);
            const toggles = fieldset.querySelectorAll('.correct-toggle');
            toggles.forEach(t => t.classList.remove('selected'));
            const target = fieldset.querySelector(`.correct-toggle[onclick="toggleCorrectOption(${qIdx}, ${opt})"]`);
            target.classList.add('selected');
            const checkbox = target.querySelector('input[type="checkbox"]');
            checkbox.checked = true;
            updateQuestionCompletion(qIdx);
        }

        function selectTrueFalse(el, qIdx, val) {
            const fieldset = document.getElementById(`question-${qIdx}`);
            fieldset.querySelectorAll('.true-false-option').forEach(opt => opt.classList.remove('selected'));
            el.classList.add('selected');
            fieldset.querySelector(`input[value="${val}"]`).checked = true;
            updateQuestionCompletion(qIdx);
        }

        function validateQuestionFields(idx) {
            const fieldset = document.getElementById(`question-${idx}`);
            const text = fieldset.querySelector('textarea').value.trim();
            const points = fieldset.querySelector('.points-input').value;
            let valid = text && points >= 1;
            if (quizType === 'Multiple Choice') {
                const opts = fieldset.querySelectorAll('.option-input');
                const correct = fieldset.querySelector('input[name*="correctOption"]:checked');
                valid = valid && Array.from(opts).every(o => o.value.trim()) && correct;
            } else if (quizType === 'True/False') {
                valid = valid && fieldset.querySelector('input[name*="correctOption"]:checked');
            }
            questions[idx].completed = valid;
            updateProgress(); updatePagination();
            return valid;
        }

        function updateQuestionCompletion(idx) { validateQuestionFields(idx); }

        function removeQuestion(idx) {
            document.getElementById(`question-${idx}`).remove();
            questions = questions.filter(q => q.index !== idx);
            questions.forEach((q, i) => q.index = i);
            if (currentQuestionIndex >= questions.length && questions.length > 0) currentQuestionIndex = questions.length - 1;
            updatePagination(); showQuestion(currentQuestionIndex); updateProgress();
        }

        function updateProgress() {
            const total = questions.length;
            const done = questions.filter(q => q.completed).length;
            const perc = total ? (done / total) * 100 : 0;
            document.getElementById('progressFill').style.width = perc + '%';
            document.getElementById('progressLabel').textContent = `${done} of ${total} questions completed`;
            document.getElementById('progressPercentage').textContent = Math.round(perc) + '%';
        }

        function showConfirmation() {
            const title = titleInput.value || 'Untitled Quiz';
            const desc = document.getElementById('description').value || 'No instructions';
            const due = document.getElementById('dueDate').value;
            const release = document.getElementById('releaseDate').value;
            let html = `<table class="summary-table">
                <tr><td>Quiz Title</td><td>${title}</td></tr>
                <tr><td>Instructions</td><td>${desc.replace(/\n/g, '<br>')}</td></tr>
                <tr><td>Type</td><td>${quizType}</td></tr>
                <tr><td>Questions</td><td>${questions.length}</td></tr>
                <tr><td>Due Date</td><td>${due ? formatPst(due) : 'Not set'}</td></tr>
                <tr><td>Release Date</td><td>${release ? formatPst(release) : 'Not set'}</td></tr>
            </table>`;
            document.getElementById('summaryContent').innerHTML = html;
            document.getElementById('confirmationModal').classList.add('show');
        }

        function closeConfirmation() { document.getElementById('confirmationModal').classList.remove('show'); }
        function submitForm() { form.submit(); }

        document.addEventListener('DOMContentLoaded', () => {
            updateStepIndicators();
            document.getElementById('dueDate').addEventListener('change', updateDueDateDisplay);
        });
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>