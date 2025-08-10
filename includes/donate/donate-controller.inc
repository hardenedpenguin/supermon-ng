<?php
/**
 * Supermon-ng Donate Controller
 * 
 * Main controller for the donate functionality that orchestrates page generation,
 * donation options display, and UI rendering for the donation interface.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/donate/donate-config.inc";
include_once "includes/donate/donate-ui.inc";

/**
 * Initialize and run the donate system
 * 
 * This is the main entry point for the donate functionality.
 * Handles page generation, donation options display, and UI rendering.
 * 
 * @return void
 */
function runDonate(): void {
    // Get configuration data
    $title = getDonateTitle();
    $options = getDonateOptions();
    $zelleInfo = getZelleInfo();
    $metaInfo = getDonateMetaInfo();
    
    // Generate HTML head
    generateDonateHead($title, $metaInfo);
    
    // Generate styles
    generateDonateStyles();
    ?>
    </head>
    <body>
    <?php
    
    // Generate header
    generateDonateHeader();
    
    // Generate main content
    ?>
    <div style="padding: 20px; min-height: 400px; display: flex; flex-direction: column;">
        <h2 style="text-align: center; color: var(--text-color); margin-bottom: 15px;">Support This Project</h2>
        <p style="text-align: center; color: var(--text-color); margin-bottom: 20px; font-size: 0.9em;">If you find this system useful, please consider a donation to help with maintenance and development.</p>
        
        <?php
        // Generate donation options
        generateDonateOptions($options);
        ?>
        
        <!-- Spacer to push close button to bottom -->
        <div style="flex-grow: 1;"></div>
        
        <!-- Close button at bottom -->
        <div style="text-align: center; margin-top: 20px;">
            <input type="button" style="padding: 8px 16px; font-size: 0.9em; background-color: var(--table-header-bg); color: var(--text-color); border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer;" Value="Close" onclick="self.close()">
        </div>
    </div>
    <?php
    
    // Generate Zelle modal
    generateZelleModal($zelleInfo);
    
    // Generate JavaScript
    generateDonateJavaScript();
    ?>
    </body>
    </html>
    <?php
}
