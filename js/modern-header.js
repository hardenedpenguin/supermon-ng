// Modern header initialization and enhancements
class ModernHeader {
    constructor() {
        this.init();
    }

    async init() {
        await this.registerServiceWorker();
        this.setupProgressiveWebApp();
        this.setupAccessibility();
        this.setupPerformanceMonitoring();
        this.setupErrorHandling();
    }

    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('js/sw.js');
                
                // Handle updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New content is available
                            this.showUpdateNotification();
                        }
                    });
                });
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }
    }

    setupProgressiveWebApp() {
        // Add to home screen functionality
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            this.showInstallPrompt();
        });

        // Handle successful installation
        window.addEventListener('appinstalled', () => {
            this.hideInstallPrompt();
        });
    }

    setupAccessibility() {
        // Skip to main content link
        this.addSkipLink();
        
        // Keyboard navigation improvements
        this.setupKeyboardNavigation();
        
        // Screen reader announcements
        this.setupScreenReaderSupport();
    }

    setupPerformanceMonitoring() {
        // Monitor Core Web Vitals
        if ('PerformanceObserver' in window) {
            try {
                const observer = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        // Send to analytics if needed
                        if (entry.name === 'LCP' && entry.value > 2500) {
                            console.warn('LCP is too slow:', entry.value);
                        }
                    }
                });
                
                observer.observe({ entryTypes: ['largest-contentful-paint', 'first-input', 'layout-shift'] });
            } catch (error) {
                console.error('Performance monitoring failed:', error);
            }
        }
    }

    setupErrorHandling() {
        // Global error handler
        window.addEventListener('error', (event) => {
            console.error('Global error:', event.error);
            this.reportError(event.error);
        });

        // Unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            this.reportError(event.reason);
        });
    }

    addSkipLink() {
        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.textContent = 'Skip to main content';
        skipLink.className = 'skip-link sr-only';
        skipLink.style.cssText = `
            position: absolute;
            top: -40px;
            left: 6px;
            z-index: 1000;
            color: white;
            background: #000;
            padding: 8px;
            text-decoration: none;
            border-radius: 4px;
        `;
        
        skipLink.addEventListener('focus', () => {
            skipLink.style.top = '6px';
        });
        
        skipLink.addEventListener('blur', () => {
            skipLink.style.top = '-40px';
        });
        
        document.body.insertBefore(skipLink, document.body.firstChild);
    }

    setupKeyboardNavigation() {
        // Trap focus in modals
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeModals();
            }
        });

        // Improve focus management
        document.addEventListener('focusin', (event) => {
            if (event.target.matches('input, button, a, select, textarea')) {
                event.target.classList.add('focused');
            }
        });

        document.addEventListener('focusout', (event) => {
            event.target.classList.remove('focused');
        });
    }

    setupScreenReaderSupport() {
        // Live regions for dynamic content
        const liveRegion = document.createElement('div');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.className = 'sr-only';
        document.body.appendChild(liveRegion);

        // Announce page changes
        this.announceToScreenReader = (message) => {
            liveRegion.textContent = message;
            setTimeout(() => {
                liveRegion.textContent = '';
            }, 1000);
        };
    }

    showUpdateNotification() {
        if (typeof alertify !== 'undefined') {
            alertify.confirm(
                'New version available',
                'A new version of Supermon-ng is available. Would you like to update?',
                (confirmed) => {
                    if (confirmed) {
                        window.location.reload();
                    }
                }
            );
        }
    }

    showInstallPrompt() {
        if (typeof alertify !== 'undefined') {
            alertify.confirm(
                'Install Supermon-ng',
                'Would you like to install Supermon-ng as a web app for easier access?',
                (confirmed) => {
                    if (confirmed && window.deferredPrompt) {
                        window.deferredPrompt.prompt();
                        window.deferredPrompt.userChoice.then((choiceResult) => {
                            if (choiceResult.outcome === 'accepted') {
                                // User accepted the install prompt
                            } else {
                                // User dismissed the install prompt
                            }
                            window.deferredPrompt = null;
                        });
                    }
                }
            );
        }
    }

    hideInstallPrompt() {
        // Hide any install prompts
        const installPrompts = document.querySelectorAll('.install-prompt');
        installPrompts.forEach(prompt => prompt.remove());
    }

    closeModals() {
        // Close any open modals
        const modals = document.querySelectorAll('.modal, [role="dialog"]');
        modals.forEach(modal => {
            if (modal.style.display !== 'none') {
                modal.style.display = 'none';
            }
        });
    }

    reportError(error) {
        // Send error to analytics or logging service
        console.error('Error reported:', error);
        
        // You could send to a logging service here
        // fetch('/api/log-error', {
        //     method: 'POST',
        //     body: JSON.stringify({
        //         message: error.message,
        //         stack: error.stack,
        //         url: window.location.href,
        //         userAgent: navigator.userAgent
        //     })
        // });
    }

    // Public methods
    announce(message) {
        if (this.announceToScreenReader) {
            this.announceToScreenReader(message);
        }
    }
}

// Initialize modern header features
$(document).ready(() => {
    window.modernHeader = new ModernHeader();
});

// Export for use in other modules
window.ModernHeader = ModernHeader; 