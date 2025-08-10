<?php
/**
 * Supermon-ng Voter
 * 
 * Provides real-time voter monitoring functionality for RTCM (Real-Time Control Monitor) nodes.
 * Displays signal strength information and voting status for multiple nodes simultaneously
 * using server-sent events for live updates.
 * 
 * Features:
 * - Multi-node monitoring support with comma-separated node lists
 * - Real-time signal strength display (0-255 range, ~30dB)
 * - Server-sent events for live updates without polling
 * - Color-coded voting status indicators
 * - Fallback support for browsers without EventSource
 * - Input validation and sanitization
 * - Session optimization for performance
 * 
 * Usage:
 * - Single node: voter.php?node=1234
 * - Multiple nodes: voter.php?node=1234,5678,9012
 * 
 * Display Information:
 * - Blue bars: Voting stations
 * - Green bars: Voted stations  
 * - Cyan bars: Non-voting mix stations
 * - Signal strength: 0-255 range (approximately 30dB)
 * 
 * Dependencies: voterserver.php for real-time data, jQuery for AJAX functionality
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include "header.inc";
include("includes/voter/voter-controller.inc");

// Run the voter system
runVoter();

include "footer.inc";
?>
