<?php
/**
 * Voter Server SSE Streaming
 * 
 * Handles the main Server-Sent Events streaming loop for voter data
 */

/**
 * Run the main SSE streaming loop for voter status
 * 
 * @param string $node The node number to monitor
 * @param array $nodeConfig The node configuration
 * @param resource $fp The AMI connection
 */
function runVoterStreamingLoop($node, $nodeConfig, $fp) {
    $spinChars = ['*', '|', '/', '-', '\\'];
    $spinIndex = 0;
    $actionIDBase = "voter" . preg_replace('/[^a-zA-Z0-9]/', '', $node);

    while (true) {
        if (connection_aborted()) {
            break;
        }

        $actionID = $actionIDBase . mt_rand(1000, 9999);
        $response = get_voter_status($fp, $actionID);

        if ($response === false) {
            $error_data = json_encode(['html' => 'Error: Disconnected from Asterisk server.', 'spinner' => 'X']);
            echo "id: " . time() . "\n";
            echo "data: " . $error_data . "\n\n";
            ob_flush(); flush();
            break;
        }

        list($parsed_nodes_data, $parsed_voted_data) = parse_voter_response($response);
        $html_message = format_node_html($node, $parsed_nodes_data, $parsed_voted_data, $nodeConfig);
        $spinner = $spinChars[$spinIndex];
        $spinIndex = ($spinIndex + 1) % count($spinChars);

        $payload = json_encode([
            'html' => $html_message,
            'spinner' => $spinner
        ]);

        echo "id: " . time() . "\n";
        echo "data: " . $payload . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();

        sleep(1);
    }
}
