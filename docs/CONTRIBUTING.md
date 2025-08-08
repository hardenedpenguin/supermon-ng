# Contributing to Supermon-ng

Welcome to the Supermon-ng project! This guide will help you get started contributing to the codebase, whether you're fixing bugs, adding features, or improving documentation.

## 🚀 Quick Start for New Contributors

### Prerequisites
- Basic knowledge of PHP, HTML, CSS, and JavaScript
- A web server with PHP support (Apache/Nginx)
- Access to an AllStar Link node for testing (optional but recommended)

### Development Setup
1. Clone the repository
2. Run the development setup script:
   ```bash
   ./scripts/dev-setup.sh
   ```
3. Copy the configuration templates and customize for your environment

## 📁 Project Structure

```
supermon-ng/
├── docs/              # Documentation
├── includes/          # Shared PHP functions and classes
├── components/        # Reusable PHP components
├── templates/         # Templates for creating new files
├── scripts/           # Development and maintenance scripts
├── css/               # Modular stylesheets
├── js/                # JavaScript files
├── user_files/        # User configuration files
└── *.php              # Main application files
```

## 🎯 Types of Contributions

### 1. **Simple Changes** (Great for beginners!)
- **CSS styling**: Edit files in `css/` directory
- **Text and labels**: Update language in PHP files
- **Bug fixes**: Small corrections to existing functionality
- **Documentation**: Improve comments and docs

**Process:**
1. Edit the relevant file
2. Test locally (see Testing section)
3. Submit a pull request

### 2. **New Features**
- **New pages**: Use templates in `templates/` directory
- **New components**: Follow existing patterns in `components/`
- **API endpoints**: Extend server-side functionality

**Process:**
1. Check if similar functionality exists
2. Use appropriate template from `templates/`
3. Follow coding standards (see below)
4. Add tests if applicable
5. Update documentation

### 3. **Complex Changes**
- **Architecture improvements**: Discuss in issues first
- **Security enhancements**: Follow security guidelines
- **Performance optimizations**: Include benchmarks

## 🛠️ Development Guidelines

### File Organization

#### PHP Files
- **Main pages**: Root directory (`*.php`)
- **Shared functions**: `includes/` directory
- **Reusable components**: `components/` directory
- **User config**: `user_files/` directory (don't edit core files here)

#### CSS Files (Load in order)
1. `css/base.css` - Variables, resets, typography
2. `css/layout.css` - Layout and containers
3. `css/menu.css` - Navigation
4. `css/tables.css` - Table styles
5. `css/forms.css` - Forms and buttons
6. `css/widgets.css` - Specific components
7. `css/responsive.css` - Mobile and print styles
8. `css/custom.css` - User customizations (loads last)

### Coding Standards

#### PHP
```php
<?php
/**
 * File description
 * 
 * @author Your Name
 * @version 2.0.3
 */

// Use meaningful variable names
$nodeConnectionData = fetchNodeData($nodeId);

// Document functions
/**
 * Connects to a node via AMI
 * @param string $nodeId The node identifier
 * @return array Connection data or false on failure
 */
function connectToNode($nodeId) {
    // Implementation
}

// Use helper classes when available
$nodeId = ValidationHelper::validateNodeId($_GET['node']);
$connection = AMIHelper::connectToNode($nodeId);
```

#### CSS
```css
/* Component-specific styles */
.node-display {
    /* Use CSS variables for consistency */
    background-color: var(--container-bg);
    border: 1px solid var(--border-color);
}

/* Mobile-first responsive design */
@media (max-width: 768px) {
    .node-display {
        padding: 10px;
    }
}
```

#### JavaScript
```javascript
// Use modern JavaScript features
const nodeData = await fetchNodeData(nodeId);

// Document complex functions
/**
 * Updates the node display with real-time data
 * @param {string} nodeId - The node identifier
 * @param {Object} data - The node data object
 */
function updateNodeDisplay(nodeId, data) {
    // Implementation
}
```

## 🧪 Testing Your Changes

### Before Submitting
```bash
# Check PHP syntax
./scripts/lint-code.sh

# Run basic tests
./scripts/run-tests.sh

# Test in development environment
./scripts/dev-server.sh
```

### Manual Testing Checklist
- [ ] Page loads without errors
- [ ] Functionality works as expected
- [ ] Mobile responsive (test on phone/tablet)
- [ ] Works with different user permission levels
- [ ] No console errors in browser

## 📋 Common Tasks

### Adding a New Menu Item
1. Edit `includes/menu.inc`
2. Add the new menu entry following existing patterns
3. Create new PHP file using `templates/new-page-template.php`
4. Update permissions in `authusers.php` if needed

### Adding New Styling
1. Identify the appropriate CSS file in `css/` directory
2. Add your styles using CSS variables when possible
3. Test responsive behavior
4. Update `css/README.md` if adding new components

### Working with AMI (Asterisk Manager Interface)
```php
// Use the AMI helper for consistency
$connection = AMIHelper::connectToNode($nodeId);
if (!$connection) {
    return ErrorHandler::handleAMIError("Failed to connect to node $nodeId");
}

$result = AMIHelper::executeCommand($connection, $command);
AMIHelper::disconnect($connection);
```

### Adding Database/Configuration Access
```php
// Use the Config helper
Config::load('user_files/global.inc');
$setting = Config::get('SOME_SETTING', 'default_value');
```

### Error Handling
```php
// Log errors for debugging
ErrorHandler::logError("Failed to process node data", ['node' => $nodeId]);

// Show user-friendly messages
echo ErrorHandler::displayUserError("Unable to connect to node. Please try again.");
```

## 🔐 Security Guidelines

- **Always validate input**: Use `ValidationHelper` functions
- **Escape output**: Use `htmlspecialchars()` for HTML output
- **Check permissions**: Verify user authorization before sensitive operations
- **Use CSRF protection**: Include CSRF tokens in forms
- **Log security events**: Use `ErrorHandler::logError()` for security-related errors

## 📚 Resources

### Documentation
- [Developer Guide](DEVELOPER_GUIDE.md) - Detailed architecture information
- [API Reference](API_REFERENCE.md) - Function and class documentation
- [Testing Guide](TESTING.md) - How to test your changes
- [Troubleshooting](TROUBLESHOOTING.md) - Common issues and solutions

### External Resources
- [AllStar Link Documentation](https://allstarlink.org/)
- [Asterisk Manager Interface](https://wiki.asterisk.org/wiki/display/AST/The+Asterisk+Manager+TCP+IP+API)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)

## 💬 Getting Help

- **GitHub Issues**: For bugs and feature requests
- **Discussions**: For questions and general help
- **Wiki**: For detailed documentation and tutorials

## 📝 Pull Request Guidelines

### Before Submitting
1. Test your changes thoroughly
2. Update documentation if needed
3. Follow the coding standards above
4. Write clear commit messages

### Pull Request Template
```
## Description
Brief description of your changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Code cleanup/refactoring

## Testing
- [ ] Tested locally
- [ ] No console errors
- [ ] Mobile responsive
- [ ] Works with different user levels

## Screenshots (if applicable)
Add screenshots for UI changes
```

## 🎉 Recognition

Contributors are recognized in:
- `CONTRIBUTORS.md` file
- Release notes
- GitHub contributor graphs

Thank you for contributing to Supermon-ng! 🚀
