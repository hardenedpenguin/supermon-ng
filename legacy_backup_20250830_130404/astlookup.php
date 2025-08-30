<?php
/**
 * AllStar Lookup Page
 * 
 * Provides lookup functionality for AllStar, EchoLink, and IRLP nodes/callsigns.
 * Modularized for better maintainability and organization.
 */

// Include core dependencies
include("includes/session.inc");
include('includes/amifunctions.inc');
include("includes/common.inc");

// Include modular lookup components
include("includes/astlookup/lookup-config.inc");
include("includes/astlookup/lookup-ui.inc");
include("includes/astlookup/lookup-allstar.inc");
include("includes/astlookup/lookup-echolink.inc");
include("includes/astlookup/lookup-irlp.inc");

// Initialize lookup page and get configuration
list($lookupNode, $localnode, $perm, $config, $astdb, $fp) = initializeLookupPage();

// Render HTML head and form
renderLookupHead($localnode);
renderLookupForm($lookupNode, $localnode, $perm);

// Process lookup results if form was submitted
processLookupResults($fp, $localnode, $perm);

// Render footer and cleanup
renderLookupFooter();
cleanupLookupAMI($fp);
?>