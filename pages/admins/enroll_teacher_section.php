<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

if (isset($_POST['enrollTeacher'])) {
    $teacherID = $_POST['teacherID'] ?? '';
    $sectionID = $_POST['sectionID'] ?? '';
    $subjectID = $_POST['subjectID'] ?? '';
    $timeSlot = $_POST['timeSlot'] ?? '';
    $day = $_POST['day'] ?? '';
    $advisory = $_POST['advisory'] ?? 0;
    // Define time slots
    $timeSlots = [
        '6:00 AM - 8:00 AM' => ['start' => '06:00:00', 'end' => '08:00:00'],
        '8:00 AM - 10:00 AM' => ['start' => '08:00:00', 'end' => '10:00:00'],
        '10:00 AM - 12:00 PM' => ['start' => '10:00:00', 'end' => '12:00:00'],
        '1:00 PM - 3:00 PM' => ['start' => '13:00:00', 'end' => '15:00:00'],
        '3:00 PM - 5:00 PM' => ['start' => '15:00:00', 'end' => '17:00:00'],
        '5:00 PM - 7:00 PM' => ['start' => '17:00:00', 'end' => '19:00:00']
    ];
    if (empty($teacherID) || empty($sectionID) || empty($subjectID) || empty($timeSlot) || empty($day)) {
        $_SESSION['error_message'] = "Please fill all required fields.";
    } else {
        try {
            // Verify teacher exists and is a Professor
            $checkTeacher = "SELECT * FROM users WHERE userID = ? AND userType = 'Professor' AND archived = 0";
            $stmt = $dbConnection->prepare($checkTeacher);
            $stmt->execute([$teacherID]);
            if ($stmt->rowCount() == 0) {
                $_SESSION['error_message'] = "Selected user is not a valid teacher.";
            } else {
                // Check for schedule conflict
                $startTime = $timeSlots[$timeSlot]['start'];
                $endTime = $timeSlots[$timeSlot]['end'];
               
                $checkConflict = "SELECT * FROM teacher_section
                    WHERE teacherID = ? AND day = ?
                    AND (
                        (startTime < ? AND endTime > ?)
                        OR (startTime < ? AND endTime > ?)
                        OR (startTime >= ? AND endTime <= ?)
                    )
                    AND archived = 0";
                $stmt = $dbConnection->prepare($checkConflict);
                $stmt->execute([
                    $teacherID, $day,
                    $endTime, $startTime,
                    $startTime, $endTime,
                    $startTime, $endTime
                ]);
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error_message'] = "Teacher already has a schedule at this time and day.";
                } else {
                    // Check for section schedule conflict
                    $checkSectionConflict = "SELECT * FROM teacher_section
                        WHERE sectionID = ? AND day = ?
                        AND (
                            (startTime < ? AND endTime > ?)
                            OR (startTime < ? AND endTime > ?)
                            OR (startTime >= ? AND endTime <= ?)
                        )
                        AND archived = 0";
                    $stmt = $dbConnection->prepare($checkSectionConflict);
                    $stmt->execute([
                        $sectionID, $day,
                        $endTime, $startTime,
                        $startTime, $endTime,
                        $startTime, $endTime
                    ]);
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['error_message'] = "Section already has a schedule at this time and day.";
                    } else {
                        // Check if subject is already assigned to this section by another teacher
                        $checkSubjectAssignment = "SELECT teacherID FROM teacher_section WHERE sectionID = ? AND subjectID = ? AND archived = 0 LIMIT 1";
                        $stmt = $dbConnection->prepare($checkSubjectAssignment);
                        $stmt->execute([$sectionID, $subjectID]);
                        if ($stmt->rowCount() > 0) {
                            $assignedTeacher = $stmt->fetchColumn();
                            if ($assignedTeacher != $teacherID) {
                                $_SESSION['error_message'] = "This subject is already assigned to another teacher in this section.";
                            }
                        }
                        if (!isset($_SESSION['error_message'])) {
                            $checkSql = "SELECT * FROM teacher_section WHERE teacherID = ? AND sectionID = ? AND subjectID = ? AND day = ? AND archived = 0";
                            $stmt = $dbConnection->prepare($checkSql);
                            $stmt->execute([$teacherID, $sectionID, $subjectID, $day]);
                           
                            if ($stmt->rowCount() > 0) {
                                $_SESSION['error_message'] = "Teacher is already assigned to this section, subject, and day.";
                            } else {
                                $token = bin2hex(random_bytes(16));
                                $sql = "INSERT INTO teacher_section (teacherID, sectionID, subjectID, startTime, endTime, day, assignmentDate, token, advisory)
                                        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
                                $stmt = $dbConnection->prepare($sql);
                                $success = $stmt->execute([$teacherID, $sectionID, $subjectID, $startTime, $endTime, $day, $token, $advisory]);
                                if ($success) {
                                    $_SESSION['success_message'] = "Teacher assigned schedule successfully.";
                                } else {
                                    $_SESSION['error_message'] = "Error assigning teacher schedule.";
                                }
                            }
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: enroll_teacher_section.php");
    exit;
}
// Fetch teachers, sections, and subjects
$teacherSQL = "SELECT userID, firstName, lastName FROM users WHERE userType = 'Professor' AND archived = 0 ORDER BY firstName, lastName";
$teacherStmt = $dbConnection->prepare($teacherSQL);
$teacherStmt->execute();
$teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);
$sectionSQL = "SELECT sectionID, sectionName, sectionCode FROM sections WHERE archived = 0 ORDER BY sectionName";
$sectionStmt = $dbConnection->prepare($sectionSQL);
$sectionStmt->execute();
$sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
$subjectSQL = "SELECT subjectID, subjectName, subjectCode FROM subjects WHERE archived = 0 ORDER BY subjectName";
$subjectStmt = $dbConnection->prepare($subjectSQL);
$subjectStmt->execute();
$subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/track_strand.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Enroll Teacher to Section</title>
    <style>
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --grey: #f3f4f6;
            --dark-grey: #9ca3af;
            --dark: #1f2937;
            --green: #10b981;
            --red: #ef4444;
        }
        .container {
            margin: 20px;
        }
        .schedule-container {
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        .schedule-container h2 {
            margin-bottom: 20px;
            color: var(--dark);
            font-size: clamp(1.9rem, 3vw, 2rem);
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: bold;
            color: var(--dark);
        }
        .form-group select, .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            background-color: var(--grey);
            border: 1px solid var(--dark-grey);
            color: var(--dark);
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        .form-group select:focus, .form-group input:focus {
            border-color: var(--blue);
            outline: none;
        }
        .form-group select option[disabled] {
            color: #999;
            text-decoration: line-through;
        }
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .form-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 48%;
        }
        .form-actions .confirm-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }
        .form-actions .confirm-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }
        .form-actions .cancel-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        .form-actions .cancel-btn:hover {
            background: linear-gradient(135deg, #c82333, #b91c1c);
        }
        .success-notification, .error-notification {
            padding: 10px;
            margin: 20px auto;
            width: 100%;
            max-width: 600px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border-radius: 5px;
            color: white;
            text-align: center;
        }
        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
        }
        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        .profile-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 10px;
            background: linear-gradient(135deg, var(--light), #007bff);
            border-radius: 8px;
        }
        .profile-section img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        .profile-section p {
            margin: 0;
            font-size: clamp(1.4rem, 3vw, 1.5rem);
            text-transform: uppercase;
            font-weight: bold;
            color: var(--dark);
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: clamp(1rem, 3vw, 1.2rem);
            min-width: 500px;
        }
        .schedule-table th, .schedule-table td {
            padding: 10px;
            text-align: center;
            vertical-align: middle;
            border: 2px solid #007bff;
        }
        .schedule-table th {
            background: #007bff;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
        }
        .schedule-table td {
            color: var(--dark);
        }
        .schedule-table td.break {
            background-color: #007bff;
            font-weight: 600;
            color: white;
        }
        .schedule-table td.empty {
            cursor: pointer;
            background: var(--grey);
            transition: background 0.3s ease;
        }
        .schedule-table td.empty:hover {
           background: linear-gradient(135deg, var(--light), var(--green));
        }
        /* .schedule-table tr:nth-child(even) {
          background: linear-gradient(135deg, #e6f0ff, var(--green));
        } */
        /* .schedule-table tr:hover {
            background: linear-gradient(135deg, #e6f0ff, var(--green));
        } */
       
        .modal-content h2 {
            margin-bottom: 20px;
            color: var(--dark);
            font-size: clamp(1.9rem, 3vw, 2rem);
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            text-align: center;
        }
        .schedule-section-name {
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--dark-grey);
        }
        .advisory-yes {
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--green);
            font-weight: 600;
        }
        .action-buttons {
            margin-top: 5px;
        }
        .action-buttons a {
            margin: 0 5px;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: clamp(1rem, 3vw, 1rem);
            transition: background-color 0.3s ease;
        }
        .action-buttons .edit-btn {
            background: var(--blue);
            color: white;
        }
        .action-buttons .edit-btn:hover {
            background: #2a6db0;
        }
        .action-buttons .archive-btn {
            background: var(--red);
            color: white;
        }
        .action-buttons .archive-btn:hover {
            background: #c82333;
        }
        #archiveDetailsTable td {
            padding: 8px;
            font-size: clamp(0.9rem, 2vw, 1rem);
        }
        #archiveDetailsTable th {
            background: var(--blue);
            color: white;
            padding: 8px;
            font-size: clamp(0.9rem, 2vw, 1rem);
        }
        @media screen and (max-width: 768px) {
            .container {
                margin: 10px;
            }
            .schedule-container {
                padding: 10px;
            }
            .profile-section img {
                width: 50px;
                height: 50px;
            }
            .profile-section p {
                font-size: 1rem;
            }
            .schedule-table {
                font-size: 0.75rem;
            }
            .schedule-table th, .schedule-table td {
                padding: 8px;
            }
            .modal-content {
                width: 95%;
                padding: 15px;
            }
        }
        @media screen and (max-width: 480px) {
            .schedule-table {
                font-size: 0.7rem;
            }
            .schedule-table th, .schedule-table td {
                padding: 6px;
            }
            .profile-section img {
                width: 40px;
                height: 40px;
            }
            .profile-section p {
                font-size: 0.9rem;
            }
            .modal-content {
                width: 98%;
                padding: 10px;
            }
            .modal-content h2 {
                font-size: 1.5rem;
            }
            .form-group label {
                font-size: 1rem;
            }
            .form-group select, .form-group input {
                font-size: 1rem;
            }
            .form-actions button {
                font-size: 1rem;
            }
            #archiveDetailsTable td,
            #archiveDetailsTable th {
                padding: 6px;
                font-size: clamp(0.7rem, 2vw, 0.8rem);
            }
        }
        .form-group label span {
            color: #c82333;
        }
        #scheduleContainer {
            color: #c82333;
            font-size: clamp(1rem, 3vw, 1.4rem);
        }
        #editModal .form-group label,
        #enrollModal .form-group label {
            text-align: left;
        }
        #editTeacherName,
        #modalTeacherName {
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--dark);
            background: linear-gradient(135deg, var(--light), #007bff);
            padding: 10px;
        }
        #archiveModal .modal-content h2 {
            text-align: left;
            font-size: clamp(1.5rem, 3vw, 2rem) !important;
        }
        #archiveModal .modal-content p {
            text-align: left;
            font-size: clamp(1.2rem, 3vw, 1.8rem) !important;
        }
        #archiveModal .form-group h3 {
            font-size: clamp(1.2rem, 3vw, 1.8rem) !important;
        }
        .no-schedules-msg {
            text-align: center;
            color: var(--dark-grey);
            font-style: italic;
            margin-bottom: 20px;
            font-size: clamp(1rem, 3vw, 1.1rem);
        }
    </style>
    <script>
        const timeSlots = [
            { label: '6:00 AM - 8:00 AM', start: '06:00:00', end: '08:00:00' },
            { label: '8:00 AM - 10:00 AM', start: '08:00:00', end: '10:00:00' },
            { label: '10:00 AM - 12:00 PM', start: '10:00:00', end: '12:00:00' },
            { label: '12:00 PM - 1:00 PM', break: true },
            { label: '1:00 PM - 3:00 PM', start: '13:00:00', end: '15:00:00' },
            { label: '3:00 PM - 5:00 PM', start: '15:00:00', end: '17:00:00' },
            { label: '5:00 PM - 7:00 PM', start: '17:00:00', end: '19:00:00' }
        ];
        function fetchTeacherSchedules() {
            const teacherID = document.getElementById('teacherID').value;
            const scheduleContainer = document.getElementById('scheduleContainer');
           
            if (!teacherID) {
                scheduleContainer.innerHTML = '<p class="select-teacher">Please select a teacher to view their schedule.</p>';
                return;
            }
            fetch('fetch_teacher_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'teacherID=' + encodeURIComponent(teacherID)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    scheduleContainer.innerHTML = '<p class="error-notification">' + data.error + '</p>';
                    return;
                }
                let html = '';
                if (data.profile) {
                    const profileImage = data.profile.image ? data.profile.image : './img/noprofile.png';
                    html += `
                        <div class="profile-section">
                            <img src="${profileImage}" alt="Profile Image">
                            <p>${data.profile.firstName} ${data.profile.lastName}</p>
                        </div>
                    `;
                }
                const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const schedules = data.schedules || [];
                const scheduleData = {};
                timeSlots.forEach(slot => {
                    scheduleData[slot.label] = daysOfWeek.reduce((acc, day) => {
                        acc[day] = { subject: '', sectionName: '', advisory: 0, teacherSectionID: null };
                        return acc;
                    }, {});
                });
                schedules.forEach(schedule => {
                    const slot = timeSlots.find(s => s.start === schedule.startTime && s.end === schedule.endTime);
                    if (slot && daysOfWeek.includes(schedule.day)) {
                        scheduleData[slot.label][schedule.day] = {
                            subject: schedule.subjectName + ' (' + schedule.subjectCode + ')',
                            sectionName: schedule.sectionName + ' (' + schedule.sectionCode + ')',
                            advisory: schedule.advisory,
                            teacherSectionID: schedule.teacherSectionID
                        };
                    }
                });
                if (schedules.length === 0) {
                    html += '<p class="no-schedules-msg">No schedules found for this teacher. Click on an empty cell to assign a schedule.</p>';
                }
                html += `
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                ${daysOfWeek.map(day => `<th>${day}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                `;
                timeSlots.forEach(slot => {
                    html += '<tr>';
                    html += `<td${slot.break ? ' class="break"' : ''}>${slot.label}</td>`;
                    if (slot.break) {
                        html += '<td class="break" colspan="6">BREAK</td>';
                    } else {
                        daysOfWeek.forEach(day => {
                            const data = scheduleData[slot.label][day];
                            if (data.subject) {
                                html += `
                                    <td>
                                        ${data.subject}
                                        <br>
                                        <span class="schedule-section-name">${data.sectionName}</span>
                                        ${data.advisory == 1 ? '<br><span class="advisory-yes">✅ Advisory</span>' : ''}
                                        <div class="action-buttons">
                                            <a href="javascript:void(0)" class="edit-btn" onclick="showEditModal('${data.teacherSectionID}')">Edit</a>
                                            <a href="javascript:void(0)" class="archive-btn" onclick="showArchiveModal('${data.teacherSectionID}')">Archive</a>
                                        </div>
                                    </td>
                                `;
                            } else {
                                html += `<td class="empty" onclick="showEnrollModal('${teacherID}', '${slot.label}', '${day}')"></td>`;
                            }
                        });
                    }
                    html += '</tr>';
                });
                html += '</tbody></table>';
                scheduleContainer.innerHTML = html;
            })
            .catch(error => {
                scheduleContainer.innerHTML = '<p class="error-notification">Error fetching schedules: ' + error.message + '</p>';
            });
        }
        function showEnrollModal(teacherID, timeSlot, day) {
            const modal = document.getElementById('enrollModal');
            const teacherName = document.getElementById('teacherID').options[document.getElementById('teacherID').selectedIndex].text;
            document.getElementById('modalTeacherName').textContent = teacherName;
            document.getElementById('modalTeacherID').value = teacherID;
            document.getElementById('modalDay').value = day;
            document.getElementById('modalTimeSlot').value = timeSlot;
            // Reset section and subject selects
            const sectionSelect = document.getElementById('modalSectionID');
            const subjectSelect = document.getElementById('modalSubjectID');
            sectionSelect.value = '';
            subjectSelect.value = '';
            // Fetch section availability
            fetch('fetch_teacher_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'teacherID=' + encodeURIComponent(teacherID) + '&day=' + encodeURIComponent(day) + '&timeSlot=' + encodeURIComponent(timeSlot)
            })
            .then(response => response.json())
            .then(data => {
                if (!data.error && data.sectionTimeSlots) {
                    const takenSectionIDs = data.sectionTimeSlots.map(schedule => schedule.sectionID);
                    Array.from(sectionSelect.options).forEach(option => {
                        if (option.value && takenSectionIDs.includes(option.value)) {
                            option.disabled = true;
                            option.style.textDecoration = 'line-through';
                        } else {
                            option.disabled = false;
                            option.style.textDecoration = 'none';
                        }
                    });
                }
                if (!data.error && data.sectionSchedules) {
                    const takenSubjectIDs = data.sectionSchedules.map(schedule => schedule.subjectID);
                    Array.from(subjectSelect.options).forEach(option => {
                        if (option.value && takenSubjectIDs.includes(option.value)) {
                            option.disabled = true;
                            option.style.textDecoration = 'line-through';
                        } else {
                            option.disabled = false;
                            option.style.textDecoration = 'none';
                        }
                    });
                }
            })
            .catch(error => console.error('Error fetching availability:', error));
            modal.style.display = 'flex';
        }
        function showEditModal(teacherSectionID) {
            fetch('fetch_teacher_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'teacherSectionID=' + encodeURIComponent(teacherSectionID)
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                const modal = document.getElementById('editModal');
                document.getElementById('editTeacherSectionID').value = teacherSectionID;
                document.getElementById('editTeacherName').textContent = `${data.schedule.firstName} ${data.schedule.lastName}`;
                document.getElementById('editTeacherID').value = data.schedule.teacherID;
                document.getElementById('editSectionID').value = data.schedule.sectionID;
                document.getElementById('editSubjectID').value = data.schedule.subjectID;
                document.getElementById('editDay').value = data.schedule.day;
                document.getElementById('editTimeSlot').value = timeSlots.find(slot => slot.start === data.schedule.startTime && slot.end === data.schedule.endTime).label;
                document.getElementById('editAdvisory').value = data.schedule.advisory;
                modal.style.display = 'flex';
            })
            .catch(error => {
                alert('Error fetching schedule details: ' + error.message);
            });
        }
        function showArchiveModal(teacherSectionID) {
            const modal = document.getElementById('archiveModal');
            const detailsTable = document.getElementById('archiveDetailsTable');
            
            // Store teacherSectionID for form submission
            document.getElementById('archiveTeacherSectionID') || 
                document.body.appendChild(
                    Object.assign(document.createElement('input'), {
                        type: 'hidden',
                        id: 'archiveTeacherSectionID',
                        name: 'teacherSectionID'
                    })
                );
            document.getElementById('archiveTeacherSectionID').value = teacherSectionID;

            // Fetch schedule details
            fetch('fetch_teacher_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'teacherSectionID=' + encodeURIComponent(teacherSectionID)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                const schedule = data.schedule;
                const advisoryText = schedule.advisory == 1 ? 'Yes ✅' : 'No';
                detailsTable.innerHTML = `
                    <tr>
                        <td>${schedule.firstName} ${schedule.lastName}</td>
                        <td>${schedule.sectionName} (${schedule.sectionCode})</td>
                        <td>${schedule.subjectName} (${schedule.subjectCode})</td>
                        <td>${schedule.day}</td>
                        <td>${timeSlots.find(slot => slot.start === schedule.startTime && slot.end === schedule.endTime).label}</td>
                        <td>${advisoryText}</td>
                    </tr>
                `;
                modal.style.display = 'flex';
            })
            .catch(error => {
                alert('Error fetching schedule details: ' + error.message);
            });
        }
        function closeEnrollModal() {
            document.getElementById('enrollModal').style.display = 'none';
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        function closeArchiveModal() {
            document.getElementById('archiveModal').style.display = 'none';
        }
        function submitEnrollForm() {
            const form = document.getElementById('enrollTeacherForm');
            const teacherID = document.getElementById('modalTeacherID').value;
            const sectionID = document.getElementById('modalSectionID').value;
            const subjectID = document.getElementById('modalSubjectID').value;
            const day = document.getElementById('modalDay').value;
            const timeSlot = document.getElementById('modalTimeSlot').value;
            const advisory = document.getElementById('modalAdvisory').value;
            if (!teacherID || !sectionID || !subjectID || !day || !timeSlot) {
                alert('Please fill all required fields.');
                return;
            }
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = 'enroll_teacher_section.php';
           
            const fields = { teacherID, sectionID, subjectID, day, timeSlot, advisory, enrollTeacher: true };
            for (const [key, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                tempForm.appendChild(input);
            }
            document.body.appendChild(tempForm);
            tempForm.submit();
        }
        function submitEditForm() {
            const form = document.getElementById('editTeacherForm');
            const teacherSectionID = document.getElementById('editTeacherSectionID').value;
            const teacherID = document.getElementById('editTeacherID').value;
            const sectionID = document.getElementById('editSectionID').value;
            const subjectID = document.getElementById('editSubjectID').value;
            const day = document.getElementById('editDay').value;
            const timeSlot = document.getElementById('editTimeSlot').value;
            const advisory = document.getElementById('editAdvisory').value;
            if (!teacherSectionID || !teacherID || !sectionID || !subjectID || !day || !timeSlot) {
                alert('Please fill all required fields.');
                return;
            }
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = 'manage_teacher_schedule.php';
           
            const fields = { teacherSectionID, teacherID, sectionID, subjectID, day, timeSlot, advisory, editTeacher: true };
            for (const [key, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                tempForm.appendChild(input);
            }
            document.body.appendChild(tempForm);
            tempForm.submit();
            setTimeout(() => fetchTeacherSchedules(), 500); // Refresh table after edit
        }
        function submitArchiveForm() {
            const teacherSectionID = document.getElementById('archiveTeacherSectionID').value;
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = 'manage_teacher_schedule.php';
            
            const fields = {
                teacherSectionID: teacherSectionID,
                archiveTeacher: 'true'
            };
            for (const [key, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                tempForm.appendChild(input);
            }
            document.body.appendChild(tempForm);
            tempForm.submit();
            closeArchiveModal();
            setTimeout(() => fetchTeacherSchedules(), 500); // Refresh table after archive
        }
        window.addEventListener('click', function (event) {
            const enrollModal = document.getElementById('enrollModal');
            const editModal = document.getElementById('editModal');
            const archiveModal = document.getElementById('archiveModal');
            if (event.target === enrollModal) {
                closeEnrollModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === archiveModal) {
                closeArchiveModal();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeEnrollModal();
                closeEditModal();
                closeArchiveModal();
            }
        });
    </script>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li><a href="./adminDash.php"><i class='bx bxs-dashboard'></i><span class="text">Dashboard</span></a></li>
            <li>
                <a href="./message_professor.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Teachers</span>
                </a>
            </li>
            <li><a href="./registration.php"><i class='bx bx-user-plus'></i><span class="text">Registration</span></a></li>
            <li>
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li><a href="./enroll_student_section.php"><i class='bx bxs-user-check'></i><span class="text">Enroll Student Section</span></a></li>
            <li class="active">
                <a href="./enroll_teacher_section.php">
                    <i class='bx bxs-user-check'></i>
                    <span class="text">Assign Teacher Schedule</span>
                </a>
            </li>
            <li>
                <a href="./game.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Games</span>
                </a>
            </li>
            <li>
                <a href="./admin_calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Academic Calendar</span>
                </a>
            </li>
            <li>
                <a href="./grading.php">
                    <i class='bx bxs-book-content'></i>
                    <span class="text">Student Grades</span>
                </a>
            </li>
        </ul>
        <ul class="side-menu">
            <li><a href="./settings.php"><i class='bx bxs-cog'></i><span class="text">Settings</span></a></li>
            <li><a href="javascript:void(0);" class="logout" onclick="showLogoutModal()"><i class='bx bxs-log-out-circle'></i><span class="text">Logout</span></a></li>
        </ul>
    </section>
    <?php require_once './view/modal.php' ?>
    <section id="content">
        <nav>
            <i class='bx bx-menu'></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
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
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Assign Teacher Schedule</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./enroll_teacher_section.php">Assign Schedule</a></li>
                    </ul>
                </div>
            </div>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
            <div class="container">
                <div class="schedule-container">
                    <h2>Teacher Schedule</h2>
                    <div class="form-group">
                        <label for="teacherID">Select Teacher <span>*</span></label>
                        <select name="teacherID" id="teacherID" required onchange="fetchTeacherSchedules()">
                            <option value="" disabled selected>- Select Teacher -</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['userID']; ?>">
                                    <?php echo htmlspecialchars($teacher['firstName'] . ' ' . $teacher['lastName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="scheduleContainer">
                        <p>Please select a teacher to view their schedule.</p>
                    </div>
                </div>
            </div>
            <!-- Enroll Modal -->
            <div id="enrollModal" class="modal">
                <div class="modal-content">
                    <h2>Assign Teacher Schedule</h2>
                    <form id="enrollTeacherForm">
                        <div class="form-group">
                            <label>Teacher</label>
                            <p id="modalTeacherName" style="font-size: clamp(1.1rem, 3vw, 1.2rem);"></p>
                            <input type="hidden" id="modalTeacherID" name="teacherID">
                        </div>
                        <div class="form-group">
                            <label for="modalSectionID">Select Section <span>*</span></label>
                            <select name="sectionID" id="modalSectionID" required>
                                <option value="" disabled selected>- Select Section -</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['sectionID']; ?>">
                                        <?php echo htmlspecialchars($section['sectionName'] . ' (' . $section['sectionCode'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modalSubjectID">Select Subject <span>*</span></label>
                            <select name="subjectID" id="modalSubjectID" required>
                                <option value="" disabled selected>- Select Subject -</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['subjectID']; ?>">
                                        <?php echo htmlspecialchars($subject['subjectName'] . ' (' . $subject['subjectCode'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modalDay">Day</label>
                            <input type="text" id="modalDay" name="day" readonly style="width: 100%; padding: 10px; border-radius: 4px; background-color: var(--grey); border: 1px solid var(--dark-grey);">
                        </div>
                        <div class="form-group">
                            <label for="modalTimeSlot">Time Slot</label>
                            <input type="text" id="modalTimeSlot" name="timeSlot" readonly style="width: 100%; padding: 10px; border-radius: 4px; background-color: var(--grey); border: 1px solid var(--dark-grey);">
                        </div>
                        <div class="form-group">
                            <label for="modalAdvisory">Advisory Role <span>*</span></label>
                            <select name="advisory" id="modalAdvisory" required>
                                <option value="0" selected>No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="confirm-btn" onclick="submitEnrollForm()">Assign</button>
                            <button type="button" class="cancel-btn" onclick="closeEnrollModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Edit Modal -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <h2>Edit Teacher Schedule</h2>
                    <form id="editTeacherForm">
                        <div class="form-group">
                            <label>Teacher</label>
                            <p id="editTeacherName" style="font-size: clamp(1.1rem, 3vw, 1.2rem);"></p>
                            <input type="hidden" id="editTeacherSectionID" name="teacherSectionID">
                            <input type="hidden" id="editTeacherID" name="teacherID">
                        </div>
                        <div class="form-group">
                            <label for="editSectionID">Select Section <span>*</span></label>
                            <select name="sectionID" id="editSectionID" required>
                                <option value="" disabled>- Select Section -</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['sectionID']; ?>">
                                        <?php echo htmlspecialchars($section['sectionName'] . ' (' . $section['sectionCode'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editSubjectID">Select Subject <span>*</span></label>
                            <select name="subjectID" id="editSubjectID" required>
                                <option value="" disabled>- Select Subject -</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['subjectID']; ?>">
                                        <?php echo htmlspecialchars($subject['subjectName'] . ' (' . $subject['subjectCode'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editDay">Day</label>
                            <input type="text" id="editDay" name="day" readonly style="width: 100%; padding: 10px; border-radius: 4px; background-color: var(--grey); border: 1px solid var(--dark-grey);">
                        </div>
                        <div class="form-group">
                            <label for="editTimeSlot">Time Slot</label>
                            <input type="text" id="editTimeSlot" name="timeSlot" readonly style="width: 100%; padding: 10px; border-radius: 4px; background-color: var(--grey); border: 1px solid var(--dark-grey);">
                        </div>
                        <div class="form-group">
                            <label for="editAdvisory">Advisory Role <span>*</span></label>
                            <select name="advisory" id="editAdvisory" required>
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="confirm-btn" onclick="submitEditForm()">Save</button>
                            <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Archive Modal -->
            <div id="archiveModal" class="modal">
                <div class="modal-content">
                    <h2>Confirm Archive</h2>
                    <p>Are you sure you want to archive this schedule?</p>
                    <div class="form-group">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Section</th>
                                    <th>Subject</th>
                                    <th>Day</th>
                                    <th>Time Slot</th>
                                    <th>Advisory</th>
                                </tr>
                            </thead>
                            <tbody id="archiveDetailsTable">
                                <!-- Schedule details will be populated dynamically -->
                            </tbody>
                        </table>
                        <h3>Schedule Details</h3>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="confirm-btn" onclick="submitArchiveForm()">Confirm</button>
                        <button type="button" class="cancel-btn" onclick="closeArchiveModal()">Cancel</button>
                    </div>
                </div>
            </div>
        </main>
    </section>
    <script src="./utils/script.js"></script>
</body>
</html>