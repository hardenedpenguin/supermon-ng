<?php
/**
 * Supermon-ng User Authentication System
 * 
 * Handles user login, authentication, and session management for the
 * Supermon-ng web interface. Provides secure authentication using
 * password hashing and rate limiting to prevent brute force attacks.
 * 
 * Features:
 * - Secure password authentication with htpasswd compatibility
 * - Rate limiting (5 attempts per 15 minutes)
 * - Session management and regeneration
 * - AJAX and form-based login support
 * - User action logging
 * - Responsive login interface
 * 
 * Security Features:
 * - Password verification using password_verify()
 * - Session regeneration on successful login
 * - Rate limiting to prevent brute force attacks
 * - Comprehensive login attempt logging
 * - IP address tracking
 * 
 * Authentication Method: htpasswd file (.htpasswd)
 * Session Duration: 8 hours (configurable in session.inc)
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/session.inc";
include_once "includes/rate_limit.inc";
include_once "includes/security.inc";
include_once "includes/login/login-controller.inc";

// Run the login system
runLoginSystem();