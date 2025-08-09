# Voter Server Modules

This directory contains the modularized components for the Voter Server functionality (`voterserver.php`).

## Module Structure

### `voter-config.inc`
**Purpose**: Configuration, initialization, and AMI connection setup
- SSE header configuration for real-time streaming
- Session management and closure for long-running connections
- Node parameter validation and sanitization
- Configuration file loading (user-specific or default)
- AllStar database loading and caching
- AMI connection establishment and authentication

**Key Functions**:
- `initializeVoterServer()` - Main initialization function
- `cleanupVoterAMI($fp)` - Connection cleanup

### `voter-sse.inc`
**Purpose**: Server-Sent Events streaming management
- Main streaming loop for real-time voter data
- Connection monitoring and error handling
- Spinner animation management
- JSON payload formatting and transmission

**Key Functions**:
- `runVoterStreamingLoop($node, $nodeConfig, $fp)` - Main SSE streaming loop

### `voter-parser.inc`
**Purpose**: AMI response parsing and data processing
- Raw VoterStatus response parsing
- Client name cleaning and normalization
- Mix station detection and flagging
- Structured data array generation

**Key Functions**:
- `parse_voter_response($response)` - Parse raw AMI response into structured data

### `voter-html.inc`
**Purpose**: HTML table generation for voter client display
- Dynamic HTML table construction
- RSSI bar visualization with color coding
- Node information display with optional URLs
- Client status indication (voted, mix, normal)

**Key Functions**:
- `format_node_html($nodeNum, $nodesData, $votedData, $currentConfig)` - Generate HTML table

### `voter-status.inc`
**Purpose**: AMI communication for voter status requests
- VoterStatus AMI command construction
- Response correlation using ActionID
- Error handling for connection failures

**Key Functions**:
- `get_voter_status($fp, $actionID)` - Send VoterStatus command and get response

## Usage Pattern

The modularized `voterserver.php` follows this pattern:

1. **Include modules**:
   ```php
   include("includes/voterserver/voter-config.inc");
   include("includes/voterserver/voter-sse.inc");
   include("includes/voterserver/voter-parser.inc");
   include("includes/voterserver/voter-html.inc");
   include("includes/voterserver/voter-status.inc");
   ```

2. **Initialize**:
   ```php
   list($node, $nodeConfig, $astdb, $fp) = initializeVoterServer();
   ```

3. **Run streaming loop**:
   ```php
   runVoterStreamingLoop($node, $nodeConfig, $fp);
   ```

4. **Cleanup**:
   ```php
   cleanupVoterAMI($fp);
   ```

## Dependencies

- `includes/session.inc` - Session management
- `includes/amifunctions.inc` - AMI client functions (`SimpleAmiClient`)
- `includes/common.inc` - Global variables and constants
- `includes/nodeinfo.inc` - Node information functions (`getAstInfo`)
- `authini.php` - Configuration file mapping (`get_ini_name`)
- `user_files/global.inc` - User configuration variables

## Global Variables Used

- `$ASTDB_TXT` - Path to AllStar database file
- `$USERFILES` - Path to user configuration files directory
- `$fp` - AMI connection resource (global for HTML formatting)
- `$astdb` - Cached AllStar database array (global for HTML formatting)

## AMI Commands Used

- `VoterStatus` - Retrieve voter client status and RSSI information

## Data Flow

1. **Initialization** → Load configuration, establish AMI connection
2. **Database Loading** → Cache AllStar database for node information
3. **Streaming Loop** → Continuous SSE data transmission:
   - Send VoterStatus AMI command
   - Parse response into structured data
   - Generate HTML table representation
   - Transmit JSON payload via SSE
   - Update spinner animation
   - Sleep and repeat

## Client Status Color Coding

- **Blue (#0099FF)** - Normal client with white text
- **Green Yellow** - Currently voted client with black text
- **Cyan** - Mix station client with black text

## RSSI Bar Visualization

- **Width**: Calculated as `(rssi / 255) * 300` pixels
- **Minimum**: 1 pixel for non-zero RSSI, 3 pixels for zero RSSI
- **Colors**: Determined by client voting status and type

## Security Considerations

- Node parameter validation and sanitization
- Configuration file access based on user session
- AMI credential validation before connecting
- HTML output properly escaped with `htmlspecialchars()`
- AMI connections properly cleaned up on exit
- Session management for SSE connections

## Performance Considerations

- Database caching to avoid repeated file reads
- Efficient JSON encoding for SSE payloads
- Proper buffer flushing for real-time updates
- Connection abort detection to prevent resource leaks
- Memory management for long-running SSE connections
