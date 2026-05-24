/* -------------------------------------------------
   1. ACTIVE MENU ITEM 
   ------------------------------------------------- */
const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

const savedActive = localStorage.getItem('activeMenu');
if (savedActive) {
    const activeLink = document.querySelector(`#sidebar .side-menu.top li a[href="${savedActive}"]`);
    if (activeLink) activeLink.parentElement.classList.add('active');
}

allSideMenu.forEach(item => {
    const li = item.parentElement;

    item.addEventListener('click', function (e) {
        allSideMenu.forEach(i => i.parentElement.classList.remove('active'));

        li.classList.add('active');

        localStorage.setItem('activeMenu', this.getAttribute('href'));
    });
});

/* -------------------------------------------------
   2. SIDEBAR TOGGLE 
   ------------------------------------------------- */
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar  = document.getElementById('sidebar');

if (localStorage.getItem('sidebarHidden') === 'true') {
    sidebar.classList.add('hide');
}

menuBar.addEventListener('click', function () {
    sidebar.classList.toggle('hide');

    const isHidden = sidebar.classList.contains('hide');
    localStorage.setItem('sidebarHidden', isHidden);
});

/* -------------------------------------------------
   3. SEARCH FORM 
   ------------------------------------------------- */
const searchButton     = document.querySelector('#content nav form .form-input button');
const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
const searchForm       = document.querySelector('#content nav form');

searchButton.addEventListener('click', function (e) {
    if (window.innerWidth < 576) {
        e.preventDefault();
        searchForm.classList.toggle('show');
        if (searchForm.classList.contains('show')) {
            searchButtonIcon.classList.replace('bx-search', 'bx-x');
        } else {
            searchButtonIcon.classList.replace('bx-x', 'bx-search');
        }
    }
});

/* -------------------------------------------------
   4. RESPONSIVE INITIAL STATE
   ------------------------------------------------- */
if (window.innerWidth < 768) {
    sidebar.classList.add('hide');
    localStorage.setItem('sidebarHidden', 'true');   
} else if (window.innerWidth > 576) {
    searchButtonIcon.classList.replace('bx-x', 'bx-search');
    searchForm.classList.remove('show');
}

window.addEventListener('resize', function () {
    if (this.innerWidth > 576) {
        searchButtonIcon.classList.replace('bx-x', 'bx-search');
        searchForm.classList.remove('show');
    }
});

/* -------------------------------------------------
   5. DARK MODE 
   ------------------------------------------------- */
const switchMode = document.getElementById('switch-mode');

if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark');
    if (switchMode) switchMode.checked = true;
}

if (switchMode) {
    switchMode.addEventListener('change', function () {
        if (this.checked) {
            document.body.classList.add('dark');
            localStorage.setItem('darkMode', 'true');
        } else {
            document.body.classList.remove('dark');
            localStorage.setItem('darkMode', 'false');
        }
    });
}