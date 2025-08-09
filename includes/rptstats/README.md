# RPT Stats Modules

This directory contains the modularized components for the AllStar RPT Stats functionality (`rptstats.php`).

## Module Structure

### `rptstats-config.inc`
**Purpose**: Configuration, authentication, and parameter validation
- User authentication and authorization checking (RSTATUSER permission)
- Parameter extraction and validation from GET requests
- Session validation and security checks
- Dependency inclusion and setup

**Key Functions**:
- `initializeRPTStatsConfig()` - Main initialization and parameter validation

### `rptstats-ami.inc`
**Purpose**: AMI communication for rpt stats commands
- AllStar rpt stats command execution via AMI
- Output formatting and sanitization
- Error handling for empty or failed responses

**Key Functions**:
- `show_rpt_stats($fp, $node)` - Execute rpt stats command and display output

### `rptstats-ui.inc`
**Purpose**: HTML template rendering for different page states
- Authentication error page template
- Stats display page header and footer
- Parameter error page template
- Consistent CSS inclusion across all templates

**Key Functions**:
- `renderAuthErrorPage($localnode_param, $node_param)` - Render authentication error
- `renderStatsPageHeader($localnode_param)` - Render stats page header
- `renderStatsPageFooter()` - Render stats page footer
- `renderParameterErrorPage()` - Render parameter error page

### `rptstats-processor.inc`
**Purpose**: Main processing logic and AMI connection management
- Configuration file loading and validation
- AMI connection establishment and authentication
- Error handling for configuration and connection failures
- Stats processing coordination

**Key Functions**:
- `processLocalNodeStats($localnode_param)` - Complete stats processing workflow

## Usage Pattern

The modularized `rptstats.php` follows this pattern:

1. **Include modules**:
   ```php
   include("includes/rptstats/rptstats-config.inc");
   include("includes/rptstats/rptstats-ami.inc");
   include("includes/rptstats/rptstats-ui.inc");
   include("includes/rptstats/rptstats-processor.inc");
   ```

2. **Initialize**:
   ```php
   list($node_param, $localnode_param, $isAuthenticated) = initializeRPTStatsConfig();
   ```

3. **Process based on parameters and authentication**:
   ```php
   if (!$isAuthenticated) {
       renderAuthErrorPage($localnode_param, $node_param);
   } elseif ($node_param > 0) {
       // Redirect to external stats
       header("Location: http://stats.allstarlink.org/stats/$node_param");
   } elseif ($localnode_param > 0) {
       // Process local node stats
       renderStatsPageHeader($localnode_param);
       processLocalNodeStats($localnode_param);
       renderStatsPageFooter();
   } else {
       renderParameterErrorPage();
   }
   ```

## Dependencies

- `includes/session.inc` - Session management
- `amifunctions.inc` - AMI client functions (`SimpleAmiClient`)
- `authusers.php` - User authorization functions (`get_user_auth`)
- `includes/common.inc` - Global variables and constants
- `authini.php` - Configuration file mapping (`get_ini_name`)

## Global Variables Used

- `$_SESSION['sm61loggedin']` - Login status flag
- `$_SESSION['user']` - Current logged-in username
- `$_GET['node']` - External node number for redirect
- `$_GET['localnode']` - Local node number for stats processing

## AMI Commands Used

- **rpt stats {node}** - Retrieve AllStar repeater statistics for specified node

## Parameter Processing

### Node Parameter (`$_GET['node']`)
- **Purpose**: External node number for redirect to AllStar Link stats
- **Validation**: Cast to integer, stripped of tags
- **Action**: Redirect to `http://stats.allstarlink.org/stats/{node}`

### Local Node Parameter (`$_GET['localnode']`)
- **Purpose**: Local node number for direct AMI stats retrieval
- **Validation**: Cast to integer, stripped of tags
- **Action**: Connect to local AMI and execute rpt stats command

## Security Features

- **User Authorization**: RSTATUSER permission required for access
- **Parameter Sanitization**: `trim()`, `strip_tags()`, and integer casting
- **Session Validation**: Checks for valid login session
- **AMI Authentication**: Full login validation before stats commands
- **Output Escaping**: `htmlspecialchars()` on all output

## Page Flow Control

### Authentication Check
1. **Authenticated**: Proceed with parameter processing
2. **Not Authenticated**: Display authentication error page and exit

### Parameter Processing
1. **node > 0**: Redirect to external AllStar Link stats
2. **localnode > 0**: Process local node stats via AMI
3. **No valid parameters**: Display parameter error page

### Local Node Processing
1. **Load Configuration**: User-specific INI file via `get_ini_name()`
2. **Validate Node**: Check if node exists in configuration
3. **Connect AMI**: Establish connection to Asterisk Manager
4. **Execute Stats**: Run `rpt stats` command and display output
5. **Cleanup**: Properly close AMI connection

## Error Handling

### Configuration Errors
- **Missing INI File**: Clear error message with file path
- **Parse Errors**: Error message for malformed INI files
- **Missing Node**: Error when node not found in configuration

### Connection Errors
- **AMI Connection Failure**: Error with host information
- **Login Failure**: Clear authentication failure message
- **Automatic Cleanup**: AMI connections properly closed on errors

### Output Handling
- **Empty Stats**: Display `<NONE_OR_EMPTY_STATS>` for empty responses
- **Failed Commands**: Handle false responses from AMI commands
- **HTML Escaping**: All output properly escaped for security

## HTML Template Features

### CSS Integration
- **Modular CSS**: Consistent inclusion of all CSS modules
- **Custom CSS**: Conditional loading of user customizations
- **Responsive Design**: Mobile-friendly layouts

### Page Structure
- **DOCTYPE**: Proper HTML5 document structure
- **Meta Tags**: Character encoding and viewport settings
- **Pre-formatted Output**: `<pre>` tags for stats display formatting

## External Integration

### AllStar Link Stats
- **URL Pattern**: `http://stats.allstarlink.org/stats/{node}`
- **Redirect Method**: HTTP Location header
- **Parameter Validation**: Integer casting for node numbers

### AMI Integration
- **Connection**: `SimpleAmiClient::connect()`
- **Authentication**: `SimpleAmiClient::login()`
- **Command Execution**: `SimpleAmiClient::command()`
- **Cleanup**: `SimpleAmiClient::logoff()`

## Performance Considerations

- **Immediate Redirects**: External nodes redirect without processing
- **Connection Efficiency**: Single AMI connection per request
- **Resource Cleanup**: Proper connection closing on all exit paths
- **Error Fast-Fail**: Quick exit on configuration or connection errors

## Monitoring and Logging

- **Error Output**: Clear error messages for troubleshooting
- **HTML Structure**: Consistent error page structure
- **Debug Information**: Host and file path information in errors
- **User Context**: User-specific configuration and session handling
