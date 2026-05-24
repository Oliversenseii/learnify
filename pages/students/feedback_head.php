<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Learnify</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../components/css/normal_text.css">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/subjects_sidebar.css">
    <script src="./logout.js"></script>
    <style>
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .feedback-container {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 40px;
        }

        .feedback-form {
            flex: 1;
            padding: 20px;
            border-radius: 8px;
            background-color: var(--light);
        }

        .feedback-form h4 {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 20px;
        }

        .feedback-form .form-group {
            margin-bottom: 15px;
        }

        .feedback-form label {
            font-weight: 600;
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 5px;
            display: block;
        }

        .feedback-form input[type="text"],
        .feedback-form textarea,
        .feedback-form select {
            width: 100%;
            padding: 14px;
            border-radius: 6px;
            color: var(--dark);
            border: 1px solid transparent;
            background-color: var(--grey);
            font-size: 1rem;
            transition: border 0.3s ease;
        }

        .feedback-form input[type="text"] {
            margin-bottom: 5px;
        }

        .feedback-form input[type="text"]:focus,
        .feedback-form textarea:focus,
        .feedback-form select:focus {
            border-color: #007bff;
            outline: none;
        }

        .feedback-form textarea {
            resize: vertical;
            min-height: 120px;
        }

        .feedback-form button {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            background-color: #28a745;
            color: white;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .feedback-form button:hover {
            background-color: #218838;
        }

        small {
            color: var(--dark);
        }

        /* Rating distribution (right side) */
        .rating-distribution {
            flex: 1;
            text-align: center;
        }

        .rating-distribution h2 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 20px;
        }

        .rating-distribution .progress-label {
            font-size: 14px;
            margin-bottom: 5px;
            display: flex;
            color: var(--dark);
            justify-content: space-between;
            align-items: center;
        }

        .rating-distribution .progress-label strong {
            display: flex;
            flex-direction: row;
        }

        .rating-distribution .rating-bar {
            background-color: #e0e0e0;
            border-radius: 10px;
            height: 25px;
            margin-bottom: 10px;
        }

        .rating-distribution .rating-bar div {
            height: 100%;
            border-radius: 10px;
            background-color: #f8d64e;
        }

        .rating-distribution .average-rating {
            font-size: 2.5rem;
            color: var(--dark);
            margin-top: 20px;
        }

        .rating-distribution .total-feedback {
            font-size: 1rem;
            color: var(--dark-grey);
        }

        /* Recent feedbacks (below both) */
        .recent-feedbacks {
            margin-top: 40px;
        }

        .recent-feedbacks h2 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 20px;
        }

        .feedback-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .feedback-item {
            flex: 1 1 calc(50% - 20px);
            background-color: var(--light);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .feedback-item .user-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #28a745;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            margin-right: 10px;
        }

        .feedback-item .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .feedback-item strong {
            color: var(--dark);
            font-size: 1.4rem;
        }

        .feedback-item p {
            color: var(--dark-grey);
            font-size: 1.2rem;
            margin: 5px 0;
        }

        .feedback-item small {
            color: var(--dark-grey);
            font-size: 0.9rem;
        }

        /* Star styling for recent feedbacks */
        .feedback-item .star {
            color: #f8d64e; 
            font-size: 1.2rem;
            margin-right: 2px;
        }

        .hr {
            width: 100%;
            border: 0;
            height: 1px;
            background: #ddd;
            margin: 20px 0;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            margin-top: 10px
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .feedback-container {
                flex-direction: column;
            }
            
            .feedback-item {
                flex: 1 1 100%;
            }
        }
        #sidebar.hide .submenu-title {
            display: none;
        }

        #sidebar.hide .subject-item .subject-texts {
            display: none;
        }
          @import url('https://fonts.googleapis.com/css2?family=Caveat:wght@700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');

        .brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            padding: 10px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #007bff, #0056b3);
            position: relative; 
        }

        .logo_img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
            transition: transform 0.3s ease;
        }

        #logo_text {
            font-family: 'Caveat', cursive;
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1;
        }

        .brand::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: 1px; 
            left: 0;
            background-color: var(--dark); 
            transition: width 0.4s ease-in-out; 
        }

        .brand:hover {
            transform: translateY(-2px);
        }

        .brand:hover::after {
            width: 100%; 
        }

        .brand:hover .logo_img {
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .brand {
                padding: 8px 15px;
            }

            .logo_img {
                width: 32px;
                height: 32px;
            }

            #logo_text {
                font-size: 20px;
            }
        }
    </style>
</head>
