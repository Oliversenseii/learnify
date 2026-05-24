// Logout Start
function showLogoutModal() {
    const modal = document.getElementById('logoutModal');
    const modalContent = modal.querySelector('.modal-content');
    modal.style.display = 'flex';
    modalContent.classList.add('modal-slide');
    setTimeout(() => {
        modalContent.classList.add('active');
    }, 10); 
}

function closeLogoutModal() {
    const modal = document.getElementById('logoutModal');
    const modalContent = modal.querySelector('.modal-content');
    modalContent.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300); 
}

function confirmLogout() {
    window.location.href = './logout.php';
}
// Logout End