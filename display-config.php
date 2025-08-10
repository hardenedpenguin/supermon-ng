<?php
/**
 * Supermon-ng Display Configuration
 * 
 * Provides a web-based interface for configuring display settings for Supermon-ng.
 * Allows users to customize how connection information is displayed including
 * detailed view options, connection count display, and maximum connections per node.
 * 
 * Features:
 * - Cookie-based settings persistence
 * - Form validation and sanitization
 * - Parent window refresh functionality
 * - Radio button controls for boolean settings
 * - Text input for numeric settings
 * - Default settings fallback
 * - Window management (close functionality)
 * 
 * Settings:
 * - Display Detailed View (YES/NO)
 * - Show connection count (YES/NO)
 * - Show ALL Connections (YES/NO)
 * - Maximum connections per node (0=ALL)
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/display-config/display-config-controller.inc");

// Run the display configuration system
runDisplayConfig();