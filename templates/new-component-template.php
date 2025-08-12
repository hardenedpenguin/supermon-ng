<?php
/**
 * Component Template for Supermon-ng
 * 
 * Use this template when creating new reusable components.
 * Copy this file to the components/ directory and modify as needed.
 * 
 * Instructions:
 * 1. Copy this file to components/ with a descriptive name (e.g., MyComponent.php)
 * 2. Rename the class to match your component
 * 3. Update the constructor and methods for your needs
 * 4. Add proper documentation for all public methods
 * 5. Remove these instruction comments
 * 
 * @author Your Name Here
 * @version 2.0.3
 */

/**
 * Example Component Class
 * 
 * Provides [brief description of what this component does].
 * 
 * Usage:
 * $component = new ExampleComponent($data, $options);
 * echo $component->render();
 */
class ExampleComponent 
{
    private $data;
    private $options;
    private $id;
    
    /**
     * Constructor
     * 
     * @param array $data Component data
     * @param array $options Component options
     */
    public function __construct($data = [], $options = []) 
    {
        $this->data = $data;
        $this->id = $options['id'] ?? 'component_' . uniqid();
        
        // Default options
        $this->options = array_merge([
            'css_class' => 'example-component',
            'show_header' => true,
            'show_footer' => false,
            'template' => 'default'
        ], $options);
    }
    
    /**
     * Render the complete component
     * 
     * @return string HTML output
     */
    public function render() 
    {
        // Validate data before rendering
        if (!$this->validateData()) {
            return $this->renderError('Invalid component data');
        }
        
        $html = '<div id="' . htmlspecialchars($this->id) . '" class="' . 
                htmlspecialchars($this->options['css_class']) . '">';
        
        if ($this->options['show_header']) {
            $html .= $this->renderHeader();
        }
        
        $html .= $this->renderContent();
        
        if ($this->options['show_footer']) {
            $html .= $this->renderFooter();
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render component header
     * 
     * @return string HTML header
     */
    private function renderHeader() 
    {
        $title = $this->data['title'] ?? 'Component Title';
        
        return '<div class="component-header">' .
               '<h3>' . htmlspecialchars($title) . '</h3>' .
               '</div>';
    }
    
    /**
     * Render main component content
     * 
     * @return string HTML content
     */
    private function renderContent() 
    {
        switch ($this->options['template']) {
            case 'list':
                return $this->renderListTemplate();
            case 'table':
                return $this->renderTableTemplate();
            case 'card':
                return $this->renderCardTemplate();
            default:
                return $this->renderDefaultTemplate();
        }
    }
    
    /**
     * Render component footer
     * 
     * @return string HTML footer
     */
    private function renderFooter() 
    {
        return '<div class="component-footer">' .
               '<small>Component ID: ' . htmlspecialchars($this->id) . '</small>' .
               '</div>';
    }
    
    /**
     * Render default template
     * 
     * @return string HTML content
     */
    private function renderDefaultTemplate() 
    {
        $content = $this->data['content'] ?? 'No content provided';
        
        return '<div class="component-content">' .
               '<p>' . htmlspecialchars($content) . '</p>' .
               '</div>';
    }
    
    /**
     * Render list template
     * 
     * @return string HTML list
     */
    private function renderListTemplate() 
    {
        $items = $this->data['items'] ?? [];
        
        if (empty($items)) {
            return '<div class="component-content">No items to display</div>';
        }
        
        $html = '<div class="component-content"><ul>';
        
        foreach ($items as $item) {
            $html .= '<li>' . htmlspecialchars($item) . '</li>';
        }
        
        $html .= '</ul></div>';
        
        return $html;
    }
    
    /**
     * Render table template
     * 
     * @return string HTML table
     */
    private function renderTableTemplate() 
    {
        $headers = $this->data['headers'] ?? [];
        $rows = $this->data['rows'] ?? [];
        
        if (empty($headers) || empty($rows)) {
            return '<div class="component-content">No table data provided</div>';
        }
        
        // Use the TableRenderer component
        return '<div class="component-content">' .
               TableRenderer::renderSimple($headers, $rows, ['class' => 'gridtable']) .
               '</div>';
    }
    
    /**
     * Render card template
     * 
     * @return string HTML card
     */
    private function renderCardTemplate() 
    {
        $title = $this->data['card_title'] ?? 'Card Title';
        $content = $this->data['card_content'] ?? 'Card content';
        $actions = $this->data['card_actions'] ?? [];
        
        $html = '<div class="component-content">';
        $html .= '<div class="card">';
        $html .= '<div class="card-header">' . htmlspecialchars($title) . '</div>';
        $html .= '<div class="card-body">' . htmlspecialchars($content) . '</div>';
        
        if (!empty($actions)) {
            $html .= '<div class="card-actions">';
            foreach ($actions as $action) {
                $html .= '<a href="' . htmlspecialchars($action['url']) . '" class="submit">' . 
                        htmlspecialchars($action['label']) . '</a> ';
            }
            $html .= '</div>';
        }
        
        $html .= '</div></div>';
        
        return $html;
    }
    
    /**
     * Render error message
     * 
     * @param string $message Error message
     * @return string HTML error
     */
    private function renderError($message) 
    {
        return ErrorHandler::displayUserError($message);
    }
    
    /**
     * Validate component data
     * 
     * @return bool True if valid, false otherwise
     */
    private function validateData() 
    {
        // Add your validation logic here
        // Example validations:
        
        // Check if required data is present
        if ($this->options['template'] === 'table') {
            return isset($this->data['headers']) && isset($this->data['rows']);
        }
        
        if ($this->options['template'] === 'list') {
            return isset($this->data['items']) && is_array($this->data['items']);
        }
        
        // Default validation passes
        return true;
    }
    
    /**
     * Get component data
     * 
     * @return array Component data
     */
    public function getData() 
    {
        return $this->data;
    }
    
    /**
     * Set component data
     * 
     * @param array $data New data
     */
    public function setData($data) 
    {
        $this->data = $data;
    }
    
    /**
     * Get component options
     * 
     * @return array Component options
     */
    public function getOptions() 
    {
        return $this->options;
    }
    
    /**
     * Set a component option
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     */
    public function setOption($key, $value) 
    {
        $this->options[$key] = $value;
    }
    
    /**
     * Get component ID
     * 
     * @return string Component ID
     */
    public function getId() 
    {
        return $this->id;
    }
    
    /**
     * Add CSS class to component
     * 
     * @param string $class CSS class to add
     */
    public function addClass($class) 
    {
        $current = $this->options['css_class'];
        $this->options['css_class'] = $current . ' ' . $class;
    }
    
    /**
     * Convert component to JSON for JavaScript use
     * 
     * @return string JSON representation
     */
    public function toJson() 
    {
        return json_encode([
            'id' => $this->id,
            'data' => $this->data,
            'options' => $this->options
        ], JSON_HEX_TAG | JSON_HEX_AMP);
    }
    
    /**
     * Static method to create component instance
     * 
     * @param array $data Component data
     * @param array $options Component options
     * @return ExampleComponent New component instance
     */
    public static function create($data = [], $options = []) 
    {
        return new self($data, $options);
    }
    
    /**
     * Static method to render component directly
     * 
     * @param array $data Component data
     * @param array $options Component options
     * @return string Rendered HTML
     */
    public static function renderStatic($data = [], $options = []) 
    {
        $component = new self($data, $options);
        return $component->render();
    }
}

/* 
 * Example usage:
 * 
 * // Basic usage
 * $component = new ExampleComponent(['content' => 'Hello World']);
 * echo $component->render();
 * 
 * // List template
 * $component = new ExampleComponent([
 *     'title' => 'My List',
 *     'items' => ['Item 1', 'Item 2', 'Item 3']
 * ], ['template' => 'list', 'show_footer' => true]);
 * echo $component->render();
 * 
 * // Table template
 * $component = new ExampleComponent([
 *     'title' => 'Data Table',
 *     'headers' => ['Name', 'Value'],
 *     'rows' => [['Row 1', 'Value 1'], ['Row 2', 'Value 2']]
 * ], ['template' => 'table']);
 * echo $component->render();
 * 
 * // Card template
 * $component = new ExampleComponent([
 *     'title' => 'Dashboard',
 *     'card_title' => 'Information Card',
 *     'card_content' => 'This is some important information.',
 *     'card_actions' => [
 *         ['label' => 'View Details', 'url' => 'details.php'],
 *         ['label' => 'Edit', 'url' => 'edit.php']
 *     ]
 * ], ['template' => 'card']);
 * echo $component->render();
 * 
 * // Static usage
 * echo ExampleComponent::renderStatic(['content' => 'Quick content']);
 */
