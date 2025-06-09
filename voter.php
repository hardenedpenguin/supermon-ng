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
                            console.error("Error parsing data for node " + node + ":", e);
                            $("#link_list_" + node).html("<div style='color:red;'>Received invalid data from server.</div>");
                        }
                    };

                    source.onerror = function(error) {
                        console.error("EventSource error for node " + node + ":", error);
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

<style>
    /* ---- Updated Styles ---- */
    .rtcm th {
        background-color: white !important;
        color: black !important;
        font-size: 16px; /* Slightly larger header font */
    }
    
    /* Target all data cells for consistent vertical alignment and a minimum height */
    .rtcm td {
        vertical-align: middle;
        height: 28px;
    }
    
    /* Style the client name cell */
    .rtcm td:first-child {
        color: white;
        font-size: 18px; /* Increase font size */
        padding-left: 5px;
    }

    /* Style the bar itself */
    .rtcm .bar {
        font-size: 18px; /* Make font size match the client name */
        line-height: 25px; /* Vertically center the text within the bar */
        height: 25px; /* Ensure the bar's background has a consistent height */
        text-align: right; /* Push number to the right */
        padding-right: 5px; /* Add some space from the edge */
        box-sizing: border-box; /* Ensures padding is included in the width calculation */
    }
</style>

<br/>

<?php foreach ($passedNodes as $node): 
    $safeNode = htmlspecialchars($node, ENT_QUOTES, 'UTF-8');
?>
<div class="voter-container" style="margin-bottom: 20px;">
    <div id="link_list_<?php echo $safeNode; ?>">
        Connecting to Node <?php echo $safeNode; ?>...
    </div>
    <div style="text-align: center; height: 20px; color: #888;">
        <span id="spinner_<?php echo $safeNode; ?>" style="font-family: monospace;"></span>
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
