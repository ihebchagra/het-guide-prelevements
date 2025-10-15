<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, maximum-scale=6.0, minimum-scale=1.0">
    <title>Manuel de Prélèvements</title>

    <?php $version = "1.72"; ?>

    <link rel="manifest" href="/manifest.webmanifest?v=<?php echo $version; ?>">
    <meta name="theme-color" content="#0077c2">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png?v=<?php echo $version; ?>">
    <link rel="icon" href="/favicon.ico?v=<?php echo $version; ?>" sizes="any">

    <link rel="stylesheet" href="css/style.css?v=<?php echo $version; ?>">
    <script src="//unpkg.com/alpinejs" defer></script>
    <script src="js/sql-wasm.js"></script>
</head>
<body>

<div id="app" x-data="guideApp()" x-init="init()">

    <!-- ===== Login View ===== -->
    <div x-show="!isAuthenticated" class="login-container" x-transition>
        <div class="login-box">
            <img src="assets/logo.webp" alt="Logo Guide" class="logo">
            <h1>Accès Protégé</h1>
            <p>Veuillez entrer le mot de passe pour continuer.</p>
            <form @submit.prevent="handleLogin()">
                <input
                    type="password"
                    x-model="passwordInput"
                    placeholder="Mot de passe"
                    aria-label="Mot de passe"
                    :class="{ 'input-error': loginError }"
                    @input="loginError = ''"
                >
                <p x-show="loginError" x-text="loginError" class="error-message"></p>
                <button type="submit">Entrer</button>
            </form>
        </div>
    </div>

    <!-- ===== Main App (only shown after authentication) ===== -->
    <div x-show="isAuthenticated">
        <!-- ===== Loading Screen ===== -->
        <div x-show="isLoading" class="loading-screen" x-transition:leave="transition-opacity ease-in-out duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="loading-content">
                <svg class="logo-spinner" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="60" height="60"><path d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" opacity=".25"/><path d="M10.72,19.9a8,8,0,0,1-6.5-9.79A7.77,7.77,0,0,1,10.4,4.16a8,8,0,0,1,9.49,6.52A1.54,1.54,0,0,0,21.38,12h.13a1.37,1.37,0,0,0,1.38-1.54,11,11,0,1,0-12.7,12.39A1.54,1.54,0,0,0,12,21.34h0A1.47,1.47,0,0,0,10.72,19.9Z"><animateTransform attributeName="transform" type="rotate" dur="0.75s" values="0 12 12;360 12 12" repeatCount="indefinite"/></path></svg>
                <p x-text="loadingStatus"></p>
                <progress x-show="loadingProgress > 0 && loadingProgress < 100" x-bind:value="loadingProgress" max="100"></progress>
            </div>
        </div>

        <!-- ===== Main Content Area ===== -->
        <div x-show="!isLoading" class="main-container">

            <!-- ===== Home View ===== -->
            <div x-show="currentView === 'home'" class="view-content" x-transition>
                <header class="home-header">
                    <img src="assets/logo.webp" alt="Logo Guide" class="logo">
                    <h1>Manuel de Prélèvements</h1>
                    <p>Hôpital d'Enfants Béchir Hamza de Tunis</p>
                </header>

                <div class="search-container">
                    <input type="search" placeholder="Rechercher un examen..." x-model="searchTerm" @keydown.enter="performSearch()" @input="handleInput()" aria-label="Rechercher">
                    <button @click="performSearch()">Chercher</button>
                </div>

                <nav class="navigation-grid">
                    <a @click.prevent="showSection('Examens biochimiques')" href="#" class="nav-card"><h3>🔬 Examens biochimiques</h3><p>Biochimie, hormonologie...</p></a>
                    <a @click.prevent="showSection('Examens hématologiques')" href="#" class="nav-card"><h3>🩸 Examens hématologiques</h3><p>Cytologie, hémostase...</p></a>
                    <a @click.prevent="showSection('Examens microbiologiques')" href="#" class="nav-card"><h3>🦠 Examens microbiologiques</h3><p>Bactériologie, virologie...</p></a>
                    <a @click.prevent="showSection('Annexes')" href="#" class="nav-card"><h3>📋 Annexes</h3><p>Fiches de renseignements...</p></a>
                    <a @click.prevent="navigateToView('share')" href="#" class="nav-card"><h3>🔗 Partager ce site</h3><p>Lien ou QR code</p></a>
                    <a 
                        @click.prevent="navigateToView('pwa')" 
                        href="#" 
                        class="nav-card"
                        x-show="pwaStatus !== 'installed'"
                    >
                        <h3>📥 Télécharger l'application</h3>
                        <p>Installer hors ligne...</p>
                    </a>
                    <a @click.prevent="navigateToView('equipe')" href="#" class="nav-card">
                        <h3>👥 Équipe réalisatrice</h3>
                        <p>Coordinateurs, auteurs et mentions légales</p>
                    </a>
                    <a href="https://drive.google.com/file/d/1RtY1SPUsOLkOZIxP5VSDtDtX3Y25VG2g/view?usp=sharing" class="nav-card"><h3>📘 Télécharger en format PDF</h3><p>Pour l'utilisation hors-ligne</p></a>
                </nav>
            </div>

            <!-- ===== Search / Section View ===== -->
            <div x-show="currentView === 'search'" class="view-content" x-transition>
                 <header class="view-header">
                    <button class="back-button" @click="goBack()">‹ Retour</button>
                    <h2 x-text="searchTitle">Résultats</h2>
                </header>
                <div class="search-container sticky-search">
                    <input type="search" placeholder="Affiner la recherche..." x-model="searchTerm" @keydown.enter="performSearch()" @input="handleInput()" aria-label="Rechercher">
                     <button @click="performSearch()">Chercher</button>
                </div>

                <div class="results-list">
                    <p x-show="searchInProgress" class="info-message">Recherche en cours...</p>
                    <p x-show="!searchInProgress && searchResults.length === 0 && searchPerformed" class="info-message">Aucun résultat trouvé pour "<span x-text="lastSearchedTerm"></span>".</p>

                    <template x-for="(result, index) in searchResults" :key="index">
                        <a href="#" @click.prevent="navigateToPage(result.page_num)" class="result-item">
                            <div class="result-snippet" x-html="result.snippet"></div>
                            <div style="display: flex; align-items: center;">
                                <template x-if="result.is_urgent"><span class="test-tag tag-urgent">Urgent</span></template>
                                <template x-if="result.is_subcontracted"><span class="test-tag tag-subcontracted">Sous-traitance</span></template>
                                <!--<span class="page-number" x-text="'p.' + result.page_num"></span>-->
                            </div>
                        </a>
                    </template>
                </div>
            </div>

        </div>

        <!-- ===== Viewer View ===== -->
        <button x-show="currentView === 'viewer'" class="back-button viewer-back-button" @click="goBack()" x-transition>‹ Retour</button>
        <div x-show="currentView === 'viewer'" class="viewer-wrapper" x-transition>
            <div id="zoom-container">
                    <div id="pages-container">
                        <?php
                        // The exact aspect ratios you provided
                        $aspect_ratio_landscape = '16 / 9';
                        $aspect_ratio_portrait = '1 / 1.41';

                        function get_aspect_ratio_string($page_num) {
                            global $aspect_ratio_portrait, $aspect_ratio_landscape;
                            if (($page_num >= 1 && $page_num <= 27) ||
                                ($page_num >= 182 && $page_num <= 184) ||
                                ($page_num >= 243 && $page_num <= 244) ||
                                ($page_num >= 332 && $page_num <= 371)) {
                                return $aspect_ratio_portrait;
                            } else {
                                return $aspect_ratio_landscape;
                            }
                        }

                        foreach (range(1, 371) as $page) {
                            $aspect_ratio = get_aspect_ratio_string($page);
                            $margin_top_style = ($page === 1) ? 'margin-top: 2rem;' : '';

                            // Here we embed the aspect-ratio directly into the style attribute
                            echo "<img id='page-{$page}'
                                     data-src='assets/pages/page_{$page}.webp'
                                     alt='Page {$page}'
                                     class='lazy-image'
                                     style='aspect-ratio: {$aspect_ratio}; {$margin_top_style}'>";
                        }
                        ?>
                    </div>
            </div>
        </div>
        <!-- ===== Share View (QR Code & Link) ===== -->
        <div x-show="currentView === 'share'" class="view-content" x-transition>
            <header class="view-header">
                <button class="back-button" @click="goBack()">‹ Retour</button>
                <h2>Partager ce site</h2>
            </header>
            
            <div class="share-container">
                <div class="qr-code-section">
                    <h3>Scanner le QR Code</h3>
                    <div class="qr-code-wrapper">
                        <img src="assets/qr-code.webp" alt="QR Code du site" class="qr-code-image">
                    </div>
                    <p class="qr-description">Scannez ce code pour accéder au site</p>
                </div>
                
                <div class="link-section">
                    <h3>Ou copier le lien</h3>
                    <div class="link-copy-wrapper">
                        <input 
                            type="text" 
                            id="shareable-link" 
                            :value="shareableUrl" 
                            readonly 
                            class="link-input"
                            @click="$event.target.select()"
                        >
                        <button 
                            @click="copyLink()" 
                            class="copy-button"
                            :class="{ 'copied': linkCopied }"
                        >
                            <span x-show="!linkCopied">📋 Copier</span>
                            <span x-show="linkCopied">✓ Copié!</span>
                        </button>
                    </div>
                    <p x-show="linkCopied" class="success-message" x-transition>Lien copié dans le presse-papiers!</p>
                </div>
            </div>
        </div>

        <!-- ===== PWA Installation View ===== -->
        <div x-show="currentView === 'pwa'" class="view-content" x-transition>
            <header class="view-header">
                <button class="back-button" @click="goBack()">‹ Retour</button>
                <h2>Installer l'application</h2>
            </header>
            
            <div class="pwa-container">
                <!-- Chrome Android - Native Install -->
                <div x-show="pwaStatus === 'installable'" class="pwa-section" x-transition>
                    <div class="pwa-icon">📱</div>
                    <h3>Installer l'application</h3>
                    <p>Installez cette application pour un accès rapide.</p>
                    <button @click="installPWA()" class="install-button">
                        📥 Installer maintenant
                    </button>
                </div>
                
                <!-- Already Installed -->
                <div x-show="pwaStatus === 'installed'" class="pwa-section pwa-success" x-transition>
                    <div class="pwa-icon">✅</div>
                    <h3>Application déjà installée</h3>
                    <p>Cette application est déjà installée sur votre appareil!</p>
                </div>
                
                <!-- iOS Safari Instructions -->
                <div x-show="pwaStatus === 'ios'" class="pwa-section" x-transition>
                    <div class="pwa-icon">🍎</div>
                    <h3>Installation sur iOS</h3>
                    <p>Pour installer cette application sur votre iPhone ou iPad:</p>
                    <ol class="install-steps">
                        <li>
                            <strong>Appuyez sur le bouton Partager</strong>
                            <span class="step-icon">
                                <svg width="20" height="28" viewBox="0 0 20 28" fill="currentColor">
                                    <path d="M14 9h3.5c.8 0 1.5.7 1.5 1.5v15c0 .8-.7 1.5-1.5 1.5h-15C1.7 27 1 26.3 1 25.5v-15C1 9.7 1.7 9 2.5 9H6V7H2.5C.6 7 0 8.6 0 10.5v15C0 27.4 1.6 29 3.5 29h15c1.9 0 3.5-1.6 3.5-3.5v-15C22 8.6 20.4 7 18.5 7H14v2zm-4-8c.6 0 1 .4 1 1v13.3l3.3-3.2c.4-.4 1-.4 1.4 0 .4.4.4 1 0 1.4l-5 5c-.4.4-1 .4-1.4 0l-5-5c-.4-.4-.4-1 0-1.4.4-.4 1-.4 1.4 0L9 15.3V2c0-.6.4-1 1-1z"/>
                                </svg>
                            </span>
                            (en bas de l'écran)
                        </li>
                        <li>
                            <strong>Faites défiler et appuyez sur</strong> "Sur l'écran d'accueil"
                            <span class="step-icon">➕</span>
                        </li>
                        <li>
                            <strong>Appuyez sur "Ajouter"</strong> en haut à droite
                        </li>
                    </ol>
                </div>
                
                <!-- Other Android Browsers -->
                <div x-show="pwaStatus === 'other-android'" class="pwa-section" x-transition>
                    <div class="pwa-icon">🤖</div>
                    <h3>Utiliser Google Chrome</h3>
                    <p>Pour installer cette application, veuillez ouvrir ce site dans Google Chrome:</p>
                    <ol class="install-steps">
                        <li><strong>Copiez ce lien:</strong></li>
                        <div class="inline-link" x-text="shareableUrl"></div>
                        <li><strong>Ouvrez Google Chrome</strong></li>
                        <li><strong>Collez le lien</strong> et accédez au site</li>
                        <li><strong>Appuyez sur le bouton "Installer"</strong> qui apparaîtra</li>
                    </ol>
                    <button @click="copyLink()" class="copy-button-secondary">
                        <span x-show="!linkCopied">📋 Copier le lien</span>
                        <span x-show="linkCopied">✓ Copié!</span>
                    </button>
                </div>
                
                <!-- Desktop/Other -->
                <div x-show="pwaStatus === 'desktop'" class="pwa-section" x-transition>
                    <div class="pwa-icon">💻</div>
                    <h3>Installation sur ordinateur</h3>
                    <p>Pour installer cette application sur votre ordinateur:</p>
                    <ol class="install-steps">
                        <li><strong>Chrome:</strong> Cliquez sur l'icône ➕ dans la barre d'adresse</li>
                        <li><strong>Edge:</strong> Cliquez sur ⚙️ → Applications → Installer ce site en tant qu'application</li>
                    </ol>
                </div>
            </div>
        </div>
        <!-- ===== Équipe Réalisatrice View ===== -->
        <div x-show="currentView === 'equipe'" class="view-content" x-transition>
            <header class="view-header">
                <button class="back-button" @click="goBack()">‹ Retour</button>
                <h2>Équipe réalisatrice</h2>
            </header>
            
            <div class="equipe-container">
                <!-- Coordinatrice Section -->
                <div class="equipe-section">
                    <h3 class="section-title">Coordinatrice</h3>
                        <div class="person-info">
                            <p class="person-name">Pr Nourelhouda Belhaj Rhouma Toumi</p>
                        </div>
                </div>

                <!-- Auteurs Section -->
                <div class="equipe-section">
                    <h3 class="section-title">Liste des auteurs</h3>
                    <div class="authors-grid">
                        <div class="author-card">
                            <p>Aida Bouafsoun</p>
                        </div>
                        <div class="author-card">
                            <p>Hager Zarrouk</p>
                        </div>
                        <div class="author-card">
                            <p>Hamida Jouini</p>
                        </div>
                        <div class="author-card">
                            <p>Hanen Smaoui</p>
                        </div>
                        <div class="author-card">
                            <p>Khaoula Meftah</p>
                        </div>
                        <div class="author-card">
                            <p>Mariem Othmeni</p>
                        </div>
                        <div class="author-card">
                            <p>Nourelhouda Belhaj Rhouma Toumi</p>
                        </div>
                        <div class="author-card">
                            <p>Rym Dabboubi</p>
                        </div>
                        <div class="author-card">
                            <p>Sirine Ben Hmida</p>
                        </div>
                        <div class="author-card">
                            <p>Siwar Chelbi</p>
                        </div>
                        <div class="author-card">
                            <p>Taieb Ben Messaoud</p>
                        </div>
                    </div>
                </div>

                <!-- Réalisateur du site web -->
                <div class="equipe-section">
                    <h3 class="section-title">Réalisateur du site web</h3>
                        <div class="person-info">
                            <p class="person-name">Résident Iheb Chagra</p>
                        </div>
                </div>

                <!-- License Section -->
                <div class="equipe-section license-section">
                    <h3 class="section-title">Licence du site web</h3>
                    <div class="license-card">
                        <p>Ce site web est distribué sous licence <strong>GNU General Public License v3.0</strong></p>
                        <p class="license-description">
                            Vous êtes libre de partager et de modifier ce code source selon les termes de la licence GPL v3.
                        </p>
                        <a 
                            href="https://github.com/ihebchagra/het-guide-prelevements" 
                            target="_blank" 
                            rel="noopener noreferrer"
                            class="github-link"
                        >
                            <svg height="20" width="20" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/>
                            </svg>
                            Voir le code source sur GitHub
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <footer x-show="currentView !== 'viewer'" class="app-footer" x-transition>
                © <span x-text="new Date().getFullYear()"></span> Hôpital d'Enfants Béchir Hamza de Tunis
        </footer>
    </div>
</div>


<script src="js/test-data.js?v=<?php echo $version; ?>"></script>
<script src="js/app.js?v=<?php echo $version; ?>"></script>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js?v=<?php echo $version; ?>')
                .then(reg => console.log('SW registration successful:', reg.scope))
                .catch(err => console.log('SW registration failed:', err));
        });
    }
</script>

</body>
</html>
