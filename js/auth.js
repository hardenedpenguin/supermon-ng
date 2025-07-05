// Authentication functions
function clearLoginForm() {
    document.getElementById('myform').reset();
    const pwCheckbox = document.getElementById("checkbox");
    if (pwCheckbox) pwCheckbox.checked = false;
    const passwdField = document.getElementById("passwd");
    if (passwdField) passwdField.type = "password";
}

function togglePasswordVisibility() {
    var pwField = document.getElementById("passwd");
    var userField = document.getElementById("user");
    var showPwCheckbox = document.getElementById("checkbox");

    if (userField.value) {
        if (pwField.type === "password") {
            pwField.type = "text";
            showPwCheckbox.checked = true;
        } else {
            pwField.type = "password";
            showPwCheckbox.checked = false;
        }
    } else {
        showPwCheckbox.checked = false;
        pwField.type = "password";
    }
}

function hideLoginUi() {
    document.getElementById("login").style.display = "none";
}

function showLoginUi() {
    document.getElementById("login").style.display = "block";
    
    // Re-attach event handlers when login UI is shown
    setTimeout(function() {
        console.log('Login UI shown, re-attaching handlers');
        
        const form = $('#myform');
        const userField = $('#user');
        const passwdField = $('#passwd');
        
        // Form submission handler
        form.off('submit').on('submit', function(e) {
            console.log('Form submit event triggered (after show)');
            e.preventDefault();
            validateCredentials();
        });
        
        // Enter key handler for password field
        passwdField.off('keypress').on('keypress', function(e) {
            console.log('Password field keypress (after show):', e.which);
            if (e.which === 13) { // Enter key
                console.log('Enter key pressed in password field (after show)');
                e.preventDefault();
                validateCredentials();
            }
        });
        
        // Enter key handler for username field
        userField.off('keypress').on('keypress', function(e) {
            console.log('Username field keypress (after show):', e.which);
            if (e.which === 13) { // Enter key
                console.log('Enter key pressed in username field (after show)');
                e.preventDefault();
                passwdField.focus();
            }
        });
        
        // Submit button click handler
        $('.login[type="submit"]').off('click').on('click', function(e) {
            console.log('Submit button clicked (after show)');
            e.preventDefault();
            validateCredentials();
        });
        
        console.log('Login form handlers re-attached after show');
    }, 100);
}

function validateCredentials() {
    console.log('validateCredentials called');
    alert('validateCredentials function called!'); // Temporary debug alert
    var user = document.getElementById("user").value;
    var passwd = document.getElementById("passwd").value;

    console.log('User:', user, 'Passwd length:', passwd.length);

    if (!user || !passwd) {
        console.log('Missing credentials, calling alertify.error');
        if (typeof alertify !== 'undefined') {
            alertify.error("Username and Password are required.");
        } else {
            console.error('alertify is not defined!');
            alert("Username and Password are required.");
        }
        return false;
    }

    console.log('Making AJAX request to login.php');
    $.ajax({
        type: "POST",
        url: "login.php",
        data: {'user': user, 'passwd': passwd},
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        async: false,
        success: function(response) {
            console.log('Login response:', response);
            
            // Try to parse as JSON first (new format)
            let jsonResponse = null;
            try {
                jsonResponse = JSON.parse(response);
            } catch (e) {
                // Not JSON, treat as text response (original format)
                jsonResponse = null;
            }
            
            if (jsonResponse && jsonResponse.success) {
                // New JSON format
                console.log('Login successful (JSON format)');
                hideLoginUi();
                if (typeof alertify !== 'undefined') {
                    alertify.success("<p style=\"font-size:28px;\"><b>Welcome " + user + "!</b></p>");
                } else {
                    console.error('alertify is not defined!');
                    alert("Welcome " + user + "!");
                }
                // Reload the page to update the UI
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else if (jsonResponse && !jsonResponse.success) {
                // JSON format with error
                console.log('Login failed (JSON format):', jsonResponse.message);
                hideLoginUi();
                if (typeof alertify !== 'undefined') {
                    alertify.error(jsonResponse.message || "Login failed. Please check your credentials.");
                } else {
                    console.error('alertify is not defined!');
                    alert(jsonResponse.message || "Login failed. Please check your credentials.");
                }
            } else {
                // Original text format
                if (response.substr(0,5) != 'Sorry') {
                    console.log('Login successful (text format)');
                    hideLoginUi();
                    if (typeof alertify !== 'undefined') {
                        alertify.success("<p style=\"font-size:28px;\"><b>Welcome " + user + "!</b></p>");
                    } else {
                        console.error('alertify is not defined!');
                        alert("Welcome " + user + "!");
                    }
                    // Use the original sleep function if available, otherwise setTimeout
                    if (typeof sleep === 'function') {
                        sleep(4000).then(() => { window.location.reload(); });
                    } else {
                        setTimeout(function() {
                            window.location.reload();
                        }, 4000);
                    }
                } else {
                    console.log('Login failed (text format)');
                    hideLoginUi();
                    if (typeof alertify !== 'undefined') {
                        alertify.error("Sorry, Login Failed!");
                    } else {
                        console.error('alertify is not defined!');
                        alert("Sorry, Login Failed!");
                    }
                }
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX error:', status, error);
            console.log('XHR status:', xhr.status);
            
            // Try to parse error response as JSON
            let errorMessage = "Error communicating with server for login.";
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.message) {
                    errorMessage = errorResponse.message;
                }
            } catch (e) {
                console.log('Could not parse error response as JSON');
            }
            
            hideLoginUi();
            if (typeof alertify !== 'undefined') {
                alertify.error(errorMessage);
            } else {
                console.error('alertify is not defined!');
                alert(errorMessage);
            }
        }
    });
    return false;
}

// Add event handlers when document is ready
$(document).ready(function() {
    console.log('Document ready, setting up login form handlers');
    
    // Check if form elements exist
    const form = $('#myform');
    const userField = $('#user');
    const passwdField = $('#passwd');
    
    console.log('Form elements found:', {
        form: form.length,
        userField: userField.length,
        passwdField: passwdField.length
    });
    
    if (form.length === 0) {
        console.error('Login form not found!');
        return;
    }
    
    // Form submission handler
    form.on('submit', function(e) {
        console.log('Form submit event triggered');
        e.preventDefault();
        validateCredentials();
    });
    
    // Enter key handler for password field
    if (passwdField.length > 0) {
        passwdField.on('keypress', function(e) {
            console.log('Password field keypress:', e.which);
            if (e.which === 13) { // Enter key
                console.log('Enter key pressed in password field');
                e.preventDefault();
                validateCredentials();
            }
        });
    }
    
    // Enter key handler for username field
    if (userField.length > 0) {
        userField.on('keypress', function(e) {
            console.log('Username field keypress:', e.which);
            if (e.which === 13) { // Enter key
                console.log('Enter key pressed in username field');
                e.preventDefault();
                passwdField.focus();
            }
        });
    }
    
    // Also add click handler for submit button as backup
    $('.login[type="submit"]').on('click', function(e) {
        console.log('Submit button clicked');
        e.preventDefault();
        validateCredentials();
    });
    
    console.log('Login form handlers setup complete');
}); 