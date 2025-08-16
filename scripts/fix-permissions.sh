#!/bin/bash

# Supermon-ng Permission Fix Script
# Fixes common permission issues on the server

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as root
check_root() {
    if [[ $EUID -eq 0 ]]; then
        error "This script should not be run as root"
        exit 1
    fi
}

# Fix Docker permissions
fix_docker_permissions() {
    log "Fixing Docker permissions..."

    # Check if Docker is installed
    if ! command -v docker &> /dev/null; then
        warning "Docker not found. Installing Docker..."
        curl -fsSL https://get.docker.com -o get-docker.sh
        sudo sh get-docker.sh
        sudo usermod -aG docker $USER
        success "Docker installed. Please logout and login again."
        return
    fi

    # Fix Docker socket permissions
    if [[ -e /var/run/docker.sock ]]; then
        sudo chmod 666 /var/run/docker.sock
        success "Docker socket permissions fixed"
    fi

    # Add user to docker group
    if ! groups $USER | grep -q docker; then
        sudo usermod -aG docker $USER
        warning "Added user to docker group. Please logout and login again."
    fi

    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        warning "Docker Compose not found. Installing..."
        sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        sudo chmod +x /usr/local/bin/docker-compose
        success "Docker Compose installed"
    fi
}

# Fix directory permissions
fix_directory_permissions() {
    log "Fixing directory permissions..."

    # Create deployment directory if it doesn't exist
    sudo mkdir -p /opt/supermon-ng

    # Fix ownership
    sudo chown -R $USER:$USER /opt/supermon-ng/

    # Fix permissions
    sudo chmod -R 755 /opt/supermon-ng/
    sudo chmod -R 777 /opt/supermon-ng/user_files/ 2>/dev/null || true
    sudo chmod -R 777 /opt/supermon-ng/logs/ 2>/dev/null || true

    # Create SSL directory
    sudo mkdir -p /opt/supermon-ng/docker/nginx/ssl
    sudo chown -R $USER:$USER /opt/supermon-ng/docker/nginx/ssl/
    sudo chmod 755 /opt/supermon-ng/docker/nginx/ssl/

    success "Directory permissions fixed"
}

# Fix file permissions
fix_file_permissions() {
    log "Fixing file permissions..."

    # Make scripts executable
    find /opt/supermon-ng -name "*.sh" -exec chmod +x {} \; 2>/dev/null || true

    # Fix environment file permissions
    if [[ -f /opt/supermon-ng/.env ]]; then
        chmod 644 /opt/supermon-ng/.env
    fi

    # Fix SSL certificate permissions
    if [[ -f /opt/supermon-ng/docker/nginx/ssl/supermon.key ]]; then
        chmod 600 /opt/supermon-ng/docker/nginx/ssl/supermon.key
    fi

    if [[ -f /opt/supermon-ng/docker/nginx/ssl/supermon.crt ]]; then
        chmod 644 /opt/supermon-ng/docker/nginx/ssl/supermon.crt
    fi

    success "File permissions fixed"
}

# Check system resources
check_system_resources() {
    log "Checking system resources..."

    # Check disk space
    DISK_USAGE=$(df /opt/supermon-ng | tail -1 | awk '{print $5}' | sed 's/%//')
    if [[ $DISK_USAGE -gt 90 ]]; then
        warning "Disk usage is high: ${DISK_USAGE}%"
    else
        success "Disk usage: ${DISK_USAGE}%"
    fi

    # Check memory
    MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.0f", $3/$2 * 100.0)}')
    if [[ $MEMORY_USAGE -gt 90 ]]; then
        warning "Memory usage is high: ${MEMORY_USAGE}%"
    else
        success "Memory usage: ${MEMORY_USAGE}%"
    fi

    # Check if ports are available
    if netstat -tlnp 2>/dev/null | grep -q ":80 "; then
        warning "Port 80 is in use"
    else
        success "Port 80 is available"
    fi

    if netstat -tlnp 2>/dev/null | grep -q ":443 "; then
        warning "Port 443 is in use"
    else
        success "Port 443 is available"
    fi
}

# Test Docker functionality
test_docker() {
    log "Testing Docker functionality..."

    if docker ps >/dev/null 2>&1; then
        success "Docker is working"
    else
        error "Docker is not working. Please check Docker service."
        sudo systemctl status docker
    fi

    if docker-compose version >/dev/null 2>&1; then
        success "Docker Compose is working"
    else
        error "Docker Compose is not working"
    fi
}

# Main function
main() {
    echo ""
    echo "🔧 Supermon-ng Permission Fix Script"
    echo "===================================="
    echo ""

    check_root
    fix_docker_permissions
    fix_directory_permissions
    fix_file_permissions
    check_system_resources
    test_docker

    echo ""
    echo "✅ Permission fixes completed!"
    echo ""
    echo "📋 Next steps:"
    echo "1. If Docker was installed, logout and login again"
    echo "2. Try running the deployment script again:"
    echo "   ./scripts/deploy.sh production"
    echo "3. If issues persist, check the logs above"
    echo ""
}

# Run main function
main "$@"
