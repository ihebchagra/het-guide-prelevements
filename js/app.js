document.addEventListener('alpine:init', () => {

    Alpine.data('guideApp', () => ({
        // --- CONFIGURATION ---
        authEnabled: false, // Set to false to disable authentication
        
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
        searchTerm: '',
        lastSearchedTerm: '',
        searchResults: [],
        searchInProgress: false,
        searchPerformed: false,
        searchTitle: 'Recherche',
        lazyLoadObserver: null,
        allTests: Object.values(testsData).flat(),
        
        // New properties for share and PWA
        shareableUrl: window.location.href.split('#')[0], // Clean URL without hash
        linkCopied: false,
        pwaStatus: 'checking',
        deferredPrompt: null,

        init() {
            // If auth is disabled, automatically authenticate
            if (!this.authEnabled) {
                this.isAuthenticated = true;
                history.replaceState({ view: 'home' }, '', '#home');
                this.initDatabase();
            } else {
                // Check localStorage to see if the user is already authenticated
                if (localStorage.getItem('guideAuth') === 'true') {
                    this.isAuthenticated = true;
                    history.replaceState({ view: 'home' }, '', '#home');
                    this.initDatabase();
                }
            }

            // Initialize PWA functionality
            this.initPWA();

            // Add a listener for the browser's back/forward button (and mobile back gesture)
            window.addEventListener('popstate', (event) => {
                // Ignore popstate events if not authenticated
                if (!this.isAuthenticated) return;

                if (event.state && event.state.view) {

                    if(event.state.view === 'search') {
                        this.searchTitle = event.state.title;
                        this.lastSearchedTerm = event.state.lastTerm;
                    }

                    this.navigateToView(event.state.view, true);
                } else {
                    this.navigateToView('home', true);
                }
            });
        },

        handleLogin() {
            if (this.passwordInput === this.hardcodedPassword) {
                // Password is correct
                this.isAuthenticated = true;
                this.loginError = '';
                // Remember this choice in localStorage for future visits (only if auth is enabled)
                if (this.authEnabled) {
                    localStorage.setItem('guideAuth', 'true');
                }

                // On successful login, set the initial history state
                history.replaceState({ view: 'home' }, '', '#home');

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

        // --- NAVIGATION ---
        navigateToView(view, isBackAction = false) {

            if (!isBackAction && this.currentView !== view) {
                const state = { view: view };
                if (view === 'search') {
                    state.title = this.searchTitle;
                    state.lastTerm = this.lastSearchedTerm;
                }
                history.pushState(state, '', `#${view}`);
            }

            this.currentView = view;
            window.scrollTo(0, 0);
        },

        goBack() {
            history.back();
        },

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

        // --- NEW METHODS FOR SHARE & PWA ---
        
        // Copy link to clipboard
        copyLink() {
            const url = this.shareableUrl;
            
            // Modern clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => {
                    this.linkCopied = true;
                    setTimeout(() => {
                        this.linkCopied = false;
                    }, 3000);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                    this.fallbackCopy(url);
                });
            } else {
                // Fallback for older browsers
                this.fallbackCopy(url);
            }
        },
        
        // Fallback copy method
        fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.linkCopied = true;
                setTimeout(() => {
                    this.linkCopied = false;
                }, 3000);
            } catch (err) {
                console.error('Fallback copy failed:', err);
                alert('Impossible de copier automatiquement. Veuillez copier manuellement.');
            }
            
            document.body.removeChild(textArea);
        },
        
        // Initialize PWA detection
        initPWA() {
            // Check if already installed
            if (window.matchMedia('(display-mode: standalone)').matches || 
                window.navigator.standalone === true) {
                this.pwaStatus = 'installed';
                return;
            }
            
            // Detect platform
            const userAgent = navigator.userAgent.toLowerCase();
            const isIOS = /iphone|ipad|ipod/.test(userAgent);
            const isAndroid = /android/.test(userAgent);
            const isChrome = /chrome/.test(userAgent) && !/edg/.test(userAgent);
            const isSafari = /safari/.test(userAgent) && !/chrome/.test(userAgent);
            
            if (isIOS && isSafari) {
                this.pwaStatus = 'ios';
            } else if (isAndroid && !isChrome) {
                this.pwaStatus = 'other-android';
            } else if (isAndroid && isChrome) {
                this.pwaStatus = 'checking';
                // Will be updated by beforeinstallprompt event
            } else {
                this.pwaStatus = 'desktop';
            }
            
            // Listen for install prompt (Chrome/Edge)
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                this.pwaStatus = 'installable';
            });
            
            // Listen for successful installation
            window.addEventListener('appinstalled', () => {
                this.pwaStatus = 'installed';
                this.deferredPrompt = null;
            });
            
            // If no prompt after 1 second and still checking, show desktop
            setTimeout(() => {
                if (this.pwaStatus === 'checking') {
                    this.pwaStatus = 'desktop';
                }
            }, 1000);
        },
        
        // Install PWA
        async installPWA() {
            if (!this.deferredPrompt) {
                return;
            }
            
            // Show the install prompt
            this.deferredPrompt.prompt();
            
            // Wait for the user's response
            const { outcome } = await this.deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('User accepted the install prompt');
            } else {
                console.log('User dismissed the install prompt');
            }
            
            // Clear the deferredPrompt
            this.deferredPrompt = null;
        }

    }));
});
