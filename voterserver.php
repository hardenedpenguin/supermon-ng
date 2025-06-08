<?php

@set_time_limit(0);

include("session.inc");
session_write_close();

if (!isset($_GET['node']) || !is_numeric($_GET['node'])) {
    exit();
}
$node = (int)$_GET['node'];

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

while (true) {
    $voter_data = get_voter_data($node);
    $html_output = format_data_as_html($voter_data, $node);

    echo "id: " . time() . "\n";
    echo "data: " . $html_output . "\n\n";

    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    if (connection_aborted()) {
        break;
    }

    sleep(2);
}

function get_voter_data($node_id) {
    $signal_strength = rand(0, 255);
    $station_types = ['voting', 'voted', 'non-voting'];
    $type = $station_types[array_rand($station_types)];

    return [
        'signal' => $signal_strength,
        'type' => $type,
        'last_updated' => date('H:i:s')
    ];
}

function format_data_as_html($data, $node_id) {
    $signal = htmlspecialchars($data['signal'], ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars($data['type'], ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars($data['last_updated'], ENT_QUOTES, 'UTF-8');
    $node_id_safe = htmlspecialchars($node_id, ENT_QUOTES, 'UTF-8');
    
    $color = '#0099FF';
    if ($type === 'voted') {
        $color = 'greenyellow';
    } else if ($type === 'non-voting') {
        $color = 'cyan';
    }

    $html = "
        <div style='padding: 5px; border: 1px solid #ccc; margin-bottom: 10px;'>
            <strong>Node: {$node_id_safe}</strong> (Last updated: {$time})<br/>
            <div style='background-color: #ddd; border-radius: 5px; padding: 2px;'>
                <div style='width: {$signal}px; max-width: 255px; background-color: {$color}; color: black; text-align: right; padding-right: 5px; border-radius: 3px; min-height: 20px; line-height: 20px;'>
                    {$signal}
                </div>
            </div>
            <em>Type: {$type}</em>
        </div>
    ";
    
    return str_replace("\n", "", $html);
}

?>