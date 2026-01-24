const form = document.getElementById('form');
const id = document.getElementById('id');
const email = document.getElementById('email');
const contact_number = document.getElementById('contact_number');
const username = document.getElementById('username');
const fname = document.getElementById('fname');
const mname= document.getElementById('mname');
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

 age.setAttribute('readonly', true);

 
 form.addEventListener('submit', async (event) => {
    event.preventDefault(); // Prevent the default form submission behavior

    if (validateInputs()) {
        const formData = new FormData(form);  // Collect form data
        const password = formData.get('password');  // Extract password for validation
        
        try {
            // First, validate the password with the server
            const passwordResponse = await fetch('../php/password_check.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'password=' + encodeURIComponent(password)
            });

            const passwordData = await passwordResponse.json();

            if (passwordData.status === 'duplicate') {
                alert(passwordData.message);  // Alert if the password is already in use
                return;  // Stop further execution if password is not unique
            }

            
            const registerResponse = await fetch('../php/register.php', {
                method: 'POST',
                body: formData  // Send form data for registration
            });

            const registerMessage = await registerResponse.json(); // Expect a JSON response
            
            // Check for errors or success based on server response
            if (registerMessage.status === 'error') {
                // Handle different error messages by targeting the relevant fields
                if (registerMessage.message.includes('ID')) setError(id, registerMessage.message);
                if (registerMessage.message.includes('Email')) setError(email, registerMessage.message);
                if (registerMessage.message.includes('Contact number')) setError(contact_number, registerMessage.message);
                if (registerMessage.message.includes('Username')) setError(username, registerMessage.message);
                return; // Stop the execution if any error occurs
            } else if (registerMessage.status === 'success') {
                resetForm(); // Clear the form fields
                window.location.href = 'login.html';
            }

        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred during registration.');
        }
    }
});



function resetForm(){
 
    document.getElementById('form').reset();
}

// function togglePasswordVisibility(passwordId) {
//     const passwordField = document.getElementById(passwordId);
//     const toggleIcon = document.getElementById('togglePassword1');
    
//     if (passwordField.type === 'password') {
//         passwordField.type = 'text'; // Show password
      
//         toggleIcon.src = '../images/eye-icon.png'; // Change icon to indicate "hide"
//     } else {
        
//         passwordField.type = 'password'; // Hide password
//         toggleIcon.src = '../images/closeeye.png'; // Change icon to indicate "show"
//     }
// }

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
    age.readonly = true;
    document.getElementById('birthday').value = birthdate.toISOString().slice(0, 10);
    

       // GENDER VALIDATION

  
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
    const ageValue = age.value.trim();
    
    const genderValue = gender.value.trim();
    const passwordValue = password.value.trim();
    const repasswordValue = repassword.value.trim();
    const street_purokValue = capitalizeWords(street_purok.value.trim());
    const barangayValue = capitalizeWords(barangay.value.trim());
    const city_municipalValue = capitalizeWords(city_municipal.value.trim());
    const provinceValue = capitalizeWords(province.value.trim());
    const countryValue = capitalizeWords(country.value.trim());
    const zipcodeValue = zipcode.value.trim();
    

    let isValid = true;
    
    

    if(ageValue === 0 || ageValue === ''){
        setError(age, 'Age is required');
       
        isValid = false;
    }else{
        setSuccess(age);
    }

    // zipcode
    if (zipcodeValue === '') {
        setError(zipcode, 'ZIP Code is required');
        isValid = false;
    } else if (!/^\d{4}$/.test(zipcodeValue)) { 
        setError(zipcode, 'ZIP Code must be exactly 4 digits');
        isValid = false;
    } else {
        setSuccess(zipcode);
    }

     // city_munipal VALIDATION 
     if (countryValue === '') {
        setError(country, 'Country is required');
        isValid = false;
    } else if (countryValue.length < 2 || countryValue.length > 30) {
        setError(country, 'Street/Purok must be between 2 and 30 characters');
        isValid = false;
    }else if(/(.)\1\1/.test(countryValue)){
        setError(country, 'Three Consecutive identical letters are not allowed');
        isValid = false;
    }else if (!/^[A-Za-z\s]+$/.test(countryValue)) {
        setError(country, ' This Field contain only letters');
        isValid = false;
    }  else {
        country.value=countryValue;
        setSuccess(country);
    }


    // city_munipal VALIDATION 
    if (provinceValue === '') {
        setError(province, 'Province is required');
        isValid = false;
    } else if (provinceValue.length < 2 || provinceValue.length > 30) {
        setError(province, 'Street/Purok must be between 2 and 30 characters');
        isValid = false;
    }else if(/(.)\1\1/.test(provinceValue)){
        setError(province , 'Three Consecutive identical letters are not allowed');
        isValid = false;
    }else if (!/^[A-Za-z\s]+$/.test(provinceValue)) {
        setError(province, ' This Field contain only letters');
        isValid = false;
    } else {
        province.value=provinceValue
        setSuccess(province);
    }

    // city_munipal VALIDATION 
    if (city_municipalValue === '') {
        setError(city_municipal, 'City or Municipality is required');
        isValid = false;
    } else if (city_municipalValue.length < 2 || city_municipalValue.length > 30) {
        setError(city_municipal, 'Street/Purok must be between 2 and 30 characters');
        isValid = false;
    } else if(/(.)\1\1/.test(city_municipalValue)){
        setError(city_municipal , 'Three Consecutive identical letters are not allowed');
        isValid = false;
    } else if (!/^[A-Za-z\s]+$/.test(city_municipalValue)) {
        setError(city_municipal, ' This Field contain only letters');
        isValid = false;
    }else {
        city_municipal.value = city_municipalValue
        setSuccess(city_municipal);
    }

    // BARANGAY VALIDATION 
    if (barangayValue === '') {
        setError(barangay, 'Barangay is required');
        isValid = false;
    } else if (barangayValue.length < 2 || barangayValue.length > 30) {
        setError(barangay, 'Barangay must be between 2 and 30 characters');
        isValid = false;
    }else if(/(.)\1\1/.test(barangayValue)){
        setError(barangay , 'Three Consecutive identical letters are not allowed');
        isValid = false;
    } else {
        barangay.value = barangayValue
        setSuccess(barangay);
    }
    

    // STREET PUROK VALIDATION 
    if (street_purokValue === '') {
        setError(street_purok, 'Street/Purok is required');
        isValid = false;
    } else if (street_purokValue.length < 2 || street_purokValue.length > 30) {
        setError(street_purok, 'Street/Purok must be between 2 and 30 characters');
        isValid = false;
    }else if(/(.)\1\1/.test(street_purokValue)){
        setError(street_purok , 'Three Consecutive identical letters are not allowed');
        isValid = false;
    } else {
        street_purok.value = street_purokValue
        setSuccess(street_purok);
    }

    // Password Validation
    if (passwordValue !== repasswordValue){
        setError(password , 'Password Do not match');
        setError(repassword , 'Password Do not match');
        isValid = false;
    }else if(passwordValue === ''){
        setError(password ,'Password is required');
        setError(repassword ,'Password is required');
        isValid = false;
    }else if (passwordValue.length < 8 || passwordValue.length > 30){
        setError(password , 'Password must be between 8 and 30 characters');
        setError(repassword , 'Password must be between 8 and 30 characters');
        isValid = false;
    }else if(passwordValue.match(/[A-Z]/g || [].length) < 2 ){
        setError(password,'Password must contain at least two(2) uppercase')
        setError(repassword,'Password must contain at least two(2) uppercase')
        isValid = false;
    }else if(passwordValue.match(/[a-z]/g || [].length) < 2 ){
        setError(password,'Password must contain at least two(2) lowercase')
        setError(repassword,'Password must contain at least two(2) lowercase')
        isValid = false;
    }else if(passwordValue.match(/[0-9]/g || [].length) < 2 ){
        setError(password,'Password must contain at least two(2) numbers')
        setError(repassword,'Password must contain at least two(2) numbers')
        isValid = false;
    }else if(passwordValue.match(/[!@#$%^&*(),.?":{}|<>]/g || [].length) < 2 ){
        setError(password,'Password must contain at least two(2) special characters')
        setError(repassword,'Password must contain at least two(2) special characters')
        isValid = false;
    }else{
        setSuccess(password);
        setSuccess(repassword);
    }
    

  


    // GENDER VALIDATION
    if(genderValue === ''){
        setError(gender,'Sex is Required');
        isValid = false;
    }else{
        setSuccess(gender);
    }


    // BIRTHDAY VALIDATION
    // BIRTHDAY VALIDATION
    if (birthdayValue === '') {
        setError(birthday, 'Birthday is required');
        isValid = false;
    } else if (isNaN(new Date(birthdayValue).getTime())) {
        setError(birthday, 'Please enter a valid date (YYYY-MM-DD)');
        isValid = false;
    }else {
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



    


    // Extention NAME VALIDATION
    if (extend_nameValue !== '') {
    // "Sr", "Jr", or Roman numerals (I, II, III, IV, etc.)
        if (!/^(Sr|Jr|Senior|Junior|[IVXLCDM]+)$/i.test(extend_nameValue)) {
            setError(extend_name, 'Enter a Valid Extention name');
            
            isValid = false;
        }else if (extend_nameValue.length <= 0 || extend_nameValue.length > 30) {
            setError(extend_name, 'Extension name must be between 0 and 30 characters');
            isValid = false;
        }else if(extend_nameValue === 'sr' || extend_nameValue === 'jr'){
            setError(extend_name,'Sr and Jr must be capital in the first letter ');
            isValid = false;

        }else if(!/[IVXLCDM]/.test(extend_nameValue)){
            setError(extend_name,'Invalid Roman Numeral');
            isValid= false;
            

        }
        else if(!/ \S/.test(extend_name)){
            setError(extend_name,'No space allowed');
            isValid= false;

        }else {
            extend_name.value = extend_nameValue;
            setSuccess(extend_name);
        }
    }else {
        extend_name.value = extend_nameValue;
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
        setError(lname, 'Last Name contain only letters');
        isValid = false;
    } else if (/(.)\1{2,}/.test(lnameValue)) {
        setError(lname, 'Three consecutive identical letters are not allowed');
        isValid = false;
    } else {
        lname.value = lnameValue;
        setSuccess(lname);
    }

    // mname NAME VALIDATION
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
            mname.value = mnameValue; // Assign the formatted value back
            setSuccess(mname);
        }
    } else {
        setSuccess(mname);
        isValid = false;
    }


    // FIRST NAME VLALIDATION
    if(fnameValue === ''){
        setError(fname, 'First Name is required');
        isValid = false;
    }else if(fnameValue.length <= 1 || fnameValue.length >= 30 ){
        setError(fname , 'First Name must between 1 and 30 characters');
        isValid = false;
    }else if(!/^[A-Za-z\s]+$/.test(fnameValue)){
        setError(fname, 'First Name contain only letters');
        isValid = false;
    }else if(/(.)\1\1/.test(fnameValue)){
        setError(fname , 'Three Consecutive identical letters are not allowed');
        isValid = false;
    }else{
        
        fname.value=fnameValue;
        setSuccess(fname);
    }


    //username


    if(usernameValue === ''){
        setError(username, 'Username is required');     
        isValid = false;
    }else if(usernameValue.length <= 1 || usernameValue.length >= 15 ){
        setError(username , 'Username must between 1 and 15 characters');
        isValid = false;
    }else if(/(.)\1\1/.test(usernameValue)){
        setError(username , 'Three Consecutive identical letters are not allowed');
        isValid = false;
    }else if(/[A-Z]/.test(usernameValue)){
        setError(username , 'No Capital Letter allowed')
        isValid = false;
    }

    else if(/ \S/.test(usernameValue)){
        setError(username , 'No Space allowed');
        isValid = false;
    }
    else if(!/^(?![0-9])/.test(usernameValue)){
        setError(username , 'Username does not start with a number.');
 }
    // else if (/[0-9]$/.test(usernameValue)) {
    //     setError(username, 'Username does not end with a number.');
    //     isValid = false;

    //     }
    // }else if(!/(?<![-_])$/.test(usernameValue)){
    //     setError(username , 'Username does not end with an underscore or hyphen.');
    // }else{
     else{   
     username.value=usernameValue;
        setSuccess(username);
    }



    // CONTACT NUMBER VALIDATION
     if(contact_numberValue === ''){
        setError(contact_number , 'Contact Number is required' );
        isValid = false;
    }else if(!/^(09\d{9}|(\+639)\d{9})$/.test(contact_numberValue)){
        setError(contact_number, 'Please Enter a Valid Contact number');
        isValid = false;
    }else{
        setSuccess(contact_number);
    }

 // EMAIL VALIDATION
    if(emailValue === ''){
        setError(email , 'Email is required');
        isValid = false;
    }else if(!/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(emailValue)){
        setError(email , 'Please Enter a Valid Email Address');
        isValid = false;
    }else{
        setSuccess(email);
    }

    // id VALIDATION
    if(idValue === '') {
        setError(id, 'Id Number is required');
        isValid = false;
    }else if (idValue.length < 6 || idValue.length > 11){
        setError(id , 'Id Number must be between 6 and 11 characters');
        isValid = false;
    }else if (!/^\d{4}-\d{4}$/.test(idValue)) {
        setError(id, 'Id Number must be in the format xxxx-xxxx');
        isValid = false;
    }else if (/\s/.test(idValue)){
        setError(id , 'Id Number cannot contain spaces');
        isValid = false;
    }else {
        setSuccess(id);
    }

    
    return isValid;
    
};

