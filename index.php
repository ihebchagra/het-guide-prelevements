<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <title>Manuel de Prélèvements</title>

    <?php $version = "1.69"; ?>

    <link rel="manifest" href="/manifest.webmanifest?v=<?php echo $version; ?>">
    <meta name="theme-color" content="#0077c2">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png?v=<?php echo $version; ?>">
    <link rel="icon" href="/favicon.ico?v=<?php echo $version; ?>" sizes="any">

    <link rel="stylesheet" href="css/style.css?v=<?php echo $version; ?>">
    <script src="//unpkg.com/alpinejs" defer></script>
    <script src="js/sql-wasm.js"></script>
    <script src="https://unpkg.com/@panzoom/panzoom@4.5.1/dist/panzoom.min.js"></script>
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

            <footer x-show="currentView === 'home' || currentView === 'search'" class="app-footer" x-transition>
                Site web réalisé par Résident Iheb Chagra
            </footer>
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
