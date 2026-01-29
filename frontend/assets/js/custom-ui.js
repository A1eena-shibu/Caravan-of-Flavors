/**
 * Custom UI Library for Caravan of Flavours
 * Replaces native alert() and confirm() with stylish Tailwind components.
 */

const CustomUI = {
    init() {
        if (document.getElementById('custom-ui-container')) return;

        const container = document.createElement('div');
        container.id = 'custom-ui-container';
        // Changed items-end to items-center for Top-Center alignment
        container.className = 'fixed inset-0 pointer-events-none z-[9999] flex flex-col items-center justify-start p-6 gap-4';
        document.body.appendChild(container);

        // Add Modal Overlay
        const overlay = document.createElement('div');
        overlay.id = 'custom-modal-overlay';
        // Verified: flex items-center justify-center centers the modal
        overlay.className = 'fixed inset-0 bg-black/60 backdrop-blur-sm z-[10000] hidden flex items-center justify-center p-4 transition-opacity duration-200 opacity-0';
        overlay.innerHTML = `
            <div class="bg-white border border-stone-200 rounded-[24px] p-8 max-w-md w-full shadow-2xl transform scale-95 transition-all duration-200" id="custom-modal-box">
                <div class="mb-6 text-center">
                    <div class="w-14 h-14 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center mb-4 mx-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-stone-900 mb-2" id="custom-modal-title">Confirm Action</h3>
                    <p class="text-stone-500 text-sm leading-relaxed" id="custom-modal-message">Are you sure you want to proceed?</p>
                </div>
                <div class="flex gap-3">
                    <button id="custom-modal-cancel" class="flex-1 px-6 py-3 rounded-xl border border-stone-200 text-stone-600 font-bold text-sm hover:bg-stone-50 transition-all">CANCEL</button>
                    <button id="custom-modal-confirm" class="flex-1 bg-[#FF7E21] hover:bg-[#E66D1A] text-white py-3 rounded-xl font-bold text-sm shadow-lg hover:shadow-orange-500/20 transition-all">CONFIRM</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    },

    showToast(message, type = 'default') {
        this.init();
        const container = document.getElementById('custom-ui-container');

        const toast = document.createElement('div');

        let icon = '';
        let borderColor = 'border-stone-200';
        let bgColor = 'bg-white';
        let textColor = 'text-stone-800';
        let prefix = '';

        if (type === 'error') {
            prefix = 'ERROR!';
            borderColor = 'border-red-100';
            bgColor = 'bg-red-50';
            textColor = 'text-red-900';
            icon = `<svg class="w-6 h-6 text-red-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
        } else if (type === 'success') {
            prefix = 'SUCCESS!';
            borderColor = 'border-transparent';
            bgColor = 'bg-[#F0FDF4]';
            textColor = 'text-[#14532D]';
            icon = `<svg class="w-6 h-6 text-[#22C55E] flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21C16.9706 21 21 16.9706 21 12C21 7.02944 16.9706 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
        } else {
            prefix = 'NOTE!';
            icon = `<svg class="w-6 h-6 text-orange-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/></svg>`;
        }

        // Prevent double prefix
        const cleanMessage = message.trim();
        const hasPrefix = cleanMessage.toUpperCase().startsWith(prefix);
        const displayMessage = hasPrefix ? cleanMessage.slice(prefix.length).trim() : cleanMessage;

        // Visual tweaks
        toast.className = `pointer-events-auto flex items-center gap-3 px-6 py-4 rounded-full border ${borderColor} ${bgColor} shadow-lg shadow-black/5 transform -translate-y-4 opacity-0 transition-all duration-300 w-auto max-w-xl`;

        toast.innerHTML = `
            ${icon}
            <p class="text-sm font-semibold ${textColor} leading-snug">
                <span class="font-extrabold tracking-wide uppercase mr-1">${prefix}</span>${displayMessage}
            </p>
        `;

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('-translate-y-4', 'opacity-0');
        });

        setTimeout(() => {
            toast.classList.add('-translate-y-4', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    },

    showConfirm(message) {
        this.init();
        return new Promise((resolve) => {
            const overlay = document.getElementById('custom-modal-overlay');
            const box = document.getElementById('custom-modal-box');
            const msgEl = document.getElementById('custom-modal-message');
            const confirmBtn = document.getElementById('custom-modal-confirm');
            const cancelBtn = document.getElementById('custom-modal-cancel');

            msgEl.textContent = message;

            overlay.classList.remove('hidden');

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    overlay.classList.remove('opacity-0');
                    box.classList.remove('scale-95');
                    box.classList.add('scale-100');
                });
            });

            const cleanup = () => {
                overlay.classList.add('opacity-0');
                box.classList.remove('scale-100');
                box.classList.add('scale-95');
                setTimeout(() => {
                    overlay.classList.add('hidden');
                }, 200);
                confirmBtn.onclick = null;
                cancelBtn.onclick = null;
            };

            confirmBtn.onclick = () => {
                cleanup();
                resolve(true);
            };

            cancelBtn.onclick = () => {
                cleanup();
                resolve(false);
            };
        });
    }
};

// Expose global helpers
window.showToast = (msg, type) => CustomUI.showToast(msg, type);
window.showConfirm = (msg) => CustomUI.showConfirm(msg);
