// Global Utilities and Session Management
(function () {
    // Helper to detect project root (e.g., /Caravan of Flavours)
    window.getProjectRoot = () => {
        const path = window.location.pathname;
        if (path.includes('/frontend/')) {
            return path.substring(0, path.indexOf('/frontend/'));
        }
        return '';
    };

    const projectRoot = window.getProjectRoot();

    // Inject Loader HTML
    // Inject Loader HTML
    const loaderHTML = `
    <div id="global-loader">
        <div class="spinner"></div>
    </div>`;

    // Append to body when DOM is ready
    if (document.body) {
        document.body.insertAdjacentHTML('beforeend', loaderHTML);
    } else {
        window.addEventListener('DOMContentLoaded', () => {
            document.body.insertAdjacentHTML('beforeend', loaderHTML);
        });
    }

    const getLoader = () => document.getElementById('global-loader');

    window.showLoader = () => {
        const loader = getLoader();
        if (loader) loader.classList.add('visible');
    };

    window.hideLoader = () => {
        const loader = getLoader();
        if (loader) loader.classList.remove('visible');
    };

    // Override fetch to handle loader and session validation
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
        const url = args[0] ? args[0].toString() : '';
        // Don't show loader for background tasks like currency check
        const isBackground = url.includes('get-profile.php') || url.includes('analytics');

        if (!isBackground) showLoader();

        try {
            const response = await originalFetch(...args);

            // Global Session Validation: If any API returns 401, redirect to login
            if (response.status === 401 && !url.includes('auth/login.php')) {
                const projectRoot = window.getProjectRoot();
                const isProtected = window.location.pathname.includes('/customer/') ||
                    window.location.pathname.includes('/farmer/') ||
                    window.location.pathname.includes('/admin/');

                if (isProtected) {
                    window.location.href = projectRoot + '/frontend/auth/login.html';
                }
            }

            return response;
        } catch (error) {
            throw error;
        } finally {
            if (!isBackground) hideLoader();
        }
    };

    // Global Session Check for protected pages
    window.checkSession = async () => {
        try {
            const projectRoot = window.getProjectRoot();
            // Find path to check-session.php relative to project root
            let path = projectRoot + '/backend/api/auth/check-session.php';

            if (window.location.pathname.endsWith('index.html') || window.location.pathname === '/' || window.location.pathname.endsWith('Caravan%20of%20Flavours/')) return; // Home skip

            const response = await originalFetch(path);
            const result = await response.json();
            if (!result.logged_in) {
                window.location.href = projectRoot + '/frontend/auth/login.html';
            }
        } catch (e) { console.error('Session check failed', e); }
    };

    // Handle Back Button and Cache: Force re-validation
    window.addEventListener('pageshow', (event) => {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            // Page loaded from cache (e.g. back button)
            if (window.checkSession) window.checkSession();
        }
    });
})();

// Global Currency Manager
const CurrencyManager = {
    settings: null,
    isFetching: false,

    // Exchange Rates (Base: USD)
    // Initially empty, populated from backend
    rates: {},

    async init() {
        // Return immediately if we have settings AND rates
        if (this.settings && Object.keys(this.rates).length > 0) return this.settings;

        // Try session storage for instant render
        const cached = sessionStorage.getItem('user_currency_settings');
        const cachedRates = sessionStorage.getItem('exchange_rates');

        if (cachedRates) {
            try {
                this.rates = JSON.parse(cachedRates);
            } catch (e) { console.error('Error parsing rates', e); }
        }

        if (cached) {
            try {
                this.settings = JSON.parse(cached);
                // Sanitize: Force USD to 1 even from cache
                if (this.settings.code === 'USD') this.settings.rate = 1;

                this.applyToStaticMarkers();
                this.fetchSettings(); // Verify in background
                this.fetchRates();    // Verify rates in background
                return this.settings;
            } catch (e) {
                console.error('Error parsing cached settings', e);
            }
        }

        // Parallel fetch
        const p1 = this.fetchSettings();
        const p2 = this.fetchRates();
        await Promise.all([p1, p2]);
        return this.settings;
    },

    async fetchRates() {
        const projectRoot = window.getProjectRoot();
        const paths = [
            projectRoot + '/backend/api/currency/get-rates.php',
            '../../backend/api/currency/get-rates.php',
            '../backend/api/currency/get-rates.php'
        ];

        for (const path of paths) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000);
                const response = await fetch(path, { signal: controller.signal });
                clearTimeout(timeoutId);
                if (response.ok) {
                    const result = await response.json();
                    if (result.success && result.rates) {
                        this.rates = result.rates;
                        sessionStorage.setItem('exchange_rates', JSON.stringify(this.rates));
                        if (this.settings && this.settings.code) {
                            this.settings.rate = this.rates[this.settings.code] || 1;
                            sessionStorage.setItem('user_currency_settings', JSON.stringify(this.settings));
                            this.refreshUI();
                        }
                        return;
                    }
                }
            } catch (e) { continue; }
        }
    },

    async fetchSettings() {
        if (this.isFetching) return;
        this.isFetching = true;

        const projectRoot = window.getProjectRoot();
        const paths = [
            projectRoot + '/backend/api/auth/get-profile.php',
            '../../backend/api/auth/get-profile.php',
            '../backend/api/auth/get-profile.php'
        ];

        for (const path of paths) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000);

                const response = await fetch(path, { signal: controller.signal });
                clearTimeout(timeoutId);

                if (response.ok) {
                    const text = await response.text();
                    try {
                        const result = JSON.parse(text);
                        if (result.success) {
                            const code = result.data.currency_code || 'USD';
                            const newSettings = {
                                symbol: result.data.currency_symbol || '$',
                                code: code,
                                rate: (code === 'USD') ? 1 : (this.rates[code] || 1) // Set rate based on code, force 1 for USD
                            };

                            this.settings = newSettings;
                            sessionStorage.setItem('user_currency_settings', JSON.stringify(this.settings));
                            this.isFetching = false;

                            this.applyToStaticMarkers();
                            this.refreshUI();
                            return this.settings;
                        }
                    } catch (e) { }
                }
            } catch (error) { continue; }
        }

        this.isFetching = false;
        if (!this.settings) {
            this.settings = { symbol: '$', code: 'USD', rate: 1 };
            this.applyToStaticMarkers();
        }
        return this.settings;
    },

    format(amount) {
        const settings = this.settings || { symbol: '$', code: 'USD', rate: 1 };
        let num = parseFloat(amount);
        if (isNaN(num)) return amount;

        // Convert Logic: database is USD -> target currency
        // Assumption: 'amount' passed in is always in USD.
        // If the 'amount' could be already converted, we might have issues, 
        // but typically raw data in frontend is what comes from DB (USD).

        const rate = (settings.code === 'USD') ? 1 : (settings.rate || 1);
        num = num * rate;

        return settings.symbol + num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    },

    getSymbol() {
        return this.settings ? this.settings.symbol : '$';
    },

    getCode() {
        return this.settings ? this.settings.code : 'USD';
    },

    applyToStaticMarkers() {
        const symbol = this.getSymbol();
        const code = this.getCode();

        document.querySelectorAll('.currency-symbol').forEach(el => {
            el.textContent = symbol;
            // Ensure visibility
            el.style.opacity = '1';
        });
        document.querySelectorAll('.currency-code').forEach(el => {
            el.textContent = code;
        });
    },

    refreshUI() {
        // List of update functions on various pages
        const refreshers = [
            'loadDashboard',   // Dashboard
            'loadStats',       // Dashboard legacy/etc
            'loadProducts',    // Inventory
            'loadOrders',      // Orders
            'loadCatalog',     // Customer Catalog
            'loadCart',        // Cart
            'loadAdminStats',  // Admin
            'loadAuctions',    // Auctions
            'renderProducts',  // Inventory secondary
            'renderOrders',    // Generic
            'loadTransactions', // Admin Transactions
            'loadActivityLogs', // Admin Activity
            'updatePaymentPrice' // Payment Page Update
        ];

        refreshers.forEach(fn => {
            if (typeof window[fn] === 'function') {
                // Call them safely
                try {
                    window[fn]();
                } catch (e) {
                    console.warn(`Safe refresh failed for ${fn}:`, e);
                }
            }
        });
    }
};

window.CurrencyManager = CurrencyManager;
window.formatPrice = (amount) => CurrencyManager.format(amount);

// Centralized status normalization helper
window.normalizeStatus = (status) => {
    if (!status) return 'ordered';
    const s = String(status).trim().toLowerCase();
    // Default problematic or empty statuses to 'ordered'
    if (s === '' || s === 'unknown' || s === 'unknown status') return 'ordered';
    return s;
};

document.addEventListener('DOMContentLoaded', () => {
    // Start init immediately, but don't await/block
    CurrencyManager.init();

    // Standard UI Interactions
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.product-card').forEach((product, index) => {
        product.style.opacity = '0';
        product.style.transform = 'translateY(20px)';
        product.style.transition = `all 0.6s ease-out ${index * 0.1}s`;
        observer.observe(product);
    });
});
