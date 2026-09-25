/* =============================================================
   Hostel Management Dashboard - Client-side interactivity
   - Sidebar tab switching between all modules (uses data-title).
   - Desktop sidebar collapse/expand toggle.
   - Mobile sidebar overlay drawer.
   - Bootstrap form validation for every form on the page.
   ============================================================= */
(function () {
    'use strict';

    const navLinks      = document.querySelectorAll('.sidebar .nav-link[data-panel]');
    const panels        = document.querySelectorAll('.panel');
    const pageTitle     = document.getElementById('pageTitle');
    const sidebar       = document.getElementById('sidebar');
    const backdrop      = document.getElementById('sidebarBackdrop');
    const menuToggle    = document.getElementById('menuToggle');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const dashboardBody = document.getElementById('dashboardBody');

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

    // Desktop sidebar collapse/expand
    function toggleSidebarCollapse() {
        if (sidebar && dashboardBody) {
            sidebar.classList.toggle('collapsed');
            dashboardBody.classList.toggle('collapsed');
            
            // Update icon direction
            if (sidebarToggle) {
                const icon = sidebarToggle.querySelector('i');
                if (icon) {
                    icon.className = sidebar.classList.contains('collapsed') 
                        ? 'bi bi-chevron-right' 
                        : 'bi bi-chevron-left';
                }
            }
        }
    }

    // Mobile sidebar overlay
    function openSidebar() {
        if (sidebar && backdrop) {
            sidebar.classList.add('open');
            backdrop.classList.add('show');
            document.body.classList.add('sidebar-open');
        }
    }
    
    function closeSidebar() {
        if (sidebar && backdrop) {
            sidebar.classList.remove('open');
            backdrop.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebarCollapse();
        });
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

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
