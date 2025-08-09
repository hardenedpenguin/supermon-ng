# Server-Sent Events (SSE) Modules

This directory contains modular components for `server.php` - the real-time Server-Sent Events endpoint that provides live node monitoring data.

## Files

### `server-functions.inc`
Core helper functions for server operations:
- `isConnectionHealthy()` - Check AMI socket health
- `getNode()` - Fetch node data via AMI (XStat/SawStat)
- `sortNodes()` - Sort connected nodes by last keyed time
- `parseNode()` - Parse and format AMI response data

### `server-config.inc`
Initialization and configuration management:
- `initializeServerEnvironment()` - Set headers and PHP settings
- `validateNodesParameter()` - Parse and validate nodes parameter
- `loadAstDatabase()` - Load ASTDB file with file locking
- `loadServerConfiguration()` - Load and parse INI configuration
- `validateNodes()` - Validate nodes against configuration
- `initializeServer()` - Master initialization function

### `server-ami.inc`
Asterisk Manager Interface connection management:
- `establishAmiConnections()` - Connect and login to AMI for all nodes
- `cleanupAmiConnections()` - Properly close all AMI connections

### `server-monitor.inc`
Main monitoring loop logic:
- `processNodeIteration()` - Process all nodes for one loop iteration
- `sendSSEData()` - Send node data via Server-Sent Events
- `sendPeriodicTimingUpdate()` - Send timing updates to client
- `calculateLoopTiming()` - Dynamic loop timing calculation
- `runMonitoringLoop()` - Master monitoring loop function

## Usage

These modules are designed to be included by `server.php`:

```php
include('includes/sse/server-functions.inc');
include('includes/sse/server-config.inc');
include('includes/sse/server-ami.inc');
include('includes/sse/server-monitor.inc');

// Initialize server and get configuration
list($nodes, $config, $astdb) = initializeServer();

// Establish AMI connections
list($fp, $servers) = establishAmiConnections($nodes, $config);

// Run main monitoring loop
runMonitoringLoop($fp, $servers, $nodes, $config);

// Cleanup AMI connections
cleanupAmiConnections($fp, $servers);
```

## Benefits

- **Modular Organization**: Logical separation of SSE-related functionality
- **Reusable Components**: Functions can be used by other real-time monitoring scripts
- **Easier Maintenance**: Changes isolated to specific functional areas
- **Better Testing**: Individual modules can be tested independently
- **Cleaner includes/**: Server-specific code organized in dedicated subdirectory
