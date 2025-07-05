// Modern authentication functionality
class AuthManager {
    constructor() {
        this.form = document.getElementById('myform');
        this.userField = document.getElementById('user');
        this.passwordField = document.getElementById('passwd');
        this.checkbox = document.getElementById('checkbox');
        this.loginContainer = document.getElementById('login');
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupFormValidation();
    }

    setupEventListeners() {
        // Password visibility toggle
        if (this.checkbox) {
            this.checkbox.addEventListener('change', this.togglePasswordVisibility.bind(this));
        }

        // Form submission
        if (this.form) {
            this.form.addEventListener('submit', this.handleSubmit.bind(this));
        }

        // Real-time validation
        if (this.userField) {
            this.userField.addEventListener('input', this.validateUserField.bind(this));
        }

        if (this.passwordField) {
            this.passwordField.addEventListener('input', this.validatePasswordField.bind(this));
        }
    }

    setupFormValidation() {
        // Add modern form validation attributes
        if (this.userField) {
            this.userField.setAttribute('minlength', '1');
            this.userField.setAttribute('maxlength', '50');
            this.userField.setAttribute('pattern', '[a-zA-Z0-9_-]+');
        }

        if (this.passwordField) {
            this.passwordField.setAttribute('minlength', '1');
            this.passwordField.setAttribute('maxlength', '100');
        }
    }

    validateUserField() {
        const value = this.userField.value.trim();
        const isValid = value.length >= 1 && /^[a-zA-Z0-9_-]+$/.test(value);
        
        this.userField.classList.toggle('valid', isValid);
        this.userField.classList.toggle('invalid', !isValid && value.length > 0);
        
        return isValid;
    }

    validatePasswordField() {
        const value = this.passwordField.value;
        const isValid = value.length >= 1;
        
        this.passwordField.classList.toggle('valid', isValid);
        this.passwordField.classList.toggle('invalid', !isValid && value.length > 0);
        
        return isValid;
    }

    togglePasswordVisibility() {
        if (!this.userField || !this.passwordField || !this.checkbox) return;

        const hasUserValue = this.userField.value.trim().length > 0;
        
        if (hasUserValue) {
            const isPassword = this.passwordField.type === 'password';
            this.passwordField.type = isPassword ? 'text' : 'password';
            this.checkbox.checked = isPassword;
        } else {
            this.checkbox.checked = false;
            this.passwordField.type = 'password';
        }
    }

    clearForm() {
        if (this.form) {
            this.form.reset();
        }
        
        if (this.checkbox) {
            this.checkbox.checked = false;
        }
        
        if (this.passwordField) {
            this.passwordField.type = 'password';
        }

        // Clear validation classes
        if (this.userField) {
            this.userField.classList.remove('valid', 'invalid');
        }
        
        if (this.passwordField) {
            this.passwordField.classList.remove('valid', 'invalid');
        }
    }

    hideLoginUI() {
        if (this.loginContainer) {
            this.loginContainer.style.display = 'none';
        }
    }

    showLoginUI() {
        if (this.loginContainer) {
            this.loginContainer.style.display = 'block';
            // Focus on username field for better UX
            if (this.userField) {
                this.userField.focus();
            }
        }
    }

    async handleSubmit(event) {
        event.preventDefault();
        
        const user = this.userField?.value.trim();
        const password = this.passwordField?.value;

        // Validate inputs
        if (!this.validateUserField() || !this.validatePasswordField()) {
            alertify.error("Please enter valid username and password.");
            return false;
        }

        if (!user || !password) {
            alertify.error("Username and Password are required.");
            return false;
        }

        try {
            // Show loading state
            this.setLoadingState(true);
            
            const response = await this.performLogin(user, password);
            
            if (response.success) {
                this.hideLoginUI();
                alertify.success(`<p style="font-size:28px;"><b>Welcome ${user}!</b></p>`);
                
                // Reload page after delay
                setTimeout(() => {
                    window.location.reload();
                }, 4000);
            } else {
                alertify.error(response.message || "Login failed. Please check your credentials.");
            }
        } catch (error) {
            console.error('Login error:', error);
            alertify.error("Login failed. Please try again.");
        } finally {
            this.setLoadingState(false);
        }

        return false;
    }

    async performLogin(username, password) {
        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    user: username,
                    passwd: password
                })
            });

            const result = await response.text();
            
            if (result.startsWith('Sorry')) {
                return { success: false, message: result };
            } else {
                return { success: true, message: result };
            }
        } catch (error) {
            throw new Error('Network error during login');
        }
    }

    setLoadingState(isLoading) {
        const submitButton = this.form?.querySelector('input[type="submit"]');
        if (submitButton) {
            submitButton.disabled = isLoading;
            submitButton.value = isLoading ? 'Logging in...' : 'submit';
        }

        // Disable form fields during loading
        const formFields = this.form?.querySelectorAll('input[type="text"], input[type="password"]');
        if (formFields) {
            formFields.forEach(field => {
                field.disabled = isLoading;
            });
        }
    }

    // Public methods for external use
    static clearLoginForm() {
        if (window.authManager) {
            window.authManager.clearForm();
        }
    }

    static showLoginUi() {
        if (window.authManager) {
            window.authManager.showLoginUI();
        }
    }

    static hideLoginUi() {
        if (window.authManager) {
            window.authManager.hideLoginUI();
        }
    }

    static validateCredentials() {
        if (window.authManager) {
            return window.authManager.handleSubmit(new Event('submit'));
        }
        return false;
    }
}

// Initialize auth manager when DOM is ready
$(document).ready(() => {
    window.authManager = new AuthManager();
});

// Expose legacy functions for backward compatibility
window.clearLoginForm = AuthManager.clearLoginForm;
window.showLoginUi = AuthManager.showLoginUi;
window.hideLoginUi = AuthManager.hideLoginUi;
window.validateCredentials = AuthManager.validateCredentials; 