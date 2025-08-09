# Supermon-ng Templates

This directory contains templates to help new contributors quickly create consistent, well-structured code following the project's standards.

## Available Templates

### 1. New Page Template (`new-page-template.php`)

Use this template when creating new web pages for the Supermon-ng interface.

**Features:**
- Standard authentication and permission checks
- Proper include structure
- CSRF protection setup
- Example form handling
- Standard HTML structure with CSS includes
- Example usage of helper classes and components
- Plugin integration examples

**Usage:**
```bash
# Copy template to create new page
cp templates/new-page-template.php my-new-feature.php

# Edit the file and customize:
# 1. Update page title and description
# 2. Modify permission requirements
# 3. Add your page logic
# 4. Update the HTML content
```

### 2. Component Template (`new-component-template.php`)

Use this template when creating reusable PHP components.

**Features:**
- Class-based structure
- Multiple rendering templates (default, list, table, card)
- Data validation
- Error handling
- JSON serialization
- Static factory methods
- Comprehensive documentation

**Usage:**
```bash
# Copy template to components directory
cp templates/new-component-template.php components/MyComponent.php

# Edit the file and customize:
# 1. Rename the class
# 2. Update constructor parameters
# 3. Modify rendering methods
# 4. Add component-specific logic
```

### 3. API Endpoint Template (`new-api-endpoint-template.php`)

Use this template when creating REST API endpoints.

**Features:**
- RESTful HTTP method handling (GET, POST, PUT, DELETE)
- JSON request/response handling
- Authentication and authorization
- CSRF protection
- Rate limiting setup
- Input validation
- Error handling with proper HTTP status codes
- CORS support (optional)
- Comprehensive logging

**Usage:**
```bash
# Copy template to create new API endpoint
cp templates/new-api-endpoint-template.php api-my-feature.php

# Edit the file and customize:
# 1. Update authentication requirements
# 2. Modify input validation
# 3. Implement your API logic
# 4. Update response structure
```

## Template Guidelines

### Security Best Practices

All templates include:
- **Authentication checks** - Verify user is logged in
- **Permission validation** - Check user has required permissions
- **Input sanitization** - Use ValidationHelper for all inputs
- **Output escaping** - Use htmlspecialchars() for HTML output
- **CSRF protection** - Include CSRF tokens in forms
- **Error logging** - Log security events and errors

### Code Standards

Templates follow these standards:
- **Documentation** - All functions have PHPDoc comments
- **Error handling** - Proper exception handling and user-friendly errors
- **Validation** - Input validation using helper classes
- **Consistency** - Standard file structure and naming conventions
- **Accessibility** - Proper HTML semantics and ARIA attributes

### Using Includes and Functions

Templates demonstrate usage of:
- `includes/common.inc` - Core functions and utilities
- `includes/session.inc` - Session management
- `includes/table.inc` - Simple table rendering
- `includes/form.inc` - Form rendering helpers
- Modular functions from `includes/sse/` and `includes/link/`
- Authentication functions like `get_user_auth()`

## Customization Guide

### For New Pages

1. **Copy the template**:
   ```bash
   cp templates/new-page-template.php my-page.php
   ```

2. **Update metadata**:
   ```php
   $pageTitle = "My Custom Page";
   $pageDescription = "Description of my page";
   ```

3. **Set permissions**:
   ```php
   // Change ADMIN to required permission
   if (!get_user_auth("ADMIN")) {
       die("Permission denied");
   }
   ```

4. **Add your content**:
   Replace the example content sections with your functionality.

5. **Add to menu** (if needed):
   Edit `includes/menu.inc` to add your page to the navigation.

### For Components

1. **Copy the template**:
   ```bash
   cp templates/new-component-template.php components/MyWidget.php
   ```

2. **Rename the class**:
   ```php
   class MyWidget  // Change from ExampleComponent
   ```

3. **Update constructor**:
   ```php
   public function __construct($widgetData, $options = []) {
       // Your constructor logic
   }
   ```

4. **Customize rendering**:
   Modify the render methods to output your component's HTML.

### For API Endpoints

1. **Copy the template**:
   ```bash
   cp templates/new-api-endpoint-template.php api-nodes.php
   ```

2. **Update authentication**:
   ```php
   // Change permission as needed
   if (!get_user_auth('ASTLKUSER')) {
       http_response_code(403);
       echo json_encode(['error' => 'Insufficient permissions']);
       exit;
   }
   ```

3. **Implement handlers**:
   Replace the example data functions with your actual logic.

4. **Test with JavaScript**:
   Use the provided JavaScript examples to test your endpoint.

## Best Practices

### File Organization
- Place pages in the root directory
- Place helper functions in `includes/` subdirectories (e.g., `includes/myfeature/`)
- Place API endpoints in the root directory with `api-` prefix
- Use descriptive filenames (e.g., `node-management.php`, `api-user-settings.php`)

### Naming Conventions
- **Files**: Use kebab-case (e.g., `my-feature.php`)
- **Classes**: Use PascalCase (e.g., `MyComponent`)
- **Functions**: Use camelCase (e.g., `getUserData`)
- **Variables**: Use camelCase (e.g., `$nodeData`)

### Documentation
- Add file-level comments explaining purpose
- Document all public methods with PHPDoc
- Include usage examples in comments
- Update this README when adding new templates

### Testing
- Test all permission levels
- Test with invalid input
- Test error conditions
- Test responsive design (for pages)
- Test API endpoints with various HTTP methods

## Template Maintenance

### Updating Templates
When updating templates:
1. Update version numbers in file headers
2. Test templates work with current codebase
3. Update this README if adding new features
4. Ensure all security best practices are included

### Adding New Templates
To add a new template:
1. Create the template file following existing patterns
2. Add comprehensive documentation
3. Include example usage
4. Add entry to this README
5. Test the template thoroughly

## Examples

### Creating a Simple Info Page
```bash
# Copy template
cp templates/new-page-template.php system-status.php

# Edit file to show system information
# Remove form examples, add system status display
# Set appropriate permissions (maybe ADMIN only)
```

### Creating a Data Widget
```bash
# Copy component template
cp templates/new-component-template.php components/SystemStats.php

# Customize for system statistics display
# Add methods to gather system data
# Use table template for stats display
```

### Creating a Settings API
```bash
# Copy API template
cp templates/new-api-endpoint-template.php api-settings.php

# Implement GET to retrieve settings
# Implement POST/PUT to update settings
# Add proper validation for setting values
```

## Getting Help

If you need help using these templates:
1. Read the [Contributing Guide](../docs/CONTRIBUTING.md)
2. Check the [Developer Guide](../docs/DEVELOPER_GUIDE.md)
3. Look at existing code for examples
4. Ask in GitHub discussions

## Template Checklist

Before submitting code based on templates, verify:

- [ ] Authentication and permissions are correctly implemented
- [ ] All user input is validated and sanitized
- [ ] All output is properly escaped
- [ ] Error handling is implemented
- [ ] Code is documented
- [ ] Security best practices are followed
- [ ] Code follows project conventions
- [ ] Templates are tested in development environment
