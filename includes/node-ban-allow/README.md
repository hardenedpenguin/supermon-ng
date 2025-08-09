# Node Ban/Allow Modules

This directory contains the modularized components for the Node Ban/Allow functionality (`node-ban-allow.php`).

## Module Structure

### `ban-config.inc`
**Purpose**: Configuration, authentication, and AMI connection setup
- User authentication and authorization checking (BANUSER permission)
- CSRF token validation for POST requests
- Parameter validation and sanitization for node numbers
- Configuration file loading and validation
- AMI connection establishment and authentication

**Key Functions**:
- `initializeBanAllow()` - Main initialization and security validation
- `cleanupBanAllowAMI($fp)` - AMI connection cleanup

### `ban-ami.inc`
**Purpose**: AMI communication utilities
- Simple wrapper functions for AMI command execution
- Consistent interface for database operations
- Error handling for AMI communication

**Key Functions**:
- `sendCmdToAMI($fp, $cmd)` - Send command to AMI and get response
- `getDataFromAMI($fp, $cmd)` - Retrieve data from AMI (alias for sendCmdToAMI)

### `ban-processor.inc`
**Purpose**: Form processing and database operations
- POST form data validation and sanitization
- AMI database command construction (put/del operations)
- Success/failure message display with styling
- Input validation for list types and actions

**Key Functions**:
- `processBanAllowForm($fp, $localnode)` - Process form submission and execute database operations

### `ban-ui.inc`
**Purpose**: HTML template and form rendering
- HTML head section with CSS includes
- Page structure and title rendering
- Form rendering with CSRF protection
- Consistent styling and layout

**Key Functions**:
- `renderBanAllowHead($localnode)` - Render HTML head with title
- `renderBanAllowBodyStart($localnode)` - Start page body with title
- `renderBanAllowForm($localnode, $Node)` - Render the ban/allow form
- `renderBanAllowFooter()` - Render page footer

### `ban-display.inc`
**Purpose**: Data display and list rendering
- Denylist data retrieval and table display
- Allowlist data retrieval and formatted display
- AMI response parsing and cleanup
- Different display formats for different list types

**Key Functions**:
- `displayDenyList($fp, $localnode)` - Display denied nodes table
- `displayAllowList($fp, $localnode)` - Display allowed nodes list

## Usage Pattern

The modularized `node-ban-allow.php` follows this pattern:

1. **Include modules**:
   ```php
   include("includes/node-ban-allow/ban-config.inc");
   include("includes/node-ban-allow/ban-ami.inc");
   include("includes/node-ban-allow/ban-processor.inc");
   include("includes/node-ban-allow/ban-ui.inc");
   include("includes/node-ban-allow/ban-display.inc");
   ```

2. **Initialize**:
   ```php
   list($Node, $localnode, $config, $fp) = initializeBanAllow();
   ```

3. **Process form if submitted**:
   ```php
   processBanAllowForm($fp, $localnode);
   ```

4. **Render page**:
   ```php
   renderBanAllowHead($localnode);
   renderBanAllowBodyStart($localnode);
   renderBanAllowForm($localnode, $Node);
   displayDenyList($fp, $localnode);
   displayAllowList($fp, $localnode);
   renderBanAllowFooter();
   ```

5. **Cleanup**:
   ```php
   cleanupBanAllowAMI($fp);
   ```

## Dependencies

- `includes/session.inc` - Session management
- `includes/amifunctions.inc` - AMI client functions (`SimpleAmiClient`)
- `includes/common.inc` - Global variables and constants
- `authusers.php` - User authorization functions (`get_user_auth`)
- `authini.php` - Configuration file mapping (`get_ini_name`)
- `includes/csrf.inc` - CSRF protection (`require_csrf`, `csrf_token_field`)
- `includes/table.inc` - Table rendering for denylist display

## Global Variables Used

- `$_SESSION['sm61loggedin']` - Login status flag
- `$_SESSION['user']` - Current logged-in username
- `$_GET['node']` / `$_GET['ban-node']` - Node number to pre-fill in form
- `$_GET['localnode']` - Local node number for operations
- `$_POST` - Form submission data (listtype, node, deleteadd, comment)

## AMI Commands Used

- **database put** - Add node to allowlist/denylist with optional comment
- **database del** - Remove node from allowlist/denylist
- **database show** - Retrieve current allowlist/denylist contents

## Database Structure

### Allowlist
- **Family**: `allowlist/{localnode}`
- **Key**: Node number
- **Value**: Optional comment
- **Purpose**: Nodes explicitly allowed to connect

### Denylist
- **Family**: `denylist/{localnode}`
- **Key**: Node number  
- **Value**: Optional comment
- **Purpose**: Nodes explicitly denied from connecting

## Security Features

- **User Authorization**: BANUSER permission required for access
- **CSRF Protection**: Token validation for all POST requests
- **Input Validation**: Regex validation for node numbers
- **Parameter Sanitization**: `trim()` and `strip_tags()` on all inputs
- **AMI Authentication**: Full login validation before operations
- **Error Handling**: Graceful failure with descriptive messages

## Form Validation

### Node Numbers
- **Pattern**: Must be numeric only (`^\d+$`)
- **Local Node**: Required parameter
- **Target Node**: Optional on page load, required for operations

### List Types
- **Allowlist**: Nodes explicitly permitted to connect
- **Denylist**: Nodes explicitly blocked from connecting
- **Validation**: Must be one of `['allowlist', 'denylist']`

### Actions
- **Add**: Add node to selected list with optional comment
- **Delete**: Remove node from selected list
- **Validation**: Must be one of `['add', 'delete']`

## Display Features

### Denylist Display
- **Format**: HTML table using `includes/table.inc`
- **Columns**: Node, Comment
- **Empty State**: "---NONE---" message

### Allowlist Display
- **Format**: Preformatted text (`<pre>` tag)
- **Processing**: Output line cleaning and formatting
- **Empty State**: "---NONE---" message

## Error Handling

- **Connection Failures**: Clear error messages for AMI connection issues
- **Authentication Failures**: Specific error for login failures
- **Validation Errors**: Individual error messages for each validation failure
- **Command Failures**: Success/failure feedback with styled messages
- **Missing Configuration**: Error messages for missing INI files or nodes

## Response Processing

### AMI Output Cleaning
- **"Output: " Prefix**: Automatically stripped from response lines
- **Result Count Lines**: Filtered out (e.g., "5 results found.")
- **Whitespace**: Normalized and trimmed
- **Empty Lines**: Filtered out to prevent display issues

## Styling Classes

- **ban-allow-page**: Body class for page-specific styling
- **ban-allow-title**: Title styling
- **ban-allow-table**: Main table styling
- **ban-allow-cell-left/right/center**: Cell positioning
- **ban-allow-button**: Submit button styling
- **error-message**: Error message styling (inherited from global CSS)
