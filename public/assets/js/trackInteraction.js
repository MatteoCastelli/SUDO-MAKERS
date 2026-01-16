// Script per tracciare le interazioni degli utenti con i libri
console.log('trackInteraction.js caricato - VERSIONE COMPLETA');

(function() {
    let pageLoadTime = Date.now();
    let currentBookId = null;
    let interactionSource = null;

    function getCurrentBookId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id');
    }

    function getInteractionSource() {
        const urlParams = new URLSearchParams(window.location.search);
        const source = urlParams.get('from');
        if (source) return source;

        const referrer = document.referrer;
        if (referrer.includes('homepage')) return 'homepage';
        if (referrer.includes('ricerca')) return 'ricerca';
        if (referrer.includes('raccomandazioni')) return 'raccomandazioni';
        if (referrer.includes('dettaglio_libro')) return 'libro_correlato';
        return 'direct';
    }

    // ========================================================
    // TRACKING con aggiornamento IMMEDIATO delle statistiche
    // ========================================================
    function trackInteraction(bookId, type, duration = null, source = null) {
        if (!bookId) return;

        console.log(`📊 Tracking: libro ${bookId}, tipo ${type}, fonte ${source}`);

        fetch('/SUDO-MAKERS/src/api/track_interaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_libro: bookId,
                tipo_interazione: type,
                durata_visualizzazione: duration,
                fonte: source
            })
        })
            .then(res => res.json())
            .then(data => {
                console.log('✅ Tracking salvato:', data);

                // Se siamo su trending.php, aggiorna IMMEDIATAMENTE le statistiche
                if (window.location.pathname.includes('trending.php') && data.updated_stats) {
                    aggiornaStatisticheLibro(bookId, data.updated_stats);
                }
            })
            .catch(err => console.error('❌ Tracking error:', err));
    }

    // ========================================================
    // AGGIORNA statistiche di UN SINGOLO libro
    // ========================================================
    function aggiornaStatisticheLibro(idLibro, stats) {
        const card = document.querySelector(`.libro-card[data-id-libro="${idLibro}"]`);

        if (!card) return;

        console.log(`📊 Aggiorno libro ${idLibro}:`, stats);

        const clickElem = card.querySelector('.click-count');
        const prestitiElem = card.querySelector('.prestiti-count');
        const prenotazioniElem = card.querySelector('.prenotazioni-count');

        if (clickElem && stats.click_ultimi_7_giorni !== undefined) {
            clickElem.textContent = stats.click_ultimi_7_giorni;

            // Animazione flash per mostrare l'aggiornamento
            clickElem.style.transition = 'all 0.3s';
            clickElem.style.color = '#0c8a1f';
            clickElem.style.transform = 'scale(1.3)';

            setTimeout(() => {
                clickElem.style.color = '';
                clickElem.style.transform = 'scale(1)';
            }, 500);
        }

        if (prestitiElem && stats.prestiti_ultimi_7_giorni !== undefined) {
            prestitiElem.textContent = stats.prestiti_ultimi_7_giorni;
        }

        if (prenotazioniElem && stats.prenotazioni_attive !== undefined) {
            prenotazioniElem.textContent = stats.prenotazioni_attive;
        }
    }

    // ========================================================
    // SETUP TRACKING DEI CLICK SU TUTTE LE CARD
    // ========================================================
    function setupBookCardTracking() {
        console.log('🔧 Inizializzazione tracking click sulle card...');
        
        // Seleziona TUTTI i link verso dettaglio_libro.php
        const links = document.querySelectorAll('a[href*="dettaglio_libro.php"]');
        
        console.log(`📚 Trovati ${links.length} link a dettaglio_libro.php`);

        links.forEach(link => {
            // Estrai l'ID del libro dall'URL
            const url = new URL(link.href);
            const bookId = url.searchParams.get('id');

            if (bookId && link.dataset.listenerAdded !== 'true') {
                link.addEventListener('click', function(e) {
                    console.log('🖱️ Click su libro:', bookId);

                    // Determina la fonte del click in base al contesto
                    let source = 'unknown';
                    
                    // Widget homepage
                    if (link.closest('.raccomandazioni-widget')) {
                        source = 'homepage_widget';
                        console.log('   └─ Fonte: Widget Raccomandazioni Homepage');
                    } 
                    else if (link.closest('.trending-widget')) {
                        source = 'homepage_trending';
                        console.log('   └─ Fonte: Widget Trending Homepage');
                    }
                    // Pagine dedicate
                    else if (link.closest('.raccomandazioni-section')) {
                        source = 'raccomandazioni';
                        console.log('   └─ Fonte: Pagina Raccomandazioni');
                    }
                    else if (link.closest('.correlati-section')) {
                        source = 'libri_correlati';
                        console.log('   └─ Fonte: Libri Correlati');
                    }
                    else if (link.closest('.trending-section') || window.location.pathname.includes('trending.php')) {
                        source = 'trending';
                        console.log('   └─ Fonte: Pagina Trending');
                    }
                    else if (link.closest('.catalogo-grid')) {
                        source = 'catalogo';
                        console.log('   └─ Fonte: Catalogo Principale');
                    }
                    else if (link.closest('.ricerca-container')) {
                        source = 'ricerca';
                        console.log('   └─ Fonte: Risultati Ricerca');
                    }

                    // Traccia immediatamente il click
                    trackInteraction(bookId, 'click', null, source);
                });

                link.dataset.listenerAdded = 'true';
            }
        });
        
        console.log('✅ Tracking click inizializzato su tutti i link');
    }

    // ========================================================
    // TRACKING DETTAGLIO PAGINA
    // ========================================================
    function setupDetailViewTracking() {
        currentBookId = getCurrentBookId();
        interactionSource = getInteractionSource();

        if (currentBookId && window.location.pathname.includes('dettaglio_libro.php')) {
            console.log(`📖 Pagina dettaglio libro ${currentBookId}, fonte: ${interactionSource}`);
            
            // Traccia SOLO la durata quando l'utente LASCIA la pagina
            window.addEventListener('beforeunload', function() {
                const duration = Math.floor((Date.now() - pageLoadTime) / 1000);
                if (duration > 2) { // Solo se ha visto la pagina per più di 2 secondi
                    console.log(`⏱️ Durata visualizzazione: ${duration}s`);
                    trackInteraction(currentBookId, 'view_dettaglio', duration, interactionSource);
                }
            });
        }
    }

    // ========================================================
    // TRACKING PRENOTAZIONI
    // ========================================================
    function setupReservationTracking() {
        document.querySelectorAll('.btn-prenota, .btn-prenotazione').forEach(btn => {
            btn.addEventListener('click', function() {
                const bookId = this.getAttribute('data-book-id') || getCurrentBookId();
                if (bookId) {
                    console.log('📅 Tentativo prenotazione libro:', bookId);
                    trackInteraction(bookId, 'prenotazione_tentata', null, 'dettaglio');
                }
            });
        });
    }

    // ========================================================
    // TRACKING RICERCHE
    // ========================================================
    function setupSearchTracking() {
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', function() {
                const query = document.getElementById('searchInput').value;
                if (query) {
                    console.log('🔍 Ricerca:', query);
                    fetch('track_search.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ query: query })
                    }).catch(err => console.error('Search tracking error:', err));
                }
            });
        }
    }

    // ========================================================
    // INIZIALIZZAZIONE
    // ========================================================
    function initialize() {
        console.log('🚀 Inizializzazione completa tracking...');
        setupBookCardTracking();
        setupDetailViewTracking();
        setupReservationTracking();
        setupSearchTracking();
        console.log('✅ Tracking completamente inizializzato');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    // Funzione globale per re-inizializzare dopo caricamenti dinamici
    window.reinitializeTracking = function() {
        console.log('🔄 Re-inizializzazione tracking...');
        setupBookCardTracking();
    };

    // ========================================================
    // AGGIORNAMENTO PERIODICO TRENDING (solo su trending.php)
    // ========================================================
    if (window.location.pathname.includes('trending.php')) {
        console.log('📊 Modalità Trending: aggiornamento automatico attivo');

        function aggiornaStatisticheTrending() {
            console.log('🔄 Aggiornamento periodico trending...');

            fetch('/SUDO-MAKERS/src/api/get_trending_stats.php')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        console.error('❌ Errore:', data.message);
                        return;
                    }

                    console.log(`✅ Ricevuti dati per ${data.total_books} libri`);

                    Object.entries(data.data).forEach(([idLibro, stats]) => {
                        const card = document.querySelector(`.libro-card[data-id-libro="${idLibro}"]`);

                        if (card) {
                            const prestitiElem = card.querySelector('.prestiti-count');
                            const clickElem = card.querySelector('.click-count');
                            const prenotazioniElem = card.querySelector('.prenotazioni-count');
                            const crescitaContainer = card.querySelector('.crescita-count');

                            if (prestitiElem) prestitiElem.textContent = stats.prestiti_ultimi_7_giorni;
                            if (clickElem) clickElem.textContent = stats.click_ultimi_7_giorni;
                            if (prenotazioniElem) prenotazioniElem.textContent = stats.prenotazioni_attive;

                            if (crescitaContainer) {
                                const val = Math.round(parseFloat(stats.velocita_trend));
                                if (val > 0) {
                                    crescitaContainer.innerHTML = `📈 <strong>+${val}%</strong> crescita`;
                                } else if (val < 0) {
                                    crescitaContainer.innerHTML = `📉 <strong>${val}%</strong>`;
                                } else {
                                    crescitaContainer.innerHTML = `📊 <strong>Stabile</strong>`;
                                }
                            }
                        }
                    });
                })
                .catch(err => console.error('❌ Fetch error:', err));
        }

        // Aggiorna subito
        aggiornaStatisticheTrending();

        // Aggiorna ogni 30 secondi
        setInterval(aggiornaStatisticheTrending, 30000);
    }

})();
