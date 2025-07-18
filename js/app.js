// Main application functionality
$(document).ready(function() {

    // Elements are now conditionally rendered on the server side
    // No need to hide/show them with JavaScript

    // Only run logged-in functionality if user is logged in
    if (window.supermonConfig && window.supermonConfig.isLoggedIn === true) {

        $('#logoutlink').click(function(event) {
            event.preventDefault();
            var user = (window.supermonConfig && window.supermonConfig.currentUser) || 'User';
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

            // Use fixed banner size for certain popups
            const bannerPopups = ["FavoritesPanel", "ControlPanel"];
            let popupWidth = width;
            let popupHeight = height;
            if (bannerPopups.some(name => windowName.startsWith(name))) {
                popupWidth = 880;
                popupHeight = 550;
            } else {
                // Responsive for others
                popupWidth = Math.max(320, Math.min(1200, Math.floor(window.innerWidth * 0.8)));
                popupHeight = Math.max(200, Math.min(900, Math.floor(window.innerHeight * 0.8)));
            }
            const left = Math.floor((window.innerWidth - popupWidth) / 2);
            const top = Math.floor((window.innerHeight - popupHeight) / 2);
            const windowSize = `width=${popupWidth},height=${popupHeight},left=${left},top=${top},resizable=yes,scrollbars=yes`;
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

        $(document).on('click', '#connect, #monitor, #permanent, #localmonitor', function() {
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
                data: { 
                    'remotenode': remoteNode, 
                    'perm': perm, 
                    'button': button, 
                    'localnode': localNode,
                    'csrf_token': csrfToken
                },
                type:'post',
                dataType: 'json',
                success: function(result) { 
                    if (result.success) {
                        alertify.success(result.message);
                    } else {
                        alertify.error(result.message || 'Operation failed');
                    }
                },
                error: function(xhr, status, error) {
                    alertify.error('Connection failed: ' + (xhr.responseJSON?.message || error));
                }
            });
        });

        $(document).on('click', '#disconnect', function() {
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
                        data: { 
                            'remotenode': remoteNode, 
                            'perm': perm, 
                            'button': button, 
                            'localnode': localNode,
                            'csrf_token': csrfToken
                        },
                        type:'post',
                        dataType: 'json',
                        success: function(result) { 
                            if (result.success) {
                                alertify.success(result.message);
                            } else {
                                alertify.error(result.message || 'Operation failed');
                            }
                        },
                        error: function(xhr, status, error) {
                            alertify.error('Disconnect failed: ' + (xhr.responseJSON?.message || error));
                        }
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
            $(document).on('click', selector, function(event) {
                openPopupWindow.call(this, event, ...params); 
            });
        });

        $(document).on('click', '#astreload', function() {
            confirmAndAjax.call(this, "Execute the Asterisk \"iax2, rpt, & extensions Reload\" for node - {localnode}", 'ast_reload.php',
                function(nodeInput, localnode, buttonId) { return { 'node': nodeInput, 'localnode': localnode, 'button': buttonId }; },
                null,
                "No reload performed"
            );
        });
        
        $(document).on('click', '#reboot', function() {
            confirmAndAjax.call(this, "Perform a full Reboot of the AllStar server?<br><br>You can only Reboot the main server from Supermon-ng not remote servers", 'reboot.php',
                function(nodeInput, localnode, buttonId) { return { 'node': nodeInput, 'button': buttonId }; },
                null, 
                "NO Reboot performed"
            );
        });

        $(document).on('click', '#fastrestart', function() {
            confirmAndAjax.call(this, "Perform a Fast-Restart of the AllStar system software at node {localnode}?", 'fastrestart.php',
                function(nodeInput, localnode, buttonId) { return { 'button': buttonId, 'localnode': localnode }; },
                null, 
                "NO action performed"
            );
        });

        $(document).on('click', '#astaroff, #astaron', function() {
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

        $(document).on('click', '#dtmf', function() {
            var localnode = $('#localnode').val();
            var dtmf_command = $('#node').val();

            if (dtmf_command.length == 0) {
                alertify.error("Please enter a DTMF command to execute on node " + localnode + '.');
                return;
            }
            $.ajax({
                url:'dtmf.php',
                data: { 
                    'node': dtmf_command, 
                    'button': this.id, 
                    'localnode': localnode,
                    'csrf_token': csrfToken
                },
                type:'post',
                success: function(result) { alertify.success(result); },
                error: function(xhr, status, error) {
                    alertify.error('DTMF command failed: ' + (xhr.responseText || error));
                }
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

    }

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

    $("#loginlink").click(function(event) {
        event.preventDefault();
        clearLoginForm();
        showLoginUi();
    });
}); 