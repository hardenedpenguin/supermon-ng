#!/bin/bash

# Supermon-ng Docker Build Fix Script
# Fixes common Docker build issues

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

# Check if we're in the project root
check_project_root() {
    if [[ ! -f "composer.json" ]]; then
        error "This script must be run from the Supermon-ng project root"
        exit 1
    fi
}

# Clean Docker build cache
clean_docker_cache() {
    log "Cleaning Docker build cache..."

    # Remove all unused containers, networks, images
    docker system prune -f

    # Remove build cache
    docker builder prune -f

    # Force remove any cached layers that might be corrupted
    docker image prune -a -f

    success "Docker cache cleaned"
}

# Check required files
check_required_files() {
    log "Checking required files..."

    local missing_files=()

    # Check for required files
    [[ -f "composer.json" ]] || missing_files+=("composer.json")
    [[ -f "docker/php.ini" ]] || missing_files+=("docker/php.ini")
    [[ -f "docker/opcache.ini" ]] || missing_files+=("docker/opcache.ini")
    [[ -f "docker/apache.conf" ]] || missing_files+=("docker/apache.conf")

    if [[ ${#missing_files[@]} -gt 0 ]]; then
        error "Missing required files: ${missing_files[*]}"
        return 1
    fi

    success "All required files present"
}

# Create missing directories
create_missing_directories() {
    log "Creating missing directories..."

    # Create docker directories if they don't exist
    mkdir -p docker/nginx/ssl
    mkdir -p logs/{nginx,php,supermon}

    success "Directories created"
}

# Build with simple Dockerfile
build_simple() {
    log "Building with simple Dockerfile..."

    # Use the simple Dockerfile
    docker build -f Dockerfile.simple -t supermon-ng:latest .

    if [[ $? -eq 0 ]]; then
        success "Docker build completed successfully"
    else
        error "Docker build failed"
        return 1
    fi
}

# Build with full Dockerfile
build_full() {
    log "Building with full Dockerfile (includes Node.js build)..."

    # Check if Node.js files exist
    if [[ ! -f "package.json" ]]; then
        error "package.json not found. Cannot build with Node.js dependencies."
        return 1
    fi

    # Use the full Dockerfile
    docker build -f Dockerfile -t supermon-ng:latest .

    if [[ $? -eq 0 ]]; then
        success "Docker build completed successfully"
    else
        error "Docker build failed"
        return 1
    fi
}

# Test Docker image
test_docker_image() {
    log "Testing Docker image..."

    # Run a test container
    docker run --rm -d --name supermon-test -p 8081:80 supermon-ng:latest

    # Wait for container to start
    sleep 10

    # Test health endpoint
    if curl -f http://localhost:8081/health.php >/dev/null 2>&1; then
        success "Docker image test passed"
    else
        warning "Docker image test failed - health endpoint not responding"
    fi

    # Stop test container
    docker stop supermon-test
}

# Show build options
show_build_options() {
    echo ""
    echo "🔧 Docker Build Options"
    echo "======================"
    echo ""
    echo "1. Simple build (PHP only, no Node.js):"
    echo "   docker build -f Dockerfile.simple -t supermon-ng:latest ."
    echo ""
    echo "2. Full build (with Node.js frontend build):"
    echo "   docker build -f Dockerfile -t supermon-ng:latest ."
    echo ""
    echo "3. Use docker-compose (recommended):"
    echo "   docker-compose up -d"
    echo ""
}

# Main function
main() {
    echo ""
    echo "🔧 Supermon-ng Docker Build Fix"
    echo "==============================="
    echo ""

    check_project_root
    clean_docker_cache
    check_required_files
    create_missing_directories

    echo ""
    echo "Choose build option:"
    echo "1. Simple build (PHP only)"
    echo "2. Full build (with Node.js)"
    echo "3. Show build options"
    echo ""

    read -p "Enter choice (1-3): " choice

    case $choice in
        1)
            build_simple
            test_docker_image
            ;;
        2)
            build_full
            test_docker_image
            ;;
        3)
            show_build_options
            ;;
        *)
            error "Invalid choice"
            exit 1
            ;;
    esac

    echo ""
    echo "✅ Docker build fix completed!"
    echo ""
    echo "📋 Next steps:"
    echo "1. Start services: docker-compose up -d"
    echo "2. Check status: docker-compose ps"
    echo "3. View logs: docker-compose logs -f"
    echo ""
}

# Run main function
main "$@"
