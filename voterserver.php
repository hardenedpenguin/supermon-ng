<?php
/**
 * Supermon-ng Voter Server SSE Endpoint
 * 
 * Provides real-time voter client status information via Server-Sent Events (SSE).
 * Streams RSSI data, voting status, and client information for voter nodes
 * in real-time for monitoring and control purposes.
 * 
 * Features:
 * - Real-time RSSI (Received Signal Strength Indicator) streaming
 * - Voter client status monitoring
 * - Live voting decision information
 * - Client connection status tracking
 * - AMI-based data collection
 * - Server-Sent Events (SSE) protocol
 * - Modular architecture for maintainability
 * 
 * Protocol: Server-Sent Events (SSE)
 * Content-Type: text/event-stream
 * 
 * Data Streamed:
 * - RSSI values for connected clients
 * - Voting status and decisions
 * - Client connection information
 * - Real-time status updates
 * 
 * Security:
 * - AMI connection authentication
 * - Input validation and sanitization
 * - Secure data streaming
 * - Connection cleanup and resource management
 * 
 * Dependencies:
 * - nodeinfo.inc: Node information functions
 * - global.inc: Global configuration
 * - common.inc: Common functions
 * - amifunctions.inc: AMI connection management
 * - voterserver/*.inc: Modular voter server components
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
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