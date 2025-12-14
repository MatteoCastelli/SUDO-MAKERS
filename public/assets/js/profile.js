// Gestione modal
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Validazione username
const usernameInput = document.getElementById('username');
const usernameSubmitBtn = document.querySelector('#usernameModal .btn-submit');

if(usernameInput && usernameSubmitBtn) {
    // Inizialmente disabilitato
    usernameSubmitBtn.disabled = true;

    usernameInput.addEventListener('input', function() {
        const isValid = this.value.trim().length > 0;

        if(isValid) {
            this.classList.add('valid-input');
            this.classList.remove('invalid-input');
            usernameSubmitBtn.disabled = false;
        } else {
            this.classList.remove('valid-input');
            this.classList.add('invalid-input');
            usernameSubmitBtn.disabled = true;
        }
    });

    // Check iniziale
    if(usernameInput.value.trim().length > 0) {
        usernameInput.classList.add('valid-input');
        usernameSubmitBtn.disabled = false;
    }
}

// Validazione password in tempo reale
const newPasswordInput = document.getElementById('new_password');
const confirmPasswordInput = document.getElementById('confirm_password');
const oldPasswordInput = document.getElementById('old_password');
const submitBtn = document.getElementById('submitBtn');

function checkPasswordRequirements() {
    if(!newPasswordInput) return false;
    const pwd = newPasswordInput.value;

    const lengthValid = pwd.length >= 8;
    const upperValid = /[A-Z]/.test(pwd);
    const numberValid = /\d/.test(pwd);
    const symbolValid = /[\W_]/.test(pwd);

    document.getElementById('req-length').classList.toggle('valid', lengthValid);
    document.getElementById('req-upper').classList.toggle('valid', upperValid);
    document.getElementById('req-number').classList.toggle('valid', numberValid);
    document.getElementById('req-symbol').classList.toggle('valid', symbolValid);

    const allValid = lengthValid && upperValid && numberValid && symbolValid;

    // Diventa verde SOLO se tutti i criteri sono soddisfatti
    if(allValid) {
        newPasswordInput.classList.add('valid-input');
        newPasswordInput.classList.remove('invalid-input');
    } else {
        newPasswordInput.classList.remove('valid-input');
        newPasswordInput.classList.add('invalid-input');
    }

    return allValid;
}

function checkPasswordMatch() {
    if(!confirmPasswordInput || !newPasswordInput) return false;
    const newPwd = newPasswordInput.value;
    const confirmPwd = confirmPasswordInput.value;
    const matchReq = document.getElementById('req-match');

    if(confirmPwd === '') {
        matchReq.classList.remove('valid');
        confirmPasswordInput.classList.remove('valid-input', 'invalid-input');
        return false;
    }

    // Diventa verde SOLO se le password coincidono E la password nuova è valida
    const isMatch = newPwd === confirmPwd && checkPasswordRequirements();

    matchReq.classList.toggle('valid', isMatch);

    if(isMatch) {
        confirmPasswordInput.classList.add('valid-input');
        confirmPasswordInput.classList.remove('invalid-input');
    } else {
        confirmPasswordInput.classList.remove('valid-input');
        confirmPasswordInput.classList.add('invalid-input');
    }

    return isMatch;
}

function checkOldPassword() {
    if(!oldPasswordInput) return false;
    const hasValue = oldPasswordInput.value.length > 0;

    // Diventa verde non appena scrivi qualcosa
    if(hasValue) {
        oldPasswordInput.classList.add('valid-input');
        oldPasswordInput.classList.remove('invalid-input');
    } else {
        oldPasswordInput.classList.remove('valid-input');
        oldPasswordInput.classList.add('invalid-input');
    }

    return hasValue;
}

function updateSubmitButton() {
    if(!submitBtn) return;
    const oldValid = checkOldPassword();
    const reqValid = checkPasswordRequirements();
    const matchValid = checkPasswordMatch();
    submitBtn.disabled = !(oldValid && reqValid && matchValid);
}

if(newPasswordInput && confirmPasswordInput && oldPasswordInput) {
    oldPasswordInput.addEventListener('input', updateSubmitButton);
    newPasswordInput.addEventListener('input', updateSubmitButton);
    confirmPasswordInput.addEventListener('input', updateSubmitButton);
}

// Validazione form password
const passwordForm = document.getElementById('passwordForm');
if(passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        if(!checkOldPassword() || !checkPasswordRequirements() || !checkPasswordMatch()) {
            e.preventDefault();
        }
    });
}