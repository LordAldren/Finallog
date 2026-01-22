(function() {
    const loader = document.getElementById('loading-overlay');
    const loaderText = document.getElementById('loader-text');
    
    if (!loader || !loaderText) {
        // Silent fail or console warn
        return;
    }

    // Logistics-themed loading messages
    const loadingMessages = [
        "CALIBRATING PREDICTIVE ROUTES...",
        "SYNCING FLEET TELEMETRY...",
        "OPTIMIZING DISPATCH ALGORITHMS...",
        "ANALYZING TRAFFIC PATTERNS...",
        "SECURING LOGISTICS DATA..."
    ];
    let messageIndex = 0;
    let textInterval;

    const MINIMUM_SHOW_TIME = 2000; // Reduced to 2s for snappier feel

    // Function to cycle text
    const cycleText = () => {
        if(loaderText) {
            loaderText.style.opacity = '0';
            setTimeout(() => {
                messageIndex = (messageIndex + 1) % loadingMessages.length;
                loaderText.textContent = loadingMessages[messageIndex];
                loaderText.style.opacity = '1';
            }, 300);
        }
    };

    const showLoader = function() {
        loader.classList.remove('loader-hidden');
        if (!textInterval) {
            textInterval = setInterval(cycleText, 2500);
        }
    };
    
    const hideLoader = function() {
        loader.classList.add('loader-hidden');
        if (textInterval) {
            clearInterval(textInterval);
            textInterval = null;
        }
    };

    // --- FIX: ROBUST PAGE LOAD DETECTION ---
    const minTimePromise = new Promise(resolve => {
        setTimeout(resolve, MINIMUM_SHOW_TIME);
    });

    const pageLoadedPromise = new Promise(resolve => {
        if (document.readyState === 'complete') {
            resolve();
        } else {
            window.addEventListener('load', resolve);
        }
    });

    Promise.all([pageLoadedPromise, minTimePromise]).then(() => {
        hideLoader();
    });

    // Start text cycle immediately
    if (!textInterval) {
        loaderText.textContent = loadingMessages[0];
        textInterval = setInterval(cycleText, 2500);
    }

    // --- FORM SUBMISSION HANDLERS ---
    const attachLoaderToForms = () => {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            // Check if form is a login/auth form
            if (form.action.includes('login.php') || 
                form.action.includes('forgot_password.php') || 
                form.action.includes('reset_password.php') ||
                form.action.includes('verify_otp.php')) {
                form.addEventListener('submit', showLoader);
            }
        });
    };
    attachLoaderToForms();

    const logoutLink = document.getElementById('logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            showLoader();
            setTimeout(() => {
                window.location.href = this.href;
            }, 500); 
        });
    }

})();

