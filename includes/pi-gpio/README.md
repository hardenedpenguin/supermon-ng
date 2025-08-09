# Pi GPIO Control Modules

This directory contains the modularized components for the Pi GPIO Control functionality (`pi-gpio.php`).

## Module Structure

### `gpio-config.inc`
**Purpose**: Configuration and authentication
- User authentication and authorization checking (GPIOUSER permission)
- Session validation and security checks
- Dependency inclusion and setup

**Key Functions**:
- `initializeGPIOConfig()` - Main initialization and security validation

### `gpio-commands.inc`
**Purpose**: Command execution and validation utilities
- Safe command execution with proper shell escaping
- GPIO pin validation (range 0-40)
- GPIO state validation (input/output/up/down/0/1)
- Error handling for command execution

**Key Functions**:
- `safe_exec($command, $args)` - Execute system commands safely
- `validate_gpio_pin($pin)` - Validate GPIO pin number
- `validate_gpio_state($state)` - Validate GPIO state/operation

### `gpio-processor.inc`
**Purpose**: Form processing and GPIO operations
- POST form data validation and processing
- GPIO command execution based on user input
- Input sanitization and parameter escaping
- GPIO mode and write operations

**Key Functions**:
- `processGPIOForm()` - Process form submission and execute GPIO operations

### `gpio-ui.inc`
**Purpose**: HTML template and form rendering
- HTML head section with CSS includes
- Page structure and title rendering
- GPIO control form with pin and state inputs
- Consistent styling and layout

**Key Functions**:
- `renderGPIOHead()` - Render HTML head section
- `renderGPIOBodyStart()` - Start page body with title
- `renderGPIOForm()` - Render the GPIO control form
- `renderGPIOFooter()` - Render page footer

### `gpio-status.inc`
**Purpose**: GPIO status display and monitoring
- GPIO status reading via `gpio readall` command
- Status data parsing and table formatting
- Error handling for status reading failures
- Integration with table rendering system

**Key Functions**:
- `displayGPIOStatus()` - Read and display current GPIO status

## Usage Pattern

The modularized `pi-gpio.php` follows this pattern:

1. **Include modules**:
   ```php
   include("includes/pi-gpio/gpio-config.inc");
   include("includes/pi-gpio/gpio-commands.inc");
   include("includes/pi-gpio/gpio-processor.inc");
   include("includes/pi-gpio/gpio-ui.inc");
   include("includes/pi-gpio/gpio-status.inc");
   ```

2. **Initialize**:
   ```php
   initializeGPIOConfig();
   ```

3. **Process form if submitted**:
   ```php
   processGPIOForm();
   ```

4. **Render page**:
   ```php
   renderGPIOHead();
   renderGPIOBodyStart();
   renderGPIOForm();
   displayGPIOStatus();
   renderGPIOFooter();
   ```

## Dependencies

- `includes/security.inc` - Security utilities
- `includes/session.inc` - Session management
- `user_files/global.inc` - User configuration variables
- `includes/common.inc` - Global variables and constants
- `authusers.php` - User authorization functions (`get_user_auth`)
- `authini.php` - Configuration file utilities
- `includes/table.inc` - Table rendering for GPIO status display

## Global Variables Used

- `$_SESSION['sm61loggedin']` - Login status flag
- `$_SESSION['user']` - Current logged-in username (indirectly via get_user_auth)
- `$_POST['Bit']` - GPIO pin number from form
- `$_POST['State']` - GPIO state/operation from form

## GPIO Commands Used

- **gpio mode {pin} input** - Set pin as input
- **gpio mode {pin} output** - Set pin as output
- **gpio mode {pin} up** - Enable pull-up resistor
- **gpio mode {pin} down** - Enable pull-down resistor
- **gpio write {pin} {value}** - Write 0 or 1 to pin
- **gpio readall** - Read status of all GPIO pins

## Security Features

- **User Authorization**: GPIOUSER permission required for access
- **Input Validation**: Pin number range checking (0-40)
- **State Validation**: Restricted to allowed GPIO operations
- **Parameter Sanitization**: Proper shell argument escaping
- **Command Escaping**: Safe command execution with `escapeshellcmd()`
- **Error Handling**: Graceful failure with descriptive messages

## GPIO Pin Validation

### Pin Numbers
- **Range**: 0-40 (standard Raspberry Pi GPIO range)
- **Type**: Must be numeric
- **Validation**: `is_numeric($pin) && $pin >= 0 && $pin <= 40`

### GPIO States/Operations
- **input**: Configure pin as input
- **output**: Configure pin as output
- **up**: Enable internal pull-up resistor
- **down**: Enable internal pull-down resistor
- **0**: Write logic low (0V) to pin
- **1**: Write logic high (3.3V) to pin

## Form Structure

### GPIO Control Form
- **Pin Input**: Number input with min=0, max=40, required
- **State Select**: Dropdown with all valid GPIO operations
- **Submit Button**: Execute the selected GPIO operation
- **Method**: POST to same page
- **Validation**: Client-side HTML5 + server-side PHP validation

## Status Display

### GPIO Status Table
- **Command**: `gpio readall` for comprehensive pin status
- **Parsing**: Regex pattern `/^(\d+)\s+(\w+)\s+(\w+)$/` for pin data
- **Columns**: Pin, Mode, Value
- **Format**: HTML table using `includes/table.inc`
- **Error Handling**: Clear message if `gpio` command unavailable

## Error Handling

### Command Execution Errors
- **Return Value**: `false` for failed commands
- **Error Suppression**: stderr redirected to `/dev/null`
- **User Feedback**: Clear error messages for failures

### Validation Errors
- **Pin Validation**: "Invalid GPIO pin number." for out-of-range pins
- **State Validation**: "Invalid GPIO state." for invalid operations
- **Authentication**: Standard error message for unauthorized access

### Status Reading Errors
- **Command Failure**: Error message with troubleshooting hint
- **Parse Failure**: Graceful handling of unexpected output format
- **Permission Issues**: Clear feedback about `gpio` command availability

## Hardware Considerations

### Raspberry Pi Compatibility
- **GPIO Layout**: Standard 40-pin header (pins 0-40)
- **Voltage Levels**: 3.3V logic levels
- **Current Limits**: Standard GPIO current limitations apply
- **Protection**: No hardware protection - software validation only

### Safety Features
- **Pin Range Validation**: Prevents access to invalid pin numbers
- **State Validation**: Restricts to safe GPIO operations
- **Command Validation**: Prevents arbitrary command execution
- **User Permissions**: GPIOUSER authorization required

## Performance Considerations

- **Command Caching**: No caching - real-time GPIO operations
- **Error Handling**: Fast failure for invalid operations
- **Status Updates**: On-demand status reading via page refresh
- **Resource Usage**: Minimal - single command executions only

## Integration Points

- **Table Rendering**: Uses `includes/table.inc` for consistent display
- **CSS Framework**: Integrates with modular CSS system
- **Authentication**: Uses existing user authorization system
- **Error Handling**: Follows site-wide error message patterns
