<?php
// academic_events.php
require_once '../../config/db_connection.php';

try {
    // Fetch unseen academic events
    $unseenSQL = "
        SELECT ae.eventID, ae.title, ae.description, ae.eventDate, ae.eventType
        FROM academic_events ae
        WHERE ae.archived = 0 
        AND ae.eventDate >= CURDATE()
        AND NOT EXISTS (
            SELECT 1 FROM seen_academic_events se 
            WHERE se.userID = :userID AND se.eventID = ae.eventID
        )
        ORDER BY ae.eventDate ASC
    ";
    $unseenStmt = $dbConnection->prepare($unseenSQL);
    $unseenStmt->bindParam(':userID', $_SESSION['userID'], PDO::PARAM_INT);
    $unseenStmt->execute();
    $unseenEvents = $unseenStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error in academic_events.php: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to load events.";
}
?>

<?php if (!empty($unseenEvents)): ?>
<div id="newEventsModal" class="modal" style="display: none;">
    <div class="modal-content-academic">
        <div class="events-list">
            <div class="slider-container">
                <div class="slider-wrapper">
                    <?php foreach ($unseenEvents as $index => $event): ?>
                        <div class="event-card" data-index="<?php echo $index; ?>">
                            <div class="event-image">
                                <!-- Debugging: Display eventType for verification -->
                                <p style="display: none;">Event Type: <?php echo htmlspecialchars($event['eventType']); ?></p>
                                <?php
                                // Normalize eventType to handle case sensitivity and whitespace
                                $eventType = strtolower(trim($event['eventType']));
                                $imagePath = './img/event.png'; // Default image
                                if ($eventType === 'holiday') {
                                    $imagePath = './img/holiday.png';
                                } elseif ($eventType !== 'event') {
                                    // Log unexpected eventType for debugging
                                    error_log("Unknown eventType: " . $event['eventType'] . " for eventID: " . $event['eventID']);
                                }
                                ?>
                                <img src="<?php echo $imagePath . '?v=' . time(); ?>" alt="<?php echo htmlspecialchars($event['eventType']); ?> image">
                            </div>
                            <div class="event-details">
                                <h3><?php echo htmlspecialchars(strtoupper($event['title'])); ?></h3>
                                <p class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                <p class="event-date"><strong>Date:</strong> <?php echo htmlspecialchars(date('F j, Y', strtotime($event['eventDate']))); ?></p>
                                <button class="btn-mark-seen" data-event-id="<?php echo $event['eventID']; ?>" onclick="markAsSeen(<?php echo $event['eventID']; ?>)">Got it</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($unseenEvents) > 1): ?>
                    <button class="slider-btn prev" onclick="moveSlide(-1)">&#10094;</button>
                    <button class="slider-btn next" onclick="moveSlide(1)">&#10095;</button>
                    <div class="slider-dots">
                        <?php foreach ($unseenEvents as $index => $event): ?>
                            <span class="dot" data-index="<?php echo $index; ?>" onclick="goToSlide(<?php echo $index; ?>)"></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .modal-content-academic {
        background: #FFFFFF;
        width: 90%;
        border-radius: 10px;
        max-width: 900px;
        height: auto;
        position: relative;
        animation: slideInTop 0.5s ease-out forwards;
        overflow: hidden;
    }

    @keyframes slideInTop {
        from {
            transform: translateY(-100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Slider Styles */
    .slider-container {
        position: relative;
        width: 100%;
        overflow: hidden;
    }

    .slider-wrapper {
        display: flex;
        transition: transform 0.5s ease-in-out;
    }

    .event-card {
        flex: 0 0 100%;
        display: none;
        align-items: center;
        gap: 1.5rem;
        padding: 1rem;
    }

    .event-card.active {
        display: flex;
    }

    .event-image {
        flex: 0 0 400px;
    }

    .event-image img {
        width: 100%;
        height: auto;
        object-fit: contain;
    }

    .event-details {
        flex: 1;
    }

    .event-details h3 {
        margin: 0 0 0.5rem;
        color: #2B6CB0;
        font-size: clamp(2.5rem, 3vw, 5rem);
        font-weight: 600;
        border-bottom: 1px solid #2B6CB0;
        text-transform: uppercase;
    }

    .event-description {
        margin-top: 20px;
        color: #4A5568;
        font-size: clamp(1.5rem, 3vw, 2rem);
    }

    .event-date {
        margin-top: 50px;
        color: #4A5568;
        font-size: clamp(1.5rem, 3vw, 2rem);
    }

    .event-date strong {
        color: #1A202C;
    }

    .btn-mark-seen {
        background: #2B6CB0;
        color: #FFFFFF;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-size: clamp(1.5rem, 3vw, 2rem);
        font-weight: 500;
        cursor: pointer;
        margin-top: 0.5rem;
        transition: background 0.3s ease, transform 0.3s ease;
    }

    .btn-mark-seen:hover {
        background: #1E4A7A;
        transform: translateY(-2px);
    }

    /* Slider Buttons */
    .slider-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.5);
        color: #FFFFFF;
        border: none;
        padding: 0.5rem 1rem;
        cursor: pointer;
        font-size: 1.5rem;
        border-radius: 5px;
        z-index: 10;
    }

    .slider-btn.prev {
        left: 10px;
    }

    .slider-btn.next {
        right: 10px;
    }

    .slider-btn:hover {
        background: rgba(0, 0, 0, 0.7);
    }

    /* Slider Dots */
    .slider-dots {
        text-align: center;
        position: absolute;
        bottom: 10px;
        width: 100%;
    }

    .dot {
        height: 10px;
        width: 10px;
        margin: 0 5px;
        background-color: #bbb;
        border-radius: 50%;
        display: inline-block;
        cursor: pointer;
    }

    .dot.active {
        background-color: #2B6CB0;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .modal-content-academic {
            width: 95%;
            max-width: 500px;
        }

        .event-card {
            flex-direction: column;
            align-items: flex-start;
        }

        .event-image {
            width: 100%;
            max-width: 200px;
        }

        .event-image img {
            width: 100%;
            height: auto;
        }

        .event-details h3 {
            font-size: 1.1rem;
        }

        .event-description, .event-date {
            font-size: 0.9rem;
        }

        .btn-mark-seen {
            padding: 0.6rem 1.2rem;
            font-size: 0.95rem;
            width: 130px;
        }

        .slider-btn {
            padding: 0.3rem 0.8rem;
            font-size: 1.2rem;
        }
    }

    @media (max-width: 480px) {
        .modal-content-academic {
            width: 98%;
        }

        .event-details h3 {
            font-size: 1rem;
        }

        .event-description, .event-date {
            font-size: 0.85rem;
        }

        .btn-mark-seen {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            width: 120px;
        }

        .slider-btn {
            padding: 0.2rem 0.6rem;
            font-size: 1rem;
        }
    }
</style>

<script>
    let currentSlide = 0;
    const slides = document.querySelectorAll('.event-card');
    const dots = document.querySelectorAll('.slider-dots .dot');
    const totalSlides = slides.length;

    function updateSlider() {
        slides.forEach((slide, index) => {
            slide.classList.toggle('active', index === currentSlide);
        });
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentSlide);
        });
    }

    function moveSlide(direction) {
        currentSlide = (currentSlide + direction + totalSlides) % totalSlides;
        updateSlider();
    }

    function goToSlide(index) {
        currentSlide = index;
        updateSlider();
    }

    function markAsSeen(eventID) {
        // Collect all event IDs from the slider
        const allEventIDs = Array.from(slides).map(slide => slide.querySelector('.btn-mark-seen').dataset.eventId);

        fetch('mark_seen.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ eventIDs: allEventIDs }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close the modal immediately
                document.getElementById('newEventsModal').style.display = 'none';
            } else {
                alert('Failed to mark events as seen.');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function showEventsModal() {
        const modal = document.getElementById('newEventsModal');
        if (modal) {
            modal.style.display = 'flex';
            if (totalSlides > 0) {
                slides[0].classList.add('active');
                if (dots.length > 0) dots[0].classList.add('active');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        showEventsModal();
    });

    setInterval(() => {
        moveSlide(1);
    }, 5000);
</script>
<?php endif; ?>