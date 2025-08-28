# Link.php Layout Analysis

## Overview
This document provides a comprehensive analysis of the `link.php` interface, which serves as the main dashboard for Supermon-ng. Understanding this layout is crucial for creating a modern frontend that maintains all functionality while improving the user experience.

## Core Architecture

### Main Entry Point: `link.php`
- **Purpose**: Primary interface for AllStar node monitoring and control
- **Authentication**: Requires session validation (`$_SESSION['sm61loggedin']`)
- **URL Structure**: `link.php?nodes=1234,2345` (comma-separated node IDs)
- **Dependencies**: Multiple include files for modular functionality

### Key Include Files
1. **`includes/link/link-config.inc`** - Configuration loading and validation
2. **`includes/link/link-functions.inc`** - Helper functions and utilities
3. **`includes/link/link-ui.inc`** - UI rendering components
4. **`includes/link/link-tables.inc`** - Node table generation
5. **`includes/link/link-javascript.inc`** - Real-time monitoring JavaScript

## Layout Structure

### 1. Header Section (`includes/header.inc`)
- **Page Title**: Dynamic based on current page and nodes
- **Security Headers**: CSP, XSS protection, frame options
- **CSS Includes**: Modular CSS files (base, layout, tables, forms, widgets, responsive)
- **JavaScript Includes**: jQuery, jQuery UI, custom scripts
- **Session Management**: User authentication and display preferences

### 2. Welcome Message
- **Conditional Display**: Different messages for logged-in vs anonymous users
- **Global Variables**: `$WELCOME_MSG`, `$WELCOME_MSG_LOGGED`

### 3. Control Panel (`renderControlPanel()`)
**Location**: `includes/link/link-ui.inc`

#### Node Selection
- **Multiple Nodes**: Dropdown with node info from ASTDB
- **Single Node**: Hidden input field
- **Node Info Format**: `NodeID => Callsign Description Location`

#### Input Fields
- **Node Input**: Text field for target node number
- **Permission Input**: Special styling for `PERMUSER` permission
- **Placeholder**: "Node to connect/DTMF"

#### Control Buttons (Permission-based)
**Primary Controls:**
- **Connect** (`CONNECTUSER`) - Connect to remote node
- **Disconnect** (`DISCUSER`) - Disconnect from remote node
- **Monitor** (`MONUSER`) - Monitor remote node
- **Local Monitor** (`LMONUSER`) - Local monitoring

**Secondary Controls:**
- **DTMF** (`DTMFUSER`) - DTMF command execution
- **Lookup** (`ASTLKUSER`) - Node lookup functionality
- **Rpt Stats** (`RSTATUSER`) - Repeater statistics (detailed view only)

**System Controls:**
- **Control** (`CTRLUSER`) - Control panel access
- **Favorites** (`FAVUSER`) - Favorites management
- **Add Favorite** (`FAVUSER`) - Add current node to favorites
- **Delete Favorite** (`FAVUSER`) - Remove from favorites

**Detailed View Additional Controls:**
- **Configuration Editor** (`CFGEDUSER`) - System configuration
- **Iax/Rpt/DP RELOAD** (`ASTRELUSER`) - Reload services
- **AST START** (`ASTSTRUSER`) - Start Asterisk
- **AST STOP** (`ASTSTPUSER`) - Stop Asterisk
- **RESTART** (`FSTRESUSER`) - Fast restart
- **Server REBOOT** (`RBTUSER`) - System reboot

**Information Controls:**
- **AllStar How To's** (`HWTOUSER`) - External help
- **AllStar Wiki** (`WIKIUSER`) - External wiki
- **CPU Status** (`CSTATUSER`) - CPU monitoring
- **AllStar Status** (`ASTATUSER`) - System status
- **Registry** (`EXNUSER`) - External nodes (if enabled)
- **Node Info** (`NINFUSER`) - Node information
- **Active Nodes** (`ACTNUSER`) - External active nodes
- **All Nodes** (`ALLNUSER`) - External all nodes
- **Database** (`DBTUSER`) - Database access (if enabled)

**Logging Controls:**
- **GPIO** (`GPIOUSER`) - GPIO control
- **Linux Log** (`LLOGUSER`) - System logs
- **AST Log** (`ASTLUSER`) - Asterisk logs
- **IRLP Log** (`IRLPUSER`) - IRLP logs (if enabled)
- **Web Access Log** (`WLOGUSER`) - Access logs
- **Web Error Log** (`WERRUSER`) - Error logs

**Access Control:**
- **Access List** (`BANUSER`) - Node ban/allow management

### 4. Bottom Utility Buttons
- **Display Configuration** - Opens display settings popup
- **Digital Dashboard** - Opens DVM dashboard (if configured)
- **System Info** - System information (if `SYSINFUSER` permission)

### 5. Node Tables (`renderNodeTables()`)
**Location**: `includes/link/link-tables.inc`

#### Table Structure
- **Container**: Centered table with `fxwidth` class
- **Per Node**: Individual table with `gridtable` or `gridtable-large` class
- **Table ID**: `table_{nodeID}` for JavaScript targeting

#### Table Header
- **Title Format**: `Node {nodeID} => {callsign} {description} {location}`
- **Links**: Bubble Chart, lsNodes, Listen Live, Archive (if configured)
- **Custom URLs**: Support for `URL_{nodeID}` variables
- **Private Nodes**: Special handling for hidden nodes

#### Column Headers
**Basic View (5 columns):**
1. Node
2. Node Information
3. Link
4. Dir
5. Mode

**Detailed View (7 columns):**
1. Node
2. Node Information
3. Received
4. Link
5. Dir
6. Connected
7. Mode

#### Initial State
- **Loading Message**: "Waiting..." with appropriate colspan
- **Real-time Updates**: JavaScript replaces content via Server-Sent Events

### 6. Real-time JavaScript (`renderMonitoringJavaScript()`)
**Location**: `includes/link/link-javascript.inc`

#### Server-Sent Events (SSE)
- **Endpoint**: `server.php?nodes={nodeList}`
- **Event Types**: `nodes`, `nodetimes`, `connection`, `error`
- **Real-time Updates**: Automatic table content replacement

#### Status Indicators
**Header Status Row:**
- **Idle** (`gColor`) - Normal operation (gray background)
- **PTT-Keyed** (`tColor`) - Push-to-talk active (dark gray)
- **COS-Detected** (`lColor`) - Carrier-operated squelch (medium gray)
- **COS-Detected and PTT-Keyed** (`bColor`) - Full-duplex (green background)

**Connected Node Rows:**
- **Keyed** (`rColor`/`rxkColor`) - Active transmission
- **Connecting** (`cColor`) - Connection in progress
- **Receive Only** (`rxColor`) - Receive mode

#### Dynamic Content
- **CPU Temperature**: Color-coded health indicators
- **System Alerts**: ALERT, WX, DISK information
- **Connection Status**: Real-time connection updates
- **Node Count**: "X shown of Y nodes connected" with navigation

### 7. Footer Section (`includes/footer.inc`)
- **User Information**: Current user and session details
- **Copyright**: Application branding
- **Closing Tags**: HTML structure completion

## Display Modes

### Basic View (`Show_Detail = 0`)
- **Font Size**: 22px (`text-large`)
- **Button Size**: Large (`submit-large`)
- **Table Class**: `gridtable-large`
- **Columns**: 5 columns (Node, Info, Link, Dir, Mode)
- **Spacing**: Reduced spacing for mobile-friendly layout

### Detailed View (`Show_Detail = 1`)
- **Font Size**: Normal (`text-normal`)
- **Button Size**: Standard (`submit`)
- **Table Class**: `gridtable`
- **Columns**: 7 columns (includes Received, Connected)
- **Additional Features**: Spinner indicator, time updates
- **Extra Controls**: All detailed view buttons

## CSS Architecture

### Core CSS Files
1. **`css/base.css`** - Basic styling and CSS variables
2. **`css/layout.css`** - Layout and positioning
3. **`css/tables.css`** - Table styling and color schemes
4. **`css/forms.css`** - Form elements and buttons
5. **`css/widgets.css`** - Component-specific styling
6. **`css/menu.css`** - Navigation styling
7. **`css/responsive.css`** - Mobile responsiveness

### Color Scheme
**CSS Variables:**
- `--primary-color`: Main accent color
- `--text-color`: Primary text color
- `--background-color`: Page background
- `--container-bg`: Container backgrounds
- `--border-color`: Border colors
- `--success-color`: Success indicators
- `--warning-color`: Warning indicators
- `--error-color`: Error indicators
- `--link-color`: Link colors

### Button Styling
**Classes:**
- `.submit` - Standard buttons
- `.submit-large` - Large buttons (basic view)
- `.submit2` - Alternative button style

**Features:**
- Rounded corners (15px border-radius)
- Hover effects with color transitions
- Permission-based visibility
- Responsive sizing

### Table Styling
**Classes:**
- `.gridtable` - Standard tables (detailed view)
- `.gridtable-large` - Large tables (basic view)

**Row Colors:**
- `.gColor` - Idle status (medium gray)
- `.tColor` - PTT active (dark gray)
- `.lColor` - COS active (distinct gray)
- `.bColor` - Full-duplex (green)
- `.rColor` - Keyed transmission (dark gray)
- `.cColor` - Connecting (dark gray)
- `.rxColor` - Receive only (medium gray)

## JavaScript Functionality

### Real-time Updates
- **SSE Connection**: Automatic connection to `server.php`
- **Event Handling**: JSON parsing and DOM manipulation
- **Error Handling**: Graceful fallback for connection issues
- **Performance**: Efficient DOM updates with jQuery

### Interactive Features
- **Node Clicking**: `onclick="toTop()"` for navigation
- **Popup Windows**: External links and configuration panels
- **Form Validation**: Input validation for node numbers
- **Dynamic Styling**: Real-time color changes based on status

### Browser Compatibility
- **SSE Support**: Feature detection for EventSource
- **Fallback**: Error message for unsupported browsers
- **jQuery**: Cross-browser compatibility layer

## Permission System

### User Authentication
- **Session-based**: `$_SESSION['sm61loggedin']`
- **Permission Checks**: `get_user_auth($permission)`
- **Conditional Rendering**: UI elements based on permissions

### Permission Categories
1. **Connection Permissions**: CONNECTUSER, DISCUSER, MONUSER, LMONUSER
2. **Control Permissions**: CTRLUSER, CFGEDUSER, ASTRELUSER, etc.
3. **Information Permissions**: CSTATUSER, ASTATUSER, NINFUSER, etc.
4. **Logging Permissions**: LLOGUSER, ASTLUSER, WLOGUSER, etc.
5. **System Permissions**: RBTUSER, FSTRESUSER, etc.

## Configuration Management

### Display Preferences
**Cookie-based Settings:**
- `number-displayed`: Number of nodes to show
- `show-number`: Show node count
- `show-all`: Show all nodes
- `show-detailed`: Detailed view mode

### Node Configuration
- **INI Files**: User-specific configuration files
- **ASTDB Integration**: AllStar database for node information
- **Custom URLs**: Support for node-specific external links

## External Integrations

### AllStarLink Resources
- **Stats**: `http://stats.allstarlink.org/`
- **Wiki**: `https://wiki.allstarlink.org`
- **Help**: `https://allstarlink.org/howto.html`
- **Node List**: `https://www.allstarlink.org/nodelist/`

### Popup Windows
- **Favorites Panel**: `favorites.php`
- **Configuration Editor**: `configeditor.php`
- **Display Configuration**: `display-config.php`
- **Add/Delete Favorites**: `addfavorite.php`, `deletefavorite.php`

## Responsive Design

### Mobile Support
- **Viewport Meta**: Responsive viewport configuration
- **CSS Media Queries**: Mobile-specific styling
- **Touch-friendly**: Large buttons and touch targets
- **Flexible Layout**: Adaptive table and form layouts

### Browser Compatibility
- **Modern Browsers**: Full SSE and CSS3 support
- **Legacy Support**: Graceful degradation for older browsers
- **JavaScript**: Feature detection and fallbacks

## Security Features

### Input Validation
- **Node Parameters**: URL parameter validation
- **User Input**: HTML escaping and sanitization
- **CSRF Protection**: Cross-site request forgery prevention

### Access Control
- **Session Validation**: Authentication checks
- **Permission-based UI**: Feature access control
- **Secure Headers**: CSP, XSS protection, frame options

## Performance Considerations

### Caching
- **Static Assets**: CSS and JavaScript caching
- **Database**: ASTDB file caching
- **Configuration**: User config caching

### Optimization
- **Efficient DOM Updates**: Minimal DOM manipulation
- **SSE Connection**: Single connection for multiple nodes
- **Lazy Loading**: Conditional content loading

## Key Takeaways for Modern Frontend

### Essential Features to Preserve
1. **Real-time Updates**: Server-Sent Events for live status
2. **Permission System**: Role-based access control
3. **Node Management**: Connect, disconnect, monitor functionality
4. **Status Indicators**: Color-coded status display
5. **Configuration**: Display preferences and user settings
6. **External Links**: AllStarLink resource integration

### Modernization Opportunities
1. **Component Architecture**: Vue.js components for modularity
2. **State Management**: Pinia for application state
3. **API Integration**: RESTful API endpoints
4. **Real-time Communication**: WebSocket or SSE with modern handling
5. **Responsive Design**: Modern CSS Grid and Flexbox
6. **Type Safety**: TypeScript for better development experience

### Layout Structure to Maintain
1. **Header**: Navigation and user information
2. **Control Panel**: Node selection and action buttons
3. **Node Tables**: Real-time status display
4. **Footer**: System information and branding

This analysis provides the foundation for creating a modern frontend that maintains all existing functionality while improving the user experience and development maintainability.


