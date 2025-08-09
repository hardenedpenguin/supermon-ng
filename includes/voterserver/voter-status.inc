<?php
/**
 * Voter AMI Status Functions
 * 
 * Handles AMI communication for voter status requests
 */

/**
 * Sends the 'VoterStatus' action to the Asterisk Manager Interface (AMI) and retrieves the response.
 * This function constructs the command, sends it over the active socket, and waits for the full response
 * that corresponds to the unique ActionID provided.
 *
 * @param  resource     $fp         The active file pointer/socket connection to the Asterisk Manager.
 * @param  string       $actionID   A unique identifier for this AMI action to correlate the command and response.
 * @return string|false The full, multi-line text response from Asterisk on success, or false on failure.
 */
function get_voter_status($fp, $actionID) {
    $amiEOL = "\r\n";
    $action = "Action: VoterStatus" . $amiEOL;
    $action .= "ActionID: " . $actionID . $amiEOL . $amiEOL;

    if ($fp && fwrite($fp, $action) > 0) {
        return SimpleAmiClient::getResponse($fp, $actionID);
    } else {
        return false;
    }
}
