#!/bin/bash

# Supermon-ng Test Package Creator
# Creates a simple tarball for testing purposes (no dependency building)

set -euo pipefail

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PACKAGE_NAME="supermon-ng-test-$(date +%Y%m%d-%H%M%S)"
PACKAGE_DIR="/tmp/$PACKAGE_NAME"

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Check if we're in the project root
check_project_root() {
    if [[ ! -f "composer.json" ]] || [[ ! -f "package.json" ]]; then
        echo "Error: This script must be run from the Supermon-ng project root"
        exit 1
    fi
}

# Clean and create package directory
setup_package() {
    log "Setting up package directory..."
    rm -rf "$PACKAGE_DIR"
    mkdir -p "$PACKAGE_DIR"
}

# Copy essential files
copy_files() {
    log "Copying essential files..."

    # Core application files
    cp -r *.php "$PACKAGE_DIR/"
    cp -r includes/ "$PACKAGE_DIR/"
    cp -r api/ "$PACKAGE_DIR/"
    cp -r css/ "$PACKAGE_DIR/"
    cp -r js/ "$PACKAGE_DIR/"
    cp -r templates/ "$PACKAGE_DIR/"
    cp -r src/ "$PACKAGE_DIR/"

    # Configuration files
    cp composer.json "$PACKAGE_DIR/"
    cp package.json "$PACKAGE_DIR/"
    cp bootstrap.php "$PACKAGE_DIR/"
    cp env.example "$PACKAGE_DIR/"
    cp env.production "$PACKAGE_DIR/"

    # Documentation
    cp -r docs/ "$PACKAGE_DIR/"
    cp README.md "$PACKAGE_DIR/"
    cp SECURITY.md "$PACKAGE_DIR/"

    # Docker files
    cp -r docker/ "$PACKAGE_DIR/"
    cp Dockerfile* "$PACKAGE_DIR/"
    cp docker-compose.yml "$PACKAGE_DIR/"

    # Scripts
    cp -r scripts/ "$PACKAGE_DIR/"

    # User files (templates)
    cp -r user_files/ "$PACKAGE_DIR/"

    # Monitoring
    cp -r monitoring/ "$PACKAGE_DIR/"

    # Tests
    cp -r tests/ "$PACKAGE_DIR/"
    cp phpunit.xml "$PACKAGE_DIR/"
}

# Create installation instructions
create_instructions() {
    log "Creating installation instructions..."

    cat > "$PACKAGE_DIR/INSTALL.md" << 'EOF'
# Supermon-ng Test Package Installation

This is a test package of Supermon-ng for evaluation purposes.

## 🚀 Quick Start

### Option 1: Docker (Recommended)
```bash
# Install Docker if not already installed
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Configure environment
cp env.production .env
# Edit .env with your settings

# Start services
docker-compose up -d
```

### Option 2: Traditional LAMP Stack
```bash
# Install dependencies
sudo apt update
sudo apt install -y apache2 php8.2 php8.2-mysql php8.2-redis php8.2-gd php8.2-zip php8.2-mbstring php8.2-xml php8.2-curl mysql-server redis-server

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs

# Install dependencies
composer install
npm install
npm run build

# Configure environment
cp env.production .env
# Edit .env with your settings

# Set up database
mysql -u root -p < docker/mysql/init.sql
```

### Option 3: Development Server
```bash
# Install dependencies
composer install
npm install

# Configure environment
cp env.example .env
# Edit .env for development

# Start development server
php -S localhost:8080
```

## 📋 Requirements

- PHP 8.2+
- MySQL 8.0+ or MariaDB 10.5+
- Redis 7.0+
- Node.js 18+
- Composer

## 🔧 Configuration

1. Copy environment template:
   ```bash
   cp env.production .env
   ```

2. Edit configuration:
   ```bash
   nano .env
   ```

3. Configure database settings:
   - DB_HOST, DB_PORT, DB_DATABASE
   - DB_USERNAME, DB_PASSWORD
   - REDIS_HOST, REDIS_PORT

4. Set application URL:
   - APP_URL=http://your-domain.com

## 📚 Documentation

- **Traditional Install:** `docs/TRADITIONAL_INSTALL.md`
- **Docker Deployment:** `docs/DEPLOYMENT_GUIDE.md`
- **Development Setup:** `docs/DEVELOPMENT_SETUP.md`

## 🆘 Troubleshooting

- Check logs in `logs/` directory
- Verify database connectivity
- Ensure all dependencies are installed
- Check file permissions

## 📄 License

This project is licensed under the MIT License.
EOF
}

# Create tarball
create_tarball() {
    log "Creating tarball..."
    cd /tmp
    tar -czf "$PACKAGE_NAME.tar.gz" "$PACKAGE_NAME/"

    # Create checksum
    sha256sum "$PACKAGE_NAME.tar.gz" > "$PACKAGE_NAME.tar.gz.sha256"

    success "Test package created: $PACKAGE_NAME.tar.gz"
}

# Main function
main() {
    log "Creating Supermon-ng test package..."

    check_project_root
    setup_package
    copy_files
    create_instructions
    create_tarball

    echo ""
    echo "📦 Test package created: /tmp/$PACKAGE_NAME.tar.gz"
    echo ""
    echo "📋 To deploy:"
    echo "1. Copy the tarball to your server"
    echo "2. Extract: tar -xzf $PACKAGE_NAME.tar.gz"
    echo "3. Follow instructions in INSTALL.md"
    echo ""
    echo "📚 For detailed instructions, see docs/ in the extracted package"
}

# Run main function
main "$@"
