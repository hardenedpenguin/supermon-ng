<?php
/**
 * Supermon-ng Real-Time Server (Server-Sent Events)
 * 
 * This script provides real-time node monitoring data via Server-Sent Events (SSE).
 * It connects to Asterisk Manager Interface (AMI) to fetch live node status,
 * connection information, and activity data for AllStar Link nodes.
 * 
 * Features:
 * - Real-time node status updates
 * - Connected node information
 * - Activity monitoring (RX/TX status)
 * - EchoLink and IRLP integration
 * - Automatic data refresh
 * - Connection status monitoring
 * 
 * Protocol: Server-Sent Events (SSE)
 * Content-Type: text/event-stream
 * 
 * Query Parameters:
 * - nodes: Comma-separated list of node IDs to monitor
 * 
 * Security: Uses session-based authentication
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("authini.php");

define('ECHOLINK_NODE_THRESHOLD', 3000000);

include('includes/amifunctions.inc');
include('includes/nodeinfo.inc');
include('includes/sse/server-functions.inc');
include('includes/sse/server-config.inc');
include('includes/sse/server-ami.inc');
include('includes/sse/server-monitor.inc');
include("user_files/global.inc");
include("includes/common.inc");

// Initialize global caches
$elnk_cache = [];
$irlp_cache = [];

// Initialize server and get configuration
list($nodes, $config, $astdb) = initializeServer();

// Establish AMI connections
list($fp, $servers) = establishAmiConnections($nodes, $config);

// Run main monitoring loop
runMonitoringLoop($fp, $servers, $nodes, $config);

// Cleanup AMI connections
cleanupAmiConnections($fp, $servers);
exit;

