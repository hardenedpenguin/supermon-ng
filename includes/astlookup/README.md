# AllStar Lookup Modules

This directory contains the modularized components for the AllStar Lookup functionality (`astlookup.php`).

## Module Structure

### `lookup-config.inc`
**Purpose**: Configuration, validation, and initialization
- Parameter validation and sanitization
- Configuration file loading
- AllStar database loading
- AMI connection establishment
- Authentication checks

**Key Functions**:
- `initializeLookupPage()` - Main initialization function
- `sendCmdToAMI($fp, $cmd)` - AMI command helper
- `getDataFromAMI($fp, $cmd)` - AMI data retrieval helper
- `cleanupLookupAMI($fp)` - Connection cleanup

### `lookup-ui.inc`
**Purpose**: HTML rendering and user interface
- HTML head and CSS includes
- Form rendering
- Results processing and display coordination

**Key Functions**:
- `renderLookupHead($localnode)` - HTML head section
- `renderLookupForm($lookupNode, $localnode, $perm)` - Main lookup form
- `processLookupResults($fp, $localnode, $perm)` - Results coordination
- `renderLookupFooter()` - HTML footer

### `lookup-allstar.inc`
**Purpose**: AllStar database lookups
- AllStar node and callsign searches
- Result processing and display

**Key Functions**:
- `do_allstar_callsign_search($fp, $lookup, $localnode)` - Search by callsign
- `do_allstar_number_search($fp, $lookup, $localnode)` - Search by node number
- `process_allstar_result($fp, $res, $localnode)` - Process and display results

### `lookup-echolink.inc`
**Purpose**: EchoLink database lookups
- EchoLink node and callsign searches via AMI
- Process management for command execution

**Key Functions**:
- `do_echolink_callsign_search($fp, $lookup)` - Search by callsign
- `do_echolink_number_search($fp, $echonode)` - Search by node number
- `process_echolink_result($res)` - Process and display results

### `lookup-irlp.inc`
**Purpose**: IRLP database lookups
- IRLP node and callsign searches
- Local file-based lookups

**Key Functions**:
- `do_irlp_callsign_search($lookup)` - Search by callsign
- `do_irlp_number_search($irlpnode)` - Search by node number
- `process_irlp_result($res)` - Process and display results

## Usage Pattern

The modularized `astlookup.php` follows this pattern:

1. **Include modules**:
   ```php
   include("includes/astlookup/lookup-config.inc");
   include("includes/astlookup/lookup-ui.inc");
   include("includes/astlookup/lookup-allstar.inc");
   include("includes/astlookup/lookup-echolink.inc");
   include("includes/astlookup/lookup-irlp.inc");
   ```

2. **Initialize**:
   ```php
   list($lookupNode, $localnode, $perm, $config, $astdb, $fp) = initializeLookupPage();
   ```

3. **Render UI**:
   ```php
   renderLookupHead($localnode);
   renderLookupForm($lookupNode, $localnode, $perm);
   processLookupResults($fp, $localnode, $perm);
   renderLookupFooter();
   ```

4. **Cleanup**:
   ```php
   cleanupLookupAMI($fp);
   ```

## Dependencies

- `includes/session.inc` - Session management
- `includes/amifunctions.inc` - AMI client functions
- `includes/common.inc` - Global variables and constants
- `authusers.php` - User authentication
- `authini.php` - Configuration file mapping
- `includes/csrf.inc` - CSRF protection

## Global Variables Used

- `$ASTDB_TXT` - Path to AllStar database file
- `$CAT`, `$AWK`, `$GREP`, `$SED`, `$HEAD` - System command paths
- `$DNSQUERY` - DNS query command path
- `$MBUFFER` - Buffer utility path
- `$IRLP_CALLS`, `$IRLP`, `$ZCAT` - IRLP-related paths and settings

## Security Considerations

- All user input is validated and sanitized
- CSRF protection for POST requests
- Authentication required with ASTLKUSER permission
- AMI connections are properly managed and cleaned up
- Shell command execution uses system-defined command paths
