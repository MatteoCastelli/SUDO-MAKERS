// Autocomplete per il comune di nascita
document.addEventListener('DOMContentLoaded', function() {
    const comuneInput = document.getElementById('comune_nascita');
    if (!comuneInput) return;

    let autocompleteList = null;
    let currentFocus = -1;
    let debounceTimer = null;

    // Wrap input in container per posizionamento assoluto
    const container = document.createElement('div');
    container.className = 'autocomplete-container';
    comuneInput.parentNode.insertBefore(container, comuneInput);
    container.appendChild(comuneInput);

    comuneInput.addEventListener('input', function() {
        const val = this.value.trim();
        closeAllLists();
        currentFocus = -1;

        if (val.length < 2) return;

        // Debounce per evitare troppe richieste
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetch(`autocomplete.php?q=${encodeURIComponent(val)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) return;

                    autocompleteList = document.createElement('div');
                    autocompleteList.className = 'autocomplete-list';
                    container.appendChild(autocompleteList);

                    data.forEach((item, index) => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        div.innerHTML = `
                            <strong>${item.nome}</strong>
                            <small>Codice: ${item.codice}</small>
                        `;
                        
                        div.addEventListener('click', function() {
                            comuneInput.value = item.nome;
                            closeAllLists();
                        });

                        autocompleteList.appendChild(div);
                    });
                })
                .catch(error => console.error('Errore autocomplete:', error));
        }, 300);
    });

    // Navigazione con tastiera
    comuneInput.addEventListener('keydown', function(e) {
        if (!autocompleteList) return;
        
        const items = autocompleteList.getElementsByClassName('autocomplete-item');
        
        if (e.keyCode === 40) { // Freccia giù
            e.preventDefault();
            currentFocus++;
            addActive(items);
        } else if (e.keyCode === 38) { // Freccia su
            e.preventDefault();
            currentFocus--;
            addActive(items);
        } else if (e.keyCode === 13) { // Enter
            e.preventDefault();
            if (currentFocus > -1 && items[currentFocus]) {
                items[currentFocus].click();
            }
        } else if (e.keyCode === 27) { // Escape
            closeAllLists();
        }
    });

    function addActive(items) {
        if (!items || items.length === 0) return;
        
        removeActive(items);
        
        if (currentFocus >= items.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = items.length - 1;
        
        items[currentFocus].classList.add('active');
        items[currentFocus].scrollIntoView({ block: 'nearest' });
    }

    function removeActive(items) {
        for (let i = 0; i < items.length; i++) {
            items[i].classList.remove('active');
        }
    }

    function closeAllLists() {
        if (autocompleteList) {
            autocompleteList.remove();
            autocompleteList = null;
        }
        currentFocus = -1;
    }

    // Chiudi autocomplete quando si clicca fuori
    document.addEventListener('click', function(e) {
        if (e.target !== comuneInput) {
            closeAllLists();
        }
    });
});
