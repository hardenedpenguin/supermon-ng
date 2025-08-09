<?php
/**
 * Link.php JavaScript and SSE Integration
 * 
 * This file contains JavaScript generation and Server-Sent Events
 * logic extracted from link.php for better organization.
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
                if (sdetail == 1) {
                    if (localNodeDataFromServer.cpu_temp) {
                        headerStatusDetails += ' CPU Temp: <span id="cpu_temp_' + localNode + '">' + localNodeDataFromServer.cpu_temp + '</span>';
                    }
                    if (localNodeDataFromServer.cpu_up) {
                        headerStatusDetails += ' CPU Uptime: <span id="cpu_up_' + localNode + '">' + localNodeDataFromServer.cpu_up + '</span>';
                    }
                    if (localNodeDataFromServer.cpu_load) {
                        headerStatusDetails += ' CPU Load: <span id="cpu_load_' + localNode + '">' + localNodeDataFromServer.cpu_load + '</span>';
                    }
                    if (localNodeDataFromServer.ALERT) {
                        headerStatusDetails += ' ALERT: <span id="alert_' + localNode + '">' + localNodeDataFromServer.ALERT + '</span>';
                    }
                    if (localNodeDataFromServer.WX) {
                        headerStatusDetails += ' WX: <span id="wx_' + localNode + '">' + localNodeDataFromServer.WX + '</span>';
                    }
                    if (localNodeDataFromServer.DISK) {
                        headerStatusDetails += ' DISK: <span id="disk_' + localNode + '">' + localNodeDataFromServer.DISK + '</span>';
                    }
                }

                let headerStatusText = headerStatusTextBase + headerStatusDetails;

                tablehtml = '<tr class="' + headerCssClass + '"><td><b>' + localNode + '</b></td><td><b>' + localNodeDataFromServer.info + '</b></td><td colspan="' + headerColspan2 + '"><b>' + headerStatusText + '</b></td>';
                if (sdetail == 1) {
                    tablehtml += '<td colspan="' + headerColspan3 + '">&nbsp;</td>';
                } else {
                    tablehtml += '<td colspan="' + headerColspan3 + '">&nbsp;</td>';
                }
                tablehtml += '</tr>';

                if (localNodeDataFromServer.remote_nodes && localNodeDataFromServer.remote_nodes.length > 0) {
                    total_nodes = localNodeDataFromServer.remote_nodes.length;
                    
                    for (var i = 0; i < localNodeDataFromServer.remote_nodes.length; i++) {
                        var remote_node_data = localNodeDataFromServer.remote_nodes[i];
                        
                        if (shown_nodes >= ndisp) break;
                        
                        var bgclass = '';
                        if (remote_node_data.keyed == 'yes') {
                            bgclass = 'rColor';
                        } else {
                            bgclass = 'cColor';
                        }

                        if (sall == 1 || remote_node_data.keyed == 'yes') {
                            tablehtml += '<tr class="' + bgclass + '">';
                            tablehtml += '<td class="nodeNum">' + remote_node_data.node + '</td>';
                            
                            if (remote_node_data.link && remote_node_data.link !== '') {
                                tablehtml += '<td><a href="' + remote_node_data.link + '" target="_blank">' + remote_node_data.info + '</a></td>';
                            } else {
                                tablehtml += '<td>' + remote_node_data.info + '</td>';
                            }
                            
                            if (sdetail == 1) {
                                tablehtml += '<td>' + (remote_node_data.ip || '') + '</td>';
                            }
                            tablehtml += '<td>' + (remote_node_data.direction || '') + '</td>';
                            tablehtml += '<td>' + (remote_node_data.elapsed || '') + '</td>';
                            if (sdetail == 1) {
                                tablehtml += '<td>' + (remote_node_data.mode || '') + '</td>';
                                tablehtml += '<td>' + (remote_node_data.last_keyed || '') + '</td>';
                            }
                            tablehtml += '</tr>';
                            shown_nodes++;
                        }
                    }
                }

                var summary_info = '';
                if (total_nodes > shown_nodes) {
                    summary_info = ' (' + shown_nodes + ' of ' + total_nodes + ' shown)';
                }

                if (sdetail == 1) {
                    tablehtml += '<tr class="blank"><td colspan="7">' + summary_info + '</td></tr>';
                } else {
                    tablehtml += '<tr class="blank"><td colspan="5">' + summary_info + '</td></tr>';
                }

                $('#table_' + localNode + ' tbody:first').html(tablehtml);
            }
        });

        source.addEventListener('nodetimes', function(event) {
            // Handle node timing updates
            var nodetimedata = JSON.parse(event.data);
            for (var localNode in nodetimedata) {
                // Update timing displays if needed
            }
        });

        source.addEventListener('connection', function(event) {
            var conndata = JSON.parse(event.data);
            // Handle connection status updates
        });

        source.addEventListener('error', function(event) {
            console.log('SSE Error:', event);
        });
      }
    });
    </script>
    <?php
}
?>
