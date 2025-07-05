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
}

function validateCredentials() {
    console.log('validateCredentials called');
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