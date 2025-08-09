<?php
/**
 * Link.php JavaScript and SSE Integration
 * 
 * This file contains the exact JavaScript generation logic
 * extracted from link.php for better organization.
 */

/**
 * Render the main JavaScript for real-time node monitoring
 * @param string $parms Comma-separated node parameters
 * @param array $displaySettings Display configuration
 */
function renderMonitoringJavaScript($parms, $displaySettings) {
    $Displayed_Nodes = $displaySettings['Displayed_Nodes'];
    $Display_Count = $displaySettings['Display_Count'];
    $Show_All = $displaySettings['Show_All'];
    $Show_Detail = $displaySettings['Show_Detail'];
    ?>
<script>
function toTop() {
    window.scrollTo(0, 0);
}
</script>

<script type="text/javascript">
$(document).ready(function() {
  if(typeof(EventSource)!=="undefined") {
    var source=new EventSource("server.php?nodes=<?php echo $parms; ?>");

    source.addEventListener('nodes', function(event) {
        var tabledata = JSON.parse(event.data);
        for (var localNode in tabledata) {
            if (tabledata[localNode].status && tabledata[localNode].status.includes("Node") && tabledata[localNode].status.includes("is not in")) {
                var tableID = 'table_' + tabledata[localNode].node;
                var colspan = (<?php echo (int) $Show_Detail; ?> == 1) ? 7 : 5;
                $('#' + tableID + ' tbody:first').html(`<tr><td colspan="${colspan}">${tabledata[localNode].status}</td></tr>`);
                continue;
            }

            var tablehtml = '';

            var total_nodes = 0;
            var shown_nodes = 0;
            var ndisp = <?php echo (int) $Displayed_Nodes; ?>;
            ndisp++;
            var sdisp = <?php echo (int) $Display_Count; ?>;
            var sall = <?php echo (int) $Show_All; ?>;
            var sdetail = <?php echo (int) $Show_Detail; ?>;

            var localNodeDataFromServer = tabledata[localNode];
            var cos_keyed = localNodeDataFromServer.cos_keyed || 0;
            var tx_keyed = localNodeDataFromServer.tx_keyed || 0;

            let headerStatusTextBase, headerCssClass, headerColspan2 = 1, headerColspan3 = 5;

            if (cos_keyed == 0) {
                if (tx_keyed == 0) { headerStatusTextBase = 'Idle'; headerCssClass = 'gColor'; }
                else { headerStatusTextBase = 'PTT-Keyed'; headerCssClass = 'tColor'; }
            } else {
                if (tx_keyed == 0) { headerStatusTextBase = 'COS-Detected'; headerCssClass = 'lColor'; }
                else {
                    headerStatusTextBase = 'COS-Detected and PTT-Keyed (Full-Duplex)'; headerCssClass = 'bColor';
                    headerColspan2 = 2; headerColspan3 = 4;
                }
            }
            
            let headerStatusDetails = '';
            if (localNodeDataFromServer.cpu_temp) {
                headerStatusDetails = `<br>${localNodeDataFromServer.ALERT || ''}<br>${localNodeDataFromServer.WX || ''}<br>CPU=${localNodeDataFromServer.cpu_temp} - ${localNodeDataFromServer.cpu_up || ''}<br>${localNodeDataFromServer.cpu_load || ''}<br>${localNodeDataFromServer.DISK || ''}`;
                if (headerStatusTextBase === 'PTT-Keyed') headerStatusTextBase = 'PTT-KEYED';
                if (headerStatusTextBase === 'COS-Detected') headerStatusTextBase = 'COS-DETECTED';
                if (headerStatusTextBase === 'COS-Detected and PTT-Keyed (Full-Duplex)') headerStatusTextBase = 'COS-Detected and PTT-Keyed (Full Duplex)';
            }
            
            tablehtml += `<tr class="${headerCssClass}"><td colspan="1" align="center">${localNode}</td><td colspan="${headerColspan2}" align="center"><b>${headerStatusTextBase}${headerStatusDetails}</b></td><td colspan="${headerColspan3}"></td></tr>`;

            if (localNodeDataFromServer.remote_nodes && Array.isArray(localNodeDataFromServer.remote_nodes)) {
                for (var row in localNodeDataFromServer.remote_nodes) {
                    let nodeData = localNodeDataFromServer.remote_nodes[row];
                    if (nodeData.info === "NO CONNECTION") {
                        tablehtml += '<tr><td colspan="' + (sdetail ? 7 : 5) + '">     No Connections.</td></tr>';
                    } else {
                        let nodeNum = nodeData.node;
                        if (nodeNum != 1 || (nodeData.info && nodeData.info !== "NO CONNECTION")) {
                            total_nodes++;
                            if (sall == 1 || (row < ndisp && (nodeData.last_keyed != "Never" || total_nodes < 2))) {
                                shown_nodes++;

                                var rowClass = '';
                                if (nodeData.keyed == 'yes') {
                                    rowClass = (nodeData.mode == 'R') ? 'rxkColor' : 'rColor';
                                } else if (nodeData.mode == 'C') {
                                    rowClass = 'cColor';
                                } else if (nodeData.mode == 'R') {
                                    rowClass = 'rxColor';
                                }
                                tablehtml += rowClass ? `<tr class="${rowClass}">` : '<tr>';

                                tablehtml += `<td id="t${localNode}c0r${row}" align="center" class="nodeNum" onclick="toTop()">${nodeData.node}</td>`;
                                tablehtml += `<td>${nodeData.info != "" ? nodeData.info : (nodeData.ip || '')}</td>`;

                                if (sdetail == 1) {
                                    tablehtml += `<td align="center" id="lkey${localNode}_${row}">${nodeData.last_keyed}</td>`;
                                }
                                tablehtml += `<td align="center">${nodeData.link || 'n/a'}</td>`;
                                tablehtml += `<td align="center">${nodeData.direction || 'n/a'}</td>`;

                                if (sdetail == 1) {
                                    tablehtml += `<td align="right" id="elap${localNode}_${row}">${nodeData.elapsed}</td>`;
                                }

                                var modeText = nodeData.mode;
                                if (nodeData.mode == 'R') modeText = 'RX Only';
                                else if (nodeData.mode == 'T') modeText = 'Transceive';
                                else if (nodeData.mode == 'C') modeText = 'Connecting';
                                else if (nodeData.mode == 'Echolink') modeText = 'Echolink';
                                else if (nodeData.mode == 'Local RX') modeText = 'Local RX';
                                tablehtml += `<td align="center">${modeText || 'n/a'}</td>`;
                                tablehtml += '</tr>';
                            }
                        }
                    }
                }
            } else if (localNodeDataFromServer.info === "NO CONNECTION") {
                 tablehtml += '<tr><td colspan="' + (sdetail ? 7 : 5) + '">     No Connections.</td></tr>';
            }

            if (sdisp === 1 && total_nodes >= shown_nodes && total_nodes > 1) {
                let countText = (shown_nodes == total_nodes) ?
                    `${total_nodes} nodes connected` :
                    `${shown_nodes} shown of ${total_nodes} nodes connected`;
                tablehtml += `<tr><td colspan="2">    ${countText}`;
                tablehtml += '    <a href="#" onclick="toTop()">^^^</a></td>' + (sdetail ? '<td colspan="5">' : '<td colspan="3">') + '</td></tr>';
            }
            $('#table_' + localNode + ' tbody:first').html(tablehtml);
        }
    });

    if (<?php echo (int) $Show_Detail; ?> == 1) {
        var spinny = "*";
        source.addEventListener('nodetimes', function(event) {
            var tabledata = JSON.parse(event.data);
            for (var localNode in tabledata) {
                if (tabledata[localNode].remote_nodes && Array.isArray(tabledata[localNode].remote_nodes)) {
                    for (var row in tabledata[localNode].remote_nodes) {
                        $('#lkey' + localNode + '_' + row).text(tabledata[localNode].remote_nodes[row].last_keyed);
                        $('#elap' + localNode + '_' + row).text(tabledata[localNode].remote_nodes[row].elapsed);
                    }
                }
            }
            const spinChars = ["*", "|", "/", "-", "\\"];
            let currentSpinIndex = spinChars.indexOf(spinny);
            spinny = spinChars[(currentSpinIndex + 1) % spinChars.length];
            $('#spinny').html(spinny);
        });
    }

    source.addEventListener('connection', function(event) {
        var statusdata = JSON.parse(event.data);
        var tableID = 'table_' + statusdata.node;
        var colspan = (<?php echo (int) $Show_Detail; ?> == 1) ? 7 : 5;
        $('#' + tableID + ' tbody:first').html(`<tr><td colspan="${colspan}">${statusdata.status}</td></tr>`);
    });

    source.addEventListener('error', function(event) {
        try {
            var errordata = JSON.parse(event.data);
            if (errordata.node) {
                var tableID = 'table_' + errordata.node;
                var colspan = (<?php echo (int) $Show_Detail; ?> == 1) ? 7 : 5;
                $('#' + tableID + ' tbody:first').html(`<tr><td colspan="${colspan}" class="error-inline">Error: ${errordata.status}</td></tr>`);
            } else if (errordata.status) {
                 var firstNodeTable = $('table[id^="table_"]:first');
                 if (firstNodeTable.length) {
                    var colspan = (<?php echo (int) $Show_Detail; ?> == 1) ? 7 : 5;
                    firstNodeTable.find('tbody:first').html(`<tr><td colspan="${colspan}" class="error-inline">Error: ${errordata.status}</td></tr>`);
                 } else {
                     $("#list_link").html("Error: " + errordata.status);
                 }
            }
        } catch (e) {
        }
    });

  } else {
    $("#list_link").html("Sorry, your browser does not support server-sent events...");
  }
});
</script>
    <?php
}
?>