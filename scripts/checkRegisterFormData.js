// ========================================
// UTILITY FUNCTIONS
// ========================================

// Capitalizza nome/cognome
function capitalizeName(str) {
    return str
        .split(' ')
        .map(word => {
            if (word.length === 0) return '';
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        })
        .join(' ');
}

// Valida nome/cognome (solo lettere, apostrofi e spazi)
function isValidName(str) {
    const nameRegex = /^[a-zA-ZÀ-ÿ\s']+$/;
    return nameRegex.test(str) && str.trim().length > 0;
}

// Aggiorna stile requisiti password
function updateReq(element, condition) {
    if (element) {
        if (condition) {
            element.classList.add("valid");
        } else {
            element.classList.remove("valid");
        }
    }
}

// ========================================
// PASSWORD VALIDATION
// ========================================

function validatePassword(passwordInput, reqLength, reqUpper, reqNumber, reqSymbol) {
    const pwd = passwordInput.value;

    const validLength = pwd.length >= 8;
    const validUpper = /[A-Z]/.test(pwd);
    const validNumber = /[0-9]/.test(pwd);
    const validSymbol = /[\W_]/.test(pwd);

    // Aggiorna colori requisiti
    updateReq(reqLength, validLength);
    updateReq(reqUpper, validUpper);
    updateReq(reqNumber, validNumber);
    updateReq(reqSymbol, validSymbol);

    const validAll = validLength && validUpper && validNumber && validSymbol;

    // Bordo input
    passwordInput.style.border = validAll ? "2px solid #0c8a1f" : "2px solid #b30000";

    return validAll;
}

// ========================================
// NAME FIELD VALIDATION
// ========================================

function setupNameValidation(inputElement) {
    if (!inputElement) return;

    // Input event - rimuovi caratteri non validi e valida
    inputElement.addEventListener("input", function() {
        let value = this.value;

        // Rimuovi caratteri non validi
        value = value.replace(/[^a-zA-ZÀ-ÿ\s']/g, '');
        this.value = value;

        // Valida e colora bordo
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

    // Blur event - capitalizza
    inputElement.addEventListener("blur", function() {
        if (this.value.trim().length > 0) {
            this.value = capitalizeName(this.value.trim());
        }
    });
}

// ========================================
// FORM VALIDATION - REGISTER
// ========================================

function initRegisterFormValidation() {
    // Imposta data massima (oggi)
    const dataNascitaInput = document.getElementById("data_nascita");
    if (dataNascitaInput) {
        const oggi = new Date().toISOString().split("T")[0];
        dataNascitaInput.setAttribute("max", oggi);
    }

    // Elementi del form
    const passwordInput = document.getElementById("password");
    const nomeInput = document.getElementById("nome");
    const cognomeInput = document.getElementById("cognome");
    const btnSubmit = document.querySelector("button[type=submit]");

    // Requisiti password
    const reqLength = document.getElementById("req-length");
    const reqUpper = document.getElementById("req-upper");
    const reqNumber = document.getElementById("req-number");
    const reqSymbol = document.getElementById("req-symbol");

    // Setup validazione nome e cognome
    setupNameValidation(nomeInput);
    setupNameValidation(cognomeInput);

    // Setup validazione password
    if (passwordInput) {
        passwordInput.addEventListener("input", function() {
            validatePassword(passwordInput, reqLength, reqUpper, reqNumber, reqSymbol);
            validateRegisterForm();
        });
    }

    // Validazione form completa
    function validateRegisterForm() {
        const nomeValid = nomeInput && nomeInput.value.trim().length > 0 && isValidName(nomeInput.value);
        const cognomeValid = cognomeInput && cognomeInput.value.trim().length > 0 && isValidName(cognomeInput.value);
        const passwordValid = passwordInput && validatePassword(passwordInput, reqLength, reqUpper, reqNumber, reqSymbol);
        const dataNascitaValid = dataNascitaInput && dataNascitaInput.value !== '';
        const sessoInput = document.getElementById("sesso");
        const sessoValid = sessoInput && sessoInput.value !== '';
        const comuneInput = document.getElementById("comune_nascita");
        const comuneValid = comuneInput && comuneInput.value.trim().length > 0;
        const emailInput = document.getElementById("email");
        const emailValid = emailInput && emailInput.validity.valid;

        const allValid = nomeValid && cognomeValid && passwordValid && dataNascitaValid &&
            sessoValid && comuneValid && emailValid;

        if (btnSubmit) {
            btnSubmit.disabled = !allValid;
        }
    }

    // Ascolta tutti gli input
    document.querySelectorAll('input, select').forEach(element => {
        element.addEventListener('input', validateRegisterForm);
        element.addEventListener('change', validateRegisterForm);
    });

    // Disabilita pulsante all'inizio
    if (btnSubmit) {
        btnSubmit.disabled = true;
    }
}

// ========================================
// FORM VALIDATION - LOGIN
// ========================================

function initLoginFormValidation() {
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const btnSubmit = document.querySelector("button[type=submit]");

    function checkLogin() {
        const emailValid = emailInput && emailInput.validity.valid;
        const passwordValid = passwordInput && passwordInput.value.length > 0;

        if (btnSubmit) {
            btnSubmit.disabled = !(passwordValid && emailValid);
        }
    }

    if (emailInput) {
        emailInput.addEventListener("input", checkLogin);
    }

    if (passwordInput) {
        passwordInput.addEventListener("input", checkLogin);
    }

    // Disabilita pulsante all'inizio
    if (btnSubmit) {
        btnSubmit.disabled = true;
    }
}

// ========================================
// FORM VALIDATION - MODIFICA PROFILO
// ========================================

function initModificaProfiloValidation() {
    const valoreInput = document.getElementById("valore");
    const btnSubmit = document.querySelector("button[type=submit]");
    const reqLength = document.getElementById("req-length");
    const reqUpper = document.getElementById("req-upper");
    const reqNumber = document.getElementById("req-number");
    const reqSymbol = document.getElementById("req-symbol");

    // Controlla il tipo di campo
    const isPasswordField = valoreInput && valoreInput.type === 'password' && reqLength;
    const isTextField = valoreInput && valoreInput.type === 'text';
    const isDateField = valoreInput && valoreInput.type === 'date';
    const isFileField = valoreInput && valoreInput.type === 'file';
    const isSelectField = valoreInput && valoreInput.tagName === 'SELECT';

    function checkForm() {
        let valido = false;

        if (isPasswordField) {
            // Per password: verifica requisiti E che non sia vuota
            const hasValue = valoreInput.value.length > 0;
            valido = hasValue && validatePassword(valoreInput, reqLength, reqUpper, reqNumber, reqSymbol);
        } else if (isTextField) {
            // Valida campo di testo (nome, cognome, comune, email, ecc.)
            const value = valoreInput.value.trim();

            if (value.length === 0) {
                // Se vuoto, sempre invalido
                valido = false;
                valoreInput.style.border = "2px solid #b30000";
            } else {
                // Se è un campo nome/cognome, valida con regex
                const fieldName = new URLSearchParams(window.location.search).get('colonna');
                if (fieldName === 'nome' || fieldName === 'cognome') {
                    valido = isValidName(value);
                } else {
                    // Altri campi testo: basta che non siano vuoti
                    valido = true;
                }
                valoreInput.style.border = valido ? "2px solid #0c8a1f" : "2px solid #b30000";
            }
        } else if (isDateField) {
            valido = valoreInput.value !== '';
            valoreInput.style.border = valido ? "2px solid #0c8a1f" : "2px solid #b30000";
        } else if (isFileField) {
            valido = valoreInput.files.length > 0;
            valoreInput.style.border = valido ? "2px solid #0c8a1f" : "2px solid #b30000";
        } else if (isSelectField) {
            valido = valoreInput.value !== '';
            valoreInput.style.border = valido ? "2px solid #0c8a1f" : "2px solid #b30000";
        }

        if (btnSubmit) {
            btnSubmit.disabled = !valido;
        }
    }

    // Setup validazione nome se necessario
    const fieldName = new URLSearchParams(window.location.search).get('colonna');
    if ((fieldName === 'nome' || fieldName === 'cognome') && isTextField) {
        setupNameValidation(valoreInput);
    }

    // Eventi
    if (valoreInput) {
        valoreInput.addEventListener("input", checkForm);
        valoreInput.addEventListener("change", checkForm);
    }

    // Disabilita all'inizio
    if (btnSubmit) {
        btnSubmit.disabled = true;
    }
}

// ========================================
// AUTO-INIT
// ========================================

// Inizializza automaticamente in base agli elementi presenti nella pagina
document.addEventListener('DOMContentLoaded', function() {
    // Controlla quale form è presente e inizializza di conseguenza
    if (document.getElementById("nome") && document.getElementById("cognome")) {
        // Form di registrazione
        initRegisterFormValidation();
    } else if (document.getElementById("valore")) {
        // Form di modifica profilo
        initModificaProfiloValidation();
    } else if (document.getElementById("email") && document.getElementById("password")) {
        // Form di login
        initLoginFormValidation();
    }
});