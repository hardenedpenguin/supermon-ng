# Supermon-ng Developer Guide

This guide provides detailed information about the Supermon-ng architecture, design patterns, and development workflows.

## 🏗️ Architecture Overview

Supermon-ng follows a modular, function-based architecture designed for maintainability and extensibility.

### Core Components

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Interface │    │   Server-Side   │    │   External      │
│                 │    │   Processing    │    │   Systems       │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ • HTML Pages    │◄──►│ • PHP Scripts   │◄──►│ • Asterisk AMI  │
│ • CSS Modules   │    │ • AMI Client    │    │ • AllStar Link  │
│ • JavaScript    │    │ • Data Models   │    │ • IRLP          │
│ • SSE Client    │    │ • SSE Server    │    │ • EchoLink      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Data Flow

1. **User Request** → Web page (PHP)
2. **Authentication** → Session & permissions check
3. **Data Fetching** → AMI connection to Asterisk
4. **Processing** → Business logic & validation
5. **Response** → HTML generation or JSON/SSE output

## 📁 Directory Structure

### Core Directories

```
supermon-ng/
├── includes/          # Shared PHP libraries and modules
│   ├── session.inc    # Session management
│   ├── common.inc     # Global variables & constants
│   ├── amifunctions.inc # AMI client library
│   ├── form.inc       # Form rendering
│   ├── table.inc      # Table rendering
│   ├── sse/           # Server-Sent Events modules
│   │   ├── server-functions.inc
│   │   ├── server-config.inc
│   │   ├── server-ami.inc
│   │   └── server-monitor.inc
│   ├── link/          # Link page modules
│   │   ├── link-functions.inc
│   │   ├── link-config.inc
│   │   ├── link-ui.inc
│   │   ├── link-javascript.inc
│   │   └── link-tables.inc
│   └── ...
├── css/              # Modular stylesheets
│   ├── base.css      # Variables & base styles
│   ├── layout.css    # Layout & containers
│   ├── forms.css     # Form components
│   └── ...
├── js/               # Client-side JavaScript
│   ├── app.js        # Main application logic
│   ├── auth.js       # Authentication handling
│   └── ...
├── user_files/       # User configuration
│   ├── global.inc    # User settings
│   ├── allmon.ini    # Node configuration
│   └── ...
└── templates/        # Development templates
    └── new-page-template.php
```

### Key Files

| File | Purpose |
|------|---------|
| `index.php` | Main dashboard |
| `server.php` | SSE endpoint for real-time data |
| `login.php` | Authentication |
| `link.php` | Node monitoring interface |
| `includes/session.inc` | Session management |
| `includes/amifunctions.inc` | AMI communication |

## 🔧 Core Systems

### 1. Authentication & Authorization

```php
// Session Management (includes/session.inc)
session_start();
if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true) {
    // Redirect to login
}

// Permission Checking (authusers.php)
if (!get_user_auth("REQUIRED_PERMISSION")) {
    die("Access denied");
}
```

**User Permissions:**
- `ADMIN` - Full system access
- `CFGEDUSER` - Configuration editing
- `DTMFUSER` - DTMF command access
- `ASTLKUSER` - Node lookup functions
- `BANUSER` - Node banning/allowing
- `GPIOUSER` - GPIO control

### 2. AMI (Asterisk Manager Interface) Communication

```php
// Connection Pattern
$fp = SimpleAmiClient::connect($host);
SimpleAmiClient::login($fp, $user, $password);
$result = SimpleAmiClient::command($fp, $command);
SimpleAmiClient::logoff($fp);
```

**Common AMI Commands:**
- `rpt nodes` - Get connected nodes
- `rpt status` - Get node status
- `database show` - Show database entries
- `database put/del` - Modify database

### 3. Real-Time Updates (Server-Sent Events)

```javascript
// Client-side (JavaScript)
const eventSource = new EventSource('server.php?nodes=1234,5678');
eventSource.onmessage = function(event) {
    const data = JSON.parse(event.data);
    updateNodeDisplay(data);
};
```

```php
// Server-side (server.php)
header('Content-Type: text/event-stream');
while (true) {
    $data = fetchNodeData();
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
    sleep(1);
}
```

### 4. Configuration Management

```php
// Global Configuration (includes/common.inc)
$USERFILES = "user_files";
$TITLE_LOGGED = "Supermon-ng V2.0.3";
$ASTDB_TXT = "/var/www/html/supermon-ng/astdb.txt";

// User Configuration (user_files/global.inc)
$HAMCLOCK_ENABLED = true;
$HAMCLOCK_URL_INTERNAL = "http://192.168.1.100/hamclock";
```

## 🎨 Frontend Architecture

### CSS Modular System

The CSS is organized into logical modules that load in a specific order:

1. **base.css** - CSS variables, resets, typography
2. **layout.css** - Grid systems, containers, headers
3. **menu.css** - Navigation components
4. **tables.css** - Data table styles
5. **forms.css** - Form elements and buttons
6. **widgets.css** - Component-specific styles
7. **responsive.css** - Mobile and print styles
8. **custom.css** - User overrides (loads last)

### JavaScript Organization

```javascript
// Modular JavaScript structure
const SupermonApp = {
    // Core functionality
    init: function() {
        this.setupEventSource();
        this.bindEvents();
    },
    
    // Real-time data handling
    setupEventSource: function() {
        // SSE setup
    },
    
    // UI updates
    updateNodeDisplay: function(data) {
        // DOM manipulation
    }
};
```

## 🔒 Security Model

### Input Validation

```php
// Always validate and sanitize input
$nodeId = filter_var($_GET['node'], FILTER_VALIDATE_INT);
if (!$nodeId || $nodeId < 1) {
    die("Invalid node ID");
}

// Use whitelist validation for complex inputs
$allowedCommands = ['status', 'nodes', 'links'];
if (!in_array($command, $allowedCommands)) {
    die("Invalid command");
}
```

### Output Encoding

```php
// Always escape output for HTML context
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// For JavaScript context
echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP);
```

### CSRF Protection

```php
// Include in forms
echo csrf_token_field();

// Validate on submission
require_csrf();
```

## 📊 Data Models

### Node Data Structure

```php
$nodeData = [
    'node' => '1234',
    'info' => 'Node Description',
    'ip' => '192.168.1.100',
    'last_keyed' => '2025-01-15 12:30:45',
    'link' => 'RF',
    'direction' => 'RX/TX',
    'elapsed' => '00:05:23',
    'mode' => 'T',  // T=Transceive, R=RX Only, C=Connecting
    'keyed' => 'no'  // yes/no
];
```

### Configuration Structure

```ini
; allmon.ini format
[1234]
host=192.168.1.100
user=admin
passwd=password
title=My Node
archive=http://example.com/archive
```

## 🧪 Testing Strategies

### Unit Testing

```php
// Simple function testing
function testValidateNodeId() {
    assert(validateNodeId('1234') === true);
    assert(validateNodeId('abc') === false);
    assert(validateNodeId('') === false);
}
```

### Integration Testing

```php
// Test AMI connectivity
function testAMIConnection() {
    $config = ['host' => 'localhost', 'user' => 'test', 'passwd' => 'test'];
    $connection = AMIHelper::connectToNode($config);
    assert($connection !== false);
    AMIHelper::disconnect($connection);
}
```

### Browser Testing

- Test on multiple browsers (Chrome, Firefox, Safari, Edge)
- Test responsive behavior on mobile devices
- Verify JavaScript functionality works without errors
- Test with different user permission levels

## 🚀 Performance Considerations

### Database/File Access

```php
// Cache configuration data
class ConfigCache {
    private static $cache = [];
    
    public static function get($file) {
        if (!isset(self::$cache[$file])) {
            self::$cache[$file] = parse_ini_file($file, true);
        }
        return self::$cache[$file];
    }
}
```

### AMI Connection Management

```php
// Reuse connections when possible
class AMIConnectionPool {
    private static $connections = [];
    
    public static function getConnection($host) {
        if (!isset(self::$connections[$host])) {
            self::$connections[$host] = SimpleAmiClient::connect($host);
        }
        return self::$connections[$host];
    }
}
```

## 🔧 Development Workflows

### Adding a New Feature

1. **Planning**
   - Define requirements
   - Design data flow
   - Identify security considerations

2. **Implementation**
   - Create feature branch
   - Follow coding standards
   - Use existing components when possible

3. **Testing**
   - Unit tests for new functions
   - Integration tests for AMI interactions
   - Browser testing for UI changes

4. **Documentation**
   - Update API documentation
   - Add code comments
   - Update user documentation if needed

### Debugging Common Issues

```php
// Enable debug logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// AMI debugging
function debugAMI($response) {
    error_log("AMI Response: " . print_r($response, true));
}

// JavaScript debugging
console.log('Node data:', nodeData);
console.error('Failed to update display:', error);
```

## 📈 Extending the System

### Adding Custom Components

```php
// Create new component class
class CustomWidget {
    private $data;
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    public function render() {
        // Custom rendering logic
        return "<div class='custom-widget'>" . 
               htmlspecialchars($this->data['content']) . 
               "</div>";
    }
}

// Register with plugin system
PluginManager::register('custom_widget', function($data) {
    $widget = new CustomWidget($data);
    return $widget->render();
});
```

### Custom Themes

```css
/* css/custom.css */
:root {
    /* Override base color variables */
    --primary-color: #your-color;
    --background-color: #your-bg;
}

/* Add component-specific overrides */
.node-display {
    /* Your custom styles */
}
```

## 🔍 Code Quality

### Coding Standards Checklist

- [ ] Functions are documented with PHPDoc
- [ ] Variables have meaningful names
- [ ] Input is validated and sanitized
- [ ] Output is properly escaped
- [ ] Error handling is implemented
- [ ] Security considerations are addressed
- [ ] Code follows DRY principle
- [ ] Complex logic is commented

### Review Process

1. **Self Review**
   - Run lint checks
   - Test all functionality
   - Review security implications

2. **Peer Review**
   - Code readability
   - Architecture consistency
   - Security review

3. **Testing**
   - Automated tests pass
   - Manual testing complete
   - Performance acceptable

This developer guide provides the foundation for understanding and extending the Supermon-ng codebase. For specific implementation details, refer to the code comments and API documentation.
