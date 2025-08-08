#!/bin/bash
#
# Basic Testing Script for Supermon-ng
# 
# Runs basic functional tests to verify the application is working correctly.
# This includes configuration validation, file permissions, and basic functionality tests.
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

echo -e "${BLUE}Supermon-ng Basic Tests${NC}"
echo "======================="

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

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

print_test_result() {
    local test_name="$1"
    local result="$2"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$result" = "PASS" ]; then
        echo -e "${GREEN}[PASS]${NC} $test_name"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}[FAIL]${NC} $test_name"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

# Test functions

test_php_requirements() {
    local test_name="PHP Requirements Check"
    
    if ! command -v php >/dev/null 2>&1; then
        print_test_result "$test_name" "FAIL"
        print_error "PHP is not installed"
        return 1
    fi
    
    local php_version
    php_version=$(php -r "echo PHP_VERSION;")
    local php_major
    php_major=$(echo "$php_version" | cut -d. -f1)
    local php_minor
    php_minor=$(echo "$php_version" | cut -d. -f2)
    
    if [ "$php_major" -lt 7 ] || ([ "$php_major" -eq 7 ] && [ "$php_minor" -lt 4 ]); then
        print_test_result "$test_name" "FAIL"
        print_error "PHP version $php_version is too old (7.4+ required)"
        return 1
    fi
    
    print_test_result "$test_name" "PASS"
    return 0
}

test_file_structure() {
    local test_name="File Structure Check"
    local missing_files=()
    
    # Required files and directories
    local required_items=(
        "includes"
        "includes/session.inc"
        "includes/common.inc"
        "includes/helpers.inc"
        "includes/error-handler.inc"
        "includes/config.inc"
        "includes/plugin.inc"
        "css"
        "css/base.css"
        "css/layout.css"
        "js"
        "user_files"
        "user_files/global.inc"
        "components"
        "templates"
        "scripts"
        "docs"
    )
    
    for item in "${required_items[@]}"; do
        if [ ! -e "$PROJECT_ROOT/$item" ]; then
            missing_files+=("$item")
        fi
    done
    
    if [ ${#missing_files[@]} -eq 0 ]; then
        print_test_result "$test_name" "PASS"
        return 0
    else
        print_test_result "$test_name" "FAIL"
        print_error "Missing files/directories: ${missing_files[*]}"
        return 1
    fi
}

test_file_permissions() {
    local test_name="File Permissions Check"
    local permission_issues=()
    
    # Check if user_files directory is writable
    if [ ! -w "$PROJECT_ROOT/user_files" ]; then
        permission_issues+=("user_files directory not writable")
    fi
    
    # Check if scripts are executable
    if [ -d "$PROJECT_ROOT/scripts" ]; then
        while IFS= read -r script; do
            if [ ! -x "$script" ]; then
                permission_issues+=("$(basename "$script") not executable")
            fi
        done < <(find "$PROJECT_ROOT/scripts" -name "*.sh")
    fi
    
    # Check if configuration files are readable
    local config_files=(
        "user_files/global.inc"
        "includes/common.inc"
    )
    
    for file in "${config_files[@]}"; do
        if [ -f "$PROJECT_ROOT/$file" ] && [ ! -r "$PROJECT_ROOT/$file" ]; then
            permission_issues+=("$file not readable")
        fi
    done
    
    if [ ${#permission_issues[@]} -eq 0 ]; then
        print_test_result "$test_name" "PASS"
        return 0
    else
        print_test_result "$test_name" "FAIL"
        for issue in "${permission_issues[@]}"; do
            print_error "$issue"
        done
        return 1
    fi
}

test_php_syntax() {
    local test_name="PHP Syntax Check"
    local syntax_errors=0
    
    # Run the lint script and capture result
    if "$PROJECT_ROOT/scripts/lint-code.sh" >/dev/null 2>&1; then
        print_test_result "$test_name" "PASS"
        return 0
    else
        print_test_result "$test_name" "FAIL"
        print_error "PHP syntax errors found. Run './scripts/lint-code.sh' for details."
        return 1
    fi
}

test_configuration_loading() {
    local test_name="Configuration Loading Test"
    
    # Create a simple PHP script to test configuration loading
    local test_script="$PROJECT_ROOT/tmp/test_config.php"
    
    # Ensure tmp directory exists
    mkdir -p "$PROJECT_ROOT/tmp"
    
    cat > "$test_script" << 'EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to project root
chdir(dirname(__DIR__));

// Test basic includes
try {
    include_once 'includes/common.inc';
    include_once 'includes/helpers.inc';
    include_once 'includes/error-handler.inc';
    include_once 'includes/config.inc';
    
    // Test if classes are available
    if (!class_exists('ValidationHelper')) {
        throw new Exception('ValidationHelper class not found');
    }
    
    if (!class_exists('ErrorHandler')) {
        throw new Exception('ErrorHandler class not found');
    }
    
    if (!class_exists('Config')) {
        throw new Exception('Config class not found');
    }
    
    echo "SUCCESS";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage();
    exit(1);
}
EOF
    
    local result
    result=$(php "$test_script" 2>&1)
    
    # Clean up test script
    rm -f "$test_script"
    
    if [ "$result" = "SUCCESS" ]; then
        print_test_result "$test_name" "PASS"
        return 0
    else
        print_test_result "$test_name" "FAIL"
        print_error "Configuration loading failed: $result"
        return 1
    fi
}

test_helper_functions() {
    local test_name="Helper Functions Test"
    
    # Create a test script for helper functions
    local test_script="$PROJECT_ROOT/tmp/test_helpers.php"
    
    cat > "$test_script" << 'EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to project root
chdir(dirname(__DIR__));

try {
    include_once 'includes/helpers.inc';
    
    // Test ValidationHelper
    $result = ValidationHelper::validateNodeId('1234');
    if ($result !== '1234') {
        throw new Exception('validateNodeId failed');
    }
    
    $result = ValidationHelper::validateNodeId('abc');
    if ($result !== false) {
        throw new Exception('validateNodeId should reject non-numeric input');
    }
    
    // Test sanitizeInput
    $result = ValidationHelper::sanitizeInput('  test  ', 'string');
    if ($result !== 'test') {
        throw new Exception('sanitizeInput string test failed');
    }
    
    $result = ValidationHelper::sanitizeInput('123', 'int');
    if ($result !== 123) {
        throw new Exception('sanitizeInput int test failed');
    }
    
    // Test SecurityHelper
    $token = SecurityHelper::generateToken(16);
    if (strlen($token) !== 16) {
        throw new Exception('generateToken failed');
    }
    
    // Test password functions
    $hash = SecurityHelper::hashPassword('testpassword');
    if (!SecurityHelper::verifyPassword('testpassword', $hash)) {
        throw new Exception('Password hash/verify failed');
    }
    
    echo "SUCCESS";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage();
    exit(1);
}
EOF
    
    local result
    result=$(php "$test_script" 2>&1)
    
    # Clean up test script
    rm -f "$test_script"
    
    if [ "$result" = "SUCCESS" ]; then
        print_test_result "$test_name" "PASS"
        return 0
    else
        print_test_result "$test_name" "FAIL"
        print_error "Helper functions test failed: $result"
        return 1
    fi
}

test_component_loading() {
    local test_name="Component Loading Test"
    
    # Create a test script for components
    local test_script="$PROJECT_ROOT/tmp/test_components.php"
    
    cat > "$test_script" << 'EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to project root
chdir(dirname(__DIR__));

try {
    include_once 'components/NodeDisplay.php';
    include_once 'components/TableRenderer.php';
    
    // Test NodeDisplay
    $nodeData = [
        'node' => '1234',
        'info' => 'Test Node',
        'mode' => 'T',
        'keyed' => 'no'
    ];
    
    $nodeDisplay = new NodeDisplay($nodeData);
    $status = $nodeDisplay->getStatus();
    if (empty($status)) {
        throw new Exception('NodeDisplay getStatus failed');
    }
    
    // Test TableRenderer
    $headers = ['Column 1', 'Column 2'];
    $rows = [['Data 1', 'Data 2']];
    $table = new TableRenderer($headers, $rows);
    $html = $table->render();
    if (strpos($html, 'table') === false) {
        throw new Exception('TableRenderer failed to generate table');
    }
    
    echo "SUCCESS";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage();
    exit(1);
}
EOF
    
    local result
    result=$(php "$test_script" 2>&1)
    
    # Clean up test script
    rm -f "$test_script"
    
    if [ "$result" = "SUCCESS" ]; then
        print_test_result "$test_name" "PASS"
        return 0
    else
        print_test_result "$test_name" "FAIL"
        print_error "Component loading test failed: $result"
        return 1
    fi
}

test_css_integrity() {
    local test_name="CSS File Integrity"
    local css_issues=0
    
    # Check that all CSS files exist and are readable
    local css_files=(
        "css/base.css"
        "css/layout.css"
        "css/menu.css"
        "css/tables.css"
        "css/forms.css"
        "css/widgets.css"
        "css/responsive.css"
        "css/custom.css"
    )
    
    for css_file in "${css_files[@]}"; do
        if [ ! -f "$PROJECT_ROOT/$css_file" ]; then
            print_error "Missing CSS file: $css_file"
            css_issues=$((css_issues + 1))
        elif [ ! -r "$PROJECT_ROOT/$css_file" ]; then
            print_error "CSS file not readable: $css_file"
            css_issues=$((css_issues + 1))
        fi
    done
    
    # Basic CSS syntax check (matching braces)
    for css_file in "${css_files[@]}"; do
        if [ -f "$PROJECT_ROOT/$css_file" ]; then
            local open_braces
            local close_braces
            open_braces=$(grep -o "{" "$PROJECT_ROOT/$css_file" | wc -l)
            close_braces=$(grep -o "}" "$PROJECT_ROOT/$css_file" | wc -l)
            
            if [ "$open_braces" -ne "$close_braces" ]; then
                print_error "Mismatched braces in $css_file"
                css_issues=$((css_issues + 1))
            fi
        fi
    done
    
    if [ $css_issues -eq 0 ]; then
        print_test_result "$test_name" "PASS"
        return 0
    else
        print_test_result "$test_name" "FAIL"
        return 1
    fi
}

test_documentation() {
    local test_name="Documentation Check"
    local doc_issues=0
    
    # Check for essential documentation files
    local doc_files=(
        "README.md"
        "docs/CONTRIBUTING.md"
        "docs/DEVELOPER_GUIDE.md"
        "css/README.md"
    )
    
    for doc_file in "${doc_files[@]}"; do
        if [ ! -f "$PROJECT_ROOT/$doc_file" ]; then
            print_error "Missing documentation file: $doc_file"
            doc_issues=$((doc_issues + 1))
        fi
    done
    
    if [ $doc_issues -eq 0 ]; then
        print_test_result "$test_name" "PASS"
        return 0
    else
        print_test_result "$test_name" "FAIL"
        return 1
    fi
}

# Run all tests
print_status "Starting basic tests..."
echo ""

test_php_requirements
test_file_structure
test_file_permissions
test_php_syntax
test_configuration_loading
test_helper_functions
test_component_loading
test_css_integrity
test_documentation

# Summary
echo ""
echo -e "${BLUE}Test Results${NC}"
echo "============"
echo "Total tests: $TOTAL_TESTS"
echo "Passed: $PASSED_TESTS"
echo "Failed: $FAILED_TESTS"

if [ $FAILED_TESTS -eq 0 ]; then
    echo ""
    print_status "All tests passed! ✓"
    echo "The application appears to be set up correctly."
    exit 0
else
    echo ""
    print_error "Some tests failed! ✗"
    echo "Please address the issues above before proceeding."
    exit 1
fi
