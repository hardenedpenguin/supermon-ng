<?php
/**
 * Enhanced Table Renderer Component
 * 
 * Provides advanced table rendering capabilities with sorting, filtering,
 * pagination, and responsive design features.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 */

/**
 * Table Renderer Component Class
 * 
 * Enhanced version of the basic table.inc include with additional
 * features for complex data display and user interaction.
 */
class TableRenderer 
{
    private $headers;
    private $rows;
    private $options;
    private $tableId;
    
    /**
     * Constructor
     * 
     * @param array $headers Table headers
     * @param array $rows Table data rows
     * @param array $options Rendering options
     */
    public function __construct($headers, $rows, $options = []) 
    {
        $this->headers = $headers;
        $this->rows = $rows;
        $this->tableId = $options['id'] ?? 'table_' . uniqid();
        
        $this->options = array_merge([
            'class' => 'gridtable',
            'sortable' => false,
            'filterable' => false,
            'paginated' => false,
            'page_size' => 25,
            'responsive' => true,
            'striped' => true,
            'hover' => true,
            'bordered' => true,
            'empty_message' => 'No data available',
            'loading_message' => 'Loading...',
            'show_row_numbers' => false,
            'exportable' => false
        ], $options);
    }
    
    /**
     * Render the complete table
     * 
     * @return string HTML table output
     */
    public function render() 
    {
        if (empty($this->rows)) {
            return $this->renderEmptyTable();
        }
        
        $html = '';
        
        // Add filter interface if enabled
        if ($this->options['filterable']) {
            $html .= $this->renderFilter();
        }
        
        // Add export buttons if enabled
        if ($this->options['exportable']) {
            $html .= $this->renderExportButtons();
        }
        
        // Main table
        $html .= $this->renderTable();
        
        // Add pagination if enabled
        if ($this->options['paginated']) {
            $html .= $this->renderPagination();
        }
        
        // Add JavaScript functionality
        $html .= $this->renderJavaScript();
        
        return $html;
    }
    
    /**
     * Render the main table structure
     * 
     * @return string HTML table
     */
    private function renderTable() 
    {
        $classes = [$this->options['class']];
        
        if ($this->options['responsive']) {
            $classes[] = 'table-responsive';
        }
        if ($this->options['striped']) {
            $classes[] = 'table-striped';
        }
        if ($this->options['hover']) {
            $classes[] = 'table-hover';
        }
        if ($this->options['bordered']) {
            $classes[] = 'table-bordered';
        }
        
        $html = sprintf(
            '<div class="table-container"><table id="%s" class="%s">',
            htmlspecialchars($this->tableId),
            htmlspecialchars(implode(' ', $classes))
        );
        
        $html .= $this->renderTableHeader();
        $html .= $this->renderTableBody();
        $html .= '</table></div>';
        
        return $html;
    }
    
    /**
     * Render table header
     * 
     * @return string HTML table header
     */
    private function renderTableHeader() 
    {
        $html = '<thead><tr>';
        
        if ($this->options['show_row_numbers']) {
            $html .= '<th class="row-number-header">#</th>';
        }
        
        foreach ($this->headers as $index => $header) {
            $headerText = is_array($header) ? $header['text'] : $header;
            $sortable = $this->options['sortable'] && (!is_array($header) || $header['sortable'] !== false);
            
            $classes = ['header-cell'];
            if ($sortable) {
                $classes[] = 'sortable';
            }
            
            $attributes = '';
            if (is_array($header) && isset($header['width'])) {
                $attributes .= ' style="width: ' . htmlspecialchars($header['width']) . '"';
            }
            
            $html .= sprintf(
                '<th class="%s" data-column="%d"%s>',
                htmlspecialchars(implode(' ', $classes)),
                $index,
                $attributes
            );
            
            $html .= htmlspecialchars($headerText);
            
            if ($sortable) {
                $html .= ' <span class="sort-indicator"></span>';
            }
            
            $html .= '</th>';
        }
        
        $html .= '</tr></thead>';
        
        return $html;
    }
    
    /**
     * Render table body
     * 
     * @return string HTML table body
     */
    private function renderTableBody() 
    {
        $html = '<tbody>';
        
        $rowNumber = 1;
        foreach ($this->rows as $rowIndex => $row) {
            $rowClasses = ['data-row'];
            
            // Add custom row classes if provided
            if (is_array($row) && isset($row['_class'])) {
                $rowClasses[] = $row['_class'];
                unset($row['_class']);
            }
            
            $html .= sprintf(
                '<tr class="%s" data-row="%d">',
                htmlspecialchars(implode(' ', $rowClasses)),
                $rowIndex
            );
            
            if ($this->options['show_row_numbers']) {
                $html .= '<td class="row-number">' . $rowNumber . '</td>';
            }
            
            foreach ($row as $cellIndex => $cell) {
                $cellClasses = ['data-cell'];
                $cellAttributes = '';
                
                // Handle cell with custom attributes
                if (is_array($cell) && isset($cell['_value'])) {
                    $cellValue = $cell['_value'];
                    if (isset($cell['_class'])) {
                        $cellClasses[] = $cell['_class'];
                    }
                    if (isset($cell['_attributes'])) {
                        $cellAttributes = ' ' . $cell['_attributes'];
                    }
                } else {
                    $cellValue = $cell;
                }
                
                $html .= sprintf(
                    '<td class="%s" data-column="%d"%s>%s</td>',
                    htmlspecialchars(implode(' ', $cellClasses)),
                    $cellIndex,
                    $cellAttributes,
                    is_string($cellValue) ? htmlspecialchars($cellValue) : $cellValue
                );
            }
            
            $html .= '</tr>';
            $rowNumber++;
        }
        
        $html .= '</tbody>';
        
        return $html;
    }
    
    /**
     * Render empty table message
     * 
     * @return string HTML empty table
     */
    private function renderEmptyTable() 
    {
        $colCount = count($this->headers) + ($this->options['show_row_numbers'] ? 1 : 0);
        
        $html = sprintf(
            '<div class="table-container"><table id="%s" class="%s">',
            htmlspecialchars($this->tableId),
            htmlspecialchars($this->options['class'])
        );
        
        $html .= $this->renderTableHeader();
        $html .= '<tbody>';
        $html .= sprintf(
            '<tr><td colspan="%d" class="empty-message">%s</td></tr>',
            $colCount,
            htmlspecialchars($this->options['empty_message'])
        );
        $html .= '</tbody></table></div>';
        
        return $html;
    }
    
    /**
     * Render filter interface
     * 
     * @return string HTML filter interface
     */
    private function renderFilter() 
    {
        $html = '<div class="table-filters">';
        $html .= '<div class="filter-row">';
        $html .= '<div class="filter-group">';
        $html .= '<label for="' . $this->tableId . '_search">Search:</label>';
        $html .= '<input type="text" id="' . $this->tableId . '_search" class="table-search" placeholder="Search table...">';
        $html .= '</div>';
        $html .= '<div class="filter-group">';
        $html .= '<button type="button" class="btn btn-secondary clear-filters">Clear</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render export buttons
     * 
     * @return string HTML export buttons
     */
    private function renderExportButtons() 
    {
        $html = '<div class="table-exports">';
        $html .= '<button type="button" class="btn btn-primary export-csv">Export CSV</button>';
        $html .= '<button type="button" class="btn btn-primary export-json">Export JSON</button>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render pagination controls
     * 
     * @return string HTML pagination
     */
    private function renderPagination() 
    {
        $totalRows = count($this->rows);
        $totalPages = ceil($totalRows / $this->options['page_size']);
        
        if ($totalPages <= 1) {
            return '';
        }
        
        $html = '<div class="table-pagination">';
        $html .= '<div class="pagination-info">';
        $html .= 'Showing <span class="current-range">1-' . min($this->options['page_size'], $totalRows) . '</span>';
        $html .= ' of <span class="total-rows">' . $totalRows . '</span> rows';
        $html .= '</div>';
        
        $html .= '<div class="pagination-controls">';
        $html .= '<button type="button" class="btn btn-sm btn-secondary" id="prev-page" disabled>Previous</button>';
        $html .= '<span class="page-numbers"></span>';
        $html .= '<button type="button" class="btn btn-sm btn-secondary" id="next-page">Next</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render JavaScript functionality
     * 
     * @return string HTML script tag
     */
    private function renderJavaScript() 
    {
        $config = json_encode([
            'tableId' => $this->tableId,
            'sortable' => $this->options['sortable'],
            'filterable' => $this->options['filterable'],
            'paginated' => $this->options['paginated'],
            'pageSize' => $this->options['page_size'],
            'exportable' => $this->options['exportable']
        ]);
        
        return "<script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof TableManager !== 'undefined') {
                new TableManager($config);
            }
        });
        </script>";
    }
    
    /**
     * Static method to render simple table (backward compatibility)
     * 
     * @param array $headers Table headers
     * @param array $rows Table rows
     * @param array $options Options
     * @return string HTML table
     */
    public static function renderSimple($headers, $rows, $options = []) 
    {
        $table = new self($headers, $rows, $options);
        return $table->render();
    }
    
    /**
     * Static method to render data table with all features
     * 
     * @param array $headers Table headers
     * @param array $rows Table rows
     * @param array $options Options
     * @return string HTML table with full features
     */
    public static function renderDataTable($headers, $rows, $options = []) 
    {
        $defaultOptions = [
            'sortable' => true,
            'filterable' => true,
            'paginated' => true,
            'exportable' => true,
            'show_row_numbers' => true
        ];
        
        $options = array_merge($defaultOptions, $options);
        $table = new self($headers, $rows, $options);
        return $table->render();
    }
    
    /**
     * Generate CSV export data
     * 
     * @return string CSV data
     */
    public function generateCSV() 
    {
        $output = '';
        
        // Headers
        $headerRow = [];
        if ($this->options['show_row_numbers']) {
            $headerRow[] = '#';
        }
        foreach ($this->headers as $header) {
            $headerRow[] = is_array($header) ? $header['text'] : $header;
        }
        $output .= implode(',', array_map(function($field) {
            return '"' . str_replace('"', '""', $field) . '"';
        }, $headerRow)) . "\n";
        
        // Data rows
        $rowNumber = 1;
        foreach ($this->rows as $row) {
            $dataRow = [];
            if ($this->options['show_row_numbers']) {
                $dataRow[] = $rowNumber;
            }
            foreach ($row as $cell) {
                $cellValue = is_array($cell) && isset($cell['_value']) ? $cell['_value'] : $cell;
                $cellValue = strip_tags($cellValue); // Remove HTML
                $dataRow[] = $cellValue;
            }
            $output .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $dataRow)) . "\n";
            $rowNumber++;
        }
        
        return $output;
    }
    
    /**
     * Generate JSON export data
     * 
     * @return string JSON data
     */
    public function generateJSON() 
    {
        $data = [];
        $headerKeys = [];
        
        // Create header keys
        if ($this->options['show_row_numbers']) {
            $headerKeys[] = 'row_number';
        }
        foreach ($this->headers as $index => $header) {
            $key = is_array($header) ? ($header['key'] ?? 'column_' . $index) : 'column_' . $index;
            $headerKeys[] = $key;
        }
        
        // Build data array
        $rowNumber = 1;
        foreach ($this->rows as $row) {
            $rowData = [];
            $keyIndex = 0;
            
            if ($this->options['show_row_numbers']) {
                $rowData[$headerKeys[$keyIndex++]] = $rowNumber;
            }
            
            foreach ($row as $cell) {
                $cellValue = is_array($cell) && isset($cell['_value']) ? $cell['_value'] : $cell;
                $cellValue = strip_tags($cellValue); // Remove HTML
                $rowData[$headerKeys[$keyIndex++]] = $cellValue;
            }
            
            $data[] = $rowData;
            $rowNumber++;
        }
        
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}
