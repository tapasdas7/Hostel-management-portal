/* =============================================================
   Hostel Management Dashboard - Client-side interactivity
   - Sidebar tab switching between all modules.
   - Mobile sidebar toggle.
   - Bootstrap form validation for every form on the page.
   ============================================================= */
(function () {
    'use strict';

    // ---- Panel titles mapped to nav items ----
    const TITLES = {
        rooms:               'Room Tracking',
        complaints:          'Complaints Desk',
        notices:             'Notice Board',
        gatelogs:            'Digital Gate Log',
        applications:        'Room Applications',
        vacating:            'Vacate Room',
        visitors:            'Visitors Log',
        'admin-metrics':     'Metrics Dashboard',
        'admin-students':    'Student Records',
        'admin-applications':'Room Allocation',
        'admin-vacating':    'Vacancy Management',
        'admin-visitors':    'Visitors Control',
        'admin-furniture':   'Furniture Inventory'
    };

    const navLinks    = document.querySelectorAll('.sidebar .nav-link[data-panel]');
    const panels      = document.querySelectorAll('.panel');
    const pageTitle   = document.getElementById('pageTitle');
    const sidebar     = document.getElementById('sidebar');
    const backdrop    = document.getElementById('sidebarBackdrop');
    const menuToggle  = document.getElementById('menuToggle');

    /**
     * Switch the visible module panel.
     * @param {string} key - panel identifier (rooms | complaints | notices | gatelogs)
     */
    function showPanel(key) {
        // Update nav active state
        navLinks.forEach(function (link) {
            link.classList.toggle('active', link.dataset.panel === key);
        });

        // Show only the selected panel
        panels.forEach(function (panel) {
            panel.classList.toggle('active', panel.id === 'panel-' + key);
        });

        // Update topbar title
        if (pageTitle && TITLES[key]) {
            pageTitle.textContent = TITLES[key];
        }
    }

    // ---- Attach click handlers to sidebar nav items ----
    navLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            showPanel(link.dataset.panel);
            closeSidebar();
        });
    });

    // ---- Mobile sidebar open / close ----
    function openSidebar() {
        sidebar.classList.add('open');
        backdrop.classList.add('show');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', openSidebar);
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    // ---- Open the panel specified in the URL query string (?panel=name) ----
    // This keeps the user on the same tab after a GET-based filter submission.
    const params = new URLSearchParams(window.location.search);
    const targetPanel = params.get('panel');
    if (targetPanel && TITLES[targetPanel]) {
        showPanel(targetPanel);
    }

    // ---- Auto-hide admin action alert after 4 seconds ----
    const actionAlert = document.getElementById('adminActionAlert');
    if (actionAlert) {
        setTimeout(function () { actionAlert.style.display = 'none'; }, 4000);
    }

    // ---- Bootstrap form validation (all forms on the page) ----
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
