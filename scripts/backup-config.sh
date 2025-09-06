#!/bin/bash
#
# Configuration Backup Script for Supermon-ng
# 
# Creates backups of user configuration files and settings.
# Backs up only essential configuration files used in V4.0.0+
#
# Author: Supermon-ng Team
# Version: 4.0.0
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

# Default backup directory
BACKUP_BASE_DIR="$PROJECT_ROOT/user_files/backups"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="$BACKUP_BASE_DIR/config-backup-$TIMESTAMP"

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

# Show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -d, --dir DIR     Backup directory (default: $BACKUP_BASE_DIR)"
    echo "  -n, --name NAME   Backup name suffix"
    echo "  -c, --compress    Compress backup with gzip"
    echo "  -h, --help        Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                           # Basic backup"
    echo "  $0 --name before-update      # Backup with custom name"
    echo "  $0 --compress               # Compressed backup"
    echo "  $0 --dir /tmp/backups       # Custom backup location"
}

# Parse command line arguments
COMPRESS=false
CUSTOM_NAME=""

while [[ $# -gt 0 ]]; do
    case $1 in
        -d|--dir)
            BACKUP_BASE_DIR="$2"
            shift 2
            ;;
        -n|--name)
            CUSTOM_NAME="$2"
            shift 2
            ;;
        -c|--compress)
            COMPRESS=true
            shift
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

# Update backup directory with custom name if provided
if [ -n "$CUSTOM_NAME" ]; then
    BACKUP_DIR="$BACKUP_BASE_DIR/config-backup-$CUSTOM_NAME-$TIMESTAMP"
fi

echo -e "${BLUE}Supermon-ng Configuration Backup${NC}"
echo "================================="
echo "Backup location: $BACKUP_DIR"
echo ""

# Create backup directory
print_status "Creating backup directory..."
mkdir -p "$BACKUP_DIR"

if [ ! -d "$BACKUP_DIR" ]; then
    print_error "Failed to create backup directory: $BACKUP_DIR"
    exit 1
fi

# Configuration files to backup
CONFIG_FILES=(
    # Core configuration
    "user_files/global.inc"
    
    # Node configuration
    "user_files/allmon.ini"
    
    # Authentication configuration
    "user_files/authusers.inc"
    "user_files/.htpasswd"
    "user_files/.htaccess"
    
    # Node status configuration
    "user_files/sbin/node_info.ini"
    
    # Custom header background (if exists)
    "user_files/header-background.jpg"
    "user_files/header-background.jpeg"
    "user_files/header-background.png"
    "user_files/header-background.gif"
    "user_files/header-background.webp"
)

# Directories to backup
CONFIG_DIRS=(
    # Only backup user_files/sbin if it contains custom scripts
    # CSS directory is not user configuration
)

# Files backed up counter
BACKED_UP_FILES=0
BACKED_UP_DIRS=0

# Backup individual files
print_status "Backing up configuration files..."

for file_pattern in "${CONFIG_FILES[@]}"; do
    # Handle glob patterns
    for file in $PROJECT_ROOT/$file_pattern; do
        if [ -f "$file" ]; then
            # Get relative path
            relative_path="${file#$PROJECT_ROOT/}"
            backup_path="$BACKUP_DIR/$relative_path"
            
            # Create directory structure in backup
            backup_dir=$(dirname "$backup_path")
            mkdir -p "$backup_dir"
            
            # Copy file
            cp "$file" "$backup_path"
            print_status "Backed up: $relative_path"
            BACKED_UP_FILES=$((BACKED_UP_FILES + 1))
        fi
    done
done

# Backup directories
print_status "Backing up configuration directories..."

for dir in "${CONFIG_DIRS[@]}"; do
    if [ -d "$PROJECT_ROOT/$dir" ]; then
        # Create directory in backup
        mkdir -p "$BACKUP_DIR/$dir"
        
        # Copy directory contents
        cp -r "$PROJECT_ROOT/$dir"/* "$BACKUP_DIR/$dir/" 2>/dev/null || true
        print_status "Backed up directory: $dir"
        BACKED_UP_DIRS=$((BACKED_UP_DIRS + 1))
    fi
done

# Create backup manifest
print_status "Creating backup manifest..."

MANIFEST_FILE="$BACKUP_DIR/BACKUP_MANIFEST.txt"

cat > "$MANIFEST_FILE" << EOF
Supermon-ng Configuration Backup
================================

Backup Date: $(date)
Backup Location: $BACKUP_DIR
Backup Type: Configuration Files and Settings
Created By: $USER on $(hostname)

Files Backed Up: $BACKED_UP_FILES
Directories Backed Up: $BACKED_UP_DIRS

Configuration Files:
$(find "$BACKUP_DIR" -type f -name "*.ini" -o -name "*.inc" -o -name "*.txt" | sed "s|$BACKUP_DIR/||" | sort)

System Information:
- PHP Version: $(php -r "echo PHP_VERSION;" 2>/dev/null || echo "Not available")
- Supermon-ng Version: $(grep "VERSION_DATE" "$PROJECT_ROOT/includes/common.inc" 2>/dev/null | cut -d'"' -f2 || echo "Unknown")
- Server: $(uname -a)

Notes:
- This backup contains user configuration files only
- Web server files and logs are not included
- To restore, copy files back to their original locations
- Always test restored configurations before production use

EOF

# Add file checksums for integrity verification
print_status "Generating file checksums..."

if command -v md5sum >/dev/null 2>&1; then
    echo "" >> "$MANIFEST_FILE"
    echo "File Checksums (MD5):" >> "$MANIFEST_FILE"
    echo "=====================" >> "$MANIFEST_FILE"
    find "$BACKUP_DIR" -type f ! -name "BACKUP_MANIFEST.txt" -exec md5sum {} \; | sed "s|$BACKUP_DIR/||" >> "$MANIFEST_FILE"
elif command -v shasum >/dev/null 2>&1; then
    echo "" >> "$MANIFEST_FILE"
    echo "File Checksums (SHA1):" >> "$MANIFEST_FILE"
    echo "======================" >> "$MANIFEST_FILE"
    find "$BACKUP_DIR" -type f ! -name "BACKUP_MANIFEST.txt" -exec shasum {} \; | sed "s|$BACKUP_DIR/||" >> "$MANIFEST_FILE"
fi

# Compress backup if requested
if [ "$COMPRESS" = true ]; then
    print_status "Compressing backup..."
    
    COMPRESSED_FILE="$BACKUP_DIR.tar.gz"
    
    # Create compressed archive
    tar -czf "$COMPRESSED_FILE" -C "$BACKUP_BASE_DIR" "$(basename "$BACKUP_DIR")"
    
    if [ $? -eq 0 ]; then
        # Remove uncompressed directory
        rm -rf "$BACKUP_DIR"
        print_status "Backup compressed to: $COMPRESSED_FILE"
        BACKUP_FINAL="$COMPRESSED_FILE"
    else
        print_error "Compression failed, keeping uncompressed backup"
        BACKUP_FINAL="$BACKUP_DIR"
    fi
else
    BACKUP_FINAL="$BACKUP_DIR"
fi

# Create restore script
print_status "Creating restore script..."

RESTORE_SCRIPT="$BACKUP_DIR/restore.sh"

# If compressed, we need to adjust the restore script location
if [ "$COMPRESS" = true ]; then
    # Extract the backup temporarily to add the restore script
    temp_dir=$(mktemp -d)
    tar -xzf "$COMPRESSED_FILE" -C "$temp_dir"
    RESTORE_SCRIPT="$temp_dir/$(basename "$BACKUP_DIR")/restore.sh"
fi

cat > "$RESTORE_SCRIPT" << 'EOF'
#!/bin/bash
#
# Restore script for Supermon-ng configuration backup
# Generated automatically during backup creation
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$1"

if [ -z "$PROJECT_ROOT" ]; then
    echo "Usage: $0 <project_root_directory>"
    echo "Example: $0 /var/www/html/supermon-ng"
    exit 1
fi

if [ ! -d "$PROJECT_ROOT" ]; then
    echo "Error: Project root directory does not exist: $PROJECT_ROOT"
    exit 1
fi

echo "Restoring configuration to: $PROJECT_ROOT"
echo "WARNING: This will overwrite existing configuration files!"
read -p "Continue? (y/N): " -n 1 -r
echo

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Restore cancelled."
    exit 0
fi

# Copy files back
find "$SCRIPT_DIR" -type f ! -name "BACKUP_MANIFEST.txt" ! -name "restore.sh" | while read file; do
    relative_path="${file#$SCRIPT_DIR/}"
    target_path="$PROJECT_ROOT/$relative_path"
    
    # Create target directory
    mkdir -p "$(dirname "$target_path")"
    
    # Copy file
    cp "$file" "$target_path"
    echo "Restored: $relative_path"
done

echo "Configuration restore completed!"
echo "Please verify your settings and restart your web server if needed."
EOF

chmod +x "$RESTORE_SCRIPT"

# If compressed, recompress with the restore script
if [ "$COMPRESS" = true ]; then
    rm -f "$COMPRESSED_FILE"
    tar -czf "$COMPRESSED_FILE" -C "$temp_dir" "$(basename "$BACKUP_DIR")"
    rm -rf "$temp_dir"
fi

# Final summary
echo ""
echo -e "${GREEN}Backup completed successfully!${NC}"
echo "=========================="
echo "Backup location: $BACKUP_FINAL"
echo "Files backed up: $BACKED_UP_FILES"
echo "Directories backed up: $BACKED_UP_DIRS"

if [ "$COMPRESS" = true ]; then
    echo "Backup size: $(du -h "$COMPRESSED_FILE" | cut -f1)"
    echo ""
    echo "To restore:"
    echo "1. Extract: tar -xzf \"$(basename "$COMPRESSED_FILE")\""
    echo "2. Run: ./$(basename "$BACKUP_DIR")/restore.sh /path/to/supermon-ng"
else
    echo "Backup size: $(du -sh "$BACKUP_DIR" | cut -f1)"
    echo ""
    echo "To restore:"
    echo "  ./\"$BACKUP_DIR\"/restore.sh /path/to/supermon-ng"
fi

echo ""
echo "Backup manifest available at:"
if [ "$COMPRESS" = true ]; then
    echo "  (Extract backup to view BACKUP_MANIFEST.txt)"
else
    echo "  $MANIFEST_FILE"
fi
