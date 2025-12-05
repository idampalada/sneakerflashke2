/**
 * CUSTOM LOADING INDICATOR FOR FILAMENT
 * Letakkan file ini di: resources/js/loading-indicator.js
 * Lalu import di app.js atau langsung di blade layout
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Buat loading message element
    const loadingMessage = document.createElement('div');
    loadingMessage.className = 'custom-loading-message';
    loadingMessage.innerHTML = `
        <div class="spinner"></div>
        <span>Loading data...</span>
    `;
    document.body.appendChild(loadingMessage);

    // Buat progress bar element
    const progressBar = document.createElement('div');
    progressBar.className = 'loading-bar';
    progressBar.style.display = 'none';
    document.body.appendChild(progressBar);

    // Livewire loading start
    document.addEventListener('livewire:load', function () {
        
        // Listen to Livewire events
        Livewire.hook('message.sent', () => {
            // Show loading indicator
            loadingMessage.classList.add('active');
            progressBar.style.display = 'block';
            
            console.log('🔄 Loading orders data...');
        });

        Livewire.hook('message.processed', () => {
            // Hide loading indicator setelah selesai
            setTimeout(() => {
                loadingMessage.classList.remove('active');
                progressBar.style.display = 'none';
                
                console.log('✅ Orders data loaded successfully!');
            }, 300); // Delay sedikit agar smooth
        });

        Livewire.hook('message.failed', () => {
            // Show error message
            loadingMessage.classList.remove('active');
            progressBar.style.display = 'none';
            
            // Show error notification
            const errorMessage = document.createElement('div');
            errorMessage.className = 'custom-loading-message active';
            errorMessage.style.background = '#ef4444';
            errorMessage.innerHTML = `
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span>Failed to load data. Please try again.</span>
            `;
            document.body.appendChild(errorMessage);
            
            setTimeout(() => {
                errorMessage.remove();
            }, 3000);
            
            console.error('❌ Failed to load orders data!');
        });
    });

    // Additional: Track pagination clicks
    document.addEventListener('click', function(e) {
        // Check if clicked element is pagination button
        if (e.target.closest('.fi-pagination button')) {
            console.log('📄 Changing page...');
        }
        
        // Check if clicked element is per-page select
        if (e.target.closest('[wire\\:model*="tableRecordsPerPage"]')) {
            console.log('🔢 Changing entries per page...');
        }
    });

    // Monitor table changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            // Check if table content changed
            if (mutation.target.classList.contains('fi-ta-content')) {
                console.log('🔄 Table content updated');
            }
        });
    });

    // Start observing
    const tableContainer = document.querySelector('.filament-tables-container');
    if (tableContainer) {
        observer.observe(tableContainer, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['wire:loading']
        });
    }

    // Performance monitoring
    let loadStartTime;
    
    document.addEventListener('livewire:load', function() {
        Livewire.hook('message.sent', () => {
            loadStartTime = performance.now();
        });

        Livewire.hook('message.processed', () => {
            const loadTime = performance.now() - loadStartTime;
            console.log(`⏱️ Load time: ${loadTime.toFixed(2)}ms`);
            
            // Show warning if load time > 2 seconds
            if (loadTime > 2000) {
                console.warn('⚠️ Slow loading detected. Load time:', loadTime.toFixed(2) + 'ms');
            }
        });
    });

    // Keyboard shortcut untuk refresh (optional)
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + R untuk manual refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            if (typeof Livewire !== 'undefined') {
                Livewire.emit('refreshOrders');
                console.log('🔄 Manual refresh triggered');
            }
        }
    });

});

// Export untuk digunakan di tempat lain jika perlu
window.showLoadingMessage = function(message) {
    const loadingEl = document.querySelector('.custom-loading-message');
    if (loadingEl) {
        loadingEl.querySelector('span').textContent = message;
        loadingEl.classList.add('active');
    }
};

window.hideLoadingMessage = function() {
    const loadingEl = document.querySelector('.custom-loading-message');
    if (loadingEl) {
        loadingEl.classList.remove('active');
    }
};