# AMI Functions Upgrade Plan - Match Allmon3 Implementation

## Executive Summary

This plan outlines the upgrade of `amifunctions.inc` to match Allmon3's AMI implementation patterns for consistency, better error handling, and improved compatibility.

## Current State Analysis

### Our Current Implementation (`SimpleAmiClient`)
- ✅ Connection pooling
- ✅ ActionID tracking
- ✅ Timeout management
- ✅ Error handling
- Uses "Action: Login" with "Username:" and "Secret:"
- Uses random bytes for ActionID
- Has `action()` method for generic AMI actions
- Response parsing via `getResponse()` with ActionID matching

### Allmon3's Implementation (`AMI` class)
- ✅ Async/await (Python asyncio)
- ✅ Generic `asl_cmd_response()` method
- ✅ UUID for ActionID
- ✅ Validates "Asterisk Call Manager" greeting
- Uses "ACTION: LOGIN" with "USERNAME:" and "SECRET:"
- Reads until `\r\n\r\n` (double newline)
- Custom `AMIException` for error handling
- Specific parsing methods: `parse_xstat()`, `parse_saw_stat()`, `parse_voter_data()`

## Key Differences to Address

### 1. AMI Protocol Case Sensitivity
**Allmon3**: Uses uppercase "ACTION:", "USERNAME:", "SECRET:"
**Ours**: Uses mixed case "Action:", "Username:", "Secret:"

**Impact**: Both work, but Allmon3's uppercase is more consistent with AMI protocol standards

### 2. ActionID Generation
**Allmon3**: Uses UUID (`uuid.uuid4()`)
**Ours**: Uses random bytes (`bin2hex(random_bytes(8))`)

**Impact**: UUID is more standard and provides better uniqueness guarantees

### 3. Response Reading Strategy
**Allmon3**: Reads until `\r\n\r\n` (double newline) - simpler, more reliable
**Ours**: Reads until ActionID match found - more complex, can miss responses

**Impact**: Allmon3's approach is simpler and more reliable for AMI protocol

### 4. Error Handling
**Allmon3**: Custom `AMIException` with specific error types
**Ours**: Returns false on errors, logs operations

**Impact**: Better error handling with exceptions allows for more granular error recovery

### 5. Generic Command Method
**Allmon3**: `asl_cmd_response(cmd)` - generic method for any AMI action
**Ours**: Separate methods (`command()`, `action()`) - more specialized

**Impact**: Allmon3's generic approach is more flexible

### 6. Greeting Validation
**Allmon3**: Explicitly checks for "Asterisk Call Manager" in greeting
**Ours**: Checks for "Asterisk Call Manager" but less strict

**Impact**: Allmon3's validation is more explicit and fails fast

## Upgrade Plan

### Phase 1: Protocol Consistency

#### 1.1 Standardize AMI Protocol Headers
- Change "Action:" to "ACTION:" (uppercase)
- Change "Username:" to "USERNAME:" (uppercase)
- Change "Secret:" to "SECRET:" (uppercase)
- Change "Command:" to "COMMAND:" (uppercase)
- Change "ActionID:" to "ActionID:" (keep as is - AMI uses this case)

**Files to Update**:
- `includes/amifunctions.inc` - `login()`, `command()`, `action()` methods

#### 1.2 Improve Greeting Validation
- Make greeting validation more explicit (like Allmon3)
- Return false immediately if greeting doesn't match
- Add better error logging

**Files to Update**:
- `includes/amifunctions.inc` - `connect()` method

### Phase 2: ActionID and Response Handling

#### 2.1 Switch to UUID for ActionID
- Replace `bin2hex(random_bytes(8))` with UUID generation
- Use `ramsey/uuid` (already in composer.json) or PHP's `uniqid()` with more entropy
- Ensure ActionID is unique and traceable

**Files to Update**:
- `includes/amifunctions.inc` - All methods that generate ActionID

**Note**: PHP doesn't have built-in UUID, but we can use:
- `uniqid('', true)` with microtime (good enough)
- Or add `ramsey/uuid` if not already present

#### 2.2 Improve Response Reading
- Add alternative response reading method that reads until `\r\n\r\n`
- Keep ActionID matching as fallback
- Make response reading more robust

**Files to Update**:
- `includes/amifunctions.inc` - `getResponse()` method

### Phase 3: Error Handling Enhancement

#### 3.1 Add AMI Exception Class
- Create `AMIException` class (similar to Allmon3's `AMIException`)
- Use exceptions for connection errors, authentication failures, command errors
- Maintain backward compatibility with false returns where needed

**Files to Create/Update**:
- `includes/amifunctions.inc` - Add `AMIException` class
- Update methods to throw exceptions for critical errors

#### 3.2 Enhanced Error Types
- Connection errors (timeout, refused, etc.)
- Authentication errors (invalid credentials)
- Command errors (invalid command, AMI error response)
- Protocol errors (malformed response, unexpected format)

### Phase 4: Generic Command Method (Allmon3 Style)

#### 4.1 Enhance `action()` Method
- Make it the primary generic method (like Allmon3's `asl_cmd_response()`)
- Support any AMI action with parameters
- Better response parsing that preserves all data
- Handle both ActionID-based and `\r\n\r\n`-based responses

**Files to Update**:
- `includes/amifunctions.inc` - Enhance `action()` method
- Update `command()` to use `action()` internally

#### 4.2 Response Parsing Improvements
- Preserve all response data (not just "Output:" lines)
- Better handling of multi-line responses
- Support for different response formats (Success, Error, Follows)

### Phase 5: Parsing Methods (Allmon3 Style)

#### 5.1 Add XStat Parser
- Create `parseXStat()` method similar to Allmon3's `parse_xstat()`
- Parse connected nodes, IPs, directions, connection times, states
- Handle LinkedNodes for mode detection
- Extract TX/RX keyed status from Var: lines

**Files to Create/Update**:
- `includes/amifunctions.inc` - Add `parseXStat()` method
- Or create separate `AmiParser` class

#### 5.2 Add SawStat Parser
- Create `parseSawStat()` method similar to Allmon3's `parse_saw_stat()`
- Parse connection keyed status (CONNKEYED, CONNKEYEDNODE)
- Extract PTT, SSK (seconds since key), SSU (seconds since unkey)

**Files to Create/Update**:
- `includes/amifunctions.inc` - Add `parseSawStat()` method

#### 5.3 Enhance VoterStatus Parser
- Improve existing voter parsing to match Allmon3's format
- Parse Client, RSSI, Voted lines
- Generate HTML output similar to Allmon3

**Files to Update**:
- `includes/amifunctions.inc` - Enhance voter parsing
- Or update `NodeController::getVoterStatus()`

### Phase 6: Connection Management

#### 6.1 Persistent Connection Support
- Add methods to check connection health (ping/pong)
- Better connection validation
- Automatic reconnection logic (for WebSocket use)

**Files to Update**:
- `includes/amifunctions.inc` - Add connection health methods

#### 6.2 Connection State Tracking
- Track connection state (connected, authenticated, idle)
- Better connection lifecycle management
- Support for persistent connections in WebSocket context

## Implementation Details

### AMI Protocol Standardization

```php
// Before
$loginCmd = "Action: Login" . self::AMI_EOL;
$loginCmd .= "Username: " . $user . self::AMI_EOL;
$loginCmd .= "Secret: " . $password . self::AMI_EOL;

// After (Allmon3 style)
$loginCmd = "ACTION: LOGIN" . self::AMI_EOL;
$loginCmd .= "USERNAME: " . $user . self::AMI_EOL;
$loginCmd .= "SECRET: " . $password . self::AMI_EOL;
```

### UUID for ActionID

```php
// Before
$actionID = 'login_' . bin2hex(random_bytes(8));

// After (using uniqid with entropy)
$actionID = 'login_' . uniqid('', true) . '_' . mt_rand(1000, 9999);

// Or if ramsey/uuid is available
use Ramsey\Uuid\Uuid;
$actionID = Uuid::uuid4()->toString();
```

### Generic Command Method Enhancement

```php
/**
 * Generic AMI action method (Allmon3 style)
 * 
 * @param resource $fp AMI connection
 * @param string $action AMI action name (e.g., "RptStatus", "VoterStatus")
 * @param array $params Action parameters
 * @param int|null $timeout Optional timeout
 * @return string|false Response or false on failure
 */
public static function action($fp, $action, $params = [], $timeout = null)
{
    $actionID = self::generateActionID();
    
    $cmd = "ACTION: " . strtoupper($action) . self::AMI_EOL;
    foreach ($params as $key => $value) {
        $cmd .= strtoupper($key) . ": " . $value . self::AMI_EOL;
    }
    $cmd .= "ActionID: " . $actionID . self::AMI_EOL . self::AMI_EOL;
    
    if (fwrite($fp, $cmd) === false) {
        return false;
    }
    
    return self::getResponse($fp, $actionID);
}
```

### Response Reading Enhancement

```php
/**
 * Get AMI response - reads until \r\n\r\n (Allmon3 style)
 * Falls back to ActionID matching if needed
 */
public static function getResponse($fp, $actionID = null)
{
    $response = '';
    $foundActionID = ($actionID === null);
    
    stream_set_timeout($fp, self::VERY_LONG_TIMEOUT_SECONDS);
    
    while (true) {
        $line = fgets($fp, 4096);
        $metadata = stream_get_meta_data($fp);
        
        if ($line === false || $metadata['timed_out']) {
            return false;
        }
        
        $response .= $line;
        
        // Check for ActionID match if specified
        if ($actionID !== null && !$foundActionID) {
            if (stripos($line, "ActionID: " . $actionID) !== false) {
                $foundActionID = true;
            }
        }
        
        // Check for end of response (\r\n\r\n)
        if (substr($response, -4) === "\r\n\r\n") {
            if ($actionID === null || $foundActionID) {
                return $response;
            }
            // Reset if ActionID doesn't match
            $response = '';
        }
    }
}
```

### Exception Handling

```php
class AMIException extends Exception
{
    public const CONNECTION_FAILED = 1;
    public const AUTHENTICATION_FAILED = 2;
    public const COMMAND_FAILED = 3;
    public const PROTOCOL_ERROR = 4;
    public const TIMEOUT = 5;
    
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

## Migration Strategy

### Step 1: Add New Methods (Non-Breaking)
- Add new methods alongside existing ones
- Keep old methods working for backward compatibility
- Mark old methods as `@deprecated`

### Step 2: Update Internal Usage
- Update `NodeController` to use new methods
- Update other controllers gradually
- Test thoroughly

### Step 3: Remove Deprecated Methods
- After all code is migrated, remove deprecated methods
- Update documentation

## Testing Plan

1. **Unit Tests**:
   - Test connection with various AMI versions
   - Test authentication with valid/invalid credentials
   - Test command execution
   - Test response parsing for XStat, SawStat, VoterStatus

2. **Integration Tests**:
   - Test with real Asterisk/AllStar nodes
   - Test connection pooling
   - Test error recovery

3. **Compatibility Tests**:
   - Ensure existing code still works
   - Test with different AMI protocol versions
   - Test edge cases (timeouts, connection failures)

## Benefits

1. **Consistency**: Matches Allmon3's proven implementation
2. **Reliability**: Better error handling and response parsing
3. **Maintainability**: Cleaner code structure
4. **Compatibility**: Better AMI protocol compliance
5. **Debugging**: Better error messages and logging

## Risks and Mitigation

1. **Breaking Changes**: 
   - Mitigation: Keep old methods, mark as deprecated, migrate gradually

2. **Protocol Differences**:
   - Mitigation: Test with multiple AMI versions, maintain backward compatibility

3. **Performance Impact**:
   - Mitigation: Benchmark before/after, optimize if needed

## Implementation Checklist

- [ ] Update AMI protocol headers to uppercase
- [ ] Improve greeting validation
- [ ] Switch ActionID to UUID/uniqid
- [ ] Enhance response reading (support \r\n\r\n)
- [ ] Add AMIException class
- [ ] Enhance error handling
- [ ] Improve `action()` method (make it primary)
- [ ] Add `parseXStat()` method
- [ ] Add `parseSawStat()` method
- [ ] Enhance voter parsing
- [ ] Add connection health methods
- [ ] Update `NodeController` to use new methods
- [ ] Write unit tests
- [ ] Write integration tests
- [ ] Update documentation
- [ ] Mark old methods as deprecated

## Timeline

- **Phase 1-2**: 1-2 days (Protocol consistency, ActionID, response handling)
- **Phase 3**: 1 day (Error handling)
- **Phase 4**: 1 day (Generic command method)
- **Phase 5**: 2-3 days (Parsing methods)
- **Phase 6**: 1 day (Connection management)

**Total**: ~1 week for full upgrade

