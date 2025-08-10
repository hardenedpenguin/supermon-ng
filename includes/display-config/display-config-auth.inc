<?php
/**
 * Supermon-ng Display Configuration Authentication
 * 
 * Handles settings management, validation, and cookie operations for display configuration.
 * Provides default settings, validation logic, and cookie management.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Get default display settings
 * 
 * @return array Array of default settings
 */
function getDefaultDisplaySettings(): array {
    return [
        'number-displayed' => "0",
        'show-number'      => "0",
        'show-all'         => "1",
        'show-detailed'    => "1"
    ];
}

/**
 * Load settings from cookies with defaults
 * 
 * @param array $defaults Default settings array
 * @return array Current settings array
 */
function loadDisplaySettings(array $defaults): array {
    $settings = $defaults;

    if (isset($_COOKIE['display-data']) && is_array($_COOKIE['display-data'])) {
        foreach ($defaults as $key => $defaultValue) {
            if (isset($_COOKIE['display-data'][$key])) {
                $settings[$key] = $_COOKIE['display-data'][$key];
            }
        }
    }

    return $settings;
}

/**
 * Validate display settings
 * 
 * @param array $currentSettings Current settings to validate
 * @param array $defaultValues Default values to fall back to
 * @return void Modifies $currentSettings by reference
 */
function validateDisplaySettings(array &$currentSettings, array $defaultValues): void {
    if (!is_numeric($currentSettings['number-displayed']) || (int)$currentSettings['number-displayed'] < 0) {
        $currentSettings['number-displayed'] = $defaultValues['number-displayed'];
    }
    
    foreach (['show-number', 'show-all', 'show-detailed'] as $key) {
        if (!in_array($currentSettings[$key], ["0", "1"])) {
            $currentSettings[$key] = $defaultValues[$key];
        }
    }
}

/**
 * Process form submission and update settings
 * 
 * @param array $settings Current settings array
 * @param array $defaults Default settings array
 * @return array Updated settings and submission status
 */
function processDisplayConfigSubmission(array $settings, array $defaults): array {
    $form_submitted_successfully = false;

    if (isset($_GET["number_displayed"]) && $_GET["number_displayed"] !== "") {
        $form_submitted_successfully = true;

        $settings['number-displayed'] = $_GET["number_displayed"];
        $settings['show-number']      = $_GET["show_number"]   ?? $defaults['show-number'];
        $settings['show-all']         = $_GET["show_all"]      ?? $defaults['show-all'];
        $settings['show-detailed']    = $_GET["show_detailed"] ?? $defaults['show-detailed'];

        validateDisplaySettings($settings, $defaults);
        
        // Save settings to cookies
        saveDisplaySettingsToCookies($settings);
    }

    return [
        'settings' => $settings,
        'submitted' => $form_submitted_successfully
    ];
}

/**
 * Save settings to cookies
 * 
 * @param array $settings Settings to save
 * @return void
 */
function saveDisplaySettingsToCookies(array $settings): void {
    $expiretime = 2147483645;
    $cookie_path = "/";

    foreach ($settings as $key => $value) {
        setcookie("display-data[{$key}]", $value, $expiretime, $cookie_path);
    }
}

/**
 * Get individual setting values for form display
 * 
 * @param array $settings Settings array
 * @return array Individual setting values
 */
function getDisplaySettingValues(array $settings): array {
    return [
        'ndisp'   => $settings['number-displayed'],
        'snum'    => $settings['show-number'],
        'sall'    => $settings['show-all'],
        'sdetail' => $settings['show-detailed']
    ];
}
