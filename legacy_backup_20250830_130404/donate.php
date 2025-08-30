<?php
/**
 * Supermon-ng Donate
 * 
 * Provides a comprehensive donation interface for supporting Supermon-ng development.
 * Offers multiple payment methods including PayPal, CashApp, and Zelle with secure
 * integration and user-friendly interface design.
 * 
 * Features:
 * - Multiple donation methods (PayPal, CashApp, Zelle)
 * - Secure PayPal integration with proper form handling
 * - CashApp direct link integration
 * - Zelle information modal with contact details
 * - Responsive design for mobile and desktop viewing
 * - Professional styling with CSS variables
 * - Modal functionality for Zelle information display
 * - Popup window compatibility for embedded use
 * - Comprehensive meta information for SEO
 * - Cross-browser compatibility
 * 
 * Payment Methods:
 * - PayPal: Secure online payment processing
 * - CashApp: Direct money transfer to $anarchpeng
 * - Zelle: Bank-to-bank transfer with contact information
 * 
 * Security:
 * - HTML escaping for all user-facing content
 * - Secure form handling with proper validation
 * - External payment processor integration
 * - No sensitive data storage on server
 * 
 * Dependencies: session.inc, authusers.php, common.inc, global.inc
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("authusers.php");
include("includes/common.inc");
include("user_files/global.inc");
include("includes/donate/donate-controller.inc");

// Run the donate system
runDonate();
?> 