<?php
/**
 * Pi GPIO Control Interface
 * 
 * Provides web interface for controlling Raspberry Pi GPIO pins.
 * Supports pin mode configuration, digital I/O operations, and status monitoring.
 * Modularized for better maintainability and security.
 */

// Include modular GPIO control components
include("includes/pi-gpio/gpio-config.inc");
include("includes/pi-gpio/gpio-commands.inc");
include("includes/pi-gpio/gpio-processor.inc");
include("includes/pi-gpio/gpio-ui.inc");
include("includes/pi-gpio/gpio-status.inc");

// Initialize GPIO configuration and security
initializeGPIOConfig();

// Process form submission if present
processGPIOForm();

// Render HTML head
renderGPIOHead();

// Start page body
renderGPIOBodyStart();

// Render the GPIO control form
renderGPIOForm();

// Display current GPIO status
displayGPIOStatus();

// Render page footer
renderGPIOFooter();
?>