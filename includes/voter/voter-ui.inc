<?php
/**
 * Supermon-ng Voter UI
 * 
 * Provides the JavaScript functionality and HTML rendering for the voter interface.
 * Handles server-sent events, AJAX setup, and voter display components.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output voter parameter error
 */
function outputVoterParameterError(): void {
    die("Please provide voter node number(s). (e.g., voter.php?node=1234 or voter.php?node=1234,5678)");
}

/**
 * Output voter nodes validation error
 */
function outputVoterNodesError(): void {
    die("No valid voter node numbers provided. Please ensure nodes are comma-separated if multiple and are not empty.");
}

/**
 * Generate voter JavaScript setup
 * 
 * @param array $jsNodesArray JavaScript-safe nodes array
 */
function generateVoterJavaScript(array $jsNodesArray): void {
    ?>
    <script>
        $.ajaxSetup ({
            cache: false,
            timeout: 3000
        });

        $(document).ready(function() {
            const nodes = <?php echo json_encode($jsNodesArray); ?>;

            if (typeof(EventSource) !== "undefined") {
                nodes.forEach(function(node) {
                    if (node && node.trim() !== "") {
                        let source = new EventSource("voterserver.php?node=" + encodeURIComponent(node));
                        
                        source.onmessage = function(event) {
                            try {
                                const data = JSON.parse(event.data);
                                $("#link_list_" + node).html(data.html);
                                if (data.spinner) {
                                    $("#spinner_" + node).text(data.spinner);
                                }
                            } catch (e) {
                                $("#link_list_" + node).html("<div class='error-message'>Received invalid data from server.</div>");
                            }
                        };

                        source.onerror = function(error) {
                            $("#spinner_" + node).text('X');
                            $("#link_list_" + node).html("<div class='error-message'>Error receiving updates for node " + node + ". The connection was lost.</div>");
                        };
                    }
                });
            } else {
                nodes.forEach(function(node) {
                    if (node && node.trim() !== "") {
                        $("#link_list_" + node).html("Sorry, your browser does not support server-sent events...");
                    }
                });
            }
        });
    </script>
    <?php
}

/**
 * Generate voter container HTML
 * 
 * @param array $passedNodes Array of validated nodes
 */
function generateVoterContainers(array $passedNodes): void {
    foreach ($passedNodes as $node) {
        $safeNode = getVoterSafeNode($node);
        ?>
        <div class="voter-container">
            <div id="link_list_<?php echo $safeNode; ?>">
                Connecting to Node <?php echo $safeNode; ?>...
            </div>
            <div class="voter-spinner">
                <span id="spinner_<?php echo $safeNode; ?>" class="spinner-text"></span>
            </div>
        </div>
        <hr class="voter-separator" />
        <?php
    }
}

/**
 * Generate voter information section
 */
function generateVoterInfo(): void {
    ?>
    <div class="voter-info-container">
        <div class="voter-description">
            The numbers indicate the relative signal strength. The value ranges from 0 to 255, a range of approximately 30db.
            A value of zero means that no signal is being received. The color of the bars indicate the type of RTCM client.
        </div>
        <div class="voter-legend">
            <div class="legend-item legend-voting">A blue bar indicates a voting station.</div>
            <div class="legend-item legend-voted">Green indicates the station is voted.</div>
            <div class="legend-item legend-mix">Cyan is a non-voting mix station.</div>
        </div>
    </div>
    <?php
}
