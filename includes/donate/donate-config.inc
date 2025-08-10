<?php
/**
 * Supermon-ng Donate Configuration
 * 
 * Handles page configuration, title setting, and donation options setup.
 * Provides centralized configuration for donation methods and display options.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Get donation page title
 * 
 * @return string Page title
 */
function getDonateTitle(): string {
    return "Support Supermon-ng Development";
}

/**
 * Get donation options configuration
 * 
 * @return array Donation options array
 */
function getDonateOptions(): array {
    return [
        'paypal' => [
            'title' => 'PayPal',
            'description' => 'Donate securely via PayPal',
            'button_text' => 'Donate with PayPal',
            'button_class' => 'paypal-button',
            'action' => 'https://www.paypal.com/donate',
            'method' => 'post',
            'target' => '_blank',
            'hidden_fields' => [
                'business' => 'H2XYYRGQ9Q92E',
                'no_recurring' => '0',
                'item_name' => 'Help to Support the Continued Development',
                'currency_code' => 'USD'
            ]
        ],
        'cashapp' => [
            'title' => 'CashApp',
            'description' => 'Send money via CashApp to $anarchpeng',
            'button_text' => 'Send via CashApp',
            'button_class' => 'cashapp-button',
            'action' => 'https://cash.app/$anarchpeng',
            'method' => 'get',
            'target' => '_blank'
        ],
        'zelle' => [
            'title' => 'Zelle',
            'description' => 'Send money via Zelle using your bank\'s app',
            'button_text' => 'Get Zelle Info',
            'button_class' => 'zelle-button',
            'action' => 'javascript:showZelleInfo()',
            'method' => 'get',
            'target' => '_self'
        ]
    ];
}

/**
 * Get Zelle information
 * 
 * @return array Zelle information array
 */
function getZelleInfo(): array {
    return [
        'email' => 'geekypenguin@gmail.com',
        'name' => 'Jory Pratt'
    ];
}

/**
 * Get page meta information
 * 
 * @return array Meta information array
 */
function getDonateMetaInfo(): array {
    return [
        'description' => 'SuperMon-ng Donation Options',
        'keywords' => 'allstar monitor, app_rpt, asterisk',
        'author' => 'Jory A. Pratt, W5GLE',
        'mods' => 'New features, IRLP capability, Paul Aidukas, KN2R'
    ];
}
