# Link.php Modules

This directory contains modularized components for `link.php`, organized for better maintainability and code clarity.

## 📁 **Module Files**

### `link-functions.inc`
**Purpose:** Helper functions for link.php
- `print_auth_button()` - Render permission-based buttons
- `is_internal_ip()` - Check if IP is internal/private

### `link-config.inc`
**Purpose:** Configuration and initialization logic
- `validateLinkNodesParameter()` - Parse and validate nodes parameter
- `loadLinkConfiguration()` - Load and validate INI configuration
- `loadLinkAstDatabase()` - Load ASTDB with file locking
- `processDisplayPreferences()` - Handle cookie-based display settings
- `initializeLinkPage()` - Master initialization function

### `link-ui.inc`
**Purpose:** UI rendering components
- `renderWelcomeMessage()` - Login status based welcome message
- `renderControlPanel()` - Main control buttons and node dropdown
- `renderBottomButtons()` - Display Config, Dashboard, System Info buttons
- `renderHamClock()` - IP-based HamClock iframe embedding
- `renderUserInfo()` - User and IP information footer

### ~~`link-javascript.inc`~~ (Removed)
**Purpose:** ~~JavaScript and Server-Sent Events~~ **→ Replaced by Vue.js SSE**
- ~~`renderMonitoringJavaScript()`~~ - **Real-time updates now handled by Vue.js frontend**

### `link-tables.inc`
**Purpose:** Node table rendering
- `renderNodeTables()` - Main node display tables structure
- `renderSingleNodeTable()` - Individual node table structure
- `renderDetailedSpinner()` - Loading spinner for detailed view

## 🎯 **Benefits**

1. **Clean Separation:** Each module handles a specific aspect of functionality
2. **Reusability:** Functions can be reused in other pages if needed
3. **Maintainability:** Complex logic is organized and documented
4. **Testing:** Individual components can be tested in isolation
5. **Readability:** Main `link.php` is now ~70 lines instead of ~300

## 🔧 **Usage**

All modules are automatically included in `link.php`:

```php
include("includes/link/link-functions.inc");
include("includes/link/link-config.inc");
include("includes/link/link-ui.inc");
// include("includes/link/link-javascript.inc"); // Removed - Vue.js handles SSE
include("includes/link/link-tables.inc");
```

## 📋 **Original Functionality Preserved**

- Real-time node monitoring via Server-Sent Events
- Complex table rendering with color coding
- Permission-based button display
- HamClock integration with IP detection
- All original styling and layout maintained
