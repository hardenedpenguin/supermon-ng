# WebSocket Migration Plan - Supermon-NG

## Executive Summary

This plan outlines the migration from HTTP polling to WebSockets for real-time node data updates. The migration will eliminate the current 1-second polling interval, reduce server load, and provide true real-time updates.

## Current Architecture

### Frontend Polling System
- **Polling Interval**: 1 second (active), 5 seconds (inactive), 10 seconds (background)
- **Endpoint**: `/api/v1/nodes/ami/status?nodes=<nodeIds>`
- **Services Used**:
  - `PollingService` - Adaptive polling with activity detection
  - `BatchRequestService` - Request batching and caching
  - `CsrfTokenService` - CSRF token management
- **Data Fetched**: Node status, COS/TX keyed, CPU stats, ALERT/WX/DISK, connected nodes

### Backend API
- **Controller**: `NodeController::getAmiStatus()`
- **AMI Client**: `SimpleAmiClient` with connection pooling
- **Data Source**: Asterisk Manager Interface (AMI)
- **Current Load**: ~1 request per second per active user per monitored node

## Target Architecture

### WebSocket Architecture Decision

After reviewing Allmon3's implementation, we have two architectural options:

#### Option 1: Single Unified WebSocket (Simpler)
- **One WebSocket server** on port 8105
- **Subscription-based**: Clients subscribe to specific nodes
- **Shared AMI connections**: Server maintains one AMI connection per node, shared across clients
- **Message routing**: Server routes updates to subscribed clients
- **Pros**: Simpler infrastructure, easier to manage, single port
- **Cons**: More complex message routing, potential bottleneck

#### Option 2: Per-Node WebSocket Servers (Allmon3 Style)
- **Multiple WebSocket servers**: One per node, each on different ports
- **Direct connections**: Each node has dedicated WebSocket server
- **Dedicated AMI connections**: Each WebSocket server maintains its own AMI connection
- **Port management**: Each node gets assigned ports (status, voter, command)
- **Pros**: Better isolation, simpler per-node logic, matches Allmon3
- **Cons**: More ports to manage, more systemd services, more complex deployment

### Recommended: Per-Node WebSocket Architecture (Allmon3 Style)

**Rationale**:
- Matches proven Allmon3 architecture
- Better isolation - if one node's WebSocket fails, others continue
- Simpler per-node logic - each server only handles one node
- Easier debugging - can identify issues per node
- More scalable - can distribute across servers if needed

### WebSocket Server Structure
- **Library**: Ratchet (already in composer.json)
- **Ports**: Dynamic per-node (e.g., 8105 + nodeId for status, 8205 + nodeId for voter)
- **Protocol**: WebSocket (ws://) with optional WSS (wss://) for HTTPS
- **Connection Model**: One WebSocket server per node, each maintaining its own AMI connection

### Data Flow (Per-Node Architecture)
1. Frontend queries API to get node's WebSocket port configuration
2. Frontend connects to WebSocket server for each monitored node
3. Each WebSocket server maintains persistent AMI connection for its node
4. Server polls AMI at configurable interval (e.g., 1 second)
5. Server broadcasts updates to all connected clients for that node
6. Client receives real-time updates without HTTP overhead

## Implementation Plan

### Phase 1: Backend WebSocket Server

#### 1.1 Create WebSocket Service (Per-Node)
**File**: `src/Services/NodeWebSocketService.php`
- Implement `MessageComponentInterface` from Ratchet
- Handle client connections (`onOpen`, `onClose`, `onError`)
- Maintain persistent AMI connection for single node
- Poll AMI data and broadcast to all connected clients
- Handle reconnection logic for AMI failures

**Key Features**:
- Single node focus (one service instance per node)
- AMI connection management for that node
- Configurable polling interval (default: 1 second)
- Automatic reconnection for AMI connections
- Error handling and logging
- Broadcast to all connected WebSocket clients

#### 1.2 Create WebSocket Server Manager (Single Process)
**File**: `src/Services/WebSocketServerManager.php`
- Manages multiple WebSocket server instances in a single process
- Uses ReactPHP event loop (Ratchet is built on ReactPHP)
- Starts all WebSocket servers concurrently for all configured nodes
- Port assignment and management
- Health monitoring of WebSocket servers
- Graceful shutdown handling

**Key Features**:
- Single process manages all WebSocket servers (like Allmon3's asyncio approach)
- Each node gets its own WebSocket server instance
- All servers run concurrently in the same event loop
- Port assignment: `basePort + offset` (incremental per server)

#### 1.3 Create WebSocket Server Entry Point
**File**: `bin/websocket-server.php`
- Single entry point that manages all WebSocket servers
- Reads node configuration from `allmon.ini`
- Creates WebSocket server for each configured node
- Sets up ReactPHP event loop
- Runs all servers concurrently
- Handles graceful shutdown (SIGTERM, SIGINT)
- Sets up periodic timers for AMI polling per node

**Architecture** (matching Allmon3):
```php
// Pseudo-code structure
$loop = React\EventLoop\Factory::create();
$tasks = [];

foreach ($nodes as $nodeId => $nodeConfig) {
    $port = $basePort + count($tasks);
    $wsServer = new NodeWebSocketService($nodeId, $nodeConfig, $port);
    $tasks[] = $wsServer->start($loop); // Returns a promise/task
}

// Run all tasks concurrently
$loop->run();
```

#### 1.4 Create Systemd Service (Single Service)
**File**: `systemd/supermon-ng-websocket.service`
- **Single systemd service** (like Allmon3's `allmon3.service`)
- Auto-start on boot
- Restart on failure
- Dependencies: network.target, asterisk.service
- User: www-data
- Working directory: /var/www/html/supermon-ng
- ExecStart: `/usr/bin/php /var/www/html/supermon-ng/bin/websocket-server.php`

**Note**: This matches Allmon3's architecture - one service, one process, multiple WebSocket servers managed concurrently

#### 1.4 Update Install Script
**File**: `install.sh`
- Add Apache ProxyPass configuration for WebSocket
- Copy and enable systemd service
- Start WebSocket service
- Add to update script as well

**Apache Configuration** (Dynamic Proxy):
```apache
# Proxy WebSocket connections - port determined by node ID
# Format: /supermon-ng/ws/{nodeId} -> ws://localhost:{basePort + nodeId}
ProxyPassMatch ^/supermon-ng/ws/(\d+)$ ws://localhost:81$1
ProxyPassReverse /supermon-ng/ws/ ws://localhost:8105
```

**Or use RewriteRule for dynamic port mapping**:
```apache
RewriteEngine On
RewriteCond %{HTTP:Upgrade} =websocket [NC]
RewriteRule ^/supermon-ng/ws/(\d+)$ ws://localhost:81$1 [P,L]
```

### Phase 2: Frontend WebSocket Client

#### 2.1 Create WebSocket Service (Per-Node Connections)
**File**: `frontend/src/services/WebSocketService.ts`
- Manages multiple WebSocket connections (one per node)
- Connection lifecycle management per node
- Automatic reconnection with exponential backoff
- Per-node connection state tracking
- Message handling and routing per node
- Connection pooling and management

**API**:
```typescript
interface WebSocketService {
  connectToNode(nodeId: string, port: number): Promise<void>
  disconnectFromNode(nodeId: string): void
  onNodeMessage(nodeId: string, handler: (data: any) => void): () => void
  isNodeConnected(nodeId: string): boolean
  getAllConnectedNodes(): string[]
}
```

**Alternative**: Single service managing multiple connections
- Maintains map of nodeId -> WebSocket connection
- Handles reconnection per node independently
- Routes messages based on node ID

#### 2.2 Update RealTime Store
**File**: `frontend/src/stores/realTime.ts`
- Replace polling logic with WebSocket subscriptions
- Remove `PollingService` dependency
- Remove `BatchRequestService` dependency (keep for initialization)
- Add WebSocket connection management
- Handle WebSocket messages and update node data
- Maintain backward compatibility during migration

**Changes**:
- Remove `startIntelligentPolling()` and `stopIntelligentPolling()`
- Remove `fetchNodeDataOptimized()` polling calls
- Add `connectToNodeWebSocket(nodeId)` and `disconnectFromNodeWebSocket(nodeId)`
- Update `startMonitoring()` to connect to node's WebSocket
- Update `stopMonitoring()` to disconnect from node's WebSocket
- Fetch node's WebSocket port from API before connecting
- Handle per-node WebSocket connections independently

#### 2.3 Update Components
**Files**: 
- `frontend/src/views/Dashboard.vue`
- `frontend/src/components/Voter.vue` (if it uses polling)

**Changes**:
- Remove polling-related UI indicators (if any)
- Add WebSocket connection status indicator
- Handle WebSocket disconnection gracefully

### Phase 3: Migration Strategy

#### 3.1 Backward Compatibility
- Keep `/api/v1/nodes/ami/status` endpoint for:
  - Mobile apps
  - Initial data load
  - Fallback when WebSocket unavailable
- Mark endpoint as `@deprecated` with migration notice

#### 3.2 Feature Flags
- Add configuration option to enable/disable WebSocket
- Allow gradual rollout
- Fallback to polling if WebSocket fails

#### 3.3 Testing Plan
1. **Unit Tests**:
   - WebSocket service connection handling
   - Subscription management
   - AMI data polling and broadcasting

2. **Integration Tests**:
   - End-to-end WebSocket connection
   - Multiple client subscriptions
   - AMI connection persistence

3. **Load Tests**:
   - Multiple concurrent WebSocket connections
   - High-frequency updates
   - AMI connection stability

4. **Browser Tests**:
   - Chrome, Firefox, Safari, Edge
   - Mobile browsers
   - Connection recovery after network issues

### Phase 4: Cleanup

#### 4.1 Remove Polling Code
After successful migration and testing period:
- Remove `PollingService.ts` (or keep for other uses)
- Remove polling logic from `realTime.ts`
- Remove `BatchRequestService` if not used elsewhere
- Clean up unused imports and dependencies

#### 4.2 Documentation
- Update README with WebSocket information
- Add WebSocket troubleshooting guide
- Document WebSocket API protocol

## Technical Details

### WebSocket Message Protocol (Per-Node)

Since each WebSocket server handles one node, the protocol is simpler:

#### Client → Server Messages
```json
{
  "type": "ping"
}
```

(No subscription needed - connection to node's WebSocket implies subscription)

#### Server → Client Messages
```json
{
  "546051": {
    "status": "online",
    "cos_keyed": 0,
    "tx_keyed": 0,
    "cpu_temp": "45.2",
    "cpu_up": "5d 12h",
    "cpu_load": "0.5",
    "ALERT": null,
    "WX": null,
    "DISK": null,
    "remote_nodes": [...]
  }
}
```

Or for voter data (separate WebSocket):
```html
<div class="voter-data">...</div>
```

**Note**: Since each WebSocket is node-specific, the node ID is implicit in the connection. Messages are simpler - just the data for that node.

### AMI Connection Management (Per-Node)

#### Persistent Connections
- Each WebSocket server maintains one AMI connection for its node
- Connection is dedicated to that WebSocket server instance
- Automatic reconnection on AMI failure
- Connection health monitoring (ping/pong)

#### Polling Strategy
- Each WebSocket server polls its node every 1 second
- Only broadcast if data changed (diff detection)
- Broadcast to all connected WebSocket clients for that node
- No batching needed (single node per server)

### Security Considerations

1. **Authentication**:
   - Validate user session on WebSocket connection (via HTTP upgrade)
   - Check node access permissions before allowing connection
   - Validate node ID matches accessible nodes

2. **Authorization**:
   - Only allow connections to nodes user has access to
   - Filter AMI data based on user permissions
   - Port-based access control (node ID in port)

3. **Input Validation**:
   - Validate node ID from connection path/port
   - Sanitize all inputs
   - Prevent connection spam

4. **Resource Limits**:
   - Limit concurrent WebSocket connections per node
   - Timeout idle connections
   - Rate limit connection attempts

### Performance Optimizations

1. **Data Diffing**:
   - Only send updates when data actually changes
   - Compare previous state before broadcasting

2. **Batching**:
   - Batch multiple node updates in single message
   - Reduce WebSocket message overhead

3. **Connection Pooling**:
   - Reuse AMI connections across clients
   - Limit total AMI connections

4. **Caching**:
   - Cache node configurations
   - Cache ASTDB lookups

## Implementation Checklist

### Backend
- [ ] Create `NodeWebSocketService.php` (per-node service class)
- [ ] Create `WebSocketServerManager.php` (manages all servers in single process)
- [ ] Create `bin/websocket-server.php` (single entry point, manages all nodes)
- [ ] Create `systemd/supermon-ng-websocket.service` (single service file)
- [ ] Create API endpoint to get node WebSocket port configuration
- [ ] Update `install.sh` with Apache config and service setup
- [ ] Update `scripts/update.sh` with service management
- [ ] Implement port assignment logic (incremental from base port)
- [ ] Add authentication/authorization per connection
- [ ] Add logging and monitoring
- [ ] Write unit tests
- [ ] Write integration tests

### Frontend
- [ ] Create `WebSocketService.ts` (manages multiple per-node connections)
- [ ] Update `realTime.ts` store to use per-node WebSockets
- [ ] Add API call to fetch node WebSocket port configuration
- [ ] Remove polling dependencies
- [ ] Add per-node WebSocket connection UI indicators
- [ ] Handle reconnection logic per node
- [ ] Update error handling per node
- [ ] Write unit tests
- [ ] Test in multiple browsers

### Infrastructure
- [ ] Update Apache configuration (dynamic port proxy)
- [ ] Create single systemd service file
- [ ] Test service startup/shutdown (all nodes in one process)
- [ ] Configure port range for WebSocket servers
- [ ] Configure firewall (if needed) for port range
- [ ] Set up monitoring/alerting (single process monitoring)

### Documentation
- [ ] Update README
- [ ] Add WebSocket API documentation
- [ ] Create troubleshooting guide
- [ ] Update installation instructions

### Testing
- [ ] Unit tests (backend)
- [ ] Unit tests (frontend)
- [ ] Integration tests
- [ ] Load testing
- [ ] Browser compatibility testing
- [ ] Mobile app compatibility (ensure polling still works)

## Rollout Plan

1. **Phase 1**: Implement backend WebSocket server (no frontend changes)
2. **Phase 2**: Implement frontend WebSocket client with feature flag
3. **Phase 3**: Enable WebSocket for internal testing
4. **Phase 4**: Gradual rollout to users (10% → 50% → 100%)
5. **Phase 5**: Monitor and optimize
6. **Phase 6**: Remove polling code after stable period

## Risk Mitigation

1. **WebSocket Connection Failures**:
   - Automatic reconnection with exponential backoff
   - Fallback to HTTP polling if WebSocket unavailable
   - Connection status indicators in UI

2. **AMI Connection Issues**:
   - Health monitoring and automatic reconnection
   - Error logging and alerting
   - Graceful degradation

3. **Performance Issues**:
   - Monitor WebSocket server resources
   - Limit connections and subscriptions
   - Optimize data diffing and batching

4. **Browser Compatibility**:
   - Test in all supported browsers
   - Provide fallback for unsupported browsers
   - Progressive enhancement approach

## Success Metrics

- **Reduced Server Load**: 80-90% reduction in HTTP requests
- **Improved Latency**: Real-time updates vs 1-second polling delay
- **Better User Experience**: Instant updates, no polling indicators
- **Scalability**: Support more concurrent users
- **Resource Efficiency**: Lower CPU and network usage

## Timeline Estimate

- **Phase 1 (Backend)**: 3-4 days (single process managing multiple servers)
- **Phase 2 (Frontend)**: 2-3 days
- **Phase 3 (Testing)**: 2-3 days
- **Phase 4 (Rollout)**: 1-2 weeks (gradual)
- **Phase 5 (Cleanup)**: 1-2 days

**Total**: ~3-4 weeks for full migration

**Note**: Single systemd service managing multiple WebSocket servers (one per node) in a single process, matching Allmon3's proven asyncio-based approach. Ratchet uses ReactPHP which provides similar async capabilities.

## Notes

- Ratchet is already in `composer.json` - no new dependencies needed
- **Architecture**: Single systemd service, single PHP process, multiple WebSocket servers (one per node) managed concurrently using ReactPHP event loop
- Port assignment: Base port (e.g., 8105) + incremental offset per server (not nodeId-based)
- Maintain backward compatibility with mobile apps
- Consider WebSocket over WSS (wss://) for production HTTPS sites
- Matches Allmon3's proven approach: one service, one process, multiple async WebSocket servers
- Each node gets dedicated WebSocket server for better isolation
- Port management: Need API endpoint to provide node's WebSocket port configuration
- ReactPHP event loop allows concurrent WebSocket servers (similar to Python's asyncio)

