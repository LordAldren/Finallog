// dark_mode_handler.js
// Ang script na ito ay namamahala sa pag-save at pag-apply ng dark mode theme sa lahat ng pahina.

document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const darkModeKey = 'darkMode';

    // Function para i-enable ang dark mode
    const enableDarkMode = () => {
        body.classList.add('dark-mode');
        localStorage.setItem(darkModeKey, 'enabled');
        if (themeToggle) {
            themeToggle.checked = true;
        }
    };

    // Function para i-disable ang dark mode
    const disableDarkMode = () => {
        body.classList.remove('dark-mode');
        localStorage.setItem(darkModeKey, 'disabled');
        if (themeToggle) {
            themeToggle.checked = false;
        }
    };

    // Tinitingnan ang localStorage para sa naka-save na theme preference
    let darkMode = localStorage.getItem(darkModeKey);

    // Kung walang naka-save na preference, tinitingnan ang system preference
    if (darkMode === null) {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            enableDarkMode();
        } else {
            disableDarkMode();
        }
    } else {
        // Ina-apply ang naka-save na theme
        if (darkMode === 'enabled') {
            enableDarkMode();
        } else {
            disableDarkMode();
        }
    }

    // Naglalagay ng event listener sa toggle switch
    if (themeToggle) {
        themeToggle.addEventListener('change', () => {
            // Tinitingnan ulit ang kasalukuyang state mula sa localStorage bago mag-toggle
            darkMode = localStorage.getItem(darkModeKey);
            if (darkMode !== 'enabled') {
                enableDarkMode();
            } else {
                disableDarkMode();
            }
        });
    }
});
