# AllStar Statistics Modules

This directory contains the modularized components for the AllStar Statistics functionality (`stats.php`).

## Module Structure

### `stats-config.inc`
**Purpose**: Configuration, authentication, and initialization
- User authentication checks (ASTATUSER permission)
- Configuration file loading (`get_ini_name`)
- Node parameter validation
- AMI connection establishment
- Error handling for connection failures

**Key Functions**:
- `initializeStatsPage()` - Main initialization function
- `cleanupStatsAMI($fp)` - Connection cleanup

### `stats-ui.inc`
**Purpose**: HTML rendering and page structure
- HTML head with CSS includes
- Custom styling for black background theme
- Content rendering coordination
- HTML footer

**Key Functions**:
- `renderStatsHead()` - HTML head section with CSS
- `renderStatsContent($fp)` - Main content rendering coordinator
- `renderStatsFooter()` - HTML footer

### `stats-utils.inc`
**Purpose**: Utility functions for data processing
- AMI output cleaning and formatting
- Page header generation with hostname/date

**Key Functions**:
- `clean_ami_output_for_display($raw_output)` - Strip 'Output: ' prefixes
- `page_header()` - Display formatted page header

### `stats-allstar.inc`
**Purpose**: AllStar node status and peer information
- Local node discovery and connection display
- Node connection statistics (xnode, lstats)
- IAX2 peer listing and filtering

**Key Functions**:
- `show_all_nodes($fp)` - Display all local nodes and their connections
- `show_peers($fp)` - Display IAX2 peers (filtered)

### `stats-channels.inc`
**Purpose**: IAX2 channel and network statistics
- Active channel monitoring
- Network performance statistics

**Key Functions**:
- `show_channels($fp)` - Display IAX2 channels
- `show_netstats($fp)` - Display IAX2 network statistics

## Usage Pattern

The modularized `stats.php` follows this pattern:

1. **Include modules**:
   ```php
   include("includes/stats/stats-config.inc");
   include("includes/stats/stats-ui.inc");
   include("includes/stats/stats-utils.inc");
   include("includes/stats/stats-allstar.inc");
   include("includes/stats/stats-channels.inc");
   ```

2. **Initialize**:
   ```php
   list($node, $config, $fp) = initializeStatsPage();
   ```

3. **Render UI**:
   ```php
   renderStatsHead();
   renderStatsContent($fp);
   renderStatsFooter();
   ```

4. **Cleanup**:
   ```php
   cleanupStatsAMI($fp);
   ```

## Dependencies

- `includes/session.inc` - Session management
- `includes/amifunctions.inc` - AMI client functions (`SimpleAmiClient`)
- `includes/common.inc` - Global variables and constants
- `authusers.php` - User authentication (`get_user_auth`)
- `authini.php` - Configuration file mapping (`get_ini_name`)

## Global Variables Used

- `$HOSTNAME`, `$AWK`, `$DATE` - System command paths for header
- `$TAIL`, `$HEAD`, `$GREP`, `$SED`, `$EGREP` - Command line tools for data processing

## AMI Commands Used

- `rpt localnodes` - List all local AllStar nodes
- `rpt xnode <node>` - Get node connection information
- `rpt lstats <node>` - Get node link statistics
- `iax2 show channels` - Display active IAX2 channels
- `iax2 show netstats` - Display IAX2 network statistics
- `iax2 show peers` - Display IAX2 peer connections

## Security Considerations

- Requires ASTATUSER permission for access
- Node parameter validation (must exist in user's config)
- AMI credential validation before connecting
- Shell command output properly escaped with `htmlspecialchars()`
- AMI connections properly cleaned up on exit

## Data Flow

1. **Authentication** → Verify user has ASTATUSER permission
2. **Configuration** → Load user's INI file and validate node
3. **AMI Connection** → Connect to specified node's Asterisk Manager
4. **Data Collection** → Execute AMI commands to gather statistics
5. **Display** → Format and display results with proper HTML escaping
6. **Cleanup** → Close AMI connection
