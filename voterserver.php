<?php
/**
 * Voter Server SSE Endpoint
 * 
 * Provides real-time voter client status information via Server-Sent Events.
 * Streams RSSI data, voting status, and client information for voter nodes.
 * Modularized for better maintainability and organization.
 */

// Include core dependencies
include_once('nodeinfo.inc');
include_once("user_files/global.inc");
include_once("includes/common.inc");
include_once('includes/amifunctions.inc');

// Include modular voter server components
include("includes/voterserver/voter-config.inc");
include("includes/voterserver/voter-sse.inc");
include("includes/voterserver/voter-parser.inc");
include("includes/voterserver/voter-html.inc");
include("includes/voterserver/voter-status.inc");

// Initialize voter server and get configuration
list($node, $nodeConfig, $astdb, $fp) = initializeVoterServer();

// Run the main SSE streaming loop
runVoterStreamingLoop($node, $nodeConfig, $fp);

// Cleanup AMI connection
cleanupVoterAMI($fp);
exit;
?>