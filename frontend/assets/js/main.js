// Global Loader Logic
(function() {
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

    // Intercept Fetch API to auto-show loader
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
        // Optional: Filter out background pings if needed
        showLoader();
        try {
            const response = await originalFetch(...args);
            return response;
        } catch (error) {
            throw error;
        } finally {
            hideLoader();
        }
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    // Add hover sound or micro-interactions
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', () => {
            // Subtle scale effect already in CSS, but could add more here
        });
    });

    // Simple scroll reveal
    const observerOptions = {
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    const products = document.querySelectorAll('.product-card');
    products.forEach((product, index) => {
        product.style.opacity = '0';
        product.style.transform = 'translateY(20px)';
        product.style.transition = `all 0.6s ease-out ${index * 0.1}s`;
        observer.observe(product);
    });

    // Ticker animation duplication for seamless loop (if needed)
    const tickerContent = document.querySelector('.ticker-content');
    if (tickerContent) {
        tickerContent.innerHTML += tickerContent.innerHTML;
    }

    // Heat level button interaction
    const heatBtns = document.querySelectorAll('.heat-btn');
    heatBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            heatBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });
});
