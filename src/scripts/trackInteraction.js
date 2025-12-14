// Script per tracciare le interazioni degli utenti con i libri
console.log('trackInteraction.js caricato - VERSIONE REAL-TIME');

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

    // Setup tracking dei click
    function setupBookCardTracking() {
        const links = document.querySelectorAll('.libro-card a[href*="dettaglio_libro.php"], .libro-card-mini a[href*="dettaglio_libro.php"]');

        links.forEach(link => {
            const urlParams = new URLSearchParams(new URL(link.href).search);
            const bookId = urlParams.get('id');

            if (bookId && link.dataset.listenerAdded !== 'true') {
                link.addEventListener('click', function(e) {
                    console.log('🖱️ Click su libro:', bookId);

                    let source = 'unknown';
                    if (link.closest('.raccomandazioni-section')) source = 'raccomandazioni';
                    else if (link.closest('.correlati-section')) source = 'libri_correlati';
                    else if (link.closest('.trending-section')) source = 'trending';
                    else if (link.closest('.catalogo-grid')) source = 'catalogo';
                    else if (link.closest('.ricerca-container')) source = 'ricerca';

                    trackInteraction(bookId, 'click', null, source);
                });

                link.dataset.listenerAdded = 'true';
            }
        });
    }

    function setupDetailViewTracking() {
        currentBookId = getCurrentBookId();
        interactionSource = getInteractionSource();

        if (currentBookId && window.location.pathname.includes('dettaglio_libro.php')) {
            // NON tracciare "view_dettaglio" all'apertura (già tracciato come "click")
            // Traccia SOLO la durata quando l'utente LASCIA la pagina

            window.addEventListener('beforeunload', function() {
                const duration = Math.floor((Date.now() - pageLoadTime) / 1000);
                if (duration > 2) { // Solo se ha visto la pagina per più di 2 secondi
                    trackInteraction(currentBookId, 'view_dettaglio', duration, interactionSource);
                }
            });
        }
    }

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

    // Inizializzazione
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

    // ========================================================
    // AGGIORNAMENTO PERIODICO COMPLETO (ogni 30 secondi)
    // ========================================================
    if (window.location.pathname.includes('trending.php')) {

        function aggiornaStatisticheTrending() {
            console.log('🔄 Aggiornamento periodico trending...');

            fetch('get_trending_stats.php')
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