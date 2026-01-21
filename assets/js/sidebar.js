/**
 * SLATE Logistics - Sidebar & Dropdown Handler
 * Manages the scifi-themed sidebar interactions and animations.
 */
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const hamburger = document.getElementById('hamburger');
    const dropdowns = document.querySelectorAll('.sidebar .dropdown');

    // 1. Initialize Active Dropdowns (Expand them on page load)
    // We expand anything marked 'active' by PHP
    const activeDropdown = document.querySelector('.sidebar .dropdown.active');
    if (activeDropdown) {
        expandDropdown(activeDropdown);
    }

    // 2. Handle Burger Menu (Collapse/Expand Sidebar)
    if (hamburger) {
        hamburger.addEventListener('click', function (e) {
            e.stopPropagation(); // Prevent document click from closing it immediately
            if (window.innerWidth <= 992) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                if (mainContent) mainContent.classList.toggle('expanded');
            }
        });
    }

    // Close mobile sidebar when clicking main content or outside
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 992 && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });

    // 3. Handle Dropdown Toggles
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');

        if (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const isOpen = dropdown.classList.contains('open');

                // Close other open dropdowns (Accordion style)
                dropdowns.forEach(d => {
                    if (d !== dropdown && d.classList.contains('open')) {
                        collapseDropdown(d);
                    }
                });

                if (isOpen) {
                    collapseDropdown(dropdown);
                } else {
                    expandDropdown(dropdown);
                }
            });
        }
    });

    // Helper: Expand with animation
    function expandDropdown(el) {
        const menu = el.querySelector('.dropdown-menu');
        if (!menu) return;

        el.classList.add('open');
        // We set maxHeight to scrollHeight to allow CSS transition from 0 to actual size
        menu.style.maxHeight = menu.scrollHeight + 'px';

        // Optional: After transition, set to 'none' if you want it to be truly auto-height?
        // No, stay with scrollHeight px for smooth reversal.
    }

    // Helper: Collapse with animation
    function collapseDropdown(el) {
        const menu = el.querySelector('.dropdown-menu');
        if (!menu) return;

        el.classList.remove('open');
        menu.style.maxHeight = '0';
    }

    // Handle window resize
    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
            sidebar.classList.remove('show');
        }
    });
});
