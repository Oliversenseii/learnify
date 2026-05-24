// Open the modal when the notification is clicked (even if the count is zero)
document.getElementById('notification').addEventListener('click', function(e) {
    e.preventDefault(); // Prevent the default action (anchor tag)
    var modal = document.getElementById('notification-modal');
    modal.style.display = 'block'; // Open the modal
});

// Close the modal when the close button is clicked
document.getElementById('close-modal').addEventListener('click', function() {
    var modal = document.getElementById('notification-modal');
    modal.style.display = 'none'; // Close the modal
});

// Close the modal when clicking outside the modal content
window.addEventListener('click', function(e) {
    var modal = document.getElementById('notification-modal');

    // Close the modal if the background (modal itself) is clicked
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});
