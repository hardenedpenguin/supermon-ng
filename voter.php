<?php
include("session.inc");
include "header.inc";

$nodeInput = isset($_GET['node']) ? trim(strip_tags($_GET['node'])) : '';

if (empty($nodeInput)) {
    die ("Please provide voter node number(s). (e.g., voter.php?node=1234 or voter.php?node=1234,5678)");
}

$passedNodes = array_filter(explode(',', $nodeInput), 'strlen');

if (empty($passedNodes)) {
    die ("No valid voter node numbers provided. Please ensure nodes are comma-separated if multiple and are not empty.");
}
$jsNodesArray = array_values($passedNodes);

session_write_close();

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

<br/>

<?php foreach ($passedNodes as $node): 
    $safeNode = htmlspecialchars($node, ENT_QUOTES, 'UTF-8');
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
<?php endforeach; ?>

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
<br>

<?php include "footer.inc"; ?>
