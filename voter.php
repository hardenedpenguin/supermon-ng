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
    // No changes needed here
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
                    
                    // --- THIS IS THE UPDATED SECTION ---
                    source.onmessage = function(event) {
                        try {
                            // 1. Parse the JSON string received from the server
                            const data = JSON.parse(event.data);

                            // 2. Use the 'html' property to update the main content
                            $("#link_list_" + node).html(data.html);

                            // 3. (Optional but recommended) Update the spinner element
                            if (data.spinner) {
                                $("#spinner_" + node).text(data.spinner);
                            }
                        } catch (e) {
                            // This will catch any errors if the server sends invalid JSON
                            console.error("Error parsing data for node " + node + ":", e);
                            $("#link_list_" + node).html("<div style='color:red;'>Received invalid data from server.</div>");
                        }
                    };
                    // --- END OF UPDATED SECTION ---

                    source.onerror = function(error) {
                        console.error("EventSource error for node " + node + ":", error);
                        // Update both the content and the spinner on error
                        $("#spinner_" + node).text('X');
                        $("#link_list_" + node).html("<div style='color:red; font-weight:bold;'>Error receiving updates for node " + node + ". The connection was lost.</div>");
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
<div class="voter-container" style="margin-bottom: 20px;">
    <!-- We add a spinner element that the JavaScript can update -->
    <h4>
        Status for Node <?php echo $safeNode; ?>
        <span id="spinner_<?php echo $safeNode; ?>" style="font-family: monospace; margin-left: 10px; color: #888;"></span>
    </h4>
    <!-- This is the original div where the table will be rendered -->
    <div id="link_list_<?php echo $safeNode; ?>">
        Connecting...
    </div>
</div>
<hr style="border: none; border-top: 1px solid #ccc; margin-bottom: 20px;" />
<?php endforeach; ?>


<div style="display: flex; align-items: flex-start; justify-content: flex-start; gap: 30px; max-width: 800px; margin: 20px 0;">
    <div style='flex: 1; text-align:left;'>
        The numbers indicate the relative signal strength. The value ranges from 0 to 255, a range of approximately 30db.
        A value of zero means that no signal is being received. The color of the bars indicate the type of RTCM client.
    </div>
    <div style='width: 240px; text-align:left;'>
        <div style='background-color: #0099FF; color: white; text-align: center;'>A blue bar indicates a voting station.</div>
        <div style='background-color: greenyellow; color: black; text-align: center;'>Green indicates the station is voted.</div>
        <div style='background-color: cyan; color: black; text-align: center;'>Cyan is a non-voting mix station. </div>
    </div>
</div>
<br>

<?php include "footer.inc"; ?>