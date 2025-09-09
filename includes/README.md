# Supermon-ng Includes Directory

This directory contains the essential PHP library files that provide core functionality for the Supermon-ng application backend. After extensive modernization, only the most essential files remain.

## Core Include Files

### AMI & Communication

#### `amifunctions.inc`
**Purpose**: Asterisk Manager Interface (AMI) client
- `SimpleAmiClient`: Low-level AMI communication
- Connection management and authentication  
- Command execution and response parsing
- Error handling for AMI operations

#### `sse/server-functions.inc`
**Purpose**: Server-Sent Events functionality
- Real-time data streaming to Vue.js frontend
- Event handling and connection management

### Data & Configuration

#### `common.inc`
**Purpose**: Global constants and configuration variables
- Application version information
- File path constants
- Global configuration variables
- Legacy compatibility constants

#### `nodeinfo.inc`
**Purpose**: Node information and database handling
- AllStar node database processing
- Node lookup and information retrieval
- Database caching and optimization

### Security & Session Management

#### `session.inc`
**Purpose**: Session management and security
- Session initialization and configuration
- Session timeout handling (8 hours default)
- Secure session cookie settings

#### `csrf.inc`
**Purpose**: Cross-Site Request Forgery (CSRF) protection
- CSRF token generation and validation
- Form token embedding functions
- Request validation for state-changing operations

#### `helpers.inc`
**Purpose**: Utility classes for common operations
- `AMIHelper`: Standardized Asterisk Manager Interface operations
- `ValidationHelper`: Input validation and sanitization
- `SecurityHelper`: Authentication and authorization utilities
- `FileHelper`: Safe file operations with security checks

### Specialized Modules

#### `node-ban-allow/`
**Purpose**: Node banning and allowing functionality
- `ban-ami.inc`: AMI communication utilities
- `ban-display.inc`: Data display and list rendering
- Contains README.md with detailed documentation

## Modern Architecture

The includes directory has been streamlined as part of the Vue.js modernization:

- **Frontend**: All UI components migrated to Vue.js with TypeScript
- **Backend**: Clean REST API with minimal PHP dependencies
- **Legacy Removed**: 150+ legacy files eliminated during modernization
- **Essential Only**: Only files actively used by the modern API remain

## Usage in Modern API

These files are primarily used by:
- REST API controllers (`src/Application/Controllers/`)
- Background services (Node Status service)
- Legacy compatibility endpoints

## Dependencies

### Required PHP Extensions
- `openssl`: For secure token generation
- `json`: For configuration and data handling
- `session`: For session management

### External Dependencies
- AllStar Link node database (`astdb.txt`)
- Asterisk Manager Interface (AMI)
- **No jQuery** - Fully replaced by Vue.js frontend

## Migration Status

✅ **Completed**: Full migration to Vue.js frontend with TypeScript
✅ **Modernized**: REST API with clean separation of concerns  
✅ **Streamlined**: 65% reduction in backend complexity
✅ **Professional**: Modern development practices implemented

This represents the final, minimalist backend structure supporting the modern Vue.js frontend.
