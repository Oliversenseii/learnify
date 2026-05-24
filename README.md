# 📚 Learning Management System (Learnify)

A web-based Learning Management System designed for Senior High School institutions, supporting gamified learning, attendance tracking, quizzes, assignments, and real-time communication between students and teachers.

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 7.2+ |
| Database | MariaDB 11.4 (MySQL-compatible) |
| DB Management | phpMyAdmin 4.9 |
| Frontend | HTML, CSS, JavaScript |
| Hosting | InfinityFree (shared hosting) |
| Auth | Session-based + OTP + QR Login |

---

## 🔖 Version

**v1.0.0** — Initial Release (November 2025)

---

## 📝 Short Description

Learnify is a gamified LMS built for Senior High School. It supports multiple user roles (SuperAdmin, Admin, Professor, Student), section and strand management, interactive mini-games (BrainPix, Speech-to-Text), live quizzes (LearnQuiz), and academic tools like attendance, announcements, modules, and grade tracking — all in one platform.

---

## ✨ Features

- 👤 **Multi-role user system** — SuperAdmin, Admin, Professor, Student
- 🏫 **Section & Strand management** — STEM, ABM, HUMSS, TVL tracks and more
- 📋 **Attendance tracking** — Per subject, per section
- 📝 **Quizzes & Assignments** — With scoring, submission, and grading weights
- 📢 **Announcements & Modules** — With file attachments and comment threads
- 🎮 **BrainPix** — Image-based rebus puzzle game with badges and progress maps
- 🎤 **Speech-to-Text Game** — Vocabulary-building game using voice recognition
- ⚡ **LearnQuiz** — Live multiplayer quiz game with game codes
- 📅 **Academic Events Calendar** — Holidays and school events
- 💬 **Messaging** — Admin-to-admin and private teacher-student messaging
- 📊 **Grading Weights** — Configurable attendance, quiz, and assignment weights
- 🔐 **Security** — Failed login tracking, OTP verification, QR-based login, session timeout
- 🎨 **Branding** — Customizable logo and platform name

---

## 🗄️ Database Overview

- **Database:** `if0_40036013_db_lms`
- **Engine:** InnoDB (with MyISAM for select tables)
- **Charset:** utf8mb4
- **Tables:** 40+
- **Key entities:** `users`, `sections`, `subjects`, `teacher_section`, `student_section`, `track_strands`, `quizzes`, `assignments`, `attendance`, `brainpix_*`, `speech_to_text_*`, `learn_quiz_*`
