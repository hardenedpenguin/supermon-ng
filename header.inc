<?php
include("user_files/global.inc");
include("common.inc");

if (!isset($SHOWPW)) {
	$SHOWPW = true;
}

if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = "";
}

$Displayed_Nodes = null;
$Display_Count = null;
$Show_All = null;
$Show_Detail = "1";

if (isset($_COOKIE['display-data'])) {
    foreach ($_COOKIE['display-data'] as $name => $value) {
        $name_clean = htmlspecialchars($name);
        $value_clean = htmlspecialchars($value);
        switch ($name_clean) {
            case "number-displayed":
               $Displayed_Nodes = $value_clean;
               break;
            case "show-number":
               $Display_Count = $value_clean;
               break;
            case "show-all":
               $Show_All = $value_clean;
               break;
            case "show-detailed":
               $Show_Detail = $value_clean;
               break;
        }
    }
}

$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode(basename($uri_path));

if (!empty($SMSERVERNAME)) {
    $pageTitle = $SMSERVERNAME . " | Supermon-ng | ";
} else {
    $pageTitle = strtoupper($_SERVER['SERVER_NAME']) . " | Supermon | ";
}

if (preg_match("/voter\.php\?node=(\d+)$/", $_SERVER['REQUEST_URI'], $matches)) {
    $pageTitle .= "RTCM " . $matches[1];
} elseif (preg_match("/link\.php\?nodes=(.+)$/", $_SERVER['REQUEST_URI'], $matches)) {
    $pageTitle .= $matches[1];
} elseif (strpos($uri, "about") !== false) {
    $pageTitle .= "About";
}

$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$home = ($script_dir == '/' || $script_dir == '\\') ? '/' : $script_dir;

?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo htmlspecialchars($pageTitle); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="generator" content="By hand with vi text editor">
<meta name="description" content="AllStar node manager">
<meta name="keywords" content="allstar monitor, app_rpt, asterisk">
<meta name="robots" content="noindex, nofollow">
<meta name="author" content="Tim Sawyer, WD6AWP">
<meta name="mods" content="New features, IRLP capability, Paul Aidukas, KN2R">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link type="text/css" rel="stylesheet" href="supermon-ng.css">
<link type="text/css" rel="stylesheet" href="js/jquery-ui.css">
<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js"></script>
<script src="js/sweetalert2.min.js"></script>
<link rel="stylesheet" href="js/sweetalert2.min.css"/>

<script type="text/javascript">
// Configure SweetAlert2 to behave more like Alertify
Swal.mixin({
    toast: true,
    position: 'top-right',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
}).bindInputClass('form-control').bindDefaults({
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: true,
    showClass: {
        popup: 'animate__animated animate__fadeInDown'
    },
    hideClass: {
        popup: 'animate__animated animate__fadeOutUp'
    }
});

// Custom functions to mimic Alertify behavior
window.alertify = {
    success: function(message) {
        console.log('alertify.success called with:', message);
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            html: message,
            toast: true,
            position: 'top-right',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#d4edda',
            color: '#155724',
            iconColor: '#28a745'
        });
    },
    error: function(message) {
        console.log('alertify.error called with:', message);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html: message,
            toast: true,
            position: 'top-right',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            background: '#f8d7da',
            color: '#721c24',
            iconColor: '#dc3545'
        });
    },
    confirm: function(message, callback) {
        console.log('alertify.confirm called with:', message);
        Swal.fire({
            title: 'Confirm',
            html: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            background: '#fff',
            color: '#333'
        }).then((result) => {
            if (result.isConfirmed) {
                callback(true);
            } else {
                callback(false);
            }
        });
    }
};

// Ensure alertify is available globally
console.log('alertify object defined:', typeof window.alertify);
function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

$(document).ready(function() {

<?php if ($_SESSION['sm61loggedin'] === true) { ?>
    $("#loginlink").hide();
<?php } else { ?>
    $('#connect_form').hide();
    $('#logoutlink').hide();
<?php } ?>

<?php if ($_SESSION['sm61loggedin'] === true) { ?>

    $('#logoutlink').click(function(event) {
        event.preventDefault();
        var user = <?php echo json_encode($_SESSION['user']); ?>;
        $.post("logout.php", "", function(response) {
            if (response.substr(0,5) != 'Sorry') {
                alertify.success("<p style=\"font-size:28px;\"><b>Goodbye " + user + "!</b></p>");
                setTimeout(function() {
                    window.location.reload();
                }, 2000); // Wait 2 seconds before reload
            }
        });
    });

    function openPopupWindow(event, templateUrl, windowNameTemplate, width, height, checkNodeField = false, errorMessage = '', moveToTop = true) {
        event.preventDefault();
        const localNode = $('#localnode').val();
        const nodeInput = $('#node').val();
        const perm = $('input:checkbox:checked').val() || '';

        if (checkNodeField && nodeInput.length === 0) {
            alertify.error(errorMessage.replace('{localnode}', localNode).replace('{node}', nodeInput));
            return;
        }

        const url = templateUrl.replace('{localnode}', localNode).replace('{node}', nodeInput).replace('{perm}', perm);
        const windowName = windowNameTemplate.replace('{localnode}', localNode);
        const windowSize = `height=${height},width=${width}`;
        
        const newWindow = window.open(url, windowName, windowSize);
        if (newWindow && moveToTop) {
            newWindow.moveTo(20, 20);
        }
    }

    function confirmAndAjax(confirmMsgTemplate, ajaxUrl, dataBuilder, successHandler, errorHandlerMsg) {
        const localNode = $('#localnode').val();
        const nodeInput = $('#node').val();
        const buttonId = $(this).attr('id');

        const confirmMessage = confirmMsgTemplate
            .replace('{localnode}', localNode)
            .replace('{node}', nodeInput);

        alertify.confirm(confirmMessage, function(e) {
            if (e) {
                const ajaxData = dataBuilder(nodeInput, localNode, buttonId);
                $.ajax({
                    url: ajaxUrl,
                    data: ajaxData,
                    type: 'post',
                    success: function(result) {
                        if (typeof successHandler === 'function') {
                            successHandler(result);
                        } else {
                            alertify.success(result);
                        }
                    }
                });
            } else {
                if (errorHandlerMsg) alertify.error(errorHandlerMsg);
            }
        });
    }

    $('#connect, #monitor, #permanent, #localmonitor').click(function() {
        var button = this.id;
        var localNode = $('#localnode').val();
        var remoteNode = $('#node').val(); 
        var perm = $('input:checkbox:checked').val() || '';
        
        if (remoteNode.length == 0) {
            alertify.error('Please enter the remote node number you want node ' + localNode + ' to connect with.');
            return;
        }
        $.ajax({
            url:'connect.php',
            data: { 'remotenode': remoteNode, 'perm': perm, 'button': button, 'localnode': localNode },
            type:'post',
            success: function(result) { alertify.success(result); }
        });
    });

    $('#disconnect').click(function() {
        var button = this.id;
        var localNode = $('#localnode').val();
        var remoteNode = $('#node').val();
        var perm = $('input:checkbox:checked').val() || '';

        if (remoteNode.length == 0) {
            alertify.error('Please enter the remote node number you want node ' + localNode + ' to disconnect from.');
            return;
        }
        alertify.confirm("Disconnect " + remoteNode + " from " + localNode + "?", function (e) {
            if (e) {
                $.ajax({
                    url:'disconnect.php',
                    data: { 'remotenode': remoteNode, 'perm': perm, 'button': button, 'localnode': localNode },
                    type:'post',
                    success: function(result) { alertify.success(result); }
                });
            }
        });
    });
    
    const popups = {
        '#controlpanel': ["controlpanel.php?node={localnode}", "ControlPanel{localnode}", 1000, 560],
        '#favoritespanel': ["favorites.php?node={localnode}", "FavoritesPanel{localnode}", 800, 500],
        '#astlog': ["astlog.php", "AsteriskLog{localnode}", 1300, 560],
        '#stats': ["stats.php?node={localnode}", "AllStarStatistics{localnode}", 1400, 560],
        '#cpustats': ["cpustats.php", "CPUstatistics{localnode}", 1000, 760],
        '#database': ["database.php?node={node}&localnode={localnode}", "Database{localnode}", 950, 560],
        '#rptstats': ["rptstats.php?node={node}&localnode={localnode}", "RptStatistics{localnode}", 900, 800],
        '#astlookup': ["astlookup.php?node={node}&localnode={localnode}&perm={perm}", "AstLookup{localnode}", 1000, 500, true, 'Please enter a Callsign or Node number to look up on node {localnode}.'],
        '#astnodes': ["astnodes.php", "AstNodes{localnode}", 750, 560],
        '#extnodes': ["extnodes.php", "ExtNodes{localnode}", 850, 560],
        '#linuxlog': ["linuxlog.php", "LinuxLog{localnode}", 1300, 560],
        '#irlplog': ["irlplog.php", "IRLPLog{localnode}", 1100, 560],
        '#webacclog': ["webacclog.php", "WebAccessLog{localnode}", 1400, 560],
        '#weberrlog': ["weberrlog.php", "WebErrorLog{localnode}", 1400, 560],
        '#openpigpio': ["pi-gpio.php", "Pi-GPIO{localnode}", 900, 900],
        '#openbanallow': ["node-ban-allow.php?node={node}&localnode={localnode}", "Ban-Allow{localnode}", 1050, 700],
        '#smlog': ["smlog.php", "SMLog{localnode}", 1200, 560]
    };

    $.each(popups, function(selector, params) {
        $(selector).click(function(event) {
            openPopupWindow.call(this, event, ...params); 
        });
    });

    $('#astreload').click(function() {
        confirmAndAjax.call(this, "Execute the Asterisk \"iax2, rpt, & extensions Reload\" for node - {localnode}", 'ast_reload.php',
            function(nodeInput, localnode, buttonId) { return { 'node': nodeInput, 'localnode': localnode, 'button': buttonId }; },
            null,
            "No reload performed"
        );
    });
    
    $('#reboot').click(function() {
        confirmAndAjax.call(this, "Perform a full Reboot of the AllStar server?<br><br>You can only Reboot the main server from Supermon-ng not remote servers", 'reboot.php',
            function(nodeInput, localnode, buttonId) { return { 'node': nodeInput, 'button': buttonId }; },
            null, 
            "NO Reboot performed"
        );
    });

    $('#fastrestart').click(function() {
        confirmAndAjax.call(this, "Perform a Fast-Restart of the AllStar system software at node {localnode}?", 'fastrestart.php',
            function(nodeInput, localnode, buttonId) { return { 'button': buttonId, 'localnode': localnode }; },
            null, 
            "NO action performed"
        );
    });

    $('#astaroff, #astaron').click(function() {
        var button = this.id;
        var confirmMsg = (button == 'astaroff') ? 
            "Perform Shutdown of AllStar system software?" : 
            "Perform Startup of AllStar system software?";
        
        alertify.confirm(confirmMsg, function(e) {
            if (e) {
                $.ajax({
                    url: 'astaronoff.php', data: { 'button': button }, type: 'post',
                    success: function(result) { alertify.success(result); }
                });
            } else {
                alertify.error("NO Action performed");
            }
        });
    });

    $('#dtmf').click(function() {
        var localnode = $('#localnode').val();
        var dtmf_command = $('#node').val();

        if (dtmf_command.length == 0) {
            alertify.error("Please enter a DTMF command to execute on node " + localnode + '.');
            return;
        }
        $.ajax({
            url:'dtmf.php',
            data: { 'node': dtmf_command, 'button': this.id, 'localnode': localnode },
            type:'post',
            success: function(result) { alertify.success(result); }
        });
    });

    $('#map').click(function() {
        var nodeInput = $('#node').val();
        var localnode = $('#localnode').val();
        $.ajax({
            url:'bubblechart.php',
            data: { 'node': nodeInput, 'localnode': localnode, 'button': this.id },
            type:'post',
            success: function(result) {
                $('#test_area').html(result).stop().css('opacity', 1).fadeIn(50).delay(1000).fadeOut(2000);
            }
        });
    });

<?php } ?>

    $('table').on('click', 'td.nodeNum', function() {
        $('#connect_form #node').val($(this).text());
        var tableId = $(this).closest('table').attr('id');
        if (tableId) {
            var idarr = tableId.split('_');
            if (idarr.length > 1) {
                 $('#connect_form #localnode').val(idarr[1]);
            }
        }
    });
});
</script>
</head>

<body style="width: 100%; max-width: 880px; margin: auto; <?php echo isset($DISPLAY_BACKGROUND) ? "background-color:$DISPLAY_BACKGROUND;" : ""; ?>">

<?php
$header_div_style = "margin-top:5px;";
if (isset($BACKGROUND)) $header_div_style .= " background-image: url($BACKGROUND);";
if (isset($BACKGROUND_COLOR)) $header_div_style .= " background-color: $BACKGROUND_COLOR;";
if (isset($BACKGROUND_HEIGHT)) $header_div_style .= " height: $BACKGROUND_HEIGHT;";

if ($Show_Detail != "1") {
    $header_div_style = "xfont-size:22px; margin-top: 10px;" . $header_div_style;
}

print "<div id=\"header\" style=\"$header_div_style\">";

$headerTitleClass = ($Show_Detail == "1") ? "headerTitle" : "headerTitle-large";
$headerTitle2Class = ($Show_Detail == "1") ? "headerTitle2" : "headerTitle2-large";
$headerTagClass = ($Show_Detail == "1") ? "headerTag" : "headerTag-large";
$header2TagClass = ($Show_Detail == "1") ? "header2Tag" : "header2Tag-large";

$currentTitle = ($_SESSION['sm61loggedin'] === true) ? $TITLE_LOGGED : $TITLE_NOT_LOGGED;
print "<div id=\"$headerTitleClass\"><a href=\"index.php\"><i>$currentTitle</i></a></div>";

if (isset($MY_URL)) {
    $target = (substr($MY_URL, -1) == ">") ? 'target="_blank"' : '';
    $myUrlClean = (substr($MY_URL, -1) == ">") ? substr_replace($MY_URL,"",-1) : $MY_URL;
    print "<div id=\"$headerTitle2Class\"><a href=\"$myUrlClean\" $target><i>$CALL</i></a></div>";
} else {
    print "<div id=\"$headerTitle2Class\"><i>$CALL</i></div>";
}

print "<div id=\"$headerTagClass\"><i>$LOCATION<br>$TITLE2</i></div>";

if ($_SESSION['sm61loggedin'] === true) {
   print "<div id=\"$header2TagClass\"><i>$TITLE3</i></div>";
}
?>

<div id="headerLogout">
    <div id="headerLogout2">
        <a href="#" id="logoutlink">Logout <?php echo htmlspecialchars($_SESSION["user"]); ?></span></a>
    </div>
</div>

<div id="headerLogin">
    <a href="#" id="loginlink">Login</a>  
</div>

<div id="headerImg"><a href="https://www.allstarlink.org" target="_blank"><img src="allstarlink.jpg" width="70%" style="border: 0px;" alt="Allstar Logo"></a></div>

<?php
if (isset($LOGO_NAME, $LOGO_SIZE, $LOGO_POSITION_RIGHT, $LOGO_POSITION_TOP)) {
    $logo_style = "border: 0px; position:absolute; top:{$LOGO_POSITION_TOP}; right:{$LOGO_POSITION_RIGHT};";
    $logo_img = "<img src=\"$LOGO_NAME\" width=\"$LOGO_SIZE\" style=\"$logo_style\" alt=\"Logo\">";
    if (isset($LOGO_URL)) {
        $logo_target = (substr($LOGO_URL, -1) == ">") ? 'target="_blank"' : '';
        $logoUrlClean = (substr($LOGO_URL, -1) == ">") ? substr_replace($LOGO_URL,"",-1) : $LOGO_URL;
        print "<div><a href=\"$logoUrlClean\" $logo_target>$logo_img</a></div>";
    } else {
        print "<div>$logo_img</div>";
    }
}
?>
</div>

<div class="clearer"></div>

<div style="display:none;" id="login">
    <form id="myform" action="" method="post">
        <fieldset> 
            <label class="exit" style="float:right;clear:both;"><input type="radio" onclick="hideLoginUi()"/><span><b>X</b></span></label>
            <p style="font-size:1.5em;color:white;margin-top:1em;margin-bottom:0.1em;text-align:center;"><b>Supermon-ng</b></p>
            <p style="font-size:1.2em;color:white;margin-top:0;margin-bottom:.5em;text-align:center;"><b>Manager Log in</b></p>
            <input style="font-weight:bold;margin-bottom:.5em;" type="text" id="user" placeholder="Username" autocapitalize="none" required size="10">
            <br>
            <input style="font-weight:bold;" type="password" id="passwd" placeholder="Password" required size="10">
            <br>
            <?php if ($SHOWPW): ?>
                <label style="font-weight:bold;color:white;"><input style="margin-top:1em;" id="checkbox" type="checkbox" onclick="togglePasswordVisibility()">Show Password</label>
            <?php endif; ?>
            <br>
            <label style="font-weight:bold;color:white;"><input type="checkbox" style="font-weight:bold;margin-top:.5em;" onclick="clearLoginForm()">Clear</label>
            <input class="login" style="font-weight:bold;float:right;clear:both;margin-top:.5em;" type="submit" name="submit" value="submit" onclick="return validateCredentials()">
        </fieldset>
    </form>
</div>

<script>
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

$("#loginlink").click(function(event) {
    event.preventDefault();
    clearLoginForm();
    showLoginUi();
});
</script>

<?php include "menu.inc" ?>
<div id="test_area"></div>

</body>
</html>
