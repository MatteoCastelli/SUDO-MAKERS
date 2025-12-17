<?php
if(!function_exists('requireAnyRole')) {
    require_once __DIR__ . '/check_permissions.php';
}

use Proprietario\SudoMakers\Database;
require_once "Database.php";

$pdo = Database::getInstance()->getConnection();

// GESTIONE AUTOMATICA PRENOTAZIONI - Esegui controlli in background
if(file_exists(__DIR__ . '/auto_gestione_prenotazioni.php')) {
    include_once __DIR__ . '/auto_gestione_prenotazioni.php';
}
?>
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-left">
            <a href="homepage.php" class="nav-logo">Biblioteca</a>
        </div>

        <div class="nav-center">
            <form action="ricerca.php" method="GET" class="search-form" id="searchForm">
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
                <a href="ricerca_avanzata.php" class="advanced-search-link" title="Ricerca avanzata">Avanzate</a>

                <!-- Dropdown autocomplete -->
                <div id="autocompleteResults" class="autocomplete-dropdown"></div>
            </form>
        </div>

        <ul class="nav-list">
            <?php if(isset($_SESSION['id_utente'])): ?>
                <li class="nav-item">
                    <a href="le_mie_prenotazioni.php" class="nav-link">
                        I Tuoi Libri
<!--                        --><?php
//                        // Badge con numero prenotazioni attive
//                        $stmt = $pdo->prepare("
//                            SELECT COUNT(*)
//                            FROM prenotazione
//                            WHERE id_utente = :id
//                            AND stato IN ('attiva', 'disponibile')
//                        ");
//                        $stmt->execute(['id' => $_SESSION['id_utente']]);
//                        $num = $stmt->fetchColumn();
//                        if($num > 0): ?>
<!--                            <span style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 5px; font-weight: bold;">-->
<!--                                --><?php //= $num ?>
<!--                            </span>-->
<!--                        --><?php //endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">Profilo</a>
                </li>
                <?php if(hasAnyRole(['bibliotecario', 'amministratore'])): ?>
                    <li class="nav-item">
                        <a href="dashboard_bibliotecario.php" class="nav-link">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="gestione_prenotazioni.php" class="nav-link">Code</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">Esci</a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a href="login.php" class="nav-link">Login</a>
                </li>
                <li class="nav-item">
                    <a href="register.php" class="nav-link">Registrati</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<script>
    // Autocomplete
    const searchInput = document.getElementById('searchInput');
    const autocompleteResults = document.getElementById('autocompleteResults');
    let autocompleteTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(autocompleteTimeout);
        const query = this.value.trim();

        if(query.length < 2) {
            autocompleteResults.style.display = 'none';
            return;
        }

        autocompleteTimeout = setTimeout(() => {
            fetch(`autocomplete.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if(data.length > 0) {
                        autocompleteResults.innerHTML = data.map(item => {
                            if(item.type === 'libro') {
                                return `<a href="dettaglio_libro.php?id=${item.id}" class="autocomplete-item">
                                <span class="item-icon">📖</span>
                                <div class="item-info">
                                    <strong>${item.titolo}</strong>
                                    <small>${item.autore || 'Autore sconosciuto'}</small>
                                </div>
                            </a>`;
                            } else if(item.type === 'autore') {
                                return `<a href="ricerca.php?autore=${encodeURIComponent(item.nome)}" class="autocomplete-item">
                                <span class="item-icon">✍️</span>
                                <strong>${item.nome}</strong>
                            </a>`;
                            } else if(item.type === 'categoria') {
                                return `<a href="ricerca.php?categoria=${encodeURIComponent(item.nome)}" class="autocomplete-item">
                                <span class="item-icon">🏷️</span>
                                <strong>${item.nome}</strong>
                            </a>`;
                            }
                        }).join('');
                        autocompleteResults.style.display = 'block';
                    } else {
                        autocompleteResults.style.display = 'none';
                    }
                });
        }, 300);
    });

    // Chiudi autocomplete quando clicchi fuori
    document.addEventListener('click', function(e) {
        if(!searchInput.contains(e.target) && !autocompleteResults.contains(e.target)) {
            autocompleteResults.style.display = 'none';
        }
    });
</script>