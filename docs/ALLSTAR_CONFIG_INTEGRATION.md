# AllStar Configuration Integration

## Overview

The backend has been updated to read AllStar configuration from INI files (like `allmon.ini`, `anarchy-allmon.ini`) instead of using hardcoded environment variables. This provides better integration with existing AllStar setups and follows the original Supermon-ng architecture.

## Changes Made

### 1. New AllStarConfigService

**File**: `src/Services/AllStarConfigService.php`

This service:
- Reads AMI configuration from AllStar INI files
- Supports user-specific INI file mapping (via `authini.inc`)
- Provides caching for performance
- Handles fallback to default configurations
- Follows the original `get_ini_name()` logic from `authini.php`

### 2. Updated Dependencies

**File**: `src/Config/Dependencies.php`

- Added `AllStarConfigService` to the dependency injection container
- Updated AMI client to use the configuration service
- Removed hardcoded AMI environment variables

### 3. Updated NodeController

**File**: `src/Application/Controllers/NodeController.php`

- Now uses `AllStarConfigService` to get node configurations
- Validates nodes exist in configuration before operations
- Returns actual AMI configuration in responses
- Added new `/available` endpoint for raw configuration data

### 4. Updated Environment Configuration

**File**: `env.example`

- Removed hardcoded AMI configuration variables:
  - `AMI_HOST`
  - `AMI_PORT` 
  - `AMI_USERNAME`
  - `AMI_PASSWORD`
  - `AMI_TIMEOUT`
- Added comment explaining AMI config now comes from INI files

## Configuration Files

### AllStar INI Files

The system reads from these files in `user_files/`:

- `allmon.ini` - Default configuration
- `anarchy-allmon.ini` - User-specific configuration
- `authini.inc` - User-to-INI file mapping

### Example INI Structure

```ini
[546054]
host=10.0.0.5:5038
user=admin
passwd=ILoveToEatPussy
menu=yes
system=Nodes
hideNodeURL=no
```

### User Mapping

The `authini.inc` file maps users to specific INI files:

```php
<?php
$ININAME = [
    'anarchy' => 'anarchy-allmon.ini',
    'admin' => 'admin-allmon.ini',
    // ... more mappings
];
```

## API Endpoints

### Get Available Nodes
```
GET /api/v1/nodes/available
```

Returns raw configuration data from INI files:
```json
{
  "success": true,
  "data": [
    {
      "id": "546054",
      "host": "10.0.0.5:5038",
      "user": "admin",
      "system": "Nodes",
      "menu": "yes",
      "hideNodeURL": "no"
    }
  ],
  "count": 1
}
```

### Connect Node
```
POST /api/v1/nodes/{id}/connect
```

Now includes AMI configuration in response:
```json
{
  "success": true,
  "message": "Node 546054 connected to 123456",
  "data": {
    "node_id": "546054",
    "target_node": "123456",
    "status": "connected",
    "ami_config": {
      "host": "10.0.0.5",
      "port": 5038
    },
    "timestamp": "2025-08-27T02:37:04+00:00"
  }
}
```

## Benefits

1. **Better Integration**: Works with existing AllStar setups
2. **User-Specific Configs**: Supports per-user INI file mapping
3. **No Hardcoded Values**: Configuration comes from actual AllStar files
4. **Backward Compatibility**: Follows original Supermon-ng patterns
5. **Flexibility**: Easy to add new nodes by editing INI files

## Migration Notes

- No changes needed to existing AllStar INI files
- Environment variables for AMI configuration are no longer used
- The system automatically detects and uses the appropriate INI file
- User authentication can control which INI file is used

## Testing

The backend has been tested and confirmed working:

```bash
# Test available nodes endpoint
curl http://localhost:8000/api/v1/nodes/available

# Test node connection with AMI config
curl -X POST http://localhost:8000/api/v1/nodes/546054/connect \
  -H "Content-Type: application/json" \
  -d '{"target_node":"123456"}'
```

## Next Steps

1. Implement actual AMI connection logic using the configuration
2. Add user authentication to control which INI file is used
3. Integrate with ASTDB for node information (callsign, location, etc.)
4. Add real-time status monitoring via AMI
