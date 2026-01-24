let maxAttempts = 9;
let loginAttempts = 0;
let countdownInterval;

// Define lockout times based on attempts (in seconds)
const lockoutDurations = {
    3: 15, 
    6: 30,  
    9: 60   
};




function handleLockout(lockoutDuration) {
    if (!lockoutDuration) return; // Exit if lockoutDuration is undefined

    const now = Math.floor(Date.now() / 1000);
    const lockoutEndTime = now + lockoutDuration;

    const countdownElement = document.getElementById('error-message');
    const loginButton = document.getElementById('login-button');
    const username = document.getElementById('username');
    const password = document.getElementById('password');

    const registerLink = document.getElementById('registerLink');

    // Disable the login button and register link
    loginButton.disabled = true;
    username.disabled = true;
    password.disabled = true;
    registerLink.classList.add('disabled');
    registerLink.removeAttribute('href');

    // Store the lockout end time in localStorage
    localStorage.setItem('lockoutEndTime', lockoutEndTime);

    clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        const currentTime = Math.floor(Date.now() / 1000);
        const countdown = lockoutEndTime - currentTime;

        if (countdown <= 0) {
            clearInterval(countdownInterval);
            countdownElement.innerText = '';
            countdownElement.style.display = 'none';

            // Re-enable login button and register link
            loginButton.disabled = false;
            username.disabled = false;
            password.disabled = false;
            registerLink.classList.remove('disabled');
            registerLink.setAttribute('href', 'register.php'); // Reset the href attribute

            // Clear lockout data after timer ends
            localStorage.removeItem('lockoutEndTime');
        } else {
            countdownElement.style.display = 'block';
            preventBackNavigation();
            countdownElement.innerText = `Access Denied!!. Try Again in ${countdown} seconds !!`;
        }
    }, 1000);
}




function preventBackNavigation() {
    // Push multiple states to history on page load
    const j = 0;
    for (let i = 1; i < j; i++) {
        history.pushState(null, '', location.href);
    }

    // Listen for back button clicks
    window.onpopstate = function () {
        // Immediately push state again to prevent navigation
        history.pushState(null, '', location.href);
    };
}

function changeToLogout(){
    const authButton = document.getElementById("signupToLogout");
    authButton.textContent = "Log out";
}

function handleLogin(event) {
    event.preventDefault();
    

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();

    const error = document.querySelector('.error');
    const errorPassword = document.querySelector('.error-password');

    

    if (!username) {
        error.style.display = 'block';
        error.innerText = 'This is required';
        return;
    }  if (username.length <= 1 || username.length > 15) {
        error.style.display = 'block';
        error.innerText = 'Username must be between 1 and 15 characters';
        return;
    } else if (!/^(?![-_])/.test(username)) {
        error.style.display = 'block';
        error.innerText = 'Username must not start with - or _';
        return;
    } else if (!/(?<![-_])$/.test(username)) {
        error.style.display = 'block';
        error.innerText = 'Username must not end with - or _';
        return;
    } else {
        error.style.display = 'none';
    }

    if (!password) {
        errorPassword.style.display = 'block';
        errorPassword.innerText = 'Enter Your Password';
        return;
    } else {
        errorPassword.style.display = 'none';
    }
    

    fetch('../php/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ username, password })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) { 
           
                window.location.href = '../php/home.php';
            
            
        } else {
            loginAttempts++;

            document.getElementById('error-message').innerText = data.message;
            document.getElementById('error-message').style.display = 'block';

            

           // Show reset password option after 2 consecutive errors
           if (loginAttempts % 3 === 2) {
            showResetPassword();
        }

            if (loginAttempts > 9) {
                showResetPassword();
            }

            // Use the lockout duration from the PHP response
            if (data.lockout_duration) {
                handleLockout(parseInt(data.lockout_duration));  // Passing dynamic lockout duration
            }
            
           

            form.reset();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function showResetPassword() {
    const forgotPassword = document.getElementById('forgot-password');
    
    // Show the forgot password section by changing its display style
    forgotPassword.style.display = 'block';  // Show the element

    // If you want to redirect to a reset password page, you can use the following line:
    // window.location.href = 'reset-password.php';
}

function togglePasswordVisibility1(passwordId) {
    const passwordField = document.getElementById(passwordId);
    const toggleIcon = document.getElementById('togglePassword1');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text'; // Show password
        toggleIcon.src = '../images/close-eye.png'; // Change icon to indicate "show"

    } else {
        
        passwordField.type = 'password'; // Hide password
        toggleIcon.src = '../images/eye-icon.png'; // Change icon to indicate "hide"

    }
}

const form = document.getElementById('loginForm');
form.addEventListener('submit', handleLogin);




window.addEventListener('load', () => {
    const lockoutEndTime = localStorage.getItem('lockoutEndTime');

    if (lockoutEndTime) {
        const now = Math.floor(Date.now() / 1000);
        const remainingTime = lockoutEndTime - now;
        if (remainingTime > 0) {
            handleLockout(remainingTime);
        }
    }
});