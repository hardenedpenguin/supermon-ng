// Modern application functionality using ES6+ features
class SupermonApp {
    constructor() {
        this.isLoggedIn = window.isLoggedIn || false;
        this.currentUser = window.currentUser || '';
        this.init();
    }

    init() {
        this.setupUI();
        this.bindEvents();
    }

    setupUI() {
        // Hide/show elements based on login state
        if (this.isLoggedIn) {
            $("#loginlink").hide();
        } else {
            $('#connect_form').hide();
            $('#logoutlink').hide();
        }
    }

    bindEvents() {
        // Logout functionality
        $('#logoutlink').on('click', this.handleLogout.bind(this));
        
        // Node operations
        $('#connect, #monitor, #permanent, #localmonitor').on('click', this.handleConnect.bind(this));
        $('#disconnect').on('click', this.handleDisconnect.bind(this));
        
        // System operations
        $('#astreload').on('click', this.handleAsteriskReload.bind(this));
        $('#reboot').on('click', this.handleReboot.bind(this));
        $('#fastrestart').on('click', this.handleFastRestart.bind(this));
        $('#astaroff, #astaron').on('click', this.handleAsteriskToggle.bind(this));
        $('#dtmf').on('click', this.handleDTMF.bind(this));
        $('#map').on('click', this.handleMap.bind(this));
        
        // Popup windows
        this.setupPopups();
        
        // Table interactions
        $('table').on('click', 'td.nodeNum', this.handleNodeClick.bind(this));
        $("#loginlink").on('click', this.handleLoginClick.bind(this));
    }

    async handleLogout(event) {
        event.preventDefault();
        try {
            const response = await $.post("logout.php", "");
            if (!response.startsWith('Sorry')) {
                alertify.success(`<p style="font-size:28px;"><b>Goodbye ${this.currentUser}!</b></p>`);
                setTimeout(() => window.location.reload(), 2000);
            }
        } catch (error) {
            console.error('Logout error:', error);
            alertify.error("Logout failed. Please try again.");
        }
    }

    async handleConnect(event) {
        const button = event.target.id;
        const localNode = $('#localnode').val();
        const remoteNode = $('#node').val();
        const perm = $('input:checkbox:checked').val() || '';
        
        if (!remoteNode) {
            alertify.error(`Please enter the remote node number you want node ${localNode} to connect with.`);
            return;
        }

        try {
            const result = await $.ajax({
                url: 'connect.php',
                data: { remotenode: remoteNode, perm, button, localnode: localNode },
                type: 'post'
            });
            alertify.success(result);
        } catch (error) {
            console.error('Connect error:', error);
            alertify.error("Connection failed. Please try again.");
        }
    }

    async handleDisconnect(event) {
        const button = event.target.id;
        const localNode = $('#localnode').val();
        const remoteNode = $('#node').val();
        const perm = $('input:checkbox:checked').val() || '';

        if (!remoteNode) {
            alertify.error(`Please enter the remote node number you want node ${localNode} to disconnect from.`);
            return;
        }

        const confirmed = await this.showConfirm(`Disconnect ${remoteNode} from ${localNode}?`);
        if (confirmed) {
            try {
                const result = await $.ajax({
                    url: 'disconnect.php',
                    data: { remotenode: remoteNode, perm, button, localnode: localNode },
                    type: 'post'
                });
                alertify.success(result);
            } catch (error) {
                console.error('Disconnect error:', error);
                alertify.error("Disconnect failed. Please try again.");
            }
        }
    }

    async handleAsteriskReload(event) {
        const confirmed = await this.showConfirm("Execute the Asterisk \"iax2, rpt, & extensions Reload\" for node - {localnode}");
        if (confirmed) {
            await this.performAjaxAction('ast_reload.php', {
                node: $('#node').val(),
                localnode: $('#localnode').val(),
                button: event.target.id
            });
        } else {
            alertify.error("No reload performed");
        }
    }

    async handleReboot(event) {
        const confirmed = await this.showConfirm("Perform a full Reboot of the AllStar server?<br><br>You can only Reboot the main server from Supermon-ng not remote servers");
        if (confirmed) {
            await this.performAjaxAction('reboot.php', {
                node: $('#node').val(),
                button: event.target.id
            });
        } else {
            alertify.error("NO Reboot performed");
        }
    }

    async handleFastRestart(event) {
        const confirmed = await this.showConfirm("Perform a Fast-Restart of the AllStar system software at node {localnode}?");
        if (confirmed) {
            await this.performAjaxAction('fastrestart.php', {
                button: event.target.id,
                localnode: $('#localnode').val()
            });
        } else {
            alertify.error("NO action performed");
        }
    }

    async handleAsteriskToggle(event) {
        const button = event.target.id;
        const confirmMsg = button === 'astaroff' 
            ? "Perform Shutdown of AllStar system software?" 
            : "Perform Startup of AllStar system software?";
        
        const confirmed = await this.showConfirm(confirmMsg);
        if (confirmed) {
            await this.performAjaxAction('astaronoff.php', { button });
        } else {
            alertify.error("NO Action performed");
        }
    }

    async handleDTMF(event) {
        const localnode = $('#localnode').val();
        const dtmf_command = $('#node').val();

        if (!dtmf_command) {
            alertify.error(`Please enter a DTMF command to execute on node ${localnode}.`);
            return;
        }

        await this.performAjaxAction('dtmf.php', {
            node: dtmf_command,
            button: event.target.id,
            localnode
        });
    }

    async handleMap(event) {
        const nodeInput = $('#node').val();
        const localnode = $('#localnode').val();
        
        try {
            const result = await $.ajax({
                url: 'bubblechart.php',
                data: { node: nodeInput, localnode, button: event.target.id },
                type: 'post'
            });
            $('#test_area').html(result).stop().css('opacity', 1).fadeIn(50).delay(1000).fadeOut(2000);
        } catch (error) {
            console.error('Map error:', error);
            alertify.error("Failed to load map data.");
        }
    }

    setupPopups() {
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

        Object.entries(popups).forEach(([selector, params]) => {
            $(selector).on('click', (event) => this.openPopupWindow(event, ...params));
        });
    }

    openPopupWindow(event, templateUrl, windowNameTemplate, width, height, checkNodeField = false, errorMessage = '', moveToTop = true) {
        event.preventDefault();
        const localNode = $('#localnode').val();
        const nodeInput = $('#node').val();
        const perm = $('input:checkbox:checked').val() || '';

        if (checkNodeField && !nodeInput) {
            alertify.error(errorMessage.replace('{localnode}', localNode).replace('{node}', nodeInput));
            return;
        }

        const url = templateUrl
            .replace('{localnode}', localNode)
            .replace('{node}', nodeInput)
            .replace('{perm}', perm);
        const windowName = windowNameTemplate.replace('{localnode}', localNode);
        const windowSize = `height=${height},width=${width}`;
        
        const newWindow = window.open(url, windowName, windowSize);
        if (newWindow && moveToTop) {
            newWindow.moveTo(20, 20);
        }
    }

    handleNodeClick(event) {
        $('#connect_form #node').val(event.target.textContent);
        const tableId = $(event.target).closest('table').attr('id');
        if (tableId) {
            const idarr = tableId.split('_');
            if (idarr.length > 1) {
                $('#connect_form #localnode').val(idarr[1]);
            }
        }
    }

    handleLoginClick(event) {
        event.preventDefault();
        if (typeof clearLoginForm === 'function') clearLoginForm();
        if (typeof showLoginUi === 'function') showLoginUi();
    }

    async showConfirm(message) {
        return new Promise((resolve) => {
            alertify.confirm(message, (result) => resolve(result));
        });
    }

    async performAjaxAction(url, data) {
        try {
            const result = await $.ajax({
                url,
                data,
                type: 'post'
            });
            alertify.success(result);
        } catch (error) {
            console.error(`AJAX error for ${url}:`, error);
            alertify.error("Operation failed. Please try again.");
        }
    }
}

// Initialize the app when DOM is ready
$(document).ready(() => {
    new SupermonApp();
}); 