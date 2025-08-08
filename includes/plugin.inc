<?php
/**
 * Plugin System for Supermon-ng
 * 
 * Provides a simple but extensible plugin architecture that allows
 * users and developers to extend functionality without modifying core files.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 */

/**
 * Plugin Manager Class
 * 
 * Manages plugin registration, execution, and hooks throughout the application.
 */
class PluginManager 
{
    private static $plugins = [];
    private static $hooks = [];
    private static $pluginData = [];
    private static $enabled = true;
    
    /**
     * Register a plugin with a specific name
     * 
     * @param string $name Plugin name
     * @param callable $callback Plugin callback function
     * @param array $metadata Plugin metadata (version, author, etc.)
     */
    public static function register($name, $callback, $metadata = []) 
    {
        if (!self::$enabled) {
            return;
        }
        
        if (!is_callable($callback)) {
            ErrorHandler::logError("Invalid plugin callback", ['plugin' => $name]);
            return;
        }
        
        self::$plugins[$name] = $callback;
        self::$pluginData[$name] = array_merge([
            'name' => $name,
            'version' => '1.0.0',
            'author' => 'Unknown',
            'description' => 'No description provided',
            'enabled' => true
        ], $metadata);
        
        ErrorHandler::logError("Plugin registered", ['plugin' => $name], 'INFO');
    }
    
    /**
     * Execute a plugin by name
     * 
     * @param string $name Plugin name
     * @param mixed $data Data to pass to plugin
     * @return mixed Plugin return value or null if not found
     */
    public static function execute($name, $data = []) 
    {
        if (!self::$enabled || !isset(self::$plugins[$name])) {
            return null;
        }
        
        if (!self::$pluginData[$name]['enabled']) {
            return null;
        }
        
        try {
            return call_user_func(self::$plugins[$name], $data);
        } catch (Exception $e) {
            ErrorHandler::logError("Plugin execution error", [
                'plugin' => $name,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Register a hook for a specific action
     * 
     * @param string $action Action name
     * @param callable $callback Hook callback
     * @param int $priority Priority (lower = higher priority)
     */
    public static function addHook($action, $callback, $priority = 10) 
    {
        if (!self::$enabled) {
            return;
        }
        
        if (!isset(self::$hooks[$action])) {
            self::$hooks[$action] = [];
        }
        
        self::$hooks[$action][] = [
            'callback' => $callback,
            'priority' => $priority
        ];
        
        // Sort by priority
        usort(self::$hooks[$action], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }
    
    /**
     * Execute all hooks for a specific action
     * 
     * @param string $action Action name
     * @param mixed $data Data to pass to hooks
     * @return mixed Modified data after all hooks
     */
    public static function executeHooks($action, $data = null) 
    {
        if (!self::$enabled || !isset(self::$hooks[$action])) {
            return $data;
        }
        
        foreach (self::$hooks[$action] as $hook) {
            try {
                $result = call_user_func($hook['callback'], $data);
                if ($result !== null) {
                    $data = $result;
                }
            } catch (Exception $e) {
                ErrorHandler::logError("Hook execution error", [
                    'action' => $action,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $data;
    }
    
    /**
     * Get list of registered plugins
     * 
     * @return array Plugin list with metadata
     */
    public static function getPlugins() 
    {
        return self::$pluginData;
    }
    
    /**
     * Enable or disable a specific plugin
     * 
     * @param string $name Plugin name
     * @param bool $enabled Whether to enable the plugin
     */
    public static function setPluginEnabled($name, $enabled) 
    {
        if (isset(self::$pluginData[$name])) {
            self::$pluginData[$name]['enabled'] = $enabled;
            ErrorHandler::logError("Plugin " . ($enabled ? "enabled" : "disabled"), ['plugin' => $name], 'INFO');
        }
    }
    
    /**
     * Load plugins from a directory
     * 
     * @param string $directory Plugin directory path
     */
    public static function loadFromDirectory($directory) 
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $files = glob($directory . '/*.php');
        foreach ($files as $file) {
            self::loadPluginFile($file);
        }
    }
    
    /**
     * Load a specific plugin file
     * 
     * @param string $file Plugin file path
     */
    public static function loadPluginFile($file) 
    {
        if (!file_exists($file) || !is_readable($file)) {
            ErrorHandler::logError("Plugin file not found or not readable", ['file' => $file]);
            return;
        }
        
        try {
            include_once $file;
            ErrorHandler::logError("Plugin file loaded", ['file' => $file], 'INFO');
        } catch (Exception $e) {
            ErrorHandler::logError("Plugin file load error", [
                'file' => $file,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Enable or disable the entire plugin system
     * 
     * @param bool $enabled Whether to enable plugins
     */
    public static function setEnabled($enabled) 
    {
        self::$enabled = $enabled;
    }
    
    /**
     * Check if plugin system is enabled
     * 
     * @return bool True if enabled
     */
    public static function isEnabled() 
    {
        return self::$enabled;
    }
}

/**
 * Common Plugin Hooks
 * 
 * Pre-defined hooks that plugins can use to extend functionality
 */
class PluginHooks 
{
    // Page rendering hooks
    const BEFORE_PAGE_RENDER = 'before_page_render';
    const AFTER_PAGE_RENDER = 'after_page_render';
    const BEFORE_HEADER = 'before_header';
    const AFTER_HEADER = 'after_header';
    const BEFORE_FOOTER = 'before_footer';
    const AFTER_FOOTER = 'after_footer';
    
    // Menu hooks
    const BEFORE_MENU_RENDER = 'before_menu_render';
    const AFTER_MENU_RENDER = 'after_menu_render';
    const MENU_ITEMS = 'menu_items';
    
    // Node display hooks
    const BEFORE_NODE_DISPLAY = 'before_node_display';
    const AFTER_NODE_DISPLAY = 'after_node_display';
    const NODE_DATA_PROCESS = 'node_data_process';
    
    // Authentication hooks
    const BEFORE_LOGIN = 'before_login';
    const AFTER_LOGIN = 'after_login';
    const BEFORE_LOGOUT = 'before_logout';
    const AFTER_LOGOUT = 'after_logout';
    
    // Data hooks
    const AMI_DATA_RECEIVED = 'ami_data_received';
    const CONFIG_LOADED = 'config_loaded';
    
    // Error hooks
    const ERROR_OCCURRED = 'error_occurred';
    const SECURITY_VIOLATION = 'security_violation';
}

/**
 * Plugin Helper Functions
 */

/**
 * Register a simple plugin (convenience function)
 * 
 * @param string $name Plugin name
 * @param callable $callback Plugin callback
 * @param array $metadata Plugin metadata
 */
function register_plugin($name, $callback, $metadata = []) 
{
    PluginManager::register($name, $callback, $metadata);
}

/**
 * Execute a plugin (convenience function)
 * 
 * @param string $name Plugin name
 * @param mixed $data Data to pass
 * @return mixed Plugin result
 */
function execute_plugin($name, $data = []) 
{
    return PluginManager::execute($name, $data);
}

/**
 * Add a hook (convenience function)
 * 
 * @param string $action Action name
 * @param callable $callback Hook callback
 * @param int $priority Priority
 */
function add_hook($action, $callback, $priority = 10) 
{
    PluginManager::addHook($action, $callback, $priority);
}

/**
 * Execute hooks (convenience function)
 * 
 * @param string $action Action name
 * @param mixed $data Data to pass
 * @return mixed Modified data
 */
function do_hooks($action, $data = null) 
{
    return PluginManager::executeHooks($action, $data);
}

/**
 * Example Plugin Definitions
 * 
 * These examples show how plugins can be created and used
 */

// Example: Custom node display plugin
register_plugin('custom_node_display', function($nodeData) {
    if (isset($nodeData['node']) && $nodeData['node'] == '1234') {
        return '<div class="special-node">Special Node: ' . htmlspecialchars($nodeData['info']) . '</div>';
    }
    return null;
});

// Example: Add custom menu item
add_hook(PluginHooks::MENU_ITEMS, function($menuItems) {
    $menuItems[] = [
        'text' => 'Custom Page',
        'url' => 'custom-page.php',
        'permission' => 'ADMIN'
    ];
    return $menuItems;
});

// Example: Process AMI data
add_hook(PluginHooks::AMI_DATA_RECEIVED, function($data) {
    // Add custom processing
    if (isset($data['nodes'])) {
        foreach ($data['nodes'] as &$node) {
            $node['custom_field'] = 'custom_value';
        }
    }
    return $data;
});

// Example: Add custom CSS
add_hook(PluginHooks::AFTER_HEADER, function() {
    return '<style>.custom-node { border: 2px solid red; }</style>';
});

// Load user plugins if directory exists
if (is_dir('user_files/plugins')) {
    PluginManager::loadFromDirectory('user_files/plugins');
}
