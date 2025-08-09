# System Info Modules

This directory contains the modularized components for the System Information display functionality (`system-info.php`).

## Module Structure

### `sysinfo-config.inc`
**Purpose**: Configuration, authentication, and parameter validation
- User authentication and authorization checking (SYSINFUSER permission)
- Session validation and security checks
- Cookie parameter processing for display preferences
- Initial setup and dependency inclusion

**Key Functions**:
- `initializeSystemInfo()` - Main initialization and security validation

### `sysinfo-commands.inc`
**Purpose**: Safe command execution utilities
- Secure command path validation and resolution
- Command execution with proper escaping and error handling
- Command path discovery across common system locations
- Centralized command security controls

**Key Functions**:
- `get_safe_command_path($command_name, $default_path)` - Find and validate command paths
- `safe_exec($command, $args)` - Execute commands safely with error handling
- `initializeSystemCommands()` - Initialize all required system command paths

### `sysinfo-ui.inc`
**Purpose**: HTML template and page structure
- HTML head section with CSS includes and JavaScript
- Page body structure and container setup
- Footer rendering with close button functionality
- Responsive design and styling integration

**Key Functions**:
- `renderSystemInfoHead()` - Render HTML head section
- `renderSystemInfoBodyStart($Show_Detail)` - Start page body with proper container
- `renderSystemInfoFooter()` - Render page footer and close button

### `sysinfo-collectors.inc`
**Purpose**: System data collection functions
- Basic system information gathering (hostname, date, ports)
- Network IP address detection (WAN/LAN)
- SSH configuration and system version collection
- Hardware and software version information

**Key Functions**:
- `collectBasicSystemInfo($commands)` - Collect core system information
- `collectNetworkInfo($commands)` - Gather network and IP information
- `collectSSHAndVersionInfo($commands)` - Get SSH and version details

### `sysinfo-status.inc`
**Purpose**: Configuration status and advanced monitoring
- User file and INI configuration status display
- System uptime and load average monitoring
- Core dump detection and warning display
- CPU temperature monitoring with threshold alerts

**Key Functions**:
- `displayConfigurationStatus()` - Show user configuration status
- `displayUptimeAndLoad($commands, $myday)` - Display system uptime and load
- `displayCoreDumpInfo()` - Check and display core dump information
- `displayCPUTemperature()` - Monitor and display CPU temperature with alerts

## Usage Pattern

The modularized `system-info.php` follows this pattern:

1. **Include modules**:
   ```php
   include("includes/system-info/sysinfo-config.inc");
   include("includes/system-info/sysinfo-commands.inc");
   include("includes/system-info/sysinfo-ui.inc");
   include("includes/system-info/sysinfo-collectors.inc");
   include("includes/system-info/sysinfo-status.inc");
   ```

2. **Initialize**:
   ```php
   list($Show_Detail) = initializeSystemInfo();
   $commands = initializeSystemCommands();
   ```

3. **Render page**:
   ```php
   renderSystemInfoHead();
   renderSystemInfoBodyStart($Show_Detail);
   
   // Collect and display data
   $basic_info = collectBasicSystemInfo($commands);
   $network_info = collectNetworkInfo($commands);
   $ssh_version_info = collectSSHAndVersionInfo($commands);
   
   displayConfigurationStatus();
   displayUptimeAndLoad($commands, $basic_info['myday']);
   displayCoreDumpInfo();
   displayCPUTemperature();
   
   renderSystemInfoFooter();
   ```

## Dependencies

- `includes/session.inc` - Session management
- `includes/security.inc` - Security utilities
- `includes/common.inc` - Global variables and constants
- `user_files/global.inc` - User configuration variables
- `authusers.php` - User authorization functions (`get_user_auth`)
- `authini.php` - Configuration file mapping (`get_ini_name`)
- `favini.php` - Favorites INI validation (`faviniValid`, `get_fav_ini_name`)
- `cntrlini.php` - Control panel INI validation (`cntrliniValid`, `get_cntrl_ini_name`)

## Global Variables Used

- `$USERFILES` - Path to user configuration files directory
- `$WANONLY` - Flag to determine WAN-only mode for IP detection
- `$_SESSION['user']` - Current logged-in username
- `$_SESSION['sm61loggedin']` - Login status flag

## System Commands Used

- **hostname** - System hostname retrieval
- **awk** - Text processing and field extraction
- **date** - Date and time formatting
- **cat** - File content reading
- **egrep/grep** - Pattern matching and text filtering
- **sed** - Stream editing and text substitution
- **head/tail** - File content limiting
- **curl** - External IP address lookup
- **cut** - Field extraction from delimited text
- **ip** - Network interface information
- **uptime** - System uptime and load average

## Security Features

- **Command Path Validation**: All commands validated for existence and execution permissions
- **Shell Escaping**: All command arguments properly escaped with `escapeshellarg()`
- **Command Escaping**: Commands escaped with `escapeshellcmd()`
- **User Authorization**: SYSINFUSER permission required for access
- **Error Handling**: Graceful failure with 'N/A' for failed commands
- **Input Sanitization**: Cookie data sanitized with `htmlspecialchars()`

## Monitoring Features

### Core Dump Detection
- **Location**: `/var/crash` directory
- **Warning Levels**: 
  - 1-2 dumps: Warning (yellow)
  - 3+ dumps: Error (red)
  - 0 dumps: Normal

### CPU Temperature Monitoring
- **Script**: `user_files/sbin/get_temp`
- **Thresholds**:
  - Normal: < 50°C
  - Warning: 50-64°C (yellow)
  - High: ≥ 65°C (red)
- **Format**: Parses "CPU: XXX°C @ HH:MM" format

### Network IP Detection
- **External IP**: Uses `https://api.ipify.org` via curl
- **Internal IP**: Uses `ip addr show` command
- **Fallback**: Multiple command strategies for reliability
- **WAN Mode**: Configurable WAN-only mode support

## Configuration Status Display

- **Selective INI**: Username-based INI file selection
- **Button Authorization**: Username-based button access control
- **Favorites INI**: User-specific favorites configuration
- **Control Panel INI**: User-specific control panel settings
- **File Validation**: Checks for configuration file existence and validity

## Performance Considerations

- **Command Caching**: Commands resolved once during initialization
- **Error Suppression**: stderr redirected to `/dev/null` for clean output
- **Timeout Handling**: curl commands have connection and maximum time limits
- **Resource Management**: Proper cleanup and error handling for all operations
- **Minimal Dependencies**: Only essential system commands used

## Error Handling

- **Command Failures**: Return 'N/A' for failed command execution
- **Missing Commands**: Automatic fallback to alternative paths
- **Permission Errors**: Graceful handling of unreadable directories/files
- **Network Failures**: Timeout and error handling for external IP lookup
- **Script Validation**: Existence and permission checking for temperature script
