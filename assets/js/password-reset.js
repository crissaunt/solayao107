// password-reset.js
let currentSessionId = '';
let currentUserId = 0;
let otpTimer;
let resendTimer;
let answerAttempts = 0;
const maxAnswerAttempts = 3;

// Function to check if OTP verification is locked
async function checkOTPLockStatus(sessionId) {
    try {
        const response = await fetch('../php/check-otp-lock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ session_id: sessionId })
        });

        const data = await response.json();

        if (data.locked) {
            // OTP verification is locked, disable fields
            lockOTPFields(data.remaining_seconds);
        }

    } catch (error) {
        console.error('Error checking OTP lock status:', error);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    // Load saved session if exists
    const savedSession = localStorage.getItem('password_reset_session');
    if (savedSession) {
        const session = JSON.parse(savedSession);
        currentSessionId = session.sessionId;
        currentUserId = session.userId;

        // Check which step we were on
        if (session.step >= 2) {
            // Fetch actual remaining time from backend
            fetchRemainingTime(session.sessionId).then(remainingSeconds => {
                if (remainingSeconds > 0) {
                    goToStep(2);
                    document.getElementById('email').value = session.email || '';
                    document.getElementById('email-success').style.display = 'block';
                    document.getElementById('email-success').textContent = 'OTP sent to ' + (session.email || 'your email');
                    startOTPTimer(remainingSeconds); // Use actual remaining time

                    // ADD THIS: Check if OTP verification is locked
                    checkOTPLockStatus(session.sessionId);
                } else {
                    // OTP expired, go back to step 1
                    localStorage.removeItem('password_reset_session');
                    alert('Your OTP has expired. Please request a new one.');
                }
            });
        }
    }

    // Password strength checker
    document.getElementById('new-password').addEventListener('input', checkPasswordStrength);
    document.getElementById('confirm-password').addEventListener('input', checkPasswordMatch);

    // Enter key support
    document.getElementById('email').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            sendOTP();
        }
    });

    document.getElementById('otp').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            verifyOTP();
        }
    });

    document.getElementById('security-answer').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            verifyAnswer();
        }
    });

    document.getElementById('new-password').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            updatePassword();
        }
    });

    document.getElementById('confirm-password').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            updatePassword();
        }
    });
});

// Add this function to fetch remaining time
async function fetchRemainingTime(sessionId) {
    try {
        const response = await fetch('../php/get-otp-time.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ session_id: sessionId })
        });

        const data = await response.json();

        if (data.success) {
            return data.remaining_seconds;
        } else {
            return 0; // Session expired or not found
        }
    } catch (error) {
        console.error('Error fetching OTP time:', error);
        return 0;
    }
}

// Step navigation
function goToStep(stepNumber) {
    // Hide all step contents
    for (let i = 1; i <= 4; i++) {
        document.getElementById(`step${i}-content`).style.display = 'none';
        document.getElementById(`step${i}`).classList.remove('active', 'completed');
    }

    // Hide success content
    document.getElementById('success-content').style.display = 'none';

    // Show current step
    document.getElementById(`step${stepNumber}-content`).style.display = 'block';
    document.getElementById(`step${stepNumber}`).classList.add('active');

    // Mark previous steps as completed
    for (let i = 1; i < stepNumber; i++) {
        document.getElementById(`step${i}`).classList.add('completed');
    }

    // Save progress
    if (stepNumber > 1) {
        const session = {
            sessionId: currentSessionId,
            userId: currentUserId,
            step: stepNumber,
            email: document.getElementById('email').value
        };
        localStorage.setItem('password_reset_session', JSON.stringify(session));
    } else {
        // If going back to step 1, clear the session
        localStorage.removeItem('password_reset_session');
        currentSessionId = '';
        currentUserId = 0;

        // Clear any timers
        clearInterval(otpTimer);
        clearInterval(resendTimer);

        // Reset OTP timer display
        document.getElementById('otp-timer').textContent = '';
        document.getElementById('resend-timer').style.display = 'none';
        document.getElementById('resend-link').classList.remove('disabled');

        // Clear error messages
        document.getElementById('email-error').style.display = 'none';
        document.getElementById('email-success').style.display = 'none';
        document.getElementById('otp-error').style.display = 'none';
        document.getElementById('answer-error').style.display = 'none';
        document.getElementById('answer-attempts').textContent = '';
        document.getElementById('confirm-error').style.display = 'none';

        // Clear input fields
        document.getElementById('otp').value = '';
        document.getElementById('security-question').value = '';
        document.getElementById('security-answer').value = '';
        document.getElementById('new-password').value = '';
        document.getElementById('confirm-password').value = '';

        // Re-enable any potentially disabled fields
        document.getElementById('otp').disabled = false;
        document.getElementById('verify-otp-btn').disabled = false;
        document.getElementById('security-question').disabled = false;
        document.getElementById('security-answer').disabled = false;
        document.getElementById('verify-answer-btn').disabled = false;
        document.getElementById('resend-link').classList.remove('disabled');

        // Reset password requirements
        resetPasswordRequirements();

        // Reset attempts counter
        answerAttempts = 0;

        // Enable all fields
        document.getElementById('security-question').disabled = false;
        document.getElementById('security-answer').disabled = false;
        document.getElementById('verify-answer-btn').disabled = false;
    }
}

// Step 1: Send OTP
function sendOTP() {
    const email = document.getElementById('email').value.trim();
    const emailError = document.getElementById('email-error');
    const emailSuccess = document.getElementById('email-success');

    // Reset messages
    emailError.style.display = 'none';
    emailSuccess.style.display = 'none';

    // Validate email
    if (!email) {
        emailError.textContent = 'Email is required';
        emailError.style.display = 'block';
        return;
    }

    if (!isValidEmail(email)) {
        emailError.textContent = 'Please enter a valid email address';
        emailError.style.display = 'block';
        return;
    }

    // Disable button
    const btn = document.getElementById('send-otp-btn');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    // Send request
    fetch('../php/forgot-password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email: email })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentSessionId = data.session_id;

                // Show success message
                emailSuccess.textContent = data.message;
                emailSuccess.style.display = 'block';

                // Move to step 2
                setTimeout(() => {
                    goToStep(2);
                    // Use actual remaining seconds from backend
                    startOTPTimer(data.remaining_seconds || 600);
                    startResendTimer(60);
                }, 1000);

            } else {
                emailError.textContent = data.message;
                emailError.style.display = 'block';

                // Check if email doesn't exist
                if (data.message.toLowerCase().includes('email not found') ||
                    data.message.toLowerCase().includes('no account found') ||
                    data.message.toLowerCase().includes('does not exist') ||
                    data.message.toLowerCase().includes('not registered')) {

                    // Offer to go back to email entry
                    setTimeout(() => {
                        emailError.innerHTML = data.message + '<br><br>' +
                            '<button onclick="clearEmailAndRetry()" style="background: #f0f0f0; border: 1px solid #ddd; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Try a different email</button> ' +
                            '<button onclick="window.location.href=\'register.php\'" style="background: #4CAF50; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Create new account</button>';
                    }, 100);
                }
            }
        })
        .catch(error => {
            emailError.textContent = 'Network error. Please try again.';
            emailError.style.display = 'block';
            console.error('Error:', error);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Send OTP';
        });
}

// Step 2: Verify OTP
function verifyOTP() {
    const otp = document.getElementById('otp').value.trim();
    const otpError = document.getElementById('otp-error');

    // Reset error
    otpError.style.display = 'none';

    // Validate OTP
    if (!otp) {
        otpError.textContent = 'Please enter the 6-digit OTP';
        otpError.style.display = 'block';
        return;
    }

    if (otp.length !== 6 || !/^[A-Z0-9]+$/i.test(otp)) {
        otpError.textContent = 'Please enter a valid 6-character OTP (letters and numbers allowed)';
        otpError.style.display = 'block';
        return;
    }

    // Disable button
    const btn = document.getElementById('verify-otp-btn');
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    // Send request
    fetch('../php/verify-otp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            session_id: currentSessionId,
            otp: otp
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentUserId = data.user_id;
                clearInterval(otpTimer);

                // Load security questions
                loadSecurityQuestions();

            } else {
                otpError.textContent = data.message;
                otpError.style.display = 'block';

                // Check if we should lock
                if (data.locked) {
                    // Use lock time from server response
                    lockOTPFields(data.lock_seconds);
                    return; // Don't continue to load security questions
                }

                // Check if session expired or invalid
                if (data.message.toLowerCase().includes('session') ||
                    data.message.toLowerCase().includes('expired') ||
                    data.message.toLowerCase().includes('invalid') ||
                    data.message.toLowerCase().includes('not found')) {

                    // Suggest going back to step 1
                    setTimeout(() => {
                        otpError.innerHTML = data.message + '<br><br>' +
                            '<button onclick="goBackToEmail()" style="background: #f0f0f0; border: 1px solid #ddd; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Try with a different email</button>';
                    }, 100);
                }
            }
        })
        .catch(error => {
            otpError.textContent = 'Network error. Please try again.';
            otpError.style.display = 'block';
            console.error('Error:', error);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Verify OTP';
        });
}

// Load security questions
function loadSecurityQuestions() {
    fetch('../php/get-questions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ user_id: currentUserId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('security-question');
                select.innerHTML = '<option value="">-- Select a question --</option>';

                data.questions.forEach(question => {
                    const option = document.createElement('option');
                    option.value = question.question_id;
                    option.textContent = question.question_text;
                    select.appendChild(option);
                });

                goToStep(3);
            } else {
                alert(data.message);

                // If no security questions found or user not found, go back to email
                if (data.message.toLowerCase().includes('no questions') ||
                    data.message.toLowerCase().includes('user not found') ||
                    data.message.toLowerCase().includes('not found')) {

                    setTimeout(() => {
                        if (confirm("Account verification failed. Please try with a different email.")) {
                            goToStep(1);
                            document.getElementById('email').value = '';
                            document.getElementById('email').focus();
                        }
                    }, 500);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load security questions');
        });
}

// Step 3: Verify security answer
function verifyAnswer() {
    const questionId = document.getElementById('security-question').value;
    const answer = document.getElementById('security-answer').value.trim();
    const answerError = document.getElementById('answer-error');
    const attemptsDisplay = document.getElementById('answer-attempts');

    // Reset error
    answerError.style.display = 'none';

    // Validate inputs
    if (!questionId) {
        answerError.textContent = 'Please select a security question';
        answerError.style.display = 'block';
        return;
    }

    if (!answer) {
        answerError.textContent = 'Please enter your answer';
        answerError.style.display = 'block';
        return;
    }

    // Disable button
    const btn = document.getElementById('verify-answer-btn');
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    // Send request
    fetch('../php/verify-answer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: currentUserId,
            question_id: questionId,
            answer: answer
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                answerAttempts = 0;
                attemptsDisplay.textContent = '';
                goToStep(4);
            } else {
                answerAttempts++;
                answerError.textContent = data.message;
                answerError.style.display = 'block';

                // Check for lockout from server
                if (data.lock_time && data.lock_time > 0) {
                    attemptsDisplay.textContent = 'Too many attempts. Account is locked.';
                    btn.disabled = true;

                    // Use database-provided lock time instead of hardcoded 1800
                    lockAnswerField(data.lock_time);

                    // Offer to go back to email entry
                    setTimeout(() => {
                        answerError.innerHTML = data.message + '<br><br>' +
                            '<button onclick="goBackToEmail()" style="background: #f0f0f0; border: 1px solid #ddd; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Try with a different email</button>';
                    }, 100);
                } else {
                    // Update attempts display for non-lockout failures
                    attemptsDisplay.textContent = data.message;
                }
            }
        })
        .catch(error => {
            answerError.textContent = 'Network error. Please try again.';
            answerError.style.display = 'block';
            console.error('Error:', error);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Verify Answer';
        });
}

// Step 4: Update password
function updatePassword() {
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    const confirmError = document.getElementById('confirm-error');

    // Reset error
    confirmError.style.display = 'none';

    // Validate passwords
    if (!newPassword || !confirmPassword) {
        confirmError.textContent = 'Both password fields are required';
        confirmError.style.display = 'block';
        return;
    }

    if (newPassword !== confirmPassword) {
        confirmError.textContent = 'Passwords do not match';
        confirmError.style.display = 'block';
        return;
    }

    // Check password strength
    if (!checkPasswordStrength()) {
        confirmError.textContent = 'Please meet all password requirements';
        confirmError.style.display = 'block';
        return;
    }

    // Disable button
    const btn = document.getElementById('update-password-btn');
    btn.disabled = true;
    btn.textContent = 'Updating...';

    // Send request
    fetch('../php/update-password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: currentUserId,
            new_password: newPassword,
            confirm_password: confirmPassword
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear local storage
                localStorage.removeItem('password_reset_session');

                // Show success
                document.getElementById('success-content').style.display = 'block';
                document.getElementById('step4-content').style.display = 'none';
            } else {
                confirmError.textContent = data.message;
                confirmError.style.display = 'block';

                // If user not found or session expired
                if (data.message.toLowerCase().includes('user not found') ||
                    data.message.toLowerCase().includes('session expired') ||
                    data.message.toLowerCase().includes('not found')) {

                    setTimeout(() => {
                        confirmError.innerHTML = data.message + '<br><br>' +
                            '<button onclick="goBackToEmail()" style="background: #f0f0f0; border: 1px solid #ddd; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Try with a different email</button>';
                    }, 100);
                }
            }
        })
        .catch(error => {
            confirmError.textContent = 'Network error. Please try again.';
            confirmError.style.display = 'block';
            console.error('Error:', error);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Update Password';
        });
}

// Resend OTP
function resendOTP() {
    const resendLink = document.getElementById('resend-link');
    const resendTimer = document.getElementById('resend-timer');

    if (resendLink.classList.contains('disabled')) {
        return;
    }

    // Disable link temporarily
    resendLink.classList.add('disabled');

    // Send request
    fetch('../php/forgot-password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email: document.getElementById('email').value,
            resend: true
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('New OTP sent to your email');
                startOTPTimer();
                startResendTimer(60); // 60 seconds cooldown
            } else {
                alert(data.message);

                // If email doesn't exist
                if (data.message.toLowerCase().includes('email not found') ||
                    data.message.toLowerCase().includes('not found')) {
                    if (confirm("Email not found. Would you like to enter a different email?")) {
                        goToStep(1);
                        document.getElementById('email').value = '';
                        document.getElementById('email').focus();
                    }
                }
            }
        })
        .catch(error => {
            alert('Failed to resend OTP');
            console.error('Error:', error);
        })
        .finally(() => {
            resendLink.classList.remove('disabled');
        });
}


// Modify startOTPTimer to accept remaining time
function startOTPTimer(initialTime = 600) {
    let timeLeft = initialTime; // Use fetched time instead of hardcoded 600
    const timerElement = document.getElementById('otp-timer');

    clearInterval(otpTimer);

    otpTimer = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;

        timerElement.textContent = `OTP expires in: ${minutes}:${seconds.toString().padStart(2, '0')}`;

        if (timeLeft <= 0) {
            clearInterval(otpTimer);
            timerElement.textContent = 'OTP has expired. Please request a new one.';
            timerElement.style.color = '#f44336';
        }

        timeLeft--;
    }, 1000);
}

function startResendTimer(seconds) {
    const resendLink = document.getElementById('resend-link');
    const resendTimerElement = document.getElementById('resend-timer');
    const countdownElement = document.getElementById('countdown');

    // Only disable resend link, not verification button
    resendLink.classList.add('disabled');
    resendTimerElement.style.display = 'block';
    countdownElement.textContent = seconds;

    clearInterval(resendTimer);

    resendTimer = setInterval(() => {
        seconds--;
        countdownElement.textContent = seconds;

        if (seconds <= 0) {
            clearInterval(resendTimer);
            resendLink.classList.remove('disabled');
            resendTimerElement.style.display = 'none';
        }
    }, 1000);
}

function lockResend(seconds) {
    const resendLink = document.getElementById('resend-link');
    const resendTimerElement = document.getElementById('resend-timer');
    const countdownElement = document.getElementById('countdown');
    const verifyOTPBtn = document.getElementById('verify-otp-btn');

    resendLink.classList.add('disabled');
    if (verifyOTPBtn) verifyOTPBtn.disabled = true;
    resendTimerElement.style.display = 'block';

    clearInterval(resendTimer);

    resendTimer = setInterval(() => {
        seconds--;
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        countdownElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;

        if (seconds <= 0) {
            clearInterval(resendTimer);
            resendLink.classList.remove('disabled');
            if (verifyOTPBtn) verifyOTPBtn.disabled = false; // ADD THIS LINE
            resendTimerElement.style.display = 'none';
        }
    }, 1000);
}

function lockOTPFields(seconds) {
    const otpInput = document.getElementById('otp');
    const verifyOTPBtn = document.getElementById('verify-otp-btn');
    const resendLink = document.getElementById('resend-link');
    const otpError = document.getElementById('otp-error');
    const resendTimerElement = document.getElementById('resend-timer');
    const countdownElement = document.getElementById('countdown');

    // Disable all OTP-related fields
    if (otpInput) otpInput.disabled = true;
    if (verifyOTPBtn) verifyOTPBtn.disabled = true;
    if (resendLink) resendLink.classList.add('disabled');

    // Show lock message and timer
    if (resendTimerElement) {
        resendTimerElement.style.display = 'block';
    }

    const lockTimer = setInterval(() => {
        seconds--;
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;

        // Update both error message and timer display
        if (otpError) {
            otpError.textContent = `Maximum attempts reached. Please try again in ${minutes}:${secs.toString().padStart(2, '0')}`;
            otpError.style.display = 'block';
        }

        if (countdownElement) {
            countdownElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
        }

        if (seconds <= 0) {
            clearInterval(lockTimer);

            // Re-enable everything
            if (otpInput) {
                otpInput.disabled = false;
                otpInput.value = '';
                otpInput.focus();
            }
            if (verifyOTPBtn) verifyOTPBtn.disabled = false;
            if (resendLink) resendLink.classList.remove('disabled');
            if (otpError) otpError.style.display = 'none';
            if (resendTimerElement) resendTimerElement.style.display = 'none';
        }
    }, 1000);
}

function lockAnswerField(seconds) {
    const questionSelect = document.getElementById('security-question');
    const answerInput = document.getElementById('security-answer');
    const verifyBtn = document.getElementById('verify-answer-btn');
    const attemptsDisplay = document.getElementById('answer-attempts');

    questionSelect.disabled = true;
    answerInput.disabled = true;
    verifyBtn.disabled = true;

    const lockTimer = setInterval(() => {
        seconds--;
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;

        // Fix: Use Math.floor() to remove decimals from seconds
        const displaySecs = Math.floor(secs);
        attemptsDisplay.textContent = `Locked. Try again in ${minutes}:${displaySecs.toString().padStart(2, '0')}`;

        if (seconds <= 0) {
            clearInterval(lockTimer);
            questionSelect.disabled = false;
            answerInput.disabled = false;
            verifyBtn.disabled = false;
            answerAttempts = 0;
            attemptsDisplay.textContent = '';

            answerInput.value = '';

            // Offer to go back to email entry
            if (confirm("Lockout period ended. Would you like to try with a different email?")) {
                goToStep(1);
                document.getElementById('email').value = '';
                document.getElementById('email').focus();
            }
        }
    }, 1000);
}

// Password validation
function checkPasswordStrength() {
    const password = document.getElementById('new-password').value;

    // Length check
    const lengthCheck = document.getElementById('length-check');
    const hasLength = password.length >= 8;
    lengthCheck.className = hasLength ? 'requirement met' : 'requirement not-met';

    // Uppercase check
    const uppercaseCheck = document.getElementById('uppercase-check');
    const hasUppercase = /[A-Z]/.test(password);
    uppercaseCheck.className = hasUppercase ? 'requirement met' : 'requirement not-met';

    // Lowercase check
    const lowercaseCheck = document.getElementById('lowercase-check');
    const hasLowercase = /[a-z]/.test(password);
    lowercaseCheck.className = hasLowercase ? 'requirement met' : 'requirement not-met';

    // Number check
    const numberCheck = document.getElementById('number-check');
    const hasNumber = /[0-9]/.test(password);
    numberCheck.className = hasNumber ? 'requirement met' : 'requirement not-met';

    return hasLength && hasUppercase && hasLowercase && hasNumber;
}

function resetPasswordRequirements() {
    const requirements = ['length-check', 'uppercase-check', 'lowercase-check', 'number-check'];
    requirements.forEach(id => {
        document.getElementById(id).className = 'requirement not-met';
    });
}

function checkPasswordMatch() {
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    const confirmError = document.getElementById('confirm-error');

    if (confirmPassword && newPassword !== confirmPassword) {
        confirmError.textContent = 'Passwords do not match';
        confirmError.style.display = 'block';
        return false;
    } else {
        confirmError.style.display = 'none';
        return true;
    }
}

// Utility functions
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Navigation functions
function goBackToEmail() {
    if (confirm("Are you sure you want to go back to email entry? This will cancel the current reset process.")) {
        goToStep(1);
        document.getElementById('email').value = '';
        document.getElementById('email').focus();
    }
}

function clearEmailAndRetry() {
    document.getElementById('email').value = '';
    document.getElementById('email').focus();
    document.getElementById('email-error').style.display = 'none';
}

function lockOTPVerification(seconds) {
    const otpInput = document.getElementById('otp');
    const verifyOTPBtn = document.getElementById('verify-otp-btn');
    const resendLink = document.getElementById('resend-link');
    const otpError = document.getElementById('otp-error');

    // Disable OTP input and verification button
    otpInput.disabled = true;
    verifyOTPBtn.disabled = true;
    resendLink.classList.add('disabled');

    // Show lock message
    const lockTimer = setInterval(() => {
        seconds--;
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;

        otpError.textContent = `Maximum attempts reached. Please try again in ${minutes}:${secs.toString().padStart(2, '0')}`;
        otpError.style.display = 'block';

        if (seconds <= 0) {
            clearInterval(lockTimer);
            // Re-enable everything
            otpInput.disabled = false;
            verifyOTPBtn.disabled = false;
            resendLink.classList.remove('disabled');
            otpError.style.display = 'none';
            otpInput.value = '';
            otpInput.focus();

            // Also reset the resend timer if needed
            startResendTimer(60);
        }
    }, 1000);
}