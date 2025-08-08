# Supermon-ng Includes Directory

This directory contains the core PHP library files that provide shared functionality across the Supermon-ng application. These files implement common patterns, security features, and utility functions to simplify development and maintain consistency.

## Core Include Files

### Authentication & Security

#### `session.inc`
**Purpose**: Session management and security
- Session initialization and configuration
- Session timeout handling (8 hours default)
- Secure session cookie settings
- Session cleanup and destruction

#### `security.inc`
**Purpose**: Core security configuration and headers
- Security headers (CSP, XSS protection, etc.)
- Security constants and configuration
- Basic security utility functions

#### `csrf.inc`
**Purpose**: Cross-Site Request Forgery (CSRF) protection
- CSRF token generation and validation
- Form token embedding functions
- Request validation for state-changing operations

#### `rate_limit.inc`
**Purpose**: Rate limiting for preventing abuse
- Login attempt limiting
- API request rate limiting
- Automatic cleanup of old rate limit data

### Data & Configuration

#### `common.inc`
**Purpose**: Global constants and configuration variables
- Application version information
- File path constants
- Global configuration variables
- Legacy compatibility functions

#### `config.inc`
**Purpose**: Advanced configuration management system
- Multi-format configuration loading (INI, PHP, JSON)
- Nested configuration access with dot notation
- User-specific configuration handling
- Environment variable support
- Configuration caching

### Helper Classes

#### `helpers.inc`
**Purpose**: Utility classes for common operations
- `AMIHelper`: Standardized Asterisk Manager Interface operations
- `ValidationHelper`: Input validation and sanitization
- `SecurityHelper`: Authentication and authorization utilities
- `FileHelper`: Safe file operations with security checks

#### `error-handler.inc`
**Purpose**: Centralized error handling and logging
- `ErrorHandler`: Error logging, user error display, and debugging
- Standardized error responses for different error types
- Error statistics and monitoring
- Log rotation and cleanup

### User Interface

#### `form.inc` & `form_field.inc`
**Purpose**: Reusable form rendering system
- Standardized form generation
- Field validation and rendering
- Support for various input types (text, select, textarea, hidden)
- Accessibility features and proper labeling

#### `table.inc`
**Purpose**: Basic table rendering (legacy)
- Simple table generation from headers and rows
- Basic CSS class support
- Used by older parts of the application

#### `header.inc` & `footer.inc`
**Purpose**: Standard page layout components
- HTML head section with CSS includes
- Navigation menu integration
- Consistent page structure
- Plugin hook integration

#### `menu.inc`
**Purpose**: Navigation menu generation
- Permission-based menu items
- Dynamic menu construction
- User-specific menu customization

### External Integration

#### `amifunctions.inc`
**Purpose**: Asterisk Manager Interface (AMI) client
- `SimpleAmiClient`: Low-level AMI communication
- Connection management and authentication
- Command execution and response parsing
- Error handling for AMI operations

#### `nodeinfo.inc`
**Purpose**: Node information and database handling
- AllStar node database processing
- Node lookup and information retrieval
- Database caching and optimization

### Advanced Features

#### `plugin.inc`
**Purpose**: Plugin system for extensibility
- `PluginManager`: Plugin registration and execution
- Hook system for extending functionality
- Plugin loading from directories
- Standard plugin patterns and examples

## Helper Class Quick Reference

### AMIHelper
```php
// Connect to a node
$connection = AMIHelper::connectToNode('1234');

// Execute command
$result = AMIHelper::executeCommand($connection, 'rpt nodes');

// Disconnect
AMIHelper::disconnect($connection);
```

### ValidationHelper
```php
// Validate node ID
$nodeId = ValidationHelper::validateNodeId($_GET['node']);

// Sanitize input
$input = ValidationHelper::sanitizeInput($_POST['data'], 'string');

// Validate file path
$path = ValidationHelper::validateFilePath($filepath);
```

### SecurityHelper
```php
// Check if logged in
if (SecurityHelper::isLoggedIn()) {
    // User is authenticated
}

// Require specific permission
SecurityHelper::requirePermission('ADMIN');

// Generate secure token
$token = SecurityHelper::generateToken();
```

### ErrorHandler
```php
// Log error with context
ErrorHandler::logError('Operation failed', ['user' => $username]);

// Display user-friendly error
echo ErrorHandler::displayUserError('Please try again later.');

// Handle specific error types
echo ErrorHandler::handleAMIError($error);
```

### Config
```php
// Load configuration
Config::load('user_files/global.inc');

// Get configuration value
$value = Config::get('HAMCLOCK_ENABLED', false);

// Get nested value
$url = Config::get('database.host', 'localhost');
```

## File Loading Order

When creating new pages, include files in this order:

1. **Session management**: `session.inc`
2. **Authentication**: `authusers.php` (if using permissions)
3. **Core functionality**: `common.inc`, `helpers.inc`
4. **Specialized includes**: As needed for your functionality

```php
<?php
// Standard include pattern for new pages
include_once "includes/session.inc";
include_once "includes/common.inc";
include_once "includes/helpers.inc";
include_once "includes/error-handler.inc";
include_once "authusers.php";

// Check authentication
SecurityHelper::requireLogin();
SecurityHelper::requirePermission('REQUIRED_PERMISSION');
?>
```

## Security Best Practices

### Input Validation
```php
// Always validate input
$nodeId = ValidationHelper::validateNodeId($_GET['node']);
if (!$nodeId) {
    echo ErrorHandler::displayUserError('Invalid node ID');
    exit;
}
```

### Output Escaping
```php
// Always escape output
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// For JavaScript context
echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP);
```

### Error Handling
```php
// Log errors for debugging
ErrorHandler::logError('Database connection failed', ['host' => $host]);

// Show user-friendly messages
echo ErrorHandler::displayUserError('Unable to connect. Please try again.');
```

## Creating New Include Files

When creating new include files:

1. **Add file header documentation**:
```php
<?php
/**
 * Purpose of this include file
 * 
 * Detailed description of functionality provided.
 * 
 * @author Your Name
 * @version 2.0.3
 */
```

2. **Follow naming conventions**:
   - Use descriptive names ending in `.inc`
   - Group related functionality
   - Use lowercase with hyphens for multi-word names

3. **Document all functions**:
```php
/**
 * Function description
 * 
 * @param string $param Parameter description
 * @return bool Return value description
 */
function myFunction($param) {
    // Implementation
}
```

4. **Include error handling**:
```php
try {
    // Your code
} catch (Exception $e) {
    ErrorHandler::logError('Operation failed: ' . $e->getMessage());
    return false;
}
```

## Backward Compatibility

Some include files maintain backward compatibility with the original Supermon codebase:

- `common.inc`: Contains legacy global variables and constants
- `amifunctions.inc`: Maintains original AMI client interface
- `table.inc`: Simple table rendering for existing code

New development should use the helper classes and modern patterns while maintaining compatibility with existing functionality.

## Dependencies

### Required PHP Extensions
- `openssl`: For secure token generation
- `json`: For configuration and data handling
- `session`: For session management

### External Dependencies
- jQuery (included in `js/` directory)
- AllStar Link node database (`astdb.txt`)
- Asterisk Manager Interface (AMI)

## Debugging

### Error Logging
Errors are logged to `/tmp/supermon-ng-errors.log` by default. Configure logging in `error-handler.inc`.

### Debug Mode
Enable debug mode in development:
```php
// In your configuration
$DEBUG_MODE = true;
```

### Common Issues
- **Include path errors**: Ensure files are included from the correct location
- **Permission errors**: Check file permissions for include files
- **Session issues**: Verify session configuration in `session.inc`
- **AMI connection errors**: Check Asterisk Manager configuration

## Contributing

When modifying include files:

1. **Test thoroughly**: Changes affect the entire application
2. **Maintain backward compatibility**: Don't break existing functionality
3. **Update documentation**: Keep this README current
4. **Follow security practices**: All input validation and output escaping
5. **Add appropriate logging**: Use ErrorHandler for consistent logging

## Migration Guide

### From Legacy Code
To modernize legacy code:

1. Replace direct database access with helper functions
2. Use ValidationHelper for input validation
3. Use ErrorHandler for error management
4. Replace manual form generation with form includes
5. Use Config class for configuration management

### Example Migration
```php
// Old style
$node = $_GET['node'];
if (!is_numeric($node)) {
    die('Invalid node');
}

// New style
$node = ValidationHelper::validateNodeId($_GET['node']);
if (!$node) {
    echo ErrorHandler::displayUserError('Invalid node ID');
    exit;
}
```
