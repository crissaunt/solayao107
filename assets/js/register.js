const form = document.getElementById('form');
const id = document.getElementById('id');
const email = document.getElementById('email');
const contact_number = document.getElementById('contact_number');
const username = document.getElementById('username');
const fname = document.getElementById('fname');
const mname = document.getElementById('mname');
const lname = document.getElementById('lname');
const extend_name = document.getElementById('extend_name');
const birthday = document.getElementById('birthday');
const age = document.getElementById('age');
const gender = document.getElementById('gender');
const password = document.getElementById('password');
const repassword = document.getElementById('repassword');
const street_purok = document.getElementById('street_purok');
const barangay = document.getElementById('barangay');
const city_municipal = document.getElementById('city_municipal');
const province = document.getElementById('province');
const country = document.getElementById('country');
const zipcode = document.getElementById('zipcode');

const security_question1 = document.getElementById('security_question1');
const security_answer1 = document.getElementById('security_answer1');
const security_question2 = document.getElementById('security_question2');
const security_answer2 = document.getElementById('security_answer2');
const security_question3 = document.getElementById('security_question3');
const security_answer3 = document.getElementById('security_answer3');

age.setAttribute('readonly', true);

// Create loading overlay
const loadingOverlay = document.createElement('div');
loadingOverlay.id = 'loading-overlay';
loadingOverlay.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.9);
    display: none;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    z-index: 9999;
    backdrop-filter: blur(2px);
`;

// Create loading spinner
const loadingSpinner = document.createElement('div');
loadingSpinner.id = 'loading-spinner';
loadingSpinner.style.cssText = `
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
`;

// Create loading text
const loadingText = document.createElement('p');
loadingText.id = 'loading-text';
loadingText.textContent = 'Processing your registration...';
loadingText.style.cssText = `
    font-family: Arial, sans-serif;
    font-size: 16px;
    color: #333;
    text-align: center;
`;

// Add CSS animation for spinner
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;

// Append elements to document
document.head.appendChild(style);
loadingOverlay.appendChild(loadingSpinner);
loadingOverlay.appendChild(loadingText);
document.body.appendChild(loadingOverlay);

// Loading functions
function showLoading(message = 'Processing your registration...') {
    loadingText.textContent = message;
    loadingOverlay.style.display = 'flex';
    // Disable form inputs while loading
    const inputs = form.querySelectorAll('input, select, button');
    inputs.forEach(input => {
        input.disabled = true;
    });
}

function hideLoading() {
    loadingOverlay.style.display = 'none';
    // Re-enable form inputs
    const inputs = form.querySelectorAll('input, select, button');
    inputs.forEach(input => {
        input.disabled = false;
    });
}

form.addEventListener('submit', async (event) => {
    event.preventDefault(); // Prevent the default form submission behavior

    console.log('=== FORM SUBMISSION START ===');
    
    // Test: Check if security questions have values
    console.log('Security Q1 value:', security_question1.value, 'Type:', typeof security_question1.value);
    console.log('Security A1 value:', security_answer1.value);
    console.log('Security Q2 value:', security_question2.value);
    console.log('Security A2 value:', security_answer2.value);
    console.log('Security Q3 value:', security_question3.value);
    console.log('Security A3 value:', security_answer3.value);
    
    console.log('Calling validateInputs()...');
    const isValid = validateInputs();
    console.log('validateInputs() returned:', isValid);
    
    if (isValid) {
        console.log('Validation passed, creating FormData...');
        showLoading('Preparing registration data...');
        
        // Debug: Check if security elements are in the form
        console.log('Form contains security_question1?', form.contains(security_question1));
        console.log('Form contains security_answer1?', form.contains(security_answer1));
        
        // Create FormData from the form
        const formData = new FormData(form);
        
        // Debug: Log ALL FormData entries
        console.log('=== FORMDATA CONTENTS ===');
        const formDataEntries = [];
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
            formDataEntries[pair[0]] = pair[1];
        }
        console.log('=== END FORMDATA ===');
        
        // Check specific fields
        console.log('FormData has security_question1?', formData.has('security_question1'));
        console.log('FormData security_question1 value:', formData.get('security_question1'));
        console.log('FormData has security_answer1?', formData.has('security_answer1'));
        console.log('FormData security_answer1 value:', formData.get('security_answer1'));
        
        // Check if ALL form fields are present in FormData
        const requiredFields = [
            'id', 'email', 'contact_number', 'username', 'fname', 'lname', 
            'birthday', 'age', 'sex', 'password', 'repassword',
            'street_purok', 'barangay', 'city_municipal', 'province', 'country', 'zipcode',
            'security_question1', 'security_answer1', 'security_question2', 'security_answer2',
            'security_question3', 'security_answer3'
        ];
        
        let allFieldsPresent = true;
        for (const field of requiredFields) {
            if (!formData.has(field)) {
                console.log(`ERROR: FormData missing field: ${field}`);
                allFieldsPresent = false;
            }
        }
        
        if (!allFieldsPresent) {
            console.log('WARNING: Some fields missing from FormData, creating manual FormData');
            // Create a new FormData with all fields
            const manualFormData = new FormData();
            
            // Add all required fields manually
            const fieldsToAdd = [
                {name: 'id', value: id.value},
                {name: 'email', value: email.value},
                {name: 'contact_number', value: contact_number.value},
                {name: 'username', value: username.value},
                {name: 'fname', value: fname.value},
                {name: 'mname', value: mname.value},
                {name: 'lname', value: lname.value},
                {name: 'extend_name', value: extend_name.value},
                {name: 'birthday', value: birthday.value},
                {name: 'age', value: age.value},
                {name: 'sex', value: gender.value},
                {name: 'password', value: password.value},
                {name: 'repassword', value: repassword.value},
                {name: 'street_purok', value: street_purok.value},
                {name: 'barangay', value: barangay.value},
                {name: 'city_municipal', value: city_municipal.value},
                {name: 'province', value: province.value},
                {name: 'country', value: country.value},
                {name: 'zipcode', value: zipcode.value},
                {name: 'security_question1', value: parseInt(security_question1.value) || 0},
                {name: 'security_answer1', value: security_answer1.value},
                {name: 'security_question2', value: parseInt(security_question2.value) || 0},
                {name: 'security_answer2', value: security_answer2.value},
                {name: 'security_question3', value: parseInt(security_question3.value) || 0},
                {name: 'security_answer3', value: security_answer3.value}
            ];
            
            fieldsToAdd.forEach(field => {
                if (field.value !== undefined && field.value !== null) {
                    manualFormData.append(field.name, field.value);
                }
            });
            
            // Use the manual FormData instead
            console.log('=== MANUAL FORMDATA CONTENTS ===');
            for (let pair of manualFormData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            console.log('=== END MANUAL FORMDATA ===');
            
            // Use the manual form data for submission
            await submitRegistration(manualFormData);
        } else {
            // Use the original form data
            await submitRegistration(formData);
        }
    } else {
        console.log('Validation failed - check error messages on form');
        // No need for hideLoading() here since we never showed it
    }
    
    console.log('=== FORM SUBMISSION END ===');
});

// Separate function for registration submission
async function submitRegistration(formData) {
    // Test: Create a simple test object to send
    const testData = {
        id: formData.get('id'),
        email: formData.get('email'),
        username: formData.get('username'),
        fname: formData.get('fname'),
        lname: formData.get('lname'),
        birthday: formData.get('birthday'),
        age: formData.get('age'),
        sex: formData.get('sex'),
        password: formData.get('password'),
        street_purok: formData.get('street_purok'),
        barangay: formData.get('barangay'),
        city_municipal: formData.get('city_municipal'),
        province: formData.get('province'),
        country: formData.get('country'),
        zipcode: formData.get('zipcode'),
        security_question1: formData.get('security_question1'),
        security_answer1: formData.get('security_answer1'),
        security_question2: formData.get('security_question2'),
        security_answer2: formData.get('security_answer2'),
        security_question3: formData.get('security_question3'),
        security_answer3: formData.get('security_answer3')
    };
    
    console.log('Test data to send:', testData);
    
    // First test: Send to a test endpoint
    console.log('Sending to test endpoint first...');
    showLoading('Testing connection...');
    
    
    // Then try the real registration
    console.log('Now sending to register.php...');
    showLoading('Creating your account...');
    
    try {
        const registerResponse = await fetch('../php/register.php', {
            method: 'POST',
            body: formData
        });

        console.log('Response status:', registerResponse.status);
        
        const responseText = await registerResponse.text();
        console.log('Raw response from register.php:', responseText);
        
        try {
            const registerMessage = JSON.parse(responseText);
            console.log('Parsed response:', registerMessage);
            
            if (registerMessage.status === 'error') {
                console.error('Server error:', registerMessage.message);
                hideLoading();
                
                // Set errors on specific fields
                if (registerMessage.message.includes('ID')) setError(id, registerMessage.message);
                if (registerMessage.message.includes('Email')) setError(email, registerMessage.message);
                if (registerMessage.message.includes('Contact number')) setError(contact_number, registerMessage.message);
                if (registerMessage.message.includes('Username')) setError(username, registerMessage.message);
                
            } else if (registerMessage.status === 'success') {
                console.log('Success!', registerMessage.message);
                showLoading('Account created successfully! Redirecting...');
                
                // Show success for a moment before redirecting
                setTimeout(() => {
                    hideLoading();
                    resetForm();
                    window.location.href = 'login.php';
                }, 1500);
            }
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            console.error('Response text was:', responseText);
            hideLoading();
            alert('Invalid server response. Check console for details.');
        }

    } catch (error) {
        console.error('Network error:', error);
        hideLoading();
        alert('An error occurred during registration. Please check your internet connection.');
    }
}

function resetForm(){
    document.getElementById('form').reset();
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

const setSecurityError = (elementId, message) => {
    const errorElement = document.getElementById(elementId + '_error');
    if (errorElement) {
        errorElement.innerText = message;
        errorElement.style.color = '#dc3545';
        errorElement.style.fontSize = '12px';
        errorElement.style.marginTop = '3px';
        
        // Also add error class to the parent container
        const parentDiv = errorElement.closest('.input-box');
        if (parentDiv) {
            parentDiv.classList.add('error');
            parentDiv.classList.remove('success');
        }
    }
};

// Helper function to set success for security question elements
const setSecuritySuccess = (elementId) => {
    const errorElement = document.getElementById(elementId + '_error');
    if (errorElement) {
        errorElement.innerText = '';
        
        // Remove error class from parent container
        const parentDiv = errorElement.closest('.input-box');
        if (parentDiv) {
            parentDiv.classList.remove('error');
            parentDiv.classList.add('success');
        }
    }
};

const setError = (element, message) => {
    const inputControl = element.parentElement;
    const errorDisplay = inputControl.querySelector('.error');

    errorDisplay.innerText = message;
    inputControl.classList.add('error');    
    inputControl.classList.remove('success');
}   

const setSuccess = (element) => {
    const inputControl = element.parentElement;
    const errorDisplay = inputControl.querySelector('.error');

    errorDisplay.innerText = ''; 
    inputControl.classList.add('success');
    inputControl.classList.remove('error');
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

function showStrength(password) {
    const uppercase = /[A-Z]/;
    const lowercase = /[a-z]/;
    const number = /[0-9]/;
    const specialChars = /[!@#$%^&*(),.?":{}|<>]/;

    let score = 0;

    if (password.length >= 8) {
        if (uppercase.test(password)) score++;
        if (lowercase.test(password)) score++;
        if (number.test(password)) score++;
        if (specialChars.test(password)) score++;
        if (password.length >= 12) score++;
    }

    let strength = 'Weak';
    if (score === 5) {
        strength = 'Superstrong';
    } else if (score >= 4) {
        strength = 'Strong';
    } else if (score >= 3) {
        strength = 'Medium';
    }

    return strength;
}

// Update the password strength meter
const passwordInput = document.getElementById('password');
const strengthMeter = document.getElementById('strength-meter');
const strengthText = document.getElementById('strength-text');

passwordInput.addEventListener('input', () => {
    const password = passwordInput.value;
    const strength = showStrength(password);

    strengthText.innerText = `Password Strength: ${strength}`;
    
    strengthMeter.className = 'strength-meter'; // Reset meter class
    if (strength === 'Weak') {
        strengthMeter.classList.add('strength-weak');
    } else if (strength === 'Medium') {
        strengthMeter.classList.add('strength-medium');
    } else if (strength === 'Strong') {
        strengthMeter.classList.add('strength-strong');
    } else if (strength === 'Superstrong') {
        strengthMeter.classList.add('strength-superstrong');
    }
});

function calculateBirthdate() {
    // Get the age input value
    const ageInput = document.getElementById('age').value;

    if (!ageInput || ageInput < 0) {
        document.getElementById('birthday').value = "";
        return;
    }

    // Parse the age as an integer
    const age = parseInt(ageInput, 10);

    // Get the current date
    const currentDate = new Date();

    // Calculate the approximate birth year
    let birthYear = currentDate.getFullYear() - age;

    // Adjust the birth year if the birthday hasn't occurred yet this year
    const monthDiff = currentDate.getMonth(); // Current month (0-11)
    const dayDiff = currentDate.getDate();   // Current day of the month

    // Assume the user was born on January 1st of the calculated year initially
    let birthMonth = 0; // January
    let birthDay = 1;

    if (monthDiff === 0 && dayDiff === 0) {
        birthYear--;
    }

    // Update the birthdate field
    const birthdate = new Date(birthYear, birthMonth, birthDay);
    document.getElementById('birthday').value = birthdate.toISOString().slice(0, 10);
}

function calculateAge() {
    // Get the input birthdate value
    const birthdateInput = document.getElementById('birthday').value;

    if (!birthdateInput) {
        document.getElementById('age').value = "Please select a birthdate.";
        return;
    }

    // Parse the birthdate input to a Date object
    const birthdate = new Date(birthdateInput);

    // Get the current date
    const currentDate = new Date();

    // Calculate the difference in years
    let age = currentDate.getFullYear() - birthdate.getFullYear();
    const monthDiff = currentDate.getMonth() - birthdate.getMonth();

    // Adjust age if the birthday hasn't occurred yet this year
    if (monthDiff < 0 || (monthDiff === 0 && currentDate.getDate() < birthdate.getDate())) {
        age--;
    }

    // Update the age input field
    document.getElementById('age').value = age;
}

const validateInputs = () => {
    
    const capitalizeWords = (str) => {
        return str
            .toLowerCase() // Normalize to lowercase
            .replace(/\b\w/g, char => char.toUpperCase()) // Capitalize first letter
            .replace(/\s{2,}/g, ' '); // Remove extra spaces
    };
    
    const idValue = id.value.trim(); 
    const emailValue = email.value.trim();
    const contact_numberValue = contact_number.value.trim();
    const usernameValue = username.value;
    const fnameValue = capitalizeWords(fname.value.trim());
    const mnameValue = capitalizeWords(mname.value.trim());
    const lnameValue = capitalizeWords(lname.value.trim());
    const extend_nameValue = extend_name.value.trim();
    const birthdayValue = birthday.value.trim();
    const ageValue = parseInt(age.value.trim()) || 0; // Convert to number
    const genderValue = gender.value.trim();
    const passwordValue = password.value.trim();
    const repasswordValue = repassword.value.trim();
    const street_purokValue = capitalizeWords(street_purok.value.trim());
    const barangayValue = capitalizeWords(barangay.value.trim());
    const city_municipalValue = capitalizeWords(city_municipal.value.trim());
    const provinceValue = capitalizeWords(province.value.trim());
    const countryValue = capitalizeWords(country.value.trim());
    const zipcodeValue = zipcode.value.trim();

    // Security questions values - ALWAYS convert to numbers
    const securityQ1Value = parseInt(security_question1.value) || 0;
    const securityA1Value = security_answer1.value.trim();
    const securityQ2Value = parseInt(security_question2.value) || 0;
    const securityA2Value = security_answer2.value.trim();
    const securityQ3Value = parseInt(security_question3.value) || 0;
    const securityA3Value = security_answer3.value.trim();

    console.log('=== VALIDATION START ===');
    console.log('ID Value:', idValue);
    console.log('Email Value:', emailValue);
    console.log('Age Value:', ageValue, '(Type:', typeof ageValue, ')');
    console.log('mnameValue:', mnameValue);
    console.log('mnameValue empty?', mnameValue === '');
    console.log('Security Q1:', securityQ1Value);
    console.log('Security Q2:', securityQ2Value);
    console.log('Security Q3:', securityQ3Value);

    let isValid = true;
    
    // Clear general security error
    const generalError = document.getElementById('security_general_error');
    if (generalError) {
        generalError.innerText = '';
    }
    
    // SECURITY QUESTIONS VALIDATION
    if (securityQ1Value === 0) {
        setSecurityError('security_question1', 'Security question 1 is required');
        isValid = false;
    } else {
        setSecuritySuccess('security_question1');
    }

    if (!securityA1Value) {
        setSecurityError('security_answer1', 'Answer 1 is required');
        isValid = false;
    } else if (securityA1Value.length < 2 || securityA1Value.length > 50) {
        setSecurityError('security_answer1', 'Answer must be between 2 and 50 characters');
        isValid = false;
    } else {
        setSecuritySuccess('security_answer1');
    }

    if (securityQ2Value === 0) {
        setSecurityError('security_question2', 'Security question 2 is required');
        isValid = false;
    } else {
        setSecuritySuccess('security_question2');
    }

    if (!securityA2Value) {
        setSecurityError('security_answer2', 'Answer 2 is required');
        isValid = false;
    } else if (securityA2Value.length < 2 || securityA2Value.length > 50) {
        setSecurityError('security_answer2', 'Answer must be between 2 and 50 characters');
        isValid = false;
    } else {
        setSecuritySuccess('security_answer2');
    }

    if (securityQ3Value === 0) {
        setSecurityError('security_question3', 'Security question 3 is required');
        isValid = false;
    } else {
        setSecuritySuccess('security_question3');
    }

    if (!securityA3Value) {
        setSecurityError('security_answer3', 'Answer 3 is required');
        isValid = false;
    } else if (securityA3Value.length < 2 || securityA3Value.length > 50) {
        setSecurityError('security_answer3', 'Answer must be between 2 and 50 characters');
        isValid = false;
    } else {
        setSecuritySuccess('security_answer3');
    }

    // Check if questions are unique
    if (securityQ1Value > 0 && securityQ2Value > 0 && securityQ3Value > 0) {
        if (securityQ1Value === securityQ2Value || 
            securityQ1Value === securityQ3Value || 
            securityQ2Value === securityQ3Value) {
            if (generalError) {
                generalError.innerText = 'Please select three different security questions';
                generalError.style.color = '#dc3545';
            }
            isValid = false;
        }
    }

    // AGE VALIDATION
    if(ageValue === 0){
        setError(age, 'Age is required');
        isValid = false;
    } else if (ageValue < 18) {
        setError(age, 'You must be at least 18 years old to register');
        isValid = false;
    } else {
        setSuccess(age);
    }

    // ZIPCODE VALIDATION
    if (zipcodeValue === '') {
        setError(zipcode, 'ZIP Code is required');
        isValid = false;
    } else if (!/^\d{4}$/.test(zipcodeValue)) { 
        setError(zipcode, 'ZIP Code must be exactly 4 digits');
        isValid = false;
    } else {
        setSuccess(zipcode);
    }

    // COUNTRY VALIDATION 
    if (countryValue === '') {
        setError(country, 'Country is required');
        isValid = false;
    } else if (countryValue.length < 2 || countryValue.length > 30) {
        setError(country, 'Country must be between 2 and 30 characters');
        isValid = false;
    } else if(/(.)\1\1/.test(countryValue)){
        setError(country, 'Three consecutive identical letters are not allowed');
        isValid = false;
    } else if (!/^[A-Za-z\s]+$/.test(countryValue)) {
        setError(country, 'This field must contain only letters');
        isValid = false;
    } else {
        setSuccess(country);
    }

    // PROVINCE VALIDATION 
    if (provinceValue === '') {
        setError(province, 'Province is required');
        isValid = false;
    } else if (provinceValue.length < 2 || provinceValue.length > 30) {
        setError(province, 'Province must be between 2 and 30 characters');
        isValid = false;
    } else if(/(.)\1\1/.test(provinceValue)){
        setError(province, 'Three consecutive identical letters are not allowed');
        isValid = false;
    } else if (!/^[A-Za-z\s]+$/.test(provinceValue)) {
        setError(province, 'This field must contain only letters');
        isValid = false;
    } else {
        setSuccess(province);
    }

    // CITY/MUNICIPAL VALIDATION 
    if (city_municipalValue === '') {
        setError(city_municipal, 'City or Municipality is required');
        isValid = false;
    } else if (city_municipalValue.length < 2 || city_municipalValue.length > 30) {
        setError(city_municipal, 'City/Municipal must be between 2 and 30 characters');
        isValid = false;
    } else if(/(.)\1\1/.test(city_municipalValue)){
        setError(city_municipal, 'Three consecutive identical letters are not allowed');
        isValid = false;
    } else if (!/^[A-Za-z\s]+$/.test(city_municipalValue)) {
        setError(city_municipal, 'This field must contain only letters');
        isValid = false;
    } else {
        setSuccess(city_municipal);
    }

    // BARANGAY VALIDATION 
    if (barangayValue === '') {
        setError(barangay, 'Barangay is required');
        isValid = false;
    } else if (barangayValue.length < 2 || barangayValue.length > 30) {
        setError(barangay, 'Barangay must be between 2 and 30 characters');
        isValid = false;
    } else if(/(.)\1\1/.test(barangayValue)){
        setError(barangay, 'Three consecutive identical letters are not allowed');
        isValid = false;
    } else {
        setSuccess(barangay);
    }
    
    // STREET/PUROK VALIDATION 
    if (street_purokValue === '') {
        setError(street_purok, 'Street/Purok is required');
        isValid = false;
    } else if (street_purokValue.length < 2 || street_purokValue.length > 30) {
        setError(street_purok, 'Street/Purok must be between 2 and 30 characters');
        isValid = false;
    } else if(/(.)\1\1/.test(street_purokValue)){
        setError(street_purok, 'Three consecutive identical letters are not allowed');
        isValid = false;
    } else {
        setSuccess(street_purok);
    }

    // PASSWORD VALIDATION
    if (passwordValue === '') {
        setError(password, 'Password is required');
        setError(repassword, 'Password is required');
        isValid = false;
    } else if (passwordValue.length < 8 || passwordValue.length > 30) {
        setError(password, 'Password must be between 8 and 30 characters');
        setError(repassword, 'Password must be between 8 and 30 characters');
        isValid = false;
    } else {
        const uppercaseCount = (passwordValue.match(/[A-Z]/g) || []).length;
        const lowercaseCount = (passwordValue.match(/[a-z]/g) || []).length;
        const numberCount = (passwordValue.match(/[0-9]/g) || []).length;
        const specialCount = (passwordValue.match(/[!@#$%^&*(),.?":{}|<>]/g) || []).length;
        
        if(uppercaseCount < 2) {
            setError(password, 'Password must contain at least two (2) uppercase letters');
            setError(repassword, 'Password must contain at least two (2) uppercase letters');
            isValid = false;
        } else if(lowercaseCount < 2) {
            setError(password, 'Password must contain at least two (2) lowercase letters');
            setError(repassword, 'Password must contain at least two (2) lowercase letters');
            isValid = false;
        } else if(numberCount < 2) {
            setError(password, 'Password must contain at least two (2) numbers');
            setError(repassword, 'Password must contain at least two (2) numbers');
            isValid = false;
        } else if(specialCount < 2) {
            setError(password, 'Password must contain at least two (2) special characters');
            setError(repassword, 'Password must contain at least two (2) special characters');
            isValid = false;
        } else if (passwordValue !== repasswordValue) {
            setError(password, 'Passwords do not match');
            setError(repassword, 'Passwords do not match');
            isValid = false;
        } else {
            setSuccess(password);
            setSuccess(repassword);
        }
    }
    
    // GENDER VALIDATION
    if(genderValue === ''){
        setError(gender, 'Sex is required');
        isValid = false;
    } else {
        setSuccess(gender);
    }

    // BIRTHDAY VALIDATION
    if (birthdayValue === '') {
        setError(birthday, 'Birthday is required');
        isValid = false;
    } else if (isNaN(new Date(birthdayValue).getTime())) {
        setError(birthday, 'Please enter a valid date (YYYY-MM-DD)');
        isValid = false;
    } else {
        // Calculate age based on birthday
        const birthDate = new Date(birthdayValue);
        const today = new Date();
        let ageCalcu = today.getFullYear() - birthDate.getFullYear();

        // Adjust age if birthday hasn't occurred this year
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            ageCalcu--;
        }

        if (ageCalcu < 18) {
            setError(birthday, 'You must be at least 18 years old to register');
            setError(age, 'You must be at least 18 years old to register');
            isValid = false;
        } else {
            setSuccess(age);
            setSuccess(birthday);
        }
    }

    // EXTENSION NAME VALIDATION
    if (extend_nameValue !== '') {
        if (!/^(Sr|Jr|Senior|Junior|[IVXLCDM]+)$/i.test(extend_nameValue)) {
            setError(extend_name, 'Enter a valid extension name (Sr, Jr, or Roman numerals)');
            isValid = false;
        } else if (extend_nameValue.length <= 0 || extend_nameValue.length > 30) {
            setError(extend_name, 'Extension name must be between 0 and 30 characters');
            isValid = false;
        } else if(extend_nameValue.toLowerCase() === 'sr' || extend_nameValue.toLowerCase() === 'jr') {
            setError(extend_name, 'Sr and Jr must be capitalized');
            isValid = false;
        } else if(!/^[IVXLCDM]+$/i.test(extend_nameValue) && /[IVXLCDM]/i.test(extend_nameValue)) {
            setError(extend_name, 'Invalid Roman numeral format');
            isValid = false;
        } else if(/\s/.test(extend_nameValue)) {
            setError(extend_name, 'No spaces allowed');
            isValid = false;
        } else {
            setSuccess(extend_name);
        }
    } else {
        setSuccess(extend_name); 
    }
    
    // LAST NAME VALIDATION
    if (lnameValue === '') {
        setError(lname, 'Last Name is required');
        isValid = false;
    } else if (lnameValue.length <= 1 || lnameValue.length >= 30) {
        setError(lname, 'Last Name must be between 1 and 30 characters');
        isValid = false;
    } else if (!/^[A-Za-z\s]+$/.test(lnameValue)) {
        setError(lname, 'Last Name must contain only letters');
        isValid = false;
    } else if (/(.)\1{2,}/.test(lnameValue)) {
        setError(lname, 'Three consecutive identical letters are not allowed');
        isValid = false;
    } else {
        setSuccess(lname);
    }

    // MIDDLE NAME VALIDATION - FIXED
    if (mnameValue !== '') {
        if (mnameValue.length <= 1 || mnameValue.length >= 30) {
            setError(mname, 'Middle Name must be between 1 and 30 characters');
            isValid = false;
        } else if (!/^[A-Za-z\s]+$/.test(mnameValue)) {
            setError(mname, 'Middle Name must contain only letters');
            isValid = false;
        } else if (/([a-zA-Z])\1\1/.test(mnameValue)) {
            setError(mname, 'Three consecutive identical letters are not allowed');
            isValid = false;
        } else {
            setSuccess(mname);
        }
    } else {
        setSuccess(mname);
        // Do NOT set isValid = false here - middle name is optional
    }

    // FIRST NAME VALIDATION
    if(fnameValue === ''){
        setError(fname, 'First Name is required');
        isValid = false;
    } else if(fnameValue.length <= 1 || fnameValue.length >= 30) {
        setError(fname, 'First Name must be between 1 and 30 characters');
        isValid = false;
    } else if(!/^[A-Za-z\s]+$/.test(fnameValue)) {
        setError(fname, 'First Name must contain only letters');
        isValid = false;
    } else if(/(.)\1\1/.test(fnameValue)) {
        setError(fname, 'Three consecutive identical letters are not allowed');
        isValid = false;
    } else {
        setSuccess(fname);
    }

    // USERNAME VALIDATION
    if(usernameValue === '') {
        setError(username, 'Username is required');     
        isValid = false;
    } else if(usernameValue.length <= 1 || usernameValue.length >= 15) {
        setError(username, 'Username must be between 1 and 15 characters');
        isValid = false;
    } else if(/(.)\1\1/.test(usernameValue)) {
        setError(username, 'Three consecutive identical characters are not allowed');
        isValid = false;
    } else if(/[A-Z]/.test(usernameValue)) {
        setError(username, 'No capital letters allowed');
        isValid = false;
    } else if(/\s/.test(usernameValue)) {
        setError(username, 'No spaces allowed');
        isValid = false;
    } else if(!/^(?![0-9])/.test(usernameValue)) {
        setError(username, 'Username cannot start with a number');
        isValid = false;
    } else {
        setSuccess(username);
    }

    // CONTACT NUMBER VALIDATION
    if(contact_numberValue === '') {
        setError(contact_number, 'Contact Number is required');
        isValid = false;
    } else if(!/^(09\d{9}|(\+639)\d{9})$/.test(contact_numberValue)) {
        setError(contact_number, 'Please enter a valid contact number');
        isValid = false;
    } else {
        setSuccess(contact_number);
    }

    // EMAIL VALIDATION
    if(emailValue === '') {
        setError(email, 'Email is required');
        isValid = false;
    } else if(!/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(emailValue)) {
        setError(email, 'Please enter a valid email address');
        isValid = false;
    } else {
        setSuccess(email);
    }

    // ID VALIDATION
    if(idValue === '') {
        setError(id, 'ID Number is required');
        isValid = false;
    } else if (idValue.length < 6 || idValue.length > 11) {
        setError(id, 'ID Number must be between 6 and 11 characters');
        isValid = false;
    } else if (!/^\d{4}-\d{4}$/.test(idValue)) {
        setError(id, 'ID Number must be in the format xxxx-xxxx');
        isValid = false;
    } else if (/\s/.test(idValue)) {
        setError(id, 'ID Number cannot contain spaces');
        isValid = false;
    } else {
        setSuccess(id);
    }

    console.log('=== VALIDATION END === isValid:', isValid);
    return isValid;
};