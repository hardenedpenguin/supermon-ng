<?php
/**
 * Supermon-ng User Authorization Configuration
 * 
 * This file controls which users have access to which buttons/features.
 * Each button has its own permission array that lists authorized usernames.
 * 
 * HOW TO USE:
 * 1. Replace "anarchy" with your actual username(s)
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
 * - Add user "john" to all buttons: sed -i 's/"anarchy"/"john"/g' authusers.inc
 * - Add user "john" to existing anarchy: sed -i 's/array("anarchy")/array("anarchy", "john")/g' authusers.inc
 * - Add user "john" to all arrays: sed -i 's/array("anarchy")/array("anarchy", "john")/g' authusers.inc
 * 
 * EXAMPLES:
 * - Single user: $CONNECTUSER=array("john");
 * - Multiple users: $CONNECTUSER=array("anarchy", "john", "operator");
 * - No access: $CONNECTUSER=array();
 */

// ========================================
// BASIC ACCESS PERMISSIONS
// ========================================

// Perm checkbox
$PERMUSER=array("anarchy");

// Connect button
$CONNECTUSER=array("anarchy", "testuser");

// Disconnect button
$DISCUSER=array("anarchy", "testuser");

// Monitor button
$MONUSER=array("anarchy", "testuser");

// Local Monitor button
$LMONUSER=array("anarchy", "testuser");

// ========================================
// ADVANCED FEATURES
// ========================================

// DTMF button (*) - Security sensitive
$DTMFUSER=array("anarchy");

// Asterisk lookup button
$ASTLKUSER=array("anarchy");

// RPT stats button
$RSTATUSER=array("anarchy");

// Bubble button
$BUBLUSER=array("anarchy");

// Favorites button
$FAVUSER=array("anarchy");

// ========================================
// ADMINISTRATIVE FEATURES (*) - Security sensitive
// ========================================

// Control Panel button (*)
$CTRLUSER=array("anarchy");

// Configuration Editor button (*)
$CFGEDUSER=array("anarchy");

// Asterisk Reload Button (*)
$ASTRELUSER=array("anarchy");

// Asterisk start button (*)
$ASTSTRUSER=array("anarchy");

// Asterisk stop button (*)
$ASTSTPUSER=array("anarchy");

// Asterisk restart button (*)
$FSTRESUSER=array("anarchy");

// Asterisk reboot button (*)
$RBTUSER=array("anarchy");

// ========================================
// INFORMATION FEATURES
// ========================================

// Hamvoip update check button
$UPDUSER=array("anarchy");

// Hamvoip howto button
$HWTOUSER=array("anarchy");

// Allstar wiki button
$WIKIUSER=array("anarchy");

// CPU status button (*)
$CSTATUSER=array("anarchy");

// Allstar status button
$ASTATUSER=array("anarchy", "testuser");

// Allstar node database button
$EXNUSER=array("anarchy", "testuser");

// Node info button
$NINFUSER=array("anarchy", "testuser");

// Active node button
$ACTNUSER=array("anarchy", "testuser");

// All nodes button
$ALLNUSER=array("anarchy", "testuser");

// Database button (*)
$DBTUSER=array("anarchy");

// ========================================
// HARDWARE & SYSTEM FEATURES
// ========================================

// GPIO button (*)
$GPIOUSER=array("anarchy");

// System Info button (*)
$SYSINFUSER=array("anarchy");

// ========================================
// LOG ACCESS (*) - Security sensitive
// ========================================

// Linux Log button
$LLOGUSER=array("anarchy");

// Asterisk log button
$ASTLUSER=array("anarchy");

// Connection log button
$CLOGUSER=array("anarchy");

// IRLP LOG button
$IRLPLOGUSER=array("anarchy");

// Web access log button (*)
$WLOGUSER=array("anarchy");

// Web error log button (*)
$WERRUSER=array("anarchy");

// ========================================
// SECURITY FEATURES (*) - Highly sensitive
// ========================================

// Restrict button (*)
$BANUSER=array("anarchy");

// Simple USB button
$SUSBUSER=array("anarchy");

?>
