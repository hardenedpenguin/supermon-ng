#!/bin/bash

# Simple Password Management Script for Supermon-ng
# This script provides a user-friendly way to manage .htpasswd passwords and .htaccess authentication

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HTPASSWD_FILE="$SCRIPT_DIR/.htpasswd"
HTACCESS_FILE="$SCRIPT_DIR/.htaccess"
MANAGE_USERS_SCRIPT="$SCRIPT_DIR/../scripts/manage_users.php"

# Colors for output
C_RESET='\033[0m'
C_RED='\033[0;31m'
C_GREEN='\033[0;32m'
C_YELLOW='\033[1;33m'
C_BLUE='\033[0;34m'
C_CYAN='\033[0;36m'

# Logging functions
log_info() {
    echo -e "${C_BLUE}[INFO]${C_RESET} $1"
}

log_success() {
    echo -e "${C_GREEN}[SUCCESS]${C_RESET} $1"
}

log_warning() {
    echo -e "${C_YELLOW}[WARNING]${C_RESET} $1"
}

log_error() {
    echo -e "${C_RED}[ERROR]${C_RESET} $1"
}

log_header() {
    echo -e "${C_CYAN}================================${C_RESET}"
    echo -e "${C_CYAN}$1${C_RESET}"
    echo -e "${C_CYAN}================================${C_RESET}"
}

# Function to show usage
show_usage() {
    log_header "Supermon-ng Password Manager"
    echo
    echo "This script helps you manage user passwords and authentication for Supermon-ng."
    echo "It automatically creates and maintains the .htaccess file for HTTP Basic Authentication."
    echo
    echo "Usage:"
    echo "  $0 [command] [options]"
    echo
    echo "Commands:"
    echo "  list                    - List all users"
    echo "  add <username>          - Add a new user (prompts for password)"
    echo "  change <username>       - Change password for existing user"
    echo "  remove <username>       - Remove a user"
    echo "  interactive             - Interactive mode (default)"
    echo
    echo "Examples:"
    echo "  $0                      # Interactive mode"
    echo "  $0 list                 # List all users"
    echo "  $0 add admin            # Add user 'admin'"
    echo "  $0 change admin         # Change password for 'admin'"
    echo "  $0 remove olduser       # Remove user 'olduser'"
    echo
}

# Function to read password securely
read_password() {
    local prompt="$1"
    local password
    
    echo -n "$prompt"
    read -s password
    echo
    echo "$password"
}

# Function to validate username
validate_username() {
    local username="$1"
    
    if [[ -z "$username" ]]; then
        log_error "Username cannot be empty"
        return 1
    fi
    
    if [[ ! "$username" =~ ^[a-zA-Z0-9_-]+$ ]]; then
        log_error "Username must contain only letters, numbers, underscores, and hyphens"
        return 1
    fi
    
    return 0
}

# Function to check if manage_users.php exists
check_manage_users_script() {
    if [[ ! -f "$MANAGE_USERS_SCRIPT" ]]; then
        log_error "manage_users.php script not found at: $MANAGE_USERS_SCRIPT"
        log_error "Please ensure the script is installed correctly"
        exit 1
    fi
}

# Function to create or update .htaccess file
create_htaccess() {
    local htaccess_content='AuthType Basic
AuthName "Supermon-ng Access"
AuthUserFile '"$HTPASSWD_FILE"'
Require valid-user'

    if [[ ! -f "$HTACCESS_FILE" ]]; then
        log_info "Creating .htaccess file at: $HTACCESS_FILE"
        echo "$htaccess_content" > "$HTACCESS_FILE"
        chmod 644 "$HTACCESS_FILE"
        log_success ".htaccess file created successfully"
    else
        # Check if .htaccess needs updating (different AuthUserFile path)
        if ! grep -q "AuthUserFile $HTPASSWD_FILE" "$HTACCESS_FILE" 2>/dev/null; then
            log_info "Updating .htaccess file with correct AuthUserFile path"
            echo "$htaccess_content" > "$HTACCESS_FILE"
            chmod 644 "$HTACCESS_FILE"
            log_success ".htaccess file updated successfully"
        fi
    fi
}

# Function to list users
list_users() {
    log_header "Current Users"
    php "$MANAGE_USERS_SCRIPT" list
}

# Function to add user
add_user() {
    local username="$1"
    
    if [[ -z "$username" ]]; then
        echo -n "Enter username: "
        read username
    fi
    
    if ! validate_username "$username"; then
        exit 1
    fi
    
    local password
    local password_confirm
    
    while true; do
        password=$(read_password "Enter password: ")
        password_confirm=$(read_password "Confirm password: ")
        
        if [[ "$password" == "$password_confirm" ]]; then
            break
        else
            log_error "Passwords do not match. Please try again."
            echo
        fi
    done
    
    if [[ ${#password} -lt 6 ]]; then
        log_warning "Password is less than 6 characters. Consider using a stronger password."
    fi
    
    php "$MANAGE_USERS_SCRIPT" add "$username" "$password"
}

# Function to change password
change_password() {
    local username="$1"
    
    if [[ -z "$username" ]]; then
        echo -n "Enter username: "
        read username
    fi
    
    if ! validate_username "$username"; then
        exit 1
    fi
    
    local password
    local password_confirm
    
    while true; do
        password=$(read_password "Enter new password: ")
        password_confirm=$(read_password "Confirm new password: ")
        
        if [[ "$password" == "$password_confirm" ]]; then
            break
        else
            log_error "Passwords do not match. Please try again."
            echo
        fi
    done
    
    if [[ ${#password} -lt 6 ]]; then
        log_warning "Password is less than 6 characters. Consider using a stronger password."
    fi
    
    php "$MANAGE_USERS_SCRIPT" change "$username" "$password"
}

# Function to remove user
remove_user() {
    local username="$1"
    
    if [[ -z "$username" ]]; then
        echo -n "Enter username to remove: "
        read username
    fi
    
    if ! validate_username "$username"; then
        exit 1
    fi
    
    echo -n "Are you sure you want to remove user '$username'? (y/N): "
    read confirm
    
    case "$confirm" in
        [yY]|[yY][eE][sS])
            php "$MANAGE_USERS_SCRIPT" remove "$username"
            ;;
        *)
            log_info "User removal cancelled"
            ;;
    esac
}

# Interactive mode
interactive_mode() {
    log_header "Supermon-ng Password Manager - Interactive Mode"
    
    while true; do
        echo
        echo "Choose an option:"
        echo "1) List users"
        echo "2) Add user"
        echo "3) Change password"
        echo "4) Remove user"
        echo "5) Exit"
        echo
        echo -n "Enter your choice (1-5): "
        read choice
        
        case "$choice" in
            1)
                list_users
                ;;
            2)
                add_user
                ;;
            3)
                change_password
                ;;
            4)
                remove_user
                ;;
            5)
                log_info "Goodbye!"
                exit 0
                ;;
            *)
                log_error "Invalid choice. Please enter 1-5."
                ;;
        esac
    done
}

# Main script
main() {
    # Check if manage_users.php exists
    check_manage_users_script
    
    # Ensure .htaccess file exists and is properly configured
    create_htaccess
    
    # Parse command line arguments
    case "${1:-interactive}" in
        help|--help|-h)
            show_usage
            ;;
        list)
            list_users
            ;;
        add)
            add_user "$2"
            ;;
        change)
            change_password "$2"
            ;;
        remove)
            remove_user "$2"
            ;;
        interactive|"")
            interactive_mode
            ;;
        *)
            log_error "Unknown command: $1"
            echo
            show_usage
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
