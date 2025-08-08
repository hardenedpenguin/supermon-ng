<?php
/**
 * Node Display Component
 * 
 * Handles the display and formatting of AllStar node information
 * in a reusable, consistent manner.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 */

/**
 * Node Display Component Class
 * 
 * Provides methods for rendering node information, status indicators,
 * and connection data in various formats.
 */
class NodeDisplay 
{
    private $nodeData;
    private $config;
    private $astdb;
    
    /**
     * Constructor
     * 
     * @param array $nodeData Node data array
     * @param array $config Node configuration
     * @param array $astdb AllStar database
     */
    public function __construct($nodeData, $config = [], $astdb = []) 
    {
        $this->nodeData = $nodeData;
        $this->config = $config;
        $this->astdb = $astdb;
    }
    
    /**
     * Render complete node display
     * 
     * @param array $options Display options
     * @return string HTML output
     */
    public function render($options = []) 
    {
        $defaults = [
            'show_details' => true,
            'show_links' => true,
            'show_status' => true,
            'css_class' => 'node-display'
        ];
        
        $options = array_merge($defaults, $options);
        
        $html = '<div class="' . htmlspecialchars($options['css_class']) . '">';
        
        if ($options['show_status']) {
            $html .= $this->renderStatus();
        }
        
        $html .= $this->renderBasicInfo();
        
        if ($options['show_details']) {
            $html .= $this->renderDetails();
        }
        
        if ($options['show_links']) {
            $html .= $this->renderLinks();
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render node status indicator
     * 
     * @return string HTML status indicator
     */
    public function renderStatus() 
    {
        $status = $this->getStatus();
        $statusClass = $this->getStatusClass($status);
        
        return sprintf(
            '<div class="node-status %s">%s</div>',
            htmlspecialchars($statusClass),
            htmlspecialchars($status)
        );
    }
    
    /**
     * Render basic node information
     * 
     * @return string HTML basic info
     */
    public function renderBasicInfo() 
    {
        $nodeId = $this->nodeData['node'] ?? 'Unknown';
        $info = $this->getNodeInfo();
        
        $html = '<div class="node-basic-info">';
        $html .= sprintf('<div class="node-id">%s</div>', htmlspecialchars($nodeId));
        $html .= sprintf('<div class="node-info">%s</div>', htmlspecialchars($info));
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render detailed node information
     * 
     * @return string HTML detailed info
     */
    public function renderDetails() 
    {
        $details = [
            'Last Keyed' => $this->nodeData['last_keyed'] ?? 'Never',
            'Link Type' => $this->nodeData['link'] ?? 'Unknown',
            'Direction' => $this->nodeData['direction'] ?? 'Unknown',
            'Connected' => $this->nodeData['elapsed'] ?? 'Unknown',
            'Mode' => $this->getFormattedMode()
        ];
        
        $html = '<div class="node-details">';
        foreach ($details as $label => $value) {
            $html .= sprintf(
                '<div class="detail-item"><span class="label">%s:</span> <span class="value">%s</span></div>',
                htmlspecialchars($label),
                htmlspecialchars($value)
            );
        }
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render node links (archive, custom URLs, etc.)
     * 
     * @return string HTML links
     */
    public function renderLinks() 
    {
        $links = [];
        $nodeId = $this->nodeData['node'] ?? '';
        
        // Archive link
        if (isset($this->config['archive'])) {
            $links[] = sprintf(
                '<a href="%s" target="_blank" class="node-link archive-link">Archive</a>',
                htmlspecialchars($this->config['archive'])
            );
        }
        
        // Custom URL
        $customUrlVar = 'URL_' . $nodeId;
        if (isset($GLOBALS[$customUrlVar])) {
            $customUrl = $GLOBALS[$customUrlVar];
            $target = '';
            
            if (substr($customUrl, -1) == '>') {
                $customUrl = substr($customUrl, 0, -1);
                $target = 'target="_blank"';
            }
            
            $links[] = sprintf(
                '<a href="%s" %s class="node-link custom-link">Info</a>',
                htmlspecialchars($customUrl),
                $target
            );
        }
        
        if (empty($links)) {
            return '';
        }
        
        return '<div class="node-links">' . implode(' | ', $links) . '</div>';
    }
    
    /**
     * Get node status
     * 
     * @return string Status description
     */
    public function getStatus() 
    {
        if (!isset($this->nodeData['mode'])) {
            return 'Unknown';
        }
        
        $mode = $this->nodeData['mode'];
        $keyed = $this->nodeData['keyed'] ?? 'no';
        
        if ($keyed === 'yes') {
            return $mode === 'R' ? 'Receiving' : 'Transmitting';
        }
        
        switch ($mode) {
            case 'C':
                return 'Connecting';
            case 'T':
                return 'Connected';
            case 'R':
                return 'Monitor Only';
            default:
                return 'Unknown';
        }
    }
    
    /**
     * Get CSS class for status
     * 
     * @param string $status Status string
     * @return string CSS class name
     */
    public function getStatusClass($status) 
    {
        $classMap = [
            'Transmitting' => 'status-transmitting',
            'Receiving' => 'status-receiving', 
            'Connected' => 'status-connected',
            'Connecting' => 'status-connecting',
            'Monitor Only' => 'status-monitor',
            'Unknown' => 'status-unknown'
        ];
        
        return $classMap[$status] ?? 'status-unknown';
    }
    
    /**
     * Get formatted mode description
     * 
     * @return string Formatted mode
     */
    public function getFormattedMode() 
    {
        $mode = $this->nodeData['mode'] ?? '';
        
        $modeMap = [
            'T' => 'Transceive',
            'R' => 'RX Only',
            'C' => 'Connecting',
            'Echolink' => 'EchoLink',
            'Local RX' => 'Local RX'
        ];
        
        return $modeMap[$mode] ?? 'Unknown';
    }
    
    /**
     * Get node information from database
     * 
     * @return string Node information
     */
    public function getNodeInfo() 
    {
        $nodeId = $this->nodeData['node'] ?? '';
        
        if (isset($this->astdb[$nodeId])) {
            $dbEntry = $this->astdb[$nodeId];
            if (isset($dbEntry[1], $dbEntry[2], $dbEntry[3])) {
                return $dbEntry[1] . ' ' . $dbEntry[2] . ' ' . $dbEntry[3];
            }
        }
        
        return $this->nodeData['info'] ?? 'Node not in database';
    }
    
    /**
     * Get connection time in human-readable format
     * 
     * @return string Formatted connection time
     */
    public function getConnectionTime() 
    {
        $elapsed = $this->nodeData['elapsed'] ?? '';
        
        if (empty($elapsed) || $elapsed === 'Unknown') {
            return 'Unknown';
        }
        
        // If already formatted (HH:MM:SS), return as-is
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $elapsed)) {
            return $elapsed;
        }
        
        // If seconds, convert to HH:MM:SS
        if (is_numeric($elapsed)) {
            $hours = floor($elapsed / 3600);
            $minutes = floor(($elapsed % 3600) / 60);
            $seconds = $elapsed % 60;
            
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        
        return $elapsed;
    }
    
    /**
     * Check if node is currently active (transmitting or receiving)
     * 
     * @return bool True if active, false otherwise
     */
    public function isActive() 
    {
        return isset($this->nodeData['keyed']) && $this->nodeData['keyed'] === 'yes';
    }
    
    /**
     * Get node IP address
     * 
     * @return string IP address or empty string
     */
    public function getIPAddress() 
    {
        return $this->nodeData['ip'] ?? '';
    }
    
    /**
     * Render as table row
     * 
     * @param bool $detailed Whether to show detailed columns
     * @return string HTML table row
     */
    public function renderAsTableRow($detailed = true) 
    {
        $nodeId = $this->nodeData['node'] ?? '';
        $info = $this->getNodeInfo();
        $status = $this->getStatus();
        $statusClass = $this->getStatusClass($status);
        
        $html = '<tr class="' . htmlspecialchars($statusClass) . '">';
        $html .= '<td class="node-id">' . htmlspecialchars($nodeId) . '</td>';
        $html .= '<td class="node-info">' . htmlspecialchars($info) . '</td>';
        
        if ($detailed) {
            $html .= '<td class="last-keyed">' . htmlspecialchars($this->nodeData['last_keyed'] ?? 'Never') . '</td>';
        }
        
        $html .= '<td class="link-type">' . htmlspecialchars($this->nodeData['link'] ?? 'Unknown') . '</td>';
        $html .= '<td class="direction">' . htmlspecialchars($this->nodeData['direction'] ?? 'Unknown') . '</td>';
        
        if ($detailed) {
            $html .= '<td class="connected-time">' . htmlspecialchars($this->getConnectionTime()) . '</td>';
        }
        
        $html .= '<td class="mode">' . htmlspecialchars($this->getFormattedMode()) . '</td>';
        $html .= '</tr>';
        
        return $html;
    }
    
    /**
     * Convert to JSON for JavaScript use
     * 
     * @return string JSON representation
     */
    public function toJson() 
    {
        $data = array_merge($this->nodeData, [
            'formatted_mode' => $this->getFormattedMode(),
            'status' => $this->getStatus(),
            'status_class' => $this->getStatusClass($this->getStatus()),
            'connection_time' => $this->getConnectionTime(),
            'is_active' => $this->isActive(),
            'node_info' => $this->getNodeInfo()
        ]);
        
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
