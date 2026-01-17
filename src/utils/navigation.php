<?php
if(!function_exists('requireAnyRole')) {
    require_once __DIR__ . '/check_permissions.php';
}

use Proprietario\SudoMakers\core\Database;

require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance()->getConnection();

// GESTIONE AUTOMATICA PRENOTAZIONI - Esegui controlli in background
if(file_exists(__DIR__ . '/../cron/auto_gestione_prenotazioni.php')) {
    include_once __DIR__ . '/../cron/auto_gestione_prenotazioni.php';
}
?>
<link rel="stylesheet" href="../../public/assets/css/autocompleteStyle.css">
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-left">
            <a href="../user/homepage.php" class="nav-logo">Biblioteca</a>
        </div>

        <div class="nav-center">
            <form action="../catalog/ricerca.php" method="GET" class="search-form" id="searchForm">
                <input
                        type="text"
                        name="q"
                        id="searchInput"
                        class="search-input"
                        placeholder="Cerca per titolo, autore, ISBN, editore..."
                        autocomplete="off"
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                >
                <button type="submit" class="search-btn">Cerca</button>
                <a href="../catalog/ricerca_avanzata.php" class="advanced-search-link" title="Ricerca avanzata">Avanzate</a>

                <!-- Dropdown autocomplete -->
                <div id="autocompleteResults" class="autocomplete-dropdown"></div>
            </form>
        </div>

        <ul class="nav-list">
            <?php if(isset($_SESSION['id_utente'])): ?>
                <li class="nav-item">
                    <a href="../user/le_mie_prenotazioni.php" class="nav-link">
                        I Tuoi Libri
                    </a>
                </li>

                <!-- Link Multe Utente -->
                <li class="nav-item">
                    <a href="../user/mie_multe.php" class="nav-link" title="Visualizza le tue multe">
                        Multe
                    </a>
                </li>

                <!-- LINK GAMIFICATION -->
                <li class="nav-item">
                    <a href="../user/gamification.php" class="nav-link">
                        Obiettivi
                    </a>
                </li>

                <li class="nav-item">
                    <a href="../user/profile.php" class="nav-link">Profilo</a>
                </li>
                
                <?php if(hasAnyRole(['bibliotecario', 'amministratore'])): ?>
                    <li class="nav-item">
                        <a href="../librarian/dashboard_bibliotecario.php" class="nav-link">Dashboard</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="../auth/logout.php" class="nav-link">Esci</a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a href="../auth/login.php" class="nav-link">Login</a>
                </li>
                <li class="nav-item">
                    <a href="../auth/register.php" class="nav-link">Registrati</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<script>
    // ========================================
    // AUTOCOMPLETE LIBRI - Sistema Completo
    // ========================================

    const searchInput = document.getElementById('searchInput');
    const autocompleteResults = document.getElementById('autocompleteResults');
    let autocompleteTimeout;

    // Funzione per determinare il percorso corretto
    function getAutocompletePath() {
        const path = window.location.pathname;

        // Determina il percorso relativo in base alla directory corrente
        if (path.includes('/catalog/')) {
            return '../utils/autocomplete_libri.php';
        } else if (path.includes('/user/')) {
            return '../utils/autocomplete_libri.php';
        } else if (path.includes('/librarian/')) {
            return '../utils/autocomplete_libri.php';
        } else if (path.includes('/auth/')) {
            return '../utils/autocomplete_libri.php';
        } else {
            return 'autocomplete_libri.php';
        }
    }

    // Evento input - Attiva ricerca
    searchInput.addEventListener('input', function() {
        clearTimeout(autocompleteTimeout);
        const query = this.value.trim();

        if(query.length < 2) {
            autocompleteResults.style.display = 'none';
            return;
        }

        // Mostra stato di caricamento
        autocompleteResults.innerHTML = '<div class="autocomplete-loading">Ricerca in corso</div>';
        autocompleteResults.style.display = 'block';

        autocompleteTimeout = setTimeout(() => {
            const autocompletePath = getAutocompletePath();

            fetch(`${autocompletePath}?q=${encodeURIComponent(query)}`)
                .then(res => {
                    if (!res.ok) throw new Error('Errore nella risposta');
                    return res.json();
                })
                .then(data => {
                    if(data.length > 0) {
                        autocompleteResults.innerHTML = data.map(item => {
                            if(item.tipo === 'libro') {
                                return `<a href="../catalog/dettaglio_libro.php?id=${item.id}" class="autocomplete-item">
                                    <span class="item-icon">📖</span>
                                    <div class="item-info">
                                        <strong>${escapeHtml(item.titolo)}</strong>
                                        <small>${escapeHtml(item.autore)}</small>
                                    </div>
                                </a>`;
                            } else if(item.tipo === 'autore') {
                                return `<a href="../catalog/ricerca.php?autore=${encodeURIComponent(item.nome)}" class="autocomplete-item">
                                    <span class="item-icon">✍️</span>
                                    <div class="item-info">
                                        <strong>${escapeHtml(item.nome)}</strong>
                                        <small>${item.num_libri} ${item.num_libri === 1 ? 'libro' : 'libri'}</small>
                                    </div>
                                </a>`;
                            } else if(item.tipo === 'categoria') {
                                return `<a href="../catalog/ricerca.php?categoria=${encodeURIComponent(item.nome)}" class="autocomplete-item">
                                    <span class="item-icon">🔠</span>
                                    <div class="item-info">
                                        <strong>${escapeHtml(item.nome)}</strong>
                                        <small>${item.num_libri} ${item.num_libri === 1 ? 'libro' : 'libri'}</small>
                                    </div>
                                </a>`;
                            } else if(item.tipo === 'suggerimento') {
                                return `<a href="../catalog/dettaglio_libro.php?id=${item.id}" class="autocomplete-item suggerimento">
                                    <span class="item-icon">💡</span>
                                    <div class="item-info">
                                        <small style="color: #888;">Forse cercavi...</small>
                                        <strong>${escapeHtml(item.titolo)}</strong>
                                        <small>${escapeHtml(item.autore)}</small>
                                    </div>
                                </a>`;
                            }
                        }).join('');
                        autocompleteResults.style.display = 'block';
                    } else {
                        autocompleteResults.innerHTML = `
                            <div class="autocomplete-item no-results">
                                <div class="item-info">
                                    <small>Nessun risultato trovato per "${escapeHtml(query)}"</small>
                                </div>
                            </div>`;
                        autocompleteResults.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Errore autocomplete:', err);
                    autocompleteResults.innerHTML = `
                        <div class="autocomplete-item no-results">
                            <div class="item-info">
                                <small>Errore di connessione. Riprova.</small>
                            </div>
                        </div>`;
                    autocompleteResults.style.display = 'block';
                });
        }, 300);
    });

    // Gestione tastiera (frecce su/giù, Enter, Esc)
    searchInput.addEventListener('keydown', function(e) {
        const items = autocompleteResults.querySelectorAll('.autocomplete-item:not(.no-results):not(.autocomplete-loading)');
        const activeItem = autocompleteResults.querySelector('.autocomplete-item.active');
        let currentIndex = Array.from(items).indexOf(activeItem);

        if(e.key === 'ArrowDown') {
            e.preventDefault();
            if(items.length === 0) return;

            if(activeItem) activeItem.classList.remove('active');
            currentIndex = (currentIndex + 1) % items.length;
            if(items[currentIndex]) {
                items[currentIndex].classList.add('active');
                items[currentIndex].scrollIntoView({ block: 'nearest' });
            }
        } else if(e.key === 'ArrowUp') {
            e.preventDefault();
            if(items.length === 0) return;

            if(activeItem) activeItem.classList.remove('active');
            currentIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
            if(items[currentIndex]) {
                items[currentIndex].classList.add('active');
                items[currentIndex].scrollIntoView({ block: 'nearest' });
            }
        } else if(e.key === 'Enter') {
            if(activeItem && !activeItem.classList.contains('no-results')) {
                e.preventDefault();
                activeItem.click();
            }
        } else if(e.key === 'Escape') {
            autocompleteResults.style.display = 'none';
            searchInput.blur();
        }
    });

    // Chiudi autocomplete quando clicchi fuori
    document.addEventListener('click', function(e) {
        if(!searchInput.contains(e.target) && !autocompleteResults.contains(e.target)) {
            autocompleteResults.style.display = 'none';
        }
    });

    // Mostra autocomplete quando focus su input (se c'è già contenuto)
    searchInput.addEventListener('focus', function() {
        if(this.value.trim().length >= 2 && autocompleteResults.innerHTML) {
            autocompleteResults.style.display = 'block';
        }
    });

    // Funzione helper per escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
</script>