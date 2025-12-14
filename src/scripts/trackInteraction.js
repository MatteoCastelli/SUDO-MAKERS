// Script per tracciare le interazioni degli utenti con i libri
console.log('trackInteraction.js caricato e script inizializzato');

(function() {
    let pageLoadTime = Date.now();
    let currentBookId = null;
    let interactionSource = null;

    // Estrai ID libro dalla URL se siamo su dettaglio_libro.php
    function getCurrentBookId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id');
    }

    // Estrai fonte dalla URL o referrer
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

    // Invia tracciamento al server
    function trackInteraction(bookId, type, duration = null, source = null) {
        if (!bookId) return;

        fetch('track_interaction.php', {
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
            .then(data => console.log('Risposta tracking:', data))
            .catch(err => console.error('Tracking error:', err));
    }

    // Traccia click su card libro
    function setupBookCardTracking() {
        const links = document.querySelectorAll('.libro-card a[href*="dettaglio_libro.php"], .libro-card-mini a[href*="dettaglio_libro.php"]');

        links.forEach(link => {
            const urlParams = new URLSearchParams(new URL(link.href).search);
            const bookId = urlParams.get('id');

            if (bookId) {
                if (link.dataset.listenerAdded === 'true') return;

                link.addEventListener('click', function(e) {
                    console.log('Click su libro con ID:', bookId);

                    let source = 'unknown';
                    if (link.closest('.raccomandazioni-section')) {
                        source = 'raccomandazioni';
                    } else if (link.closest('.correlati-section')) {
                        source = 'libri_correlati';
                    } else if (link.closest('.trending-section')) {
                        source = 'trending';
                    } else if (link.closest('.catalogo-grid')) {
                        source = 'catalogo';
                    } else if (link.closest('.ricerca-container')) {
                        source = 'ricerca';
                    }

                    trackInteraction(bookId, 'click', null, source);
                });

                link.dataset.listenerAdded = 'true';
            }
        });
    }

    // Traccia visualizzazione dettaglio libro
    function setupDetailViewTracking() {
        currentBookId = getCurrentBookId();
        interactionSource = getInteractionSource();

        if (currentBookId && window.location.pathname.includes('dettaglio_libro.php')) {
            trackInteraction(currentBookId, 'view_dettaglio', null, interactionSource);

            window.addEventListener('beforeunload', function() {
                const duration = Math.floor((Date.now() - pageLoadTime) / 1000);
                trackInteraction(currentBookId, 'view_dettaglio', duration, interactionSource);
            });

            setTimeout(function() {
                const duration = Math.floor((Date.now() - pageLoadTime) / 1000);
                trackInteraction(currentBookId, 'view_dettaglio', duration, interactionSource);
            }, 30000);
        }
    }

    // Traccia click su pulsante prenotazione
    function setupReservationTracking() {
        document.querySelectorAll('.btn-prenota, .btn-prenotazione').forEach(btn => {
            btn.addEventListener('click', function() {
                const bookId = this.getAttribute('data-book-id') || getCurrentBookId();
                if (bookId) {
                    trackInteraction(bookId, 'prenotazione_tentata', null, 'dettaglio');
                }
            });
        });
    }

    // Traccia ricerche
    function setupSearchTracking() {
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', function() {
                const query = document.getElementById('searchInput').value;
                if (query) {
                    fetch('track_search.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ query: query })
                    }).catch(err => console.error('Search tracking error:', err));
                }
            });
        }
    }

    // Inizializza tutto quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setupBookCardTracking();
            setupDetailViewTracking();
            setupReservationTracking();
            setupSearchTracking();
        });
    } else {
        setupBookCardTracking();
        setupDetailViewTracking();
        setupReservationTracking();
        setupSearchTracking();
    }

    window.reinitializeTracking = function() {
        setupBookCardTracking();
    };

    // ================================================================
    // AGGIORNAMENTO STATISTICHE TRENDING (SOLO SU trending.php)
    // ================================================================

    // Esegui SOLO se siamo su trending.php
    if (window.location.pathname.includes('trending.php')) {

        function aggiornaStatisticheTrending() {
            console.log('[Trending] Inizio aggiornamento statistiche...');

            fetch('get_trending_stats.php')
                .then(res => res.json())
                .then(data => {
                    console.log('[Trending] Dati ricevuti:', data);

                    if (!data.success) {
                        console.error('[Trending] Errore:', data.message);
                        return;
                    }

                    // Aggiorna OGNI libro nella pagina
                    Object.entries(data.data).forEach(([idLibro, stats]) => {
                        const card = document.querySelector(`.libro-card[data-id-libro="${idLibro}"]`);

                        if (card) {
                            console.log(`[Trending] Aggiorno libro ${idLibro}:`, stats);

                            // SELETTORI CORRETTI
                            const prestitiElem = card.querySelector('.prestiti-count');
                            const clickElem = card.querySelector('.click-count');
                            const prenotazioniElem = card.querySelector('.prenotazioni-count');
                            const crescitaContainer = card.querySelector('.crescita-count');

                            // Aggiorna prestiti
                            if (prestitiElem) {
                                prestitiElem.textContent = stats.prestiti_ultimi_7_giorni;
                                console.log(`  - Prestiti aggiornati: ${stats.prestiti_ultimi_7_giorni}`);
                            }

                            // Aggiorna click
                            if (clickElem) {
                                clickElem.textContent = stats.click_ultimi_7_giorni;
                                console.log(`  - Click aggiornati: ${stats.click_ultimi_7_giorni}`);
                            }

                            // Aggiorna prenotazioni
                            if (prenotazioniElem) {
                                prenotazioniElem.textContent = stats.prenotazioni_attive;
                                console.log(`  - Prenotazioni aggiornate: ${stats.prenotazioni_attive}`);
                            }

                            // Aggiorna velocità trend
                            if (crescitaContainer) {
                                const val = Math.round(parseFloat(stats.velocita_trend));
                                if (val > 0) {
                                    crescitaContainer.innerHTML = `📈 <strong>+${val}%</strong> crescita`;
                                } else if (val < 0) {
                                    crescitaContainer.innerHTML = `📉 <strong>${val}%</strong>`;
                                } else {
                                    crescitaContainer.innerHTML = `📊 <strong>Stabile</strong>`;
                                }
                                console.log(`  - Crescita aggiornata: ${val}%`);
                            }
                        } else {
                            console.warn(`[Trending] Card non trovata per libro ${idLibro}`);
                        }
                    });

                    console.log('[Trending] Aggiornamento completato!');
                })
                .catch(err => {
                    console.error('[Trending] Errore fetch:', err);
                });
        }

        // Esegui subito al caricamento
        aggiornaStatisticheTrending();

        // Aggiorna ogni 30 secondi (puoi cambiare)
        setInterval(aggiornaStatisticheTrending, 30000);
    }

})();