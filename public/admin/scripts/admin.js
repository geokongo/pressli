/**
 * Pressli CMS Admin Scripts
 * Minimal JavaScript for essential interactions
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initSidebarToggle();
        initUserMenu();
        initDropdowns();
    });

    /**
     * Sidebar toggle for mobile
     */
    function initSidebarToggle() {
        const toggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');

        if (!toggle || !sidebar) return;

        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Close sidebar on window resize if it becomes desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
            }
        });
    }

    /**
     * User menu dropdown
     */
    function initUserMenu() {
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userMenu = document.querySelector('.user-menu');
        const userDropdown = document.getElementById('userDropdown');

        if (!userMenuBtn || !userMenu) return;

        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenu.contains(e.target)) {
                userMenu.classList.remove('active');
            }
        });

        // Close dropdown when clicking a link inside
        if (userDropdown) {
            const links = userDropdown.querySelectorAll('a');
            links.forEach(function(link) {
                link.addEventListener('click', function() {
                    userMenu.classList.remove('active');
                });
            });
        }
    }

    /**
     * Generic dropdown handler (for future use)
     */
    function initDropdowns() {
        const dropdowns = document.querySelectorAll('[data-dropdown]');

        dropdowns.forEach(function(dropdown) {
            const trigger = dropdown.querySelector('[data-dropdown-trigger]');
            const menu = dropdown.querySelector('[data-dropdown-menu]');

            if (!trigger || !menu) return;

            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('active');
            });

            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('active');
                }
            });
        });
    }

    /**
     * ESC key to close dropdowns and modals
     */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Close user menu
            const userMenu = document.querySelector('.user-menu');
            if (userMenu) {
                userMenu.classList.remove('active');
            }

            // Close sidebar on mobile
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && window.innerWidth <= 768) {
                sidebar.classList.remove('active');
            }

            // Close any active dropdowns
            const activeDropdowns = document.querySelectorAll('[data-dropdown].active');
            activeDropdowns.forEach(function(dropdown) {
                dropdown.classList.remove('active');
            });
        }
    });

})();
