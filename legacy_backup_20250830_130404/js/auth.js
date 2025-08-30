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
        const form = $('#myform');
        const userField = $('#user');
        const passwdField = $('#passwd');
        
        // Form submission handler
        form.off('submit').on('submit', function(e) {
            e.preventDefault();
            validateCredentials();
        });
        
        // Enter key handler for password field
        passwdField.off('keypress').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                validateCredentials();
            }
        });
        
        // Enter key handler for username field
        userField.off('keypress').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                passwdField.focus();
            }
        });
        
        // Submit button click handler
        $('.login[type="submit"]').off('click').on('click', function(e) {
            e.preventDefault();
            validateCredentials();
        });
    }, 100);
}

function validateCredentials() {
    var user = document.getElementById("user").value;
    var passwd = document.getElementById("passwd").value;

    if (!user || !passwd) {
        if (typeof alertify !== 'undefined') {
            alertify.error("Username and Password are required.");
        } else {
            alert("Username and Password are required.");
        }
        return false;
    }

    $.ajax({
        type: "POST",
        url: "login.php",
        data: {'user': user, 'passwd': passwd},
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        async: false,
        success: function(response) {
            // Try to parse as JSON first (new format)
            let jsonResponse = null;
            try {
                // If response is already an object, use it directly
                if (typeof response === 'object' && response !== null) {
                    jsonResponse = response;
                } else {
                    jsonResponse = JSON.parse(response);
                }
            } catch (e) {
                // Not JSON, treat as text response (original format)
                jsonResponse = null;
            }
            
            if (jsonResponse && jsonResponse.success) {
                // New JSON format
                hideLoginUi();
                if (typeof alertify !== 'undefined') {
                    alertify.success("<p style=\"font-size:28px;\"><b>Welcome " + user + "!</b></p>");
                } else {
                    alert("Welcome " + user + "!");
                }
                // Reload the page to update the UI
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else if (jsonResponse && !jsonResponse.success) {
                // JSON format with error
                hideLoginUi();
                if (typeof alertify !== 'undefined') {
                    alertify.error(jsonResponse.message || "Login failed. Please check your credentials.");
                } else {
                    alert(jsonResponse.message || "Login failed. Please check your credentials.");
                }
            } else {
                // Original text format - only process if response is a string
                if (typeof response === 'string' && response.substr(0,5) != 'Sorry') {
                    hideLoginUi();
                    if (typeof alertify !== 'undefined') {
                        alertify.success("<p style=\"font-size:28px;\"><b>Welcome " + user + "!</b></p>");
                    } else {
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
                    hideLoginUi();
                    if (typeof alertify !== 'undefined') {
                        alertify.error("Sorry, Login Failed!");
                    } else {
                        alert("Sorry, Login Failed!");
                    }
                }
            }
        },
        error: function(xhr, status, error) {
            // Try to parse error response as JSON
            let errorMessage = "Error communicating with server for login.";
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.message) {
                    errorMessage = errorResponse.message;
                }
            } catch (e) {
                // Could not parse error response as JSON
            }
            
            hideLoginUi();
            if (typeof alertify !== 'undefined') {
                alertify.error(errorMessage);
            } else {
                alert(errorMessage);
            }
        }
    });
    return false;
}

// Add event handlers when document is ready
$(document).ready(function() {
    // Check if form elements exist
    const form = $('#myform');
    const userField = $('#user');
    const passwdField = $('#passwd');
    
    if (form.length === 0) {
        return;
    }
    
    // Form submission handler
    form.on('submit', function(e) {
        e.preventDefault();
        validateCredentials();
    });
    
    // Enter key handler for password field
    if (passwdField.length > 0) {
        passwdField.on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                validateCredentials();
            }
        });
    }
    
    // Enter key handler for username field
    if (userField.length > 0) {
        userField.on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                passwdField.focus();
            }
        });
    }
    
    // Also add click handler for submit button as backup
    $('.login[type="submit"]').on('click', function(e) {
        e.preventDefault();
        validateCredentials();
    });
    
    // Add logout functionality
    $('#logoutlink').on('click', function(event) {
        event.preventDefault();
        
        if (typeof alertify !== 'undefined') {
            alertify.success("<p style=\"font-size:28px;\"><b>Goodbye!</b></p>");
        } else {
            alert("Goodbye!");
        }
        
        $.post("logout.php", "", function(response) {
            if (response.substr(0,5) != 'Sorry') {
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            }
        });
    });
}); 