<?php
/**
 * System Information Display
 * 
 * Displays comprehensive system information including network configuration,
 * system versions, user configurations, uptime, core dumps, and CPU temperature.
 * Modularized for better maintainability and security.
 */

// Include modular system info components
include("includes/system-info/sysinfo-config.inc");
include("includes/system-info/sysinfo-commands.inc");
include("includes/system-info/sysinfo-ui.inc");
include("includes/system-info/sysinfo-collectors.inc");
include("includes/system-info/sysinfo-status.inc");

// Initialize system info configuration and security
list($Show_Detail) = initializeSystemInfo();

// Initialize safe command paths
$commands = initializeSystemCommands();

// Collect system information
$basic_info = collectBasicSystemInfo($commands);
$network_info = collectNetworkInfo($commands);
$ssh_version_info = collectSSHAndVersionInfo($commands);

// Render HTML head
renderSystemInfoHead();

// Start page body
renderSystemInfoBodyStart($Show_Detail);

// Display configuration status
displayConfigurationStatus();

// Display uptime and load information
displayUptimeAndLoad($commands, $basic_info['myday']);

// Display core dump information
displayCoreDumpInfo();

// Display CPU temperature
displayCPUTemperature();

// Render page footer
renderSystemInfoFooter();
?>