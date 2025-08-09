<?php
/**
 * Node Ban/Allow AMI Functions
 * 
 * Handles AMI communication for database operations
 * in node access control functionality.
 */

/**
 * Send command to AMI
 * 
 * @param resource $fp AMI connection
 * @param string $cmd Command to send
 * @return string|false Command response or false on failure
 */
function sendCmdToAMI($fp, $cmd) {
    return SimpleAmiClient::command($fp, $cmd);
}

/**
 * Get data from AMI
 * 
 * @param resource $fp AMI connection  
 * @param string $cmd Command to send
 * @return string|false Command response or false on failure
 */
function getDataFromAMI($fp, $cmd) {
    return SimpleAmiClient::command($fp, $cmd);
}
