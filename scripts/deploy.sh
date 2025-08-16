#!/bin/bash

# Supermon-ng Deployment Script
# This script handles deployment to production environments

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
DEPLOY_ENV="${1:-production}"
DOCKER_COMPOSE_FILE="docker-compose.yml"

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

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."

    # Check Docker
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed"
        exit 1
    fi

    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        error "Docker Compose is not installed"
        exit 1
    fi

    # Check if we're in the right directory
    if [[ ! -f "$DOCKER_COMPOSE_FILE" ]]; then
        error "Docker Compose file not found. Are you in the project root?"
        exit 1
    fi

    success "Prerequisites check passed"
}

# Backup current deployment
backup_deployment() {
    log "Creating backup of current deployment..."

    BACKUP_DIR="/opt/supermon-ng/backups/$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"

    if [[ -d "/opt/supermon-ng" ]]; then
        cp -r /opt/supermon-ng/* "$BACKUP_DIR/" 2>/dev/null || true
        success "Backup created at $BACKUP_DIR"
    else
        warning "No existing deployment found to backup"
    fi
}

# Stop current deployment
stop_deployment() {
    log "Stopping current deployment..."

    if [[ -d "/opt/supermon-ng" ]]; then
        cd /opt/supermon-ng
        docker-compose down --remove-orphans || true
        success "Current deployment stopped"
    fi
}

# Deploy new version
deploy_new_version() {
    log "Deploying new version..."

    # Create deployment directory
    sudo mkdir -p /opt/supermon-ng
    sudo chown $USER:$USER /opt/supermon-ng

    # Copy project files
    cp -r "$PROJECT_ROOT"/* /opt/supermon-ng/
    cd /opt/supermon-ng

    # Set proper permissions
    chmod +x scripts/*.sh
    chmod 755 user_files/

    # Create necessary directories
    mkdir -p logs/{nginx,php,supermon}
    mkdir -p docker/nginx/ssl

    # Generate SSL certificates if they don't exist
    if [[ ! -f "docker/nginx/ssl/supermon.crt" ]]; then
        log "Generating self-signed SSL certificates..."
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout docker/nginx/ssl/supermon.key \
            -out docker/nginx/ssl/supermon.crt \
            -subj "/C=US/ST=State/L=City/O=Organization/CN=supermon.local"
    fi

    # Start services
    log "Starting services..."
    docker-compose up -d

    # Wait for services to be healthy
    log "Waiting for services to be healthy..."
    sleep 30

    # Check service health
    check_service_health

    success "Deployment completed successfully"
}

# Check service health
check_service_health() {
    log "Checking service health..."

    local max_attempts=10
    local attempt=1

    while [[ $attempt -le $max_attempts ]]; do
        if curl -f http://localhost/health.php &>/dev/null; then
            success "Application is healthy"
            return 0
        fi

        warning "Health check attempt $attempt/$max_attempts failed, retrying..."
        sleep 10
        ((attempt++))
    done

    error "Health check failed after $max_attempts attempts"
    return 1
}

# Rollback deployment
rollback_deployment() {
    log "Rolling back deployment..."

    # Find latest backup
    LATEST_BACKUP=$(ls -t /opt/supermon-ng/backups/ | head -1)

    if [[ -n "$LATEST_BACKUP" ]]; then
        cd /opt/supermon-ng
        docker-compose down

        rm -rf /opt/supermon-ng/*
        cp -r "/opt/supermon-ng/backups/$LATEST_BACKUP"/* /opt/supermon-ng/

        docker-compose up -d
        success "Rollback completed"
    else
        error "No backup found for rollback"
        exit 1
    fi
}

# Show deployment status
show_status() {
    log "Deployment status:"

    if [[ -d "/opt/supermon-ng" ]]; then
        cd /opt/supermon-ng
        docker-compose ps

        echo ""
        log "Service URLs:"
        echo "  Application: http://localhost"
        echo "  Health Check: http://localhost/health.php"
        echo "  API: http://localhost/api/"

        if docker-compose ps | grep -q "grafana.*Up"; then
            echo "  Grafana: http://localhost:3000"
        fi

        if docker-compose ps | grep -q "prometheus.*Up"; then
            echo "  Prometheus: http://localhost:9090"
        fi
    else
        warning "No deployment found"
    fi
}

# Main deployment function
main() {
    log "Starting Supermon-ng deployment to $DEPLOY_ENV environment"

    check_root
    check_prerequisites

    case "${2:-deploy}" in
        "deploy")
            backup_deployment
            stop_deployment
            deploy_new_version
            show_status
            ;;
        "rollback")
            rollback_deployment
            show_status
            ;;
        "status")
            show_status
            ;;
        "stop")
            stop_deployment
            ;;
        *)
            error "Unknown command: ${2:-deploy}"
            echo "Usage: $0 [environment] [command]"
            echo "Commands: deploy, rollback, status, stop"
            exit 1
            ;;
    esac
}

# Handle script arguments
case "${1:-}" in
    "production"|"staging"|"development")
        main "$@"
        ;;
    "rollback"|"status"|"stop")
        main "production" "$@"
        ;;
    "-h"|"--help")
        echo "Supermon-ng Deployment Script"
        echo ""
        echo "Usage: $0 [environment] [command]"
        echo ""
        echo "Environments:"
        echo "  production  - Deploy to production (default)"
        echo "  staging     - Deploy to staging"
        echo "  development - Deploy to development"
        echo ""
        echo "Commands:"
        echo "  deploy   - Deploy the application (default)"
        echo "  rollback - Rollback to previous deployment"
        echo "  status   - Show deployment status"
        echo "  stop     - Stop current deployment"
        echo ""
        echo "Examples:"
        echo "  $0                    # Deploy to production"
        echo "  $0 staging deploy     # Deploy to staging"
        echo "  $0 rollback           # Rollback production"
        echo "  $0 status             # Show production status"
        ;;
    *)
        main "production" "deploy"
        ;;
esac
