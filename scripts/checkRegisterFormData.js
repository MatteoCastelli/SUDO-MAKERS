const oggi = new Date().toISOString().split("T")[0];
document.getElementById("data_nascita").setAttribute("max", oggi);

const passwordInput = document.getElementById("password");
const nomeInput = document.getElementById("nome");
const cognomeInput = document.getElementById("cognome");
const btnSubmit = document.querySelector("button[type=submit]");

const reqLength = document.getElementById("req-length");
const reqUpper  = document.getElementById("req-upper");
const reqNumber = document.getElementById("req-number");
const reqSymbol = document.getElementById("req-symbol");

// Funzione per capitalizzare nome/cognome
function capitalizeName(str) {
    return str
        .split(' ')
        .map(word => {
            if (word.length === 0) return '';
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        })
        .join(' ');
}

// Funzione per validare nome/cognome (solo lettere e spazi)
function isValidName(str) {
    // Permette lettere (anche accentate), apostrofi e spazi
    const nameRegex = /^[a-zA-ZÀ-ÿ\s']+$/;
    return nameRegex.test(str) && str.trim().length > 0;
}

// Gestione Nome
nomeInput.addEventListener("input", function() {
    let value = this.value;

    // Rimuovi caratteri non validi mentre digiti
    value = value.replace(/[^a-zA-ZÀ-ÿ\s']/g, '');
    this.value = value;

    // Valida e colora il bordo
    if (value.trim().length > 0) {
        if (isValidName(value)) {
            this.style.border = "2px solid #0c8a1f";
        } else {
            this.style.border = "2px solid #b30000";
        }
    } else {
        this.style.border = "2px solid #b30000";
    }
});

nomeInput.addEventListener("blur", function() {
    if (this.value.trim().length > 0) {
        this.value = capitalizeName(this.value.trim());
    }
});

// Gestione Cognome
cognomeInput.addEventListener("input", function() {
    let value = this.value;

    // Rimuovi caratteri non validi mentre digiti
    value = value.replace(/[^a-zA-ZÀ-ÿ\s']/g, '');
    this.value = value;

    // Valida e colora il bordo
    if (value.trim().length > 0) {
        if (isValidName(value)) {
            this.style.border = "2px solid #0c8a1f";
        } else {
            this.style.border = "2px solid #b30000";
        }
    } else {
        this.style.border = "2px solid #b30000";
    }
});

cognomeInput.addEventListener("blur", function() {
    if (this.value.trim().length > 0) {
        this.value = capitalizeName(this.value.trim());
    }
});

// Validazione Password
function validatePassword() {
    const pwd = passwordInput.value;

    const validLength = pwd.length >= 8;
    const validUpper  = /[A-Z]/.test(pwd);
    const validNumber = /[0-9]/.test(pwd);
    const validSymbol = /[\W_]/.test(pwd);

    // aggiorna colori
    updateReq(reqLength, validLength);
    updateReq(reqUpper, validUpper);
    updateReq(reqNumber, validNumber);
    updateReq(reqSymbol, validSymbol);

    const validAll = validLength && validUpper && validNumber && validSymbol;

    // bordi input
    passwordInput.style.border = validAll ? "2px solid #0c8a1f" : "2px solid #b30000";

    return validAll;
}

function updateReq(element, condition) {
    if (condition) {
        element.classList.add("valid");
    } else {
        element.classList.remove("valid");
    }
}

// aggiorna in tempo reale
passwordInput.addEventListener("input", validatePassword);

// Validazione completa form
function validateForm() {
    const nomeValid = nomeInput.value.trim().length > 0 && isValidName(nomeInput.value);
    const cognomeValid = cognomeInput.value.trim().length > 0 && isValidName(cognomeInput.value);
    const passwordValid = validatePassword();
    const dataNascitaValid = document.getElementById("data_nascita").value !== '';
    const sessoValid = document.getElementById("sesso").value !== '';
    const comuneValid = document.getElementById("comune_nascita").value.trim().length > 0;
    const emailValid = document.getElementById("email").validity.valid;

    const allValid = nomeValid && cognomeValid && passwordValid && dataNascitaValid &&
        sessoValid && comuneValid && emailValid;

    btnSubmit.disabled = !allValid;
}

// Ascolta tutti gli input per validare il form
document.querySelectorAll('input, select').forEach(element => {
    element.addEventListener('input', validateForm);
    element.addEventListener('change', validateForm);
});

// disabilita pulsante al primo caricamento
btnSubmit.disabled = true;