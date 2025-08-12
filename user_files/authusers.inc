<?php
/**
 * Supermon-ng User Authorization Configuration
 * 
 * This file controls which users have access to which buttons/features.
 * Each button has its own permission array that lists authorized usernames.
 * 
 * HOW TO USE:
 * 1. Replace "admin" with your actual username(s)
 * 2. Add multiple users by separating with commas: array("user1", "user2")
 * 3. Use sed to quickly add yourself to all buttons (see examples below)
 * 
 * SECURITY NOTES:
 * - Buttons marked with (*) are security-sensitive
 * - Only grant access to trusted users
 * - Usernames are case-sensitive
 * - Empty arrays = no access for anyone
 * 
 * SED COMMANDS FOR QUICK ADMIN SETUP:
 * - Add user "john" to all buttons: sed -i 's/"admin"/"john"/g' authusers.inc
 * - Add user "john" to existing admin: sed -i 's/array("admin")/array("admin", "john")/g' authusers.inc
 * - Add user "john" to all arrays: sed -i 's/array("admin")/array("admin", "john")/g' authusers.inc
 * 
 * EXAMPLES:
 * - Single user: $CONNECTUSER=array("john");
 * - Multiple users: $CONNECTUSER=array("admin", "john", "operator");
 * - No access: $CONNECTUSER=array();
 */

// ========================================
// BASIC ACCESS PERMISSIONS
// ========================================

// Perm checkbox
$PERMUSER=array("admin");

// Connect button
$CONNECTUSER=array("admin");

// Disconnect button
$DISCUSER=array("admin");

// Monitor button
$MONUSER=array("admin");

// Local Monitor button
$LMONUSER=array("admin");

// ========================================
// ADVANCED FEATURES
// ========================================

// DTMF button (*) - Security sensitive
$DTMFUSER=array("admin");

// Asterisk lookup button
$ASTLKUSER=array("admin");

// RPT stats button
$RSTATUSER=array("admin");

// Bubble button
$BUBLUSER=array("admin");

// Favorites button
$FAVUSER=array("admin");

// ========================================
// ADMINISTRATIVE FEATURES (*) - Security sensitive
// ========================================

// Control Panel button (*)
$CTRLUSER=array("admin");

// Configuration Editor button (*)
$CFGEDUSER=array("admin");

// Asterisk Reload Button (*)
$ASTRELUSER=array("admin");

// Asterisk start button (*)
$ASTSTRUSER=array("admin");

// Asterisk stop button (*)
$ASTSTPUSER=array("admin");

// Asterisk restart button (*)
$FSTRESUSER=array("admin");

// Asterisk reboot button (*)
$RBTUSER=array("admin");

// ========================================
// INFORMATION FEATURES
// ========================================

// Hamvoip update check button
$UPDUSER=array("admin");

// Hamvoip howto button
$HWTOUSER=array("admin");

// Allstar wiki button
$WIKIUSER=array("admin");

// CPU status button (*)
$CSTATUSER=array("admin");

// Allstar status button
$ASTATUSER=array("admin");

// Allstar node database button
$EXNUSER=array("admin");

// Node info button
$NINFUSER=array("admin");

// Active node button
$ACTNUSER=array("admin");

// All nodes button
$ALLNUSER=array("admin");

// Database button (*)
$DBTUSER=array("admin");

// ========================================
// HARDWARE & SYSTEM FEATURES
// ========================================

// GPIO button (*)
$GPIOUSER=array("admin");

// System Info button (*)
$SYSINFUSER=array("admin");

// ========================================
// LOG ACCESS (*) - Security sensitive
// ========================================

// Linux Log button
$LLOGUSER=array("admin");

// Asterisk log button
$ASTLUSER=array("admin");

// Connection log button
$CLOGUSER=array("admin");

// IRLP LOG button
$IRLPLOGUSER=array("admin");

// Web access log button (*)
$WLOGUSER=array("admin");

// Web error log button (*)
$WERRUSER=array("admin");

// ========================================
// SECURITY FEATURES (*) - Highly sensitive
// ========================================

// Restrict button (*)
$BANUSER=array("admin");

// Simple USB button
$SUSBUSER=array("admin");

?>
