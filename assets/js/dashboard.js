/* =============================================================
   Hostel Management Dashboard - Client-side interactivity
   - Sidebar tab switching between all modules (uses data-title).
   - Mobile sidebar toggle.
   - Bootstrap form validation for every form on the page.
   ============================================================= */
(function () {
    'use strict';

    const navLinks   = document.querySelectorAll('.sidebar .nav-link[data-panel]');
    const panels     = document.querySelectorAll('.panel');
    const pageTitle  = document.getElementById('pageTitle');
    const sidebar    = document.getElementById('sidebar');
    const backdrop   = document.getElementById('sidebarBackdrop');
    const menuToggle = document.getElementById('menuToggle');

    function showPanel(key) {
        navLinks.forEach(function (link) {
            link.classList.toggle('active', link.dataset.panel === key);
        });
        panels.forEach(function (panel) {
            panel.classList.toggle('active', panel.id === 'panel-' + key);
        });
        if (pageTitle) {
            const activeLink = document.querySelector('.sidebar .nav-link[data-panel="' + key + '"]');
            if (activeLink && activeLink.dataset.title) {
                pageTitle.textContent = activeLink.dataset.title;
            }
        }
    }

    navLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            showPanel(link.dataset.panel);
            closeSidebar();
        });
    });

    function openSidebar() {
        sidebar.classList.add('open');
        backdrop.classList.add('show');
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
    }

    if (menuToggle) menuToggle.addEventListener('click', openSidebar);
    if (backdrop)  backdrop.addEventListener('click', closeSidebar);

    const params = new URLSearchParams(window.location.search);
    const targetPanel = params.get('panel');
    if (targetPanel && document.getElementById('panel-' + targetPanel)) {
        showPanel(targetPanel);
    }

    const actionAlert = document.getElementById('adminActionAlert');
    if (actionAlert) {
        setTimeout(function () { actionAlert.style.display = 'none'; }, 4000);
    }

    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
