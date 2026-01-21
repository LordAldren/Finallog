(function() {
    const loader = document.getElementById('loading-overlay');
    const loaderText = document.getElementById('loader-text');
    
    if (!loader || !loaderText) {
        console.error("Hindi mahanap ang loading overlay element o text element!");
        return;
    }

    // Mga "maangas" na loading messages
    const loadingMessages = [
        "Calibrating Predictive Routes...",
        "Syncing Real-Time Fleet Data...",
        "Optimizing Logistics Matrix...",
        "Engaging Quantum Processor...",
        "Finalizing Dispatch Protocols..."
    ];
    let messageIndex = 0;
    let textInterval;

    const MINIMUM_SHOW_TIME = 3000; // 3000 milliseconds = 3 seconds

    // Function para palitan ang text
    const cycleText = () => {
        if(loaderText) {
            loaderText.style.opacity = '0';
            setTimeout(() => {
                messageIndex = (messageIndex + 1) % loadingMessages.length;
                loaderText.textContent = loadingMessages[messageIndex];
                loaderText.style.opacity = '1';
            }, 500); // Maghintay sa fade out bago magpalit
        }
    };

    // Function para ipakita ulit ang loader
    const showLoader = function() {
        loader.classList.remove('loader-hidden');
        if (!textInterval) {
            textInterval = setInterval(cycleText, 2000);
        }
    };
    
    // Function para itago ang loader
    const hideLoader = function() {
        loader.classList.add('loader-hidden');
        if (textInterval) {
            clearInterval(textInterval);
            textInterval = null;
        }
    };

    // --- BAGONG LOGIC PARA ITAGO ANG LOADER ---
    // Gumawa tayo ng dalawang pangako: isa para sa minimum show time, at isa para sa page load.
    const minTimePromise = new Promise(resolve => {
        setTimeout(resolve, MINIMUM_SHOW_TIME);
    });

    const pageLoadedPromise = new Promise(resolve => {
        window.addEventListener('load', resolve);
    });

    // Kapag natupad na pareho ang pangako (lumipas na ang 3 segundo AT fully loaded na ang page),
    // saka lang natin itatago ang loader. Ito ang mas reliable na paraan.
    Promise.all([pageLoadedPromise, minTimePromise]).then(() => {
        hideLoader();
    });
    // --- WAKAS NG BAGONG LOGIC ---


    // Simulan agad ang pagpalit ng text
    if (!textInterval) {
        // I-set agad ang unang text bago mag-interval
        loaderText.textContent = loadingMessages[0];
        textInterval = setInterval(cycleText, 2000);
    }

    // --- Mga Event Listeners para IPAKITA ang loader bago mag-navigate ---
    const loginForm = document.querySelector('form[action*="login.php"]');
    if (loginForm) {
        loginForm.addEventListener('submit', showLoader);
    }

    const logoutLink = document.getElementById('logout-link'); // Titingnan ang ID
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            showLoader();
            setTimeout(() => {
                window.location.href = this.href;
            }, 500); 
        });
    }
    
    const otherForms = document.querySelectorAll('form[action*="forgot_password.php"], form[action*="reset_password.php"], form[action*="verify_otp.php"]');
    if (otherForms) {
        otherForms.forEach(form => {
            form.addEventListener('submit', showLoader);
        });
    }

})();

