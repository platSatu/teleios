/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Form Advanced Js File
*/

document.getElementById('nextStep').addEventListener('click', function () {
    const fields = [
        {
            id: 'username',
            errorId: 'usernameError',
            validate: val => val.trim() !== ''
        },
        {
            id: 'email',
            errorId: 'emailError',
            validate: val => /\S+@\S+\.\S+/.test(val.trim())
        },
        {
            id: 'firstName',
            errorId: 'firstNameError',
            label: 'First name',
            validate: val => val.trim() !== ''
        },
        {
            id: 'lastName',
            errorId: 'lastNameError',
            label: 'Last name',
            validate: val => val.trim() !== ''
        },
        {
            id: 'address',
            errorId: 'addressError',
            label: 'Address',
            validate: val => val.trim() !== ''
        }
    ];

    let valid = true;

    fields.forEach(field => {
        const el = document.getElementById(field.id);
        let errorEl = document.getElementById(field.errorId);
        if (!errorEl && field.errorId) {
            errorEl = document.createElement('small');
            errorEl.id = field.errorId;
            errorEl.className = 'text-danger d-none';
            errorEl.textContent = `${field.label || field.id} is required.`;
            el.parentElement.appendChild(errorEl);
        }

        const isValid = field.validate(el.value);
        if (!isValid) {
            errorEl.classList.remove('d-none');
            valid = false;
        } else {
            errorEl.classList.add('d-none');
        }
    });

    if (valid) {
        document.getElementById('step1').classList.add('d-none');
        document.getElementById('step2').classList.remove('d-none');
    }
});

document.getElementById('prevStep').addEventListener('click', function () {
    document.getElementById('step2').classList.add('d-none');
    document.getElementById('step1').classList.remove('d-none');
});

document.getElementById('advancedForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const password = document.getElementById('password');
    const userType = document.getElementById('userType');
    const terms = document.getElementById('terms');
    const phone = document.getElementById('phone');
    const phoneError = document.getElementById('phoneError');

    let valid = true;

    // Password validation
    if (!password.value || password.value.length < 8) {
        document.getElementById('passwordError').classList.remove('d-none');
        valid = false;
    } else {
        document.getElementById('passwordError').classList.add('d-none');
    }

    // User type validation
    if (!userType.value) {
        document.getElementById('userTypeError').classList.remove('d-none');
        valid = false;
    } else {
        document.getElementById('userTypeError').classList.add('d-none');
    }

    // Terms validation
    if (!terms.checked) {
        document.getElementById('termsError').classList.remove('d-none');
        valid = false;
    } else {
        document.getElementById('termsError').classList.add('d-none');
    }

    // Phone number validation
    const phoneVal = phone.value.trim();
    const phoneRegex = /^[0-9]{10,15}$/;
    if (phoneVal && !phoneRegex.test(phoneVal)) {
        phoneError.classList.remove('d-none');
        valid = false;
    } else {
        phoneError.classList.add('d-none');
    }

    if (valid) {
        alert('Form submitted successfully!');
        // document.getElementById('advancedForm').reset();
    }
});
