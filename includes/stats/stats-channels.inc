<?php
/**
 * AllStar Channel and Network Statistics Functions
 * 
 * Functions for displaying IAX2 channel information and network statistics
 */

/**
 * Display IAX2 channel information
 */
function show_channels($fp)
{
    global $HEAD;
    $HEAD_CMD = isset($HEAD) ? $HEAD : '/usr/bin/head';
    $ECHO_CMD = '/bin/echo';

    $AMI1 = SimpleAmiClient::command($fp, "iax2 show channels");

    echo "<span class='ast-status-section-title'>**************************** CHANNELS *****************************</span>\n";

    if ($AMI1 === false) { echo "<span class='ast-status-error-msg'>Error retrieving IAX2 channel info.</span>\n\n"; return; }
    if (trim($AMI1) === '') { echo "<span class='ast-status-none-indicator'>No IAX2 channels reported.</span>\n\n"; return; }

    $cmd_channels = "$ECHO_CMD -n ". escapeshellarg($AMI1) ." | $HEAD_CMD --lines=-1";
    $channels = trim(`$cmd_channels 2>&1`);

    if (trim($channels) === '' && trim($AMI1) !== '') {
        $display_output = clean_ami_output_for_display($AMI1);
        echo htmlspecialchars($display_output) . "\n\n";
    } else {
        $display_output = clean_ami_output_for_display($channels);
        echo htmlspecialchars($display_output) . "\n\n";
    }
}

/**
 * Display IAX2 network statistics
 */
function show_netstats($fp)
{
    global $HEAD;
    $HEAD_CMD = isset($HEAD) ? $HEAD : '/usr/bin/head';
    $ECHO_CMD = '/bin/echo';

    $AMI1 = SimpleAmiClient::command($fp, "iax2 show netstats");

    echo "<span class='ast-status-section-title'>**************************** NETSTATS *****************************</span>\n";

     if ($AMI1 === false) { echo "<span class='ast-status-error-msg'>Error retrieving IAX2 netstats info.</span>\n\n"; return; }
     if (trim($AMI1) === '') { echo "<span class='ast-status-none-indicator'>No IAX2 netstats reported.</span>\n\n"; return; }

    $cmd_netstats = "$ECHO_CMD -n ". escapeshellarg($AMI1) ." | $HEAD_CMD --lines=-1";
    $netstats = trim(`$cmd_netstats 2>&1`);

    if (trim($netstats) === '' && trim($AMI1) !== '') {
        $display_output = clean_ami_output_for_display($AMI1);
        echo htmlspecialchars($display_output) . "\n\n";
    } else {
        $display_output = clean_ami_output_for_display($netstats);
        echo htmlspecialchars($display_output) . "\n\n";
    }
}
