<?php
/**
 * API Endpoint Template for Supermon-ng
 * 
 * Use this template when creating new API endpoints.
 * Copy this file to the root directory and modify as needed.
 * 
 * Instructions:
 * 1. Copy this file to the root directory with a descriptive name (e.g., api-my-feature.php)
 * 2. Update the endpoint description and version
 * 3. Modify authentication and permission requirements
 * 4. Add your API logic in the appropriate HTTP method sections
 * 5. Update the response structure as needed
 * 6. Remove these instruction comments
 * 
 * @author Your Name Here
 * @version 2.0.3
 */

// Prevent direct browser access (optional - remove if you want browser access)
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    // Uncomment the next line to restrict to AJAX requests only
    // http_response_code(403);
    // die(json_encode(['error' => 'This endpoint requires AJAX request']));
}

// Include required files
include_once "includes/session.inc";
include_once "includes/common.inc";
include_once "includes/helpers.inc";
include_once "includes/error-handler.inc";
include_once "includes/config.inc";
include_once "authusers.php";

// Set content type to JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// CORS headers (uncomment if needed for cross-origin requests)
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Send JSON response and exit
 * 
 * @param mixed $data Response data
 * @param int $status HTTP status code
 * @param string $message Optional message
 */
function sendResponse($data, $status = 200, $message = '') {
    http_response_code($status);
    
    $response = [
        'success' => $status >= 200 && $status < 300,
        'status' => $status,
        'timestamp' => date('c'),
        'data' => $data
    ];
    
    if (!empty($message)) {
        $response['message'] = $message;
    }
    
    echo json_encode($response, JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response and exit
 * 
 * @param string $message Error message
 * @param int $status HTTP status code
 * @param array $details Optional error details
 */
function sendError($message, $status = 400, $details = []) {
    ErrorHandler::logError("API Error: $message", $details);
    
    $response = [
        'success' => false,
        'status' => $status,
        'timestamp' => date('c'),
        'error' => $message
    ];
    
    if (!empty($details) && Config::get('DEBUG_MODE', false)) {
        $response['details'] = $details;
    }
    
    http_response_code($status);
    echo json_encode($response, JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT);
    exit;
}

try {
    // Authentication check (modify as needed)
    if (!SecurityHelper::isLoggedIn()) {
        sendError('Authentication required', 401);
    }
    
    // Permission check (modify as needed)
    // Available permissions: ADMIN, CFGEDUSER, DTMFUSER, ASTLKUSER, BANUSER, GPIOUSER
    if (!SecurityHelper::hasPermission('ADMIN')) {
        sendError('Insufficient permissions', 403);
    }
    
    // CSRF protection for state-changing operations
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
        if (!isset($_POST['csrf_token']) && !isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            sendError('CSRF token required', 400);
        }
        
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($token)) {
            sendError('Invalid CSRF token', 400);
        }
    }
    
    // Rate limiting (optional)
    if (function_exists('is_rate_limited') && is_rate_limited('api_endpoint', 60, 300)) {
        sendError('Rate limit exceeded. Please try again later.', 429);
    }
    
    // Handle different HTTP methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest();
            break;
            
        case 'POST':
            handlePostRequest();
            break;
            
        case 'PUT':
            handlePutRequest();
            break;
            
        case 'DELETE':
            handleDeleteRequest();
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    ErrorHandler::logError("API Exception: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    sendError('Internal server error', 500);
}

/**
 * Handle GET requests
 * Use for retrieving data
 */
function handleGetRequest() {
    // Validate input parameters
    $id = ValidationHelper::sanitizeInput($_GET['id'] ?? '', 'string');
    $limit = ValidationHelper::sanitizeInput($_GET['limit'] ?? 25, 'int', ['min' => 1, 'max' => 100]);
    $offset = ValidationHelper::sanitizeInput($_GET['offset'] ?? 0, 'int', ['min' => 0]);
    
    // Example: Get list of items
    if (empty($id)) {
        // Return list of items
        $items = getItemList($limit, $offset);
        sendResponse([
            'items' => $items,
            'total' => count($items),
            'limit' => $limit,
            'offset' => $offset
        ]);
    } else {
        // Return specific item
        $item = getItemById($id);
        if (!$item) {
            sendError('Item not found', 404);
        }
        sendResponse($item);
    }
}

/**
 * Handle POST requests
 * Use for creating new resources
 */
function handlePostRequest() {
    // Get JSON input if content type is JSON
    $input = [];
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $json = file_get_contents('php://input');
        $input = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON input', 400);
        }
    } else {
        $input = $_POST;
    }
    
    // Validate required fields
    $name = ValidationHelper::sanitizeInput($input['name'] ?? '', 'string');
    $value = ValidationHelper::sanitizeInput($input['value'] ?? '', 'string');
    
    if (empty($name)) {
        sendError('Name is required', 400);
    }
    
    if (empty($value)) {
        sendError('Value is required', 400);
    }
    
    // Example: Create new item
    $newItem = createItem($name, $value);
    
    // Log the action
    ErrorHandler::logUserAction("Created new item via API", [
        'item_id' => $newItem['id'],
        'name' => $name
    ]);
    
    sendResponse($newItem, 201, 'Item created successfully');
}

/**
 * Handle PUT requests
 * Use for updating existing resources
 */
function handlePutRequest() {
    // Get JSON input
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON input', 400);
    }
    
    // Validate required fields
    $id = ValidationHelper::sanitizeInput($input['id'] ?? '', 'string');
    $name = ValidationHelper::sanitizeInput($input['name'] ?? '', 'string');
    $value = ValidationHelper::sanitizeInput($input['value'] ?? '', 'string');
    
    if (empty($id)) {
        sendError('ID is required for updates', 400);
    }
    
    // Check if item exists
    $existingItem = getItemById($id);
    if (!$existingItem) {
        sendError('Item not found', 404);
    }
    
    // Example: Update item
    $updatedItem = updateItem($id, $name, $value);
    
    // Log the action
    ErrorHandler::logUserAction("Updated item via API", [
        'item_id' => $id,
        'changes' => array_diff_assoc($input, $existingItem)
    ]);
    
    sendResponse($updatedItem, 200, 'Item updated successfully');
}

/**
 * Handle DELETE requests
 * Use for deleting resources
 */
function handleDeleteRequest() {
    $id = ValidationHelper::sanitizeInput($_GET['id'] ?? '', 'string');
    
    if (empty($id)) {
        sendError('ID is required for deletion', 400);
    }
    
    // Check if item exists
    $existingItem = getItemById($id);
    if (!$existingItem) {
        sendError('Item not found', 404);
    }
    
    // Example: Delete item
    $success = deleteItem($id);
    
    if (!$success) {
        sendError('Failed to delete item', 500);
    }
    
    // Log the action
    ErrorHandler::logUserAction("Deleted item via API", [
        'item_id' => $id,
        'item_data' => $existingItem
    ]);
    
    sendResponse(['deleted' => true], 200, 'Item deleted successfully');
}

// Example data functions (replace with your actual data logic)

/**
 * Get list of items
 * 
 * @param int $limit Maximum number of items
 * @param int $offset Starting offset
 * @return array List of items
 */
function getItemList($limit = 25, $offset = 0) {
    // Example implementation - replace with your data source
    $allItems = [
        ['id' => '1', 'name' => 'Item 1', 'value' => 'Value 1', 'created' => '2025-01-01 12:00:00'],
        ['id' => '2', 'name' => 'Item 2', 'value' => 'Value 2', 'created' => '2025-01-01 12:01:00'],
        ['id' => '3', 'name' => 'Item 3', 'value' => 'Value 3', 'created' => '2025-01-01 12:02:00'],
    ];
    
    return array_slice($allItems, $offset, $limit);
}

/**
 * Get item by ID
 * 
 * @param string $id Item ID
 * @return array|null Item data or null if not found
 */
function getItemById($id) {
    // Example implementation - replace with your data source
    $items = getItemList(100, 0);
    
    foreach ($items as $item) {
        if ($item['id'] === $id) {
            return $item;
        }
    }
    
    return null;
}

/**
 * Create new item
 * 
 * @param string $name Item name
 * @param string $value Item value
 * @return array Created item data
 */
function createItem($name, $value) {
    // Example implementation - replace with your data source
    $newId = uniqid();
    
    return [
        'id' => $newId,
        'name' => $name,
        'value' => $value,
        'created' => date('Y-m-d H:i:s'),
        'updated' => date('Y-m-d H:i:s')
    ];
}

/**
 * Update existing item
 * 
 * @param string $id Item ID
 * @param string $name New name (optional)
 * @param string $value New value (optional)
 * @return array Updated item data
 */
function updateItem($id, $name = null, $value = null) {
    // Example implementation - replace with your data source
    $item = getItemById($id);
    
    if ($name !== null && $name !== '') {
        $item['name'] = $name;
    }
    
    if ($value !== null && $value !== '') {
        $item['value'] = $value;
    }
    
    $item['updated'] = date('Y-m-d H:i:s');
    
    return $item;
}

/**
 * Delete item
 * 
 * @param string $id Item ID
 * @return bool True if deleted successfully
 */
function deleteItem($id) {
    // Example implementation - replace with your data source
    // In a real implementation, you would delete from database/storage
    return true;
}

/* 
 * Example usage from JavaScript:
 * 
 * // GET request
 * fetch('api-my-feature.php?id=123')
 *   .then(response => response.json())
 *   .then(data => console.log(data));
 * 
 * // POST request
 * fetch('api-my-feature.php', {
 *   method: 'POST',
 *   headers: {
 *     'Content-Type': 'application/json',
 *     'X-Requested-With': 'XMLHttpRequest'
 *   },
 *   body: JSON.stringify({
 *     name: 'New Item',
 *     value: 'Some Value',
 *     csrf_token: document.querySelector('[name="csrf_token"]').value
 *   })
 * })
 * .then(response => response.json())
 * .then(data => console.log(data));
 * 
 * // PUT request
 * fetch('api-my-feature.php', {
 *   method: 'PUT',
 *   headers: {
 *     'Content-Type': 'application/json',
 *     'X-Requested-With': 'XMLHttpRequest'
 *   },
 *   body: JSON.stringify({
 *     id: '123',
 *     name: 'Updated Item',
 *     value: 'Updated Value'
 *   })
 * })
 * .then(response => response.json())
 * .then(data => console.log(data));
 * 
 * // DELETE request
 * fetch('api-my-feature.php?id=123', {
 *   method: 'DELETE',
 *   headers: {
 *     'X-Requested-With': 'XMLHttpRequest'
 *   }
 * })
 * .then(response => response.json())
 * .then(data => console.log(data));
 */
