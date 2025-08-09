<?php
/**
 * Voter Response Parser
 * 
 * Handles parsing of raw AMI VoterStatus responses into structured data
 */

/**
 * Parses the raw multi-line text response from Asterisk's 'VoterStatus' AMI command.
 * This function is stateful, iterating through the lines to build a structured array of clients
 * and their associated data. It also cleans client names by removing common suffixes
 * and flags "Mix" stations for later styling.
 *
 * @param string $response The raw, multi-line string from the AMI 'VoterStatus' command.
 * @return array A two-element array:
 *               [0] => An associative array of nodes, each containing an array of its clients and their data.
 *               [1] => An associative array mapping each node number to its currently voted client name.
 */
function parse_voter_response($response) {
    $lines = explode("\n", $response);
    $parsed_nodes_data = [];
    $parsed_voted_data = [];
    $currentNodeContext = null;
    $currentClientData = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $parts = explode(": ", $line, 2);
        if (count($parts) < 2) continue;
        
        list($key, $value) = $parts;

        switch ($key) {
            case 'Node':
                if ($currentNodeContext && !empty($currentClientData) && isset($currentClientData['name'])) {
                    $parsed_nodes_data[$currentNodeContext][$currentClientData['name']] = $currentClientData;
                }
                $currentNodeContext = $value;
                $currentClientData = [];
                if (!isset($parsed_nodes_data[$currentNodeContext])) {
                    $parsed_nodes_data[$currentNodeContext] = [];
                }
                break;

            case 'Client':
                if ($currentNodeContext && !empty($currentClientData) && isset($currentClientData['name'])) {
                    $parsed_nodes_data[$currentNodeContext][$currentClientData['name']] = $currentClientData;
                }
                // Check for the "Mix" suffix BEFORE cleaning it
                $isMix = (strpos($value, ' Mix') !== false);
                
                // Clean all known suffixes from the client name
                $cleanName = preg_replace('/(\sMaster\sActiveMaster|\sLocal\sLocal|\sMix)$/', '', $value);
                
                // Store the clean name and the isMix flag
                $currentClientData = ['name' => $cleanName, 'isMix' => $isMix, 'rssi' => 'N/A', 'ip' => 'N/A'];
                break;
                
            case 'RSSI':
                if (isset($currentClientData['name'])) {
                    $currentClientData['rssi'] = $value;
                }
                break;
                
            case 'IP':
                if (isset($currentClientData['name'])) {
                    $currentClientData['ip'] = $value;
                }
                break;

            case 'Voted':
                if ($currentNodeContext) {
                    $parsed_voted_data[$currentNodeContext] = $value;
                }
                break;
        }
    }

    if ($currentNodeContext && !empty($currentClientData) && isset($currentClientData['name'])) {
        $parsed_nodes_data[$currentNodeContext][$currentClientData['name']] = $currentClientData;
    }

    return [$parsed_nodes_data, $parsed_voted_data];
}
