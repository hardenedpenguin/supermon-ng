#!/bin/bash
#
# Supermon-ng Development Setup Script
# 
# Sets up the development environment for contributing to Supermon-ng.
# This script prepares the workspace with proper permissions, tools, and configurations.
#
# Author: Supermon-ng Team
# Version: 2.0.3
#

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}Supermon-ng Development Setup${NC}"
echo "====================================="

# Check if running as root
if [[ $EUID -eq 0 ]]; then
    echo -e "${YELLOW}Warning: Running as root. Some operations will use different permissions.${NC}"
fi

# Function to print status messages
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check for required tools
print_status "Checking for required tools..."

check_command() {
    if command -v "$1" >/dev/null 2>&1; then
        print_status "$1 is installed"
    else
        print_error "$1 is not installed. Please install it first."
        return 1
    fi
}

# Check required commands
REQUIRED_COMMANDS=("php" "git" "curl" "grep" "find")
MISSING_COMMANDS=()

for cmd in "${REQUIRED_COMMANDS[@]}"; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        MISSING_COMMANDS+=("$cmd")
    fi
done

if [ ${#MISSING_COMMANDS[@]} -ne 0 ]; then
    print_error "Missing required commands: ${MISSING_COMMANDS[*]}"
    echo "Please install the missing commands and run this script again."
    exit 1
fi

# Check PHP version
print_status "Checking PHP version..."
PHP_VERSION=$(php -r "echo PHP_VERSION;")
PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)

if [ "$PHP_MAJOR" -lt 7 ] || ([ "$PHP_MAJOR" -eq 7 ] && [ "$PHP_MINOR" -lt 4 ]); then
    print_warning "PHP version $PHP_VERSION detected. PHP 7.4+ is recommended."
else
    print_status "PHP version $PHP_VERSION is compatible"
fi

# Set up directory structure
print_status "Setting up directory structure..."

DIRECTORIES=(
    "logs"
    "tmp"
    "user_files/backups"
    "docs/examples"
)

for dir in "${DIRECTORIES[@]}"; do
    if [ ! -d "$PROJECT_ROOT/$dir" ]; then
        mkdir -p "$PROJECT_ROOT/$dir"
        print_status "Created directory: $dir"
    fi
done

# Set up permissions
print_status "Setting up permissions..."

# Make scripts executable
find "$PROJECT_ROOT/scripts" -name "*.sh" -exec chmod +x {} \;

# Ensure proper permissions for user files
if [ -d "$PROJECT_ROOT/user_files" ]; then
    chmod 755 "$PROJECT_ROOT/user_files"
    find "$PROJECT_ROOT/user_files" -type f -name "*.ini" -exec chmod 644 {} \;
    find "$PROJECT_ROOT/user_files" -type f -name "*.inc" -exec chmod 644 {} \;
fi

# Ensure log directory is writable
if [ -d "$PROJECT_ROOT/logs" ]; then
    chmod 755 "$PROJECT_ROOT/logs"
fi

# Create development configuration files
print_status "Setting up development configuration..."

# Create development .env file if it doesn't exist
if [ ! -f "$PROJECT_ROOT/.env.dev" ]; then
    cat > "$PROJECT_ROOT/.env.dev" << EOF
# Supermon-ng Development Environment Configuration
# Copy this to .env and modify as needed

# Debug settings
DEBUG_MODE=true
ERROR_REPORTING=E_ALL
DISPLAY_ERRORS=true

# Development URLs (change to match your setup)
BASE_URL=http://localhost/supermon-ng
HAMCLOCK_URL_INTERNAL=http://localhost/hamclock/live.html
HAMCLOCK_URL_EXTERNAL=http://yourserver.com/hamclock/live.html

# Database/Log paths (development)
LOG_PATH=./logs/
TEMP_PATH=./tmp/

# Plugin development
ENABLE_PLUGINS=true
PLUGIN_DEBUG=true
EOF
    print_status "Created development environment file: .env.dev"
fi

# Create development database backup
print_status "Backing up configuration files..."

BACKUP_DIR="$PROJECT_ROOT/user_files/backups/dev-setup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup important configuration files
CONFIG_FILES=(
    "user_files/global.inc"
    "user_files/allmon.ini"
    "user_files/authusers.inc"
    "user_files/authini.inc"
)

for file in "${CONFIG_FILES[@]}"; do
    if [ -f "$PROJECT_ROOT/$file" ]; then
        cp "$PROJECT_ROOT/$file" "$BACKUP_DIR/"
        print_status "Backed up: $file"
    fi
done

# Set up Git hooks (if in a Git repository)
if [ -d "$PROJECT_ROOT/.git" ]; then
    print_status "Setting up Git hooks..."
    
    # Pre-commit hook for syntax checking
    cat > "$PROJECT_ROOT/.git/hooks/pre-commit" << 'EOF'
#!/bin/bash
# Supermon-ng pre-commit hook

echo "Running pre-commit checks..."

# Check PHP syntax
find . -name "*.php" -not -path "./vendor/*" -not -path "./.git/*" | while read file; do
    php -l "$file" >/dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "PHP syntax error in: $file"
        exit 1
    fi
done

echo "Pre-commit checks passed!"
EOF
    
    chmod +x "$PROJECT_ROOT/.git/hooks/pre-commit"
    print_status "Git pre-commit hook installed"
fi

# Create development helper aliases
print_status "Creating development helper scripts..."

# Create lint script link
if [ ! -f "$PROJECT_ROOT/lint" ]; then
    ln -s "$PROJECT_ROOT/scripts/lint-code.sh" "$PROJECT_ROOT/lint"
    print_status "Created lint script shortcut"
fi

# Create test script link
if [ ! -f "$PROJECT_ROOT/test" ]; then
    ln -s "$PROJECT_ROOT/scripts/run-tests.sh" "$PROJECT_ROOT/test"
    print_status "Created test script shortcut"
fi

# Verify setup
print_status "Verifying setup..."

# Check if web server is accessible (basic check)
if command -v curl >/dev/null 2>&1; then
    if curl -s "http://localhost/supermon-ng/" >/dev/null 2>&1; then
        print_status "Web server appears to be accessible"
    else
        print_warning "Web server may not be running or configured"
        echo "  Make sure Apache/Nginx is running and pointing to the project directory"
    fi
fi

# Check PHP configuration
print_status "Checking PHP configuration..."
PHP_ERRORS=$(php -r "echo ini_get('display_errors') ? 'ON' : 'OFF';")
print_status "PHP display_errors: $PHP_ERRORS"

# Final summary
echo ""
echo -e "${GREEN}Development setup complete!${NC}"
echo "====================================="
echo ""
echo "Next steps:"
echo "1. Copy .env.dev to .env and customize for your environment"
echo "2. Configure your web server to serve the project directory"
echo "3. Run './scripts/lint-code.sh' to check for syntax errors"
echo "4. Run './scripts/run-tests.sh' to run basic tests"
echo "5. Review docs/CONTRIBUTING.md for development guidelines"
echo ""
echo "Development shortcuts created:"
echo "- ./lint - Run PHP syntax checks"
echo "- ./test - Run basic tests"
echo ""
echo "Configuration backup created in: $BACKUP_DIR"
echo ""
echo -e "${BLUE}Happy coding!${NC}"
