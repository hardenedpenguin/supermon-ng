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
        async: false,
        success: function(response) {
            console.log('Login response:', response);
            hideLoginUi();
            if (response.substr(0,5) != 'Sorry') {
                console.log('Login successful, calling alertify.success');
                if (typeof alertify !== 'undefined') {
                    alertify.success("<p style=\"font-size:28px;\"><b>Welcome " + user + "!</b></p>");
                } else {
                    console.error('alertify is not defined!');
                    alert("Welcome " + user + "!");
                }
                sleep(4000).then(() => { window.location.reload(); });
            } else {
                console.log('Login failed, calling alertify.error');
                if (typeof alertify !== 'undefined') {
                    alertify.error("Sorry, Login Failed!");
                } else {
                    console.error('alertify is not defined!');
                    alert("Sorry, Login Failed!");
                }
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX error:', status, error);
            hideLoginUi();
            if (typeof alertify !== 'undefined') {
                alertify.error("Error communicating with server for login.");
            } else {
                console.error('alertify is not defined!');
                alert("Error communicating with server for login.");
            }
        }
    });
    return false;
} 