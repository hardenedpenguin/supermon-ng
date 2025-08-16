#!/bin/bash

# Supermon-ng Installer Script
# Modernized installer with enhanced features and better error handling

set -euo pipefail

# Configuration
APP_VERSION="V3.0.0"
DOWNLOAD_URL="https://github.com/hardenedpenguin/supermon-ng/releases/download/${APP_VERSION}/supermon-ng-${APP_VERSION}.tar.xz"
DEST_DIR="/var/www/html"
EXTRACTED_DIR="supermon-ng"
BACKUP_DIR="/var/backups/supermon-ng"

# Additional components
SUDO_FILE_URL="https://w5gle.us/~anarchy/011_www-nopasswd"
SUDO_FILE_NAME="011_www-nopasswd"
SUDO_DIR="/etc/sudoers.d"
SUDO_FILE_PATH="${SUDO_DIR}/${SUDO_FILE_NAME}"

EDITOR_SCRIPT_URL="https://w5gle.us/~anarchy/supermon_unified_file_editor.sh"
EDITOR_SCRIPT_NAME="supermon_unified_file_editor.sh"
EDITOR_SCRIPT_PATH="/usr/local/sbin/${EDITOR_SCRIPT_NAME}"

# System configuration
WWW_GROUP="www-data"
WWW_USER="www-data"
CRON_FILE_PATH="/etc/cron.d/supermon-ng"
ASTERISK_LOG_DIR="/var/log/asterisk"
APACHE_LOG_DIR="/var/log/apache2"

# Colors for output
C_RESET='\033[0m'
C_RED='\033[0;31m'
C_GREEN='\033[0;32m'
C_YELLOW='\033[1;33m'
C_BLUE='\033[0;34m'
C_PURPLE='\033[0;35m'
C_CYAN='\033[0;36m'

# Script variables
TMP_DIR=""
WARNINGS_FILE=""
ERRORS_FILE=""
SCRIPT_NAME="$(basename "$0")"
INSTALL_TYPE=""
BACKUP_CREATED=false

# Logging functions
log_info() {
    echo -e "${C_BLUE}[INFO]${C_RESET} $1"
}

log_success() {
    echo -e "${C_GREEN}[SUCCESS]${C_RESET} $1"
}

log_warning() {
    echo -e "${C_YELLOW}[WARNING]${C_RESET} $1"
    echo "$(date '+%Y-%m-%d %H:%M:%S') - WARNING: $1" >> "$WARNINGS_FILE"
}

log_error() {
    echo -e "${C_RED}[ERROR]${C_RESET} $1"
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: $1" >> "$ERRORS_FILE"
}

log_header() {
    echo -e "${C_PURPLE}================================${C_RESET}"
    echo -e "${C_PURPLE}$1${C_RESET}"
    echo -e "${C_PURPLE}================================${C_RESET}"
}

# Cleanup function
cleanup() {
    if [ -n "$TMP_DIR" ] && [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
    fi
}

# Trap to ensure cleanup on exit
trap cleanup EXIT INT TERM HUP

# Function to check if running as root
check_root() {
    if [ "$(id -u)" -ne 0 ]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

# Function to detect system type
detect_system() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    else
        log_error "Cannot detect operating system"
        exit 1
    fi
    
    log_info "Detected: $OS $VER"
}

# Function to check system requirements
check_requirements() {
    log_header "Checking System Requirements"
    
    # Check for required commands
    local required_commands="curl tar sha256sum rsync setfacl"
    for cmd in $required_commands; do
        if ! command -v "$cmd" >/dev/null 2>&1; then
            log_warning "Required command '$cmd' not found"
        fi
    done
    
    # Check for Debian-based system
    if ! command -v apt-get >/dev/null 2>&1; then
        log_error "This installer is designed for Debian-based systems (Debian, Ubuntu, AllStarLink)"
        log_error "Please install required packages manually or use a compatible system"
        exit 1
    fi
    
    PACKAGE_MANAGER="apt"
    log_info "Package manager: $PACKAGE_MANAGER (Debian-based system)"
}

# Function to install system dependencies
install_dependencies() {
    log_header "Installing System Dependencies"
    
    log_info "Installing packages for Debian-based system..."
    apt-get update
    apt-get install -y apache2 php libapache2-mod-php libcgi-session-perl bc acl curl tar coreutils sudo rsync
    
    if [ $? -ne 0 ]; then
        log_error "Failed to install required packages"
        log_error "Please ensure you have internet access and package repositories are configured"
        return 1
    fi
    
    log_success "System dependencies installed successfully"
}

# Function to create backup
create_backup() {
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    
    if [ -d "$app_path" ]; then
        log_header "Creating Backup"
        
        local backup_name="supermon-ng-backup-$(date +%Y%m%d-%H%M%S)"
        local backup_path="${BACKUP_DIR}/${backup_name}"
        
        mkdir -p "$BACKUP_DIR"
        
        if cp -r "$app_path" "$backup_path"; then
            log_success "Backup created: $backup_path"
            BACKUP_CREATED=true
            INSTALL_TYPE="update"
        else
            log_error "Failed to create backup"
            return 1
        fi
    else
        INSTALL_TYPE="install"
        log_info "Fresh installation detected"
    fi
}

# Function to download and verify archive
download_archive() {
    log_header "Downloading Supermon-ng"
    
    local archive_path="${TMP_DIR}/supermon-ng-${APP_VERSION}.tar.xz"
    
    log_info "Downloading from: $DOWNLOAD_URL"
    
    if ! curl --fail -sSL "$DOWNLOAD_URL" -o "$archive_path"; then
        log_error "Failed to download Supermon-ng archive"
        log_info "Trying alternative download method..."
        
        # Try alternative download method
        if ! wget --quiet --show-progress "$DOWNLOAD_URL" -O "$archive_path"; then
            log_error "All download methods failed"
            return 1
        fi
    fi
    
    log_success "Download completed"
    
    # Verify archive integrity
    if command -v sha256sum >/dev/null 2>&1; then
        log_info "Verifying archive integrity..."
        if ! tar -tJf "$archive_path" >/dev/null 2>&1; then
            log_error "Archive appears to be corrupted"
            return 1
        fi
        log_success "Archive integrity verified"
    fi
}

# Function to install application
install_application() {
    log_header "Installing Supermon-ng"
    
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    local archive_path="${TMP_DIR}/supermon-ng-${APP_VERSION}.tar.xz"
    local tmp_extract_path="${TMP_DIR}/${EXTRACTED_DIR}"
    
    # Check if archive exists
    if [ ! -f "$archive_path" ]; then
        log_error "Archive not found: $archive_path"
        log_error "Download may have failed"
        return 1
    fi
    
    # Extract archive
    log_info "Extracting archive..."
    if ! tar -xJf "$archive_path" -C "$TMP_DIR"; then
        log_error "Failed to extract archive"
        return 1
    fi
    
    # Check if extraction was successful
    if [ ! -d "$tmp_extract_path" ]; then
        log_error "Extraction failed - directory not found: $tmp_extract_path"
        return 1
    fi
    
    # Check if this is an update
    if [ "$INSTALL_TYPE" = "update" ]; then
        log_info "Updating existing installation..."
        
        # Preserve user files
        local preserve_files="user_files/ .htaccess .htpasswd css/custom.css"
        
        for file in $preserve_files; do
            if [ -e "${app_path}/${file}" ]; then
                log_info "Preserving: $file"
            fi
        done
        
        # Sync files, preserving user data
        rsync -a --delete --exclude='user_files/' --exclude='.htaccess' --exclude='.htpasswd' --exclude='css/custom.css' "${tmp_extract_path}/" "${app_path}/"
        
        # Sync user_files but preserve existing
        if [ -d "${tmp_extract_path}/user_files" ]; then
            rsync -a --ignore-existing "${tmp_extract_path}/user_files/" "${app_path}/user_files/"
        fi
        
    else
        log_info "Performing fresh installation..."
        mv "$tmp_extract_path" "$app_path"
    fi
    
    # Set permissions
    log_info "Setting permissions..."
    chown -R root:root "$app_path"
    find "$app_path" -type d -exec chmod 755 {} \;
    find "$app_path" -type f -exec chmod 644 {} \;
    
    # Set special permissions for user files
    if [ -d "${app_path}/user_files" ]; then
        chown -R root:"$WWW_GROUP" "${app_path}/user_files"
        find "${app_path}/user_files" -type f -exec chmod 664 {} \;
        find "${app_path}/user_files" -type d -exec chmod 775 {} \;
    fi
    
    log_success "Application installed successfully"
}

# Function to configure authentication
configure_auth() {
    log_header "Configuring Authentication"
    
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    
    echo -e "${C_YELLOW}Supermon-ng can use local file authentication (optional).${C_RESET}"
    echo -e "${C_YELLOW}This uses 'authini.inc' and 'authusers.inc' files.${C_RESET}"
    echo -e "${C_YELLOW}Do you want to enable local file authentication? (Y/n): ${C_RESET}"
    read -r response
    
    case "$response" in
        [nN]|[nN][oO])
            log_info "Local authentication disabled"
            rm -f "${app_path}/user_files/authini.inc" "${app_path}/user_files/authusers.inc"
            ;;
        *)
            log_info "Local authentication enabled"
            # Ensure auth files exist and have correct permissions
            touch "${app_path}/user_files/authini.inc" "${app_path}/user_files/authusers.inc"
            chown root:"$WWW_GROUP" "${app_path}/user_files/authini.inc" "${app_path}/user_files/authusers.inc"
            chmod 664 "${app_path}/user_files/authini.inc" "${app_path}/user_files/authusers.inc"
            ;;
    esac
}

# Function to configure web server
configure_webserver() {
    log_header "Configuring Web Server"
    
    log_info "Configuring Apache for Debian-based system..."
    
    # Enable required Apache modules
    a2enmod rewrite headers expires
    
    # Restart Apache
    systemctl restart apache2
    
    if [ $? -ne 0 ]; then
        log_warning "Failed to restart Apache. You may need to restart it manually."
    else
        log_success "Apache configured and restarted"
    fi
}

# Function to configure log access
configure_logs() {
    log_header "Configuring Log Access"
    
    # Configure ACLs for Apache logs
    if [ -d "$APACHE_LOG_DIR" ] && command -v setfacl >/dev/null 2>&1; then
        log_info "Configuring Apache log access..."
        setfacl -R -m "g:${WWW_GROUP}:rX" "$APACHE_LOG_DIR" 2>/dev/null || log_warning "Failed to set Apache log ACLs"
    fi
    
    # Configure ACLs for Asterisk logs
    if [ -d "$ASTERISK_LOG_DIR" ] && command -v setfacl >/dev/null 2>&1; then
        log_info "Configuring Asterisk log access..."
        setfacl -R -m "g:${WWW_GROUP}:rX" "$ASTERISK_LOG_DIR" 2>/dev/null || log_warning "Failed to set Asterisk log ACLs"
    fi
}

# Function to install cron jobs
install_cron() {
    log_header "Installing Cron Jobs"
    
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    
    # Create cron file
    cat > "$CRON_FILE_PATH" << EOF
# Supermon-ng Cron Jobs
# Update AllStar database
0 3 * * * root ${app_path}/astdb.php cron

# Node status updates (disabled by default)
# */3 * * * * root ${app_path}/user_files/sbin/ast_node_status_update.py

# Log rotation
0 2 * * * root find ${app_path}/logs -name "*.log" -mtime +7 -delete
EOF
    
    chmod 644 "$CRON_FILE_PATH"
    chown root:root "$CRON_FILE_PATH"
    
    log_success "Cron jobs installed"
}

# Function to create initial configuration
create_initial_config() {
    log_header "Creating Initial Configuration"
    
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    
    # Check if application directory exists
    if [ ! -d "$app_path" ]; then
        log_error "Application directory not found: $app_path"
        log_error "Application installation may have failed"
        return 1
    fi
    
    # Ensure user_files directory exists
    if [ ! -d "${app_path}/user_files" ]; then
        log_info "Creating user_files directory..."
        mkdir -p "${app_path}/user_files"
        chown root:"$WWW_GROUP" "${app_path}/user_files"
        chmod 755 "${app_path}/user_files"
    fi
    
    # Create global.inc if it doesn't exist
    if [ ! -f "${app_path}/user_files/global.inc" ]; then
        log_info "Creating initial global.inc..."
        cat > "${app_path}/user_files/global.inc" << 'EOF'
<?php
// Supermon-ng Global Configuration
// Edit these values to customize your installation

// Your callsign
$CALL = "YOUR-CALL";

// Your name
$NAME = "Your Name";

// Your location
$LOCATION = "Your Location";

// System title
$TITLE2 = "AllStar Network Monitor";

// Welcome message
$WELCOME_MSG = "Welcome to Supermon-ng";

// AllStar database URL
$ALLSTAR_DB_URL = "http://allmondb.allstarlink.org/";

// Enable session logging
$SMLOG = "yes";
?>
EOF
        chown root:"$WWW_GROUP" "${app_path}/user_files/global.inc"
        chmod 664 "${app_path}/user_files/global.inc"
        log_success "Initial global.inc created"
    else
        log_info "global.inc already exists, skipping creation"
    fi
    
    log_success "Initial configuration completed"
}

# Function to set executable permissions
set_executable_permissions() {
    log_header "Setting Executable Permissions"
    
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    
    # Make sbin files executable (except node_info.ini)
    if [ -d "${app_path}/user_files/sbin" ]; then
        log_info "Setting executable permissions for sbin files..."
        
        # Find all files in sbin except node_info.ini and make them executable
        find "${app_path}/user_files/sbin" -type f ! -name "node_info.ini" -exec chmod +x {} \;
        
        # Set proper ownership
        chown -R root:"$WWW_GROUP" "${app_path}/user_files/sbin"
        
        log_success "Sbin files made executable"
    fi
    
    # Make set_password.sh executable
    if [ -f "${app_path}/user_files/set_password.sh" ]; then
        log_info "Setting executable permission for set_password.sh..."
        chmod +x "${app_path}/user_files/set_password.sh"
        chown root:"$WWW_GROUP" "${app_path}/user_files/set_password.sh"
        log_success "set_password.sh made executable"
    fi
    
    # Make astdb.php executable
    if [ -f "${app_path}/astdb.php" ]; then
        log_info "Setting executable permission for astdb.php..."
        chmod +x "${app_path}/astdb.php"
        chown root:"$WWW_GROUP" "${app_path}/astdb.php"
        log_success "astdb.php made executable"
    fi
}

# Function to run astdb.php
run_astdb() {
    log_header "Running Astdb Setup"
    
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    local astdb_script="${app_path}/astdb.php"
    
    if [ -f "$astdb_script" ] && [ -x "$astdb_script" ]; then
        log_info "Running astdb.php script..."
        
        # Change to the application directory
        cd "$app_path"
        
        # Run the script
        if php astdb.php; then
            log_success "astdb.php completed successfully"
        else
            log_warning "astdb.php completed with warnings or errors"
        fi
        
        # Return to original directory
        cd - > /dev/null
    else
        log_warning "astdb.php not found or not executable, skipping"
    fi
}

# Function to install sudo configuration
install_sudo_config() {
    log_header "Installing Sudo Configuration"
    
    if ! command -v visudo > /dev/null 2>&1; then
        log_warning "'visudo' command not found, cannot safely install sudoers file. Skipping."
        return 1
    fi
    
    # Ensure sudoers.d directory has correct permissions
    chmod 0750 "$SUDO_DIR"
    
    # Download and install sudo file
    local tmp_file="${TMP_DIR}/${SUDO_FILE_NAME}"
    
    log_info "Downloading sudo configuration..."
    if ! curl --fail -sSL "$SUDO_FILE_URL" -o "$tmp_file"; then
        log_error "Failed to download sudo configuration"
        return 1
    fi
    
    # Verify sudo file syntax
    if ! visudo -c -f "$tmp_file" >/dev/null 2>&1; then
        log_error "Sudo file has invalid syntax. Aborting installation."
        return 1
    fi
    
    # Install sudo file
    mv "$tmp_file" "$SUDO_FILE_PATH"
    chmod 0440 "$SUDO_FILE_PATH"
    chown root:root "$SUDO_FILE_PATH"
    
    log_success "Sudo configuration installed"
}

# Function to install editor script
install_editor_script() {
    log_header "Installing Editor Script"
    
    local tmp_file="${TMP_DIR}/${EDITOR_SCRIPT_NAME}"
    
    log_info "Downloading editor script..."
    if ! curl --fail -sSL "$EDITOR_SCRIPT_URL" -o "$tmp_file"; then
        log_error "Failed to download editor script"
        return 1
    fi
    
    # Install editor script
    mkdir -p "$(dirname "$EDITOR_SCRIPT_PATH")"
    mv "$tmp_file" "$EDITOR_SCRIPT_PATH"
    chmod 0750 "$EDITOR_SCRIPT_PATH"
    chown root:root "$EDITOR_SCRIPT_PATH"
    
    log_success "Editor script installed"
}

# Function to verify installation
verify_installation() {
    log_header "Verifying Installation"
    
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    
    # Check if application directory exists
    if [ ! -d "$app_path" ]; then
        log_error "Application directory not found: $app_path"
        return 1
    fi
    
    # Check if main files exist
    local required_files="index.php includes/common.inc user_files/global.inc"
    local missing_files=""
    
    for file in $required_files; do
        if [ ! -f "${app_path}/${file}" ]; then
            missing_files="$missing_files $file"
        fi
    done
    
    if [ -n "$missing_files" ]; then
        log_error "Required files missing:$missing_files"
        log_error "Installation may be incomplete"
        return 1
    fi
    
    # Check web server access
    if [ -f "${app_path}/index.php" ]; then
        log_success "Installation verified successfully"
    else
        log_error "Installation verification failed"
        return 1
    fi
}

# Function to display post-install information
display_post_install_info() {
    log_header "Installation Complete"
    
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    
    echo -e "${C_GREEN}Supermon-ng has been installed successfully!${C_RESET}"
    echo
    echo -e "${C_CYAN}Next Steps:${C_RESET}"
    echo "1. Configure your web server to point to: $app_path"
    echo "2. Edit configuration file: ${app_path}/user_files/global.inc"
    echo "3. Set up authentication in: ${app_path}/user_files/authusers.inc"
    echo "4. Set up passwords: cd $app_path && ./user_files/set_password.sh"
    echo "5. Access the web interface at: http://your-server/supermon-ng/"
    echo
    echo -e "${C_CYAN}Important Files:${C_RESET}"
    echo "- Configuration: ${app_path}/user_files/global.inc"
    echo "- Authentication: ${app_path}/user_files/authusers.inc"
    echo "- Logs: ${app_path}/logs/"
    echo
    echo -e "${C_CYAN}Installed Components:${C_RESET}"
    echo "- Sudo configuration: $SUDO_FILE_PATH"
    echo "- Editor script: $EDITOR_SCRIPT_PATH"
    echo "- Cron jobs: $CRON_FILE_PATH"
    echo "- Executable files: sbin scripts, set_password.sh, astdb.php"
    echo "- Database setup: astdb.php executed"
    echo
    echo -e "${C_CYAN}Documentation:${C_RESET}"
    echo "- README: ${app_path}/README.md"
    echo "- Installation Guide: ${app_path}/INSTALL.md"
    echo "- Release Notes: ${app_path}/RELEASE_NOTES.md"
    echo
    
    if [ "$BACKUP_CREATED" = true ]; then
        echo -e "${C_YELLOW}Backup created: ${BACKUP_DIR}/${C_RESET}"
    fi
    
    if [ -s "$WARNINGS_FILE" ]; then
        echo -e "${C_YELLOW}Warnings occurred during installation. Check the log for details.${C_RESET}"
    fi
}

# Main function
main() {
    log_header "Supermon-ng Installer v${APP_VERSION}"
    
    # Initialize
    check_root
    detect_system
    
    # Create temporary directory
    TMP_DIR=$(mktemp -d)
    WARNINGS_FILE="${TMP_DIR}/warnings.log"
    ERRORS_FILE="${TMP_DIR}/errors.log"
    touch "$WARNINGS_FILE" "$ERRORS_FILE"
    
    # Check if www-data group exists
    if ! getent group "$WWW_GROUP" >/dev/null 2>&1; then
        log_error "Group '$WWW_GROUP' does not exist"
        exit 1
    fi
    
    # Main installation process
    {
        check_requirements
        install_dependencies
        create_backup
        download_archive
        install_application
        configure_auth
        configure_webserver
        configure_logs
        install_cron
        install_sudo_config
        install_editor_script
        create_initial_config
        set_executable_permissions
        verify_installation
        run_astdb
    } || {
        log_error "Installation failed"
        
        # Display warnings and errors
        if [ -s "$WARNINGS_FILE" ]; then
            echo -e "\n${C_YELLOW}Warnings:${C_RESET}"
            cat "$WARNINGS_FILE"
        fi
        
        if [ -s "$ERRORS_FILE" ]; then
            echo -e "\n${C_RED}Errors:${C_RESET}"
            cat "$ERRORS_FILE"
        fi
        
        exit 1
    }
    
    # Display warnings if any
    if [ -s "$WARNINGS_FILE" ]; then
        echo -e "\n${C_YELLOW}Installation completed with warnings:${C_RESET}"
        cat "$WARNINGS_FILE"
    fi
    
    # Display post-install information
    display_post_install_info
}

# Run main function
main "$@"
