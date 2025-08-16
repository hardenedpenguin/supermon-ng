#!/bin/bash

# Supermon-ng SSL Setup Script
# Automates SSL certificate setup with Let's Encrypt/Certbot

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

# Get domain from user
get_domain() {
    echo ""
    echo "🔐 SSL Certificate Setup for Supermon-ng"
    echo "========================================"
    echo ""

    read -p "Enter your domain name (e.g., supermon.example.com): " DOMAIN

    if [[ -z "$DOMAIN" ]]; then
        error "Domain name is required"
        exit 1
    fi

    # Validate domain format
    if [[ ! "$DOMAIN" =~ ^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$ ]]; then
        error "Invalid domain format"
        exit 1
    fi

    success "Domain set to: $DOMAIN"
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."

    # Check if domain resolves
    if ! nslookup "$DOMAIN" >/dev/null 2>&1; then
        error "Domain $DOMAIN does not resolve. Please ensure DNS is configured."
        exit 1
    fi

    # Check if ports are open
    if ! nc -z "$DOMAIN" 80 2>/dev/null; then
        warning "Port 80 is not accessible. Please ensure port 80 is open."
    fi

    # Check if certbot is installed
    if ! command -v certbot &> /dev/null; then
        log "Certbot not found. Installing..."
        sudo apt update
        sudo apt install -y certbot python3-certbot-apache
    fi

    success "Prerequisites check completed"
}

# Stop services
stop_services() {
    log "Stopping services to free up port 80..."

    # Stop Apache if running
    if sudo systemctl is-active --quiet apache2; then
        sudo systemctl stop apache2
        APACHE_STOPPED=true
    fi

    # Stop Nginx if running
    if sudo systemctl is-active --quiet nginx; then
        sudo systemctl stop nginx
        NGINX_STOPPED=true
    fi

    # Stop Docker services if running
    if [[ -f "docker-compose.yml" ]] && docker-compose ps | grep -q "Up"; then
        docker-compose down
        DOCKER_STOPPED=true
    fi

    success "Services stopped"
}

# Generate SSL certificate
generate_certificate() {
    log "Generating SSL certificate for $DOMAIN..."

    # Generate certificate using standalone mode
    sudo certbot certonly --standalone \
        --non-interactive \
        --agree-tos \
        --email admin@$DOMAIN \
        --domains "$DOMAIN" \
        --expand

    if [[ $? -eq 0 ]]; then
        success "SSL certificate generated successfully"
    else
        error "Failed to generate SSL certificate"
        exit 1
    fi
}

# Copy certificates to Docker directory
copy_certificates() {
    log "Copying certificates to Docker directory..."

    # Create SSL directory
    sudo mkdir -p docker/nginx/ssl

    # Copy certificates
    sudo cp "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" docker/nginx/ssl/supermon.crt
    sudo cp "/etc/letsencrypt/live/$DOMAIN/privkey.pem" docker/nginx/ssl/supermon.key

    # Set proper permissions
    sudo chown -R $USER:$USER docker/nginx/ssl/
    sudo chmod 600 docker/nginx/ssl/supermon.key
    sudo chmod 644 docker/nginx/ssl/supermon.crt

    success "Certificates copied to docker/nginx/ssl/"
}

# Update environment configuration
update_environment() {
    log "Updating environment configuration..."

    # Check if .env file exists
    if [[ ! -f ".env" ]]; then
        cp env.production .env
        warning "Created .env file from template"
    fi

    # Update APP_URL to HTTPS
    if grep -q "APP_URL=" .env; then
        sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
    else
        echo "APP_URL=https://$DOMAIN" >> .env
    fi

    # Update SSL configuration
    if grep -q "SSL_CERT_PATH=" .env; then
        sed -i "s|SSL_CERT_PATH=.*|SSL_CERT_PATH=/etc/nginx/ssl/supermon.crt|" .env
    else
        echo "SSL_CERT_PATH=/etc/nginx/ssl/supermon.crt" >> .env
    fi

    if grep -q "SSL_KEY_PATH=" .env; then
        sed -i "s|SSL_KEY_PATH=.*|SSL_KEY_PATH=/etc/nginx/ssl/supermon.key|" .env
    else
        echo "SSL_KEY_PATH=/etc/nginx/ssl/supermon.key" >> .env
    fi

    success "Environment configuration updated"
}

# Update Nginx configuration
update_nginx_config() {
    log "Updating Nginx configuration..."

    if [[ -f "docker/nginx/nginx.conf" ]]; then
        # Update server_name in Nginx config
        sed -i "s|server_name _;|server_name $DOMAIN;|" docker/nginx/nginx.conf

        # Update server_name in HTTPS block
        sed -i "/listen 443 ssl http2;/,/server_name/ s|server_name .*;|server_name $DOMAIN;|" docker/nginx/nginx.conf

        success "Nginx configuration updated"
    else
        warning "Nginx configuration file not found"
    fi
}

# Restart services
restart_services() {
    log "Restarting services..."

    # Restart Apache if it was stopped
    if [[ "${APACHE_STOPPED:-false}" == "true" ]]; then
        sudo systemctl start apache2
        success "Apache restarted"
    fi

    # Restart Nginx if it was stopped
    if [[ "${NGINX_STOPPED:-false}" == "true" ]]; then
        sudo systemctl start nginx
        success "Nginx restarted"
    fi

    # Restart Docker services if they were stopped
    if [[ "${DOCKER_STOPPED:-false}" == "true" ]]; then
        docker-compose up -d
        success "Docker services restarted"
    fi
}

# Set up automatic renewal
setup_renewal() {
    log "Setting up automatic certificate renewal..."

    # Create renewal script
    sudo tee /opt/supermon-ng/renew-ssl.sh << EOF
#!/bin/bash

# Renew certificates
certbot renew --quiet

# Copy renewed certificates
cp /etc/letsencrypt/live/$DOMAIN/fullchain.pem /opt/supermon-ng/docker/nginx/ssl/supermon.crt
cp /etc/letsencrypt/live/$DOMAIN/privkey.pem /opt/supermon-ng/docker/nginx/ssl/supermon.key

# Set permissions
chown -R $USER:$USER /opt/supermon-ng/docker/nginx/ssl/
chmod 600 /opt/supermon-ng/docker/nginx/ssl/supermon.key
chmod 644 /opt/supermon-ng/docker/nginx/ssl/supermon.crt

# Reload Nginx container if running
if [[ -f "/opt/supermon-ng/docker-compose.yml" ]]; then
    cd /opt/supermon-ng
    docker-compose exec nginx nginx -s reload 2>/dev/null || true
fi

echo "\$(date): SSL certificates renewed for $DOMAIN"
EOF

    # Make script executable
    sudo chmod +x /opt/supermon-ng/renew-ssl.sh

    # Add to crontab if not already present
    if ! sudo crontab -l 2>/dev/null | grep -q "renew-ssl.sh"; then
        (sudo crontab -l 2>/dev/null; echo "0 12 * * * /opt/supermon-ng/renew-ssl.sh") | sudo crontab -
        success "Automatic renewal scheduled"
    else
        warning "Automatic renewal already configured"
    fi
}

# Test SSL configuration
test_ssl() {
    log "Testing SSL configuration..."

    # Wait a moment for services to start
    sleep 5

    # Test HTTPS access
    if curl -s -I "https://$DOMAIN" | grep -q "HTTP/"; then
        success "HTTPS is working"
    else
        warning "HTTPS test failed. Please check your configuration."
    fi

    # Test certificate validity
    if openssl s_client -connect "$DOMAIN:443" -servername "$DOMAIN" </dev/null 2>/dev/null | grep -q "Verify return code: 0"; then
        success "SSL certificate is valid"
    else
        warning "SSL certificate validation failed"
    fi
}

# Show summary
show_summary() {
    echo ""
    echo "🎉 SSL Setup Complete!"
    echo "====================="
    echo ""
    echo "✅ Domain: $DOMAIN"
    echo "✅ Certificate: Generated and installed"
    echo "✅ Environment: Updated for HTTPS"
    echo "✅ Auto-renewal: Configured"
    echo ""
    echo "🌐 Access your application at:"
    echo "   https://$DOMAIN"
    echo ""
    echo "📋 Next steps:"
    echo "1. Test your application at https://$DOMAIN"
    echo "2. Configure your firewall to allow port 443"
    echo "3. Set up monitoring for certificate expiration"
    echo ""
    echo "📚 For troubleshooting, see: docs/SSL_CERTBOT_SETUP.md"
}

# Main function
main() {
    check_root
    get_domain
    check_prerequisites
    stop_services
    generate_certificate
    copy_certificates
    update_environment
    update_nginx_config
    restart_services
    setup_renewal
    test_ssl
    show_summary
}

# Run main function
main "$@"
