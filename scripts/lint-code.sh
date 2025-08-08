#!/bin/bash
#
# Code Linting Script for Supermon-ng
# 
# Performs syntax checking and basic code quality analysis
# on PHP files in the project.
#
# Author: Supermon-ng Team
# Version: 2.0.3
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}Supermon-ng Code Linting${NC}"
echo "========================="

# Counters
TOTAL_FILES=0
ERROR_FILES=0
WARNING_FILES=0

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

# Check if PHP is available
if ! command -v php >/dev/null 2>&1; then
    print_error "PHP is not installed or not in PATH"
    exit 1
fi

print_status "Using PHP version: $(php -r 'echo PHP_VERSION;')"

# Find PHP files to check
print_status "Finding PHP files..."

# Exclude certain directories and files
EXCLUDE_PATTERNS=(
    "./vendor/*"
    "./tmp/*"
    "./logs/*"
    "./.git/*"
    "./user_files/backups/*"
)

# Build find command with exclusions
FIND_CMD="find $PROJECT_ROOT -name '*.php'"
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    FIND_CMD="$FIND_CMD -not -path '$pattern'"
done

# Get list of PHP files
PHP_FILES=$(eval "$FIND_CMD" | sort)

if [ -z "$PHP_FILES" ]; then
    print_warning "No PHP files found"
    exit 0
fi

echo "Found $(echo "$PHP_FILES" | wc -l) PHP files to check"

# Function to check a single file
check_file() {
    local file="$1"
    local relative_path="${file#$PROJECT_ROOT/}"
    
    TOTAL_FILES=$((TOTAL_FILES + 1))
    
    # Check PHP syntax
    local syntax_check
    syntax_check=$(php -l "$file" 2>&1)
    local syntax_result=$?
    
    if [ $syntax_result -ne 0 ]; then
        print_error "Syntax error in $relative_path"
        echo "  $syntax_check"
        ERROR_FILES=$((ERROR_FILES + 1))
        return 1
    fi
    
    # Additional checks
    local warnings=0
    
    # Check for potential security issues
    if grep -q "eval(" "$file"; then
        print_warning "Found eval() in $relative_path (potential security risk)"
        warnings=$((warnings + 1))
    fi
    
    if grep -q "exec(" "$file" && ! grep -q "escapeshellcmd\|escapeshellarg" "$file"; then
        print_warning "Found exec() without escaping in $relative_path (potential security risk)"
        warnings=$((warnings + 1))
    fi
    
    # Check for proper output escaping
    if grep -q "echo.*\$_" "$file" && ! grep -q "htmlspecialchars" "$file"; then
        print_warning "Found echo with user input without escaping in $relative_path"
        warnings=$((warnings + 1))
    fi
    
    # Check for TODO/FIXME comments
    if grep -qi "TODO\|FIXME" "$file"; then
        print_warning "Found TODO/FIXME comments in $relative_path"
        warnings=$((warnings + 1))
    fi
    
    # Check for very long lines (over 120 characters)
    local long_lines
    long_lines=$(grep -n ".\{121,\}" "$file" | wc -l)
    if [ "$long_lines" -gt 0 ]; then
        print_warning "$relative_path has $long_lines lines over 120 characters"
        warnings=$((warnings + 1))
    fi
    
    # Check for missing function documentation
    local functions_without_docs=0
    while IFS= read -r line; do
        if echo "$line" | grep -q "^function\s\|^\s*public function\|^\s*private function\|^\s*protected function"; then
            local line_num
            line_num=$(echo "$line" | cut -d: -f1)
            local prev_line_num=$((line_num - 1))
            local prev_line
            prev_line=$(sed -n "${prev_line_num}p" "$file")
            
            if ! echo "$prev_line" | grep -q "/\*\*\|\*\|//"; then
                functions_without_docs=$((functions_without_docs + 1))
            fi
        fi
    done < <(grep -n "^function\s\|^\s*public function\|^\s*private function\|^\s*protected function" "$file")
    
    if [ "$functions_without_docs" -gt 0 ]; then
        print_warning "$relative_path has $functions_without_docs functions without documentation"
        warnings=$((warnings + 1))
    fi
    
    if [ $warnings -gt 0 ]; then
        WARNING_FILES=$((WARNING_FILES + 1))
    fi
    
    # Show progress
    if [ $((TOTAL_FILES % 10)) -eq 0 ]; then
        echo -n "."
    fi
    
    return 0
}

# Main linting loop
echo ""
print_status "Checking PHP syntax and code quality..."
echo -n "Progress: "

while IFS= read -r file; do
    check_file "$file"
done <<< "$PHP_FILES"

echo ""  # New line after progress dots

# Additional project-specific checks
echo ""
print_status "Running project-specific checks..."

# Check for proper include paths
print_status "Checking include paths..."
INCLUDE_ISSUES=0

# Look for old-style includes that might need updating
if grep -r "include.*session\.inc" "$PROJECT_ROOT" --include="*.php" | grep -v "includes/session.inc"; then
    print_warning "Found includes that might need to be updated to includes/ directory"
    INCLUDE_ISSUES=$((INCLUDE_ISSUES + 1))
fi

# Check for hardcoded paths
if grep -r "/var/www/html" "$PROJECT_ROOT" --include="*.php" | grep -v "# Example\|// Example\|common.inc"; then
    print_warning "Found hardcoded paths that might need to be configurable"
    INCLUDE_ISSUES=$((INCLUDE_ISSUES + 1))
fi

# Check CSS files for basic syntax
print_status "Checking CSS files..."
CSS_FILES=$(find "$PROJECT_ROOT/css" -name "*.css" 2>/dev/null || true)
CSS_ISSUES=0

if [ -n "$CSS_FILES" ]; then
    while IFS= read -r css_file; do
        # Basic CSS syntax check (count braces)
        local open_braces
        local close_braces
        open_braces=$(grep -o "{" "$css_file" | wc -l)
        close_braces=$(grep -o "}" "$css_file" | wc -l)
        
        if [ "$open_braces" -ne "$close_braces" ]; then
            print_warning "Mismatched braces in $(basename "$css_file")"
            CSS_ISSUES=$((CSS_ISSUES + 1))
        fi
    done <<< "$CSS_FILES"
fi

# Check JavaScript files for basic syntax
print_status "Checking JavaScript files..."
JS_FILES=$(find "$PROJECT_ROOT/js" -name "*.js" 2>/dev/null | grep -v "\.min\.js" || true)
JS_ISSUES=0

if [ -n "$JS_FILES" ] && command -v node >/dev/null 2>&1; then
    while IFS= read -r js_file; do
        if ! node -c "$js_file" >/dev/null 2>&1; then
            print_warning "JavaScript syntax issues in $(basename "$js_file")"
            JS_ISSUES=$((JS_ISSUES + 1))
        fi
    done <<< "$JS_FILES"
elif [ -n "$JS_FILES" ]; then
    print_warning "Node.js not available - skipping JavaScript syntax check"
fi

# Configuration file checks
print_status "Checking configuration files..."
CONFIG_ISSUES=0

# Check for example configuration files that might be missing
REQUIRED_EXAMPLES=(
    "user_files/global.inc.example"
    "user_files/allmon.ini.example"
)

for example_file in "${REQUIRED_EXAMPLES[@]}"; do
    if [ ! -f "$PROJECT_ROOT/$example_file" ]; then
        print_warning "Missing example configuration file: $example_file"
        CONFIG_ISSUES=$((CONFIG_ISSUES + 1))
    fi
done

# Final summary
echo ""
echo -e "${BLUE}Linting Results${NC}"
echo "==============="
echo "Total PHP files checked: $TOTAL_FILES"
echo "Files with syntax errors: $ERROR_FILES"
echo "Files with warnings: $WARNING_FILES"

if [ $INCLUDE_ISSUES -gt 0 ]; then
    echo "Include path issues: $INCLUDE_ISSUES"
fi

if [ $CSS_ISSUES -gt 0 ]; then
    echo "CSS issues: $CSS_ISSUES"
fi

if [ $JS_ISSUES -gt 0 ]; then
    echo "JavaScript issues: $JS_ISSUES"
fi

if [ $CONFIG_ISSUES -gt 0 ]; then
    echo "Configuration issues: $CONFIG_ISSUES"
fi

echo ""

# Exit with appropriate code
if [ $ERROR_FILES -gt 0 ]; then
    print_error "Linting failed due to syntax errors"
    exit 1
elif [ $WARNING_FILES -gt 0 ] || [ $INCLUDE_ISSUES -gt 0 ] || [ $CSS_ISSUES -gt 0 ] || [ $JS_ISSUES -gt 0 ] || [ $CONFIG_ISSUES -gt 0 ]; then
    print_warning "Linting completed with warnings"
    echo "Consider addressing the warnings above for better code quality"
    exit 0
else
    print_status "All checks passed! Code looks good."
    exit 0
fi
