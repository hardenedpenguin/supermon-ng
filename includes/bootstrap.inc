<?php
/**
 * Supermon-ng Bootstrap File
 * 
 * Centralized include management to reduce redundancy and improve performance.
 * This file handles all common includes and initialization.
 * 
 * @author Supermon-ng Team
 * @version 3.0.1
 */

// Prevent direct access
if (!defined('SUPERMON_BOOTSTRAP')) {
    define('SUPERMON_BOOTSTRAP', true);
}

// Core includes (always needed)
if (!defined('SUPERMON_CORE_LOADED')) {
    include_once("includes/session.inc");
    include_once("includes/common.inc");
    include_once("includes/config.inc");
    include_once("includes/security.inc");
    include_once("includes/error-handler.inc");
    define('SUPERMON_CORE_LOADED', true);
}

// Optional includes (only when needed)
function load_ami_functions() {
    if (!defined('SUPERMON_AMI_LOADED')) {
        include_once("includes/amifunctions.inc");
        define('SUPERMON_AMI_LOADED', true);
    }
}

function load_cache_system() {
    if (!defined('SUPERMON_CACHE_LOADED')) {
        include_once("includes/cache.inc");
        define('SUPERMON_CACHE_LOADED', true);
    }
}

function load_auth_system() {
    if (!defined('SUPERMON_AUTH_LOADED')) {
        include_once("authusers.php");
        require_once("authini.php");
        define('SUPERMON_AUTH_LOADED', true);
    }
}

function load_user_config() {
    if (!defined('SUPERMON_USER_CONFIG_LOADED')) {
        if (file_exists("user_files/global.inc")) {
            include_once("user_files/global.inc");
        }
        define('SUPERMON_USER_CONFIG_LOADED', true);
    }
}

// Initialize core systems
if (class_exists('ErrorHandler') && method_exists('ErrorHandler', 'init')) {
    ErrorHandler::init();
}

if (class_exists('Config') && method_exists('Config', 'init')) {
    Config::init();
}
?>
