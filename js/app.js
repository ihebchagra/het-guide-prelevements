document.addEventListener('alpine:init', () => {

    Alpine.data('guideApp', () => ({
        // --- STATE ---
        isAuthenticated: false,
        passwordInput: '',
        loginError: '',
        hardcodedPassword: 'bechirhamza123456',
        isLoading: true,
        loadingStatus: "Initialisation...",
        loadingProgress : 0,
        db: null,
        currentView: 'home',
        historyStack: ['home'],
        searchTerm: '',
        lastSearchedTerm: '',
        searchResults: [],
        searchInProgress: false,
        searchPerformed: false,
        searchTitle: 'Recherche',
        lazyLoadObserver: null,
        allTests: Object.values(testsData).flat(),
        panzoomInstance: null,

        init() {
            // Check localStorage to see if the user is already authenticated
            if (localStorage.getItem('guideAuth') === 'true') {
                this.isAuthenticated = true;
                this.initDatabase();
            }
        },

        handleLogin() {
            if (this.passwordInput === this.hardcodedPassword) {
                // Password is correct
                this.isAuthenticated = true;
                this.loginError = '';
                // Remember this choice in localStorage for future visits
                localStorage.setItem('guideAuth', 'true');
                // Now that we're logged in, start loading the database
                this.initDatabase();
            } else {
                // Password is wrong
                this.loginError = 'Mot de passe incorrect.';
                this.passwordInput = '';
            }
        },
        async initDatabase() {
            try {
                this.updateStatus("Initialisation...", 5);
                this.SQL = await initSqlJs({ locateFile: file => `js/${file}` });
                this.updateStatus("Téléchargement de la base de données...", 20);
                const response = await fetch('assets/db/guide_prelevements.db');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const dbArray = await response.arrayBuffer();
                this.updateStatus("Chargement des données...", 90);
                this.db = new this.SQL.Database(new Uint8Array(dbArray));
                this.updateStatus("Prêt !", 100);
                setTimeout(() => { this.isLoading = false; }, 500);
            } catch (error) {
                console.error("Erreur d'initialisation:", error);
                this.loadingStatus = `Erreur: ${error.message}. Veuillez rafraîchir la page.`;
            }
        },

        updateStatus(message, progress) {
            this.loadingStatus = message;
            if (progress !== undefined) this.loadingProgress = progress;
        },

        navigateToView(view, isBackAction = false) {
            if (this.currentView === 'viewer' && view !== 'viewer') {
                // if (typeof window.cleanupZoomController === 'function') {
                //    window.cleanupZoomController();
                //}
                    //window.cleanupZoomController();
            }

            if (!isBackAction && this.currentView !== view) {
                this.historyStack.push(this.currentView);
            }

            this.currentView = view;
            window.scrollTo(0, 0);
            this.$nextTick(() => {
                this.initPanzoom();
            });

            // **INITIALIZATION STEP**: If the new view is 'viewer'...
            //if (view === 'viewer') {
                // Use $nextTick to ensure the viewer DOM elements are present before initializing.
                //this.$nextTick(() => {
                //    initializeZoomController();
                //});
            //}
        },

        goBack() {
            const previousView = this.historyStack.pop() || 'home';
            this.navigateToView(previousView, true);
            this.destroyPanzoom();
        },

        // The navigateToPage function remains the same as the previous version.
        // It correctly calls navigateToView('viewer'), which now handles initialization.
        navigateToPage(pageNumber) {
            this.navigateToView('viewer');
            this.$nextTick(() => {
                const element = document.getElementById(`page-${pageNumber}`);
                if (element) {
                    if (element.dataset.src) {
                        element.src = element.dataset.src;
                        element.removeAttribute('data-src');
                    }
                    setTimeout(() => {
                        element.scrollIntoView({
                            behavior: 'auto',
                            block: 'center'
                        });
                    }, 50);
                    this.initLazyLoader();
                }
            });
        },

        showSection(sectionTitle) {
            const sectionTests = testsData[sectionTitle] || [];

            this.searchResults = sectionTests.map(test => ({
                page_num: test.page,
                snippet: test.name,
                is_urgent: test.u,
                is_subcontracted: test.s
            }));

            this.searchTitle = sectionTitle;
            this.searchPerformed = true;
            this.lastSearchedTerm = '';
            this.searchTerm = '';
            this.navigateToView('search');
        },

        performSearch() {
            const term = this.searchTerm.trim();
            if (!term) {
                this.searchResults = [];
                this.searchPerformed = false;
                return;
            }
            if (!this.db) return;

            this.searchInProgress = true;
            this.searchPerformed = true;
            this.lastSearchedTerm = term;
            this.searchTitle = `Recherche : "${term}"`;
            this.navigateToView('search');

            setTimeout(() => {
                try {
                    const ftsTerm = term.split(/\s+/).filter(Boolean).map(t => `${t}*`).join(' ');
                    const stmt = this.db.prepare(`
                        SELECT DISTINCT rowid as page_num
                        FROM pages_fts
                        WHERE pages_fts MATCH :term
                        ORDER BY rank
                        LIMIT 50
                    `);
                    stmt.bind({ ':term': ftsTerm });

                    const foundPageNumbers = new Set();
                    while (stmt.step()) {
                        foundPageNumbers.add(stmt.get()[0]);
                    }
                    stmt.free();

                    // Now, find all tests that are on these pages
                    const results = this.allTests.filter(test => foundPageNumbers.has(test.page))
                        .map(test => ({
                            page_num: test.page,
                            snippet: test.name,
                            is_urgent: test.u,
                            is_subcontracted: test.s
                        }));

                    this.searchResults = results;
                } catch (e) {
                    console.error("Search error:", e);
                    this.searchResults = [];
                } finally {
                    this.searchInProgress = false;
                }
            }, 10);
        },

        handleInput() {
            if (!this.searchTerm.trim()) {
                this.searchResults = [];
                this.searchPerformed = false;
            }
        },

        setPlaceholderSizes() {
            const container = document.getElementById('pages-container');
            if (!container) return;
            const containerWidth = container.offsetWidth;
            const placeholders = document.querySelectorAll('.image-placeholder');
            placeholders.forEach(p => {
                const aspectRatio = parseFloat(p.dataset.aspectRatio);
                // Set height only if width is valid
                if (containerWidth > 0 && aspectRatio > 0) {
                    p.style.height = `${containerWidth * aspectRatio}px`;
                }
            });
        },

        initLazyLoader() {
            if (this.lazyLoadObserver) this.lazyLoadObserver.disconnect();

            // Observe the actual <img> tags now.
            const images = document.querySelectorAll('img.lazy-image[data-src]');

            this.lazyLoadObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                });
            }, {
                root: document.getElementById('viewer-wrapper'),
                rootMargin: '1200px 0px 1200px 0px' // Increased margin to load even more images ahead of time
            });

            images.forEach(img => this.lazyLoadObserver.observe(img));
        },

        initPanzoom() {
            if (this.panzoomInstance) {
                this.panzoomInstance.destroy();
            }
            const elem = document.getElementById('zoom-container');

            if (elem) {
                this.panzoomInstance = Panzoom(elem, {
                    maxScale: 6,
                    minScale: 1,
                    canvas: true,
                    contain: 'outside',
                    roundPixels : true,
                    disableYAxis: true,
                    touchAction: "pan-y",

                    // This ensures that horizontal panning only works when zoomed in.
                    panOnlyWhenZoomed: true

                }); // No other complex options are needed.

                // This is for desktop mouse wheel zoom.
                const viewerWrapper = document.querySelector('.viewer-wrapper');
                if (viewerWrapper) {
                    viewerWrapper.addEventListener('wheel', (event) => {
                        if (event.ctrlKey || event.metaKey) {
                            event.preventDefault();
                            this.panzoomInstance.zoomWithWheel(event);
                        }
                    });
                }
            }
        },

        destroyPanzoom() {
            if (this.panzoomInstance) {
                const viewerWrapper = document.querySelector('.viewer-wrapper');
                if (viewerWrapper) {
                    // Important to remove event listeners to prevent memory leaks
                    viewerWrapper.removeEventListener('wheel', this.panzoomInstance.zoomWithWheel);
                }
                this.panzoomInstance.destroy();
                this.panzoomInstance = null;
            }
        }
    }));
});
