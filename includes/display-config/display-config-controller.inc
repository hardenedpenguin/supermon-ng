<?php
/**
 * Supermon-ng Display Configuration Controller
 * 
 * Main controller for the display configuration functionality that orchestrates settings
 * management, validation, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/display-config/display-config-auth.inc";
include_once "includes/display-config/display-config-ui.inc";

/**
 * Initialize and run the display configuration system
 * 
 * This is the main entry point for the display configuration functionality.
 * Handles settings management, validation, and UI rendering.
 * 
 * @return void
 */
function runDisplayConfig(): void {
    // Get default settings
    $defaults = getDefaultDisplaySettings();
    
    // Load current settings from cookies
    $settings = loadDisplaySettings($defaults);
    
    // Validate current settings
    validateDisplaySettings($settings, $defaults);
    
    // Process form submission if any
    $result = processDisplayConfigSubmission($settings, $defaults);
    $settings = $result['settings'];
    $form_submitted_successfully = $result['submitted'];
    
    // Get individual setting values for form display
    $values = getDisplaySettingValues($settings);
    
    // Output the page head
    outputDisplayConfigHead();
    
    // Output the title
    outputDisplayConfigTitle();
    
    // Output refresh script if form was submitted
    outputDisplayConfigRefreshScript($form_submitted_successfully);
    
    // Output the configuration form
    outputDisplayConfigForm($values);
    
    // Output the page footer
    outputDisplayConfigFooter();
}
