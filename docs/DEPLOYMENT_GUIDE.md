# Supermon-ng Deployment Guide

This guide provides comprehensive instructions for deploying Supermon-ng to production environments.

## 🚀 Quick Start

### Prerequisites

- **Docker & Docker Compose**: Latest stable versions
- **Linux Server**: Ubuntu 20.04+ or CentOS 8+
- **Minimum Resources**: 2GB RAM, 2 CPU cores, 20GB storage
- **Network**: Ports 80, 443, 8080 (configurable)

### Automated Deployment

```bash
# Clone the repository
git clone https://github.com/your-org/supermon-ng.git
cd supermon-ng

# Run deployment script
./scripts/deploy.sh production
```

## 📋 Manual Deployment Steps

### 1. Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Logout and login to apply group changes
```

### 2. Environment Configuration

Create a `.env` file in the project root:

```env
# Application Settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database Configuration
DB_HOST=db
DB_PORT=3306
DB_DATABASE=supermon
DB_USERNAME=supermon_user
DB_PASSWORD=your_secure_password

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password

# AllStar Configuration
CALL=YOUR_CALLSIGN
NAME=Your Name
LOCATION=Your City, State

# Security Settings
SESSION_TIMEOUT=3600
CSRF_TIMEOUT=1800
RATE_LIMIT=60

# Monitoring
PROMETHEUS_ENABLED=true
GRAFANA_ENABLED=true
```

### 3. SSL Certificate Setup

#### Option A: Let's Encrypt (Recommended)

```bash
# Install Certbot
sudo apt install certbot

# Generate certificate
sudo certbot certonly --standalone -d your-domain.com

# Copy certificates to Docker directory
sudo mkdir -p docker/nginx/ssl
sudo cp /etc/letsencrypt/live/your-domain.com/fullchain.pem docker/nginx/ssl/supermon.crt
sudo cp /etc/letsencrypt/live/your-domain.com/privkey.pem docker/nginx/ssl/supermon.key
sudo chown -R $USER:$USER docker/nginx/ssl/
```

#### Option B: Self-Signed Certificate

```bash
# Generate self-signed certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout docker/nginx/ssl/supermon.key \
    -out docker/nginx/ssl/supermon.crt \
    -subj "/C=US/ST=State/L=City/O=Organization/CN=your-domain.com"
```

### 4. Database Setup

```bash
# Create database directory
sudo mkdir -p /opt/supermon-ng/data/mysql
sudo chown -R $USER:$USER /opt/supermon-ng/

# Initialize database (first run only)
docker-compose up -d db
sleep 30
docker-compose down
```

### 5. Application Deployment

```bash
# Start all services
docker-compose up -d

# Check service status
docker-compose ps

# View logs
docker-compose logs -f
```

## 🔧 Configuration Options

### Docker Compose Profiles

The application supports different deployment profiles:

```bash
# Production with monitoring
docker-compose --profile production --profile monitoring up -d

# Development only
docker-compose --profile development up -d

# Staging with basic monitoring
docker-compose --profile staging up -d
```

### Environment-Specific Configurations

#### Production
- Full monitoring stack (Prometheus + Grafana)
- Nginx reverse proxy with SSL
- Rate limiting enabled
- Comprehensive logging

#### Staging
- Basic monitoring
- Self-signed SSL
- Debug logging enabled
- No rate limiting

#### Development
- No external monitoring
- HTTP only
- Verbose logging
- Hot reload enabled

## 📊 Monitoring & Observability

### Health Checks

The application includes built-in health checks:

```bash
# Application health
curl http://localhost/health.php

# Docker service health
docker-compose ps

# Database connectivity
docker-compose exec db mysqladmin ping -h localhost
```

### Metrics Collection

Prometheus collects metrics from:
- Application performance
- Database statistics
- System resources
- Custom business metrics

### Grafana Dashboards

Access Grafana at `http://your-domain.com:3000`:
- Default credentials: `admin/admin`
- Pre-configured dashboards for:
  - Application performance
  - System resources
  - Database metrics
  - Custom business metrics

## 🔒 Security Configuration

### Firewall Setup

```bash
# Configure UFW firewall
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 8080/tcp
sudo ufw enable
```

### Security Headers

The application includes security headers:
- HSTS (HTTP Strict Transport Security)
- X-Frame-Options
- X-Content-Type-Options
- X-XSS-Protection
- Content-Security-Policy

### Rate Limiting

Configure rate limiting in `docker/nginx/nginx.conf`:
- API endpoints: 10 requests/second
- Login attempts: 5 requests/minute

## 🚨 Backup & Recovery

### Automated Backups

```bash
# Create backup script
cat > /opt/supermon-ng/backup.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/opt/supermon-ng/backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup database
docker-compose exec -T db mysqldump -u root -proot_password supermon > "$BACKUP_DIR/database.sql"

# Backup configuration
cp -r user_files/ "$BACKUP_DIR/"
cp docker-compose.yml "$BACKUP_DIR/"
cp .env "$BACKUP_DIR/"

# Compress backup
tar -czf "$BACKUP_DIR.tar.gz" -C /opt/supermon-ng/backups "$(basename $BACKUP_DIR)"
rm -rf "$BACKUP_DIR"

# Keep only last 7 days of backups
find /opt/supermon-ng/backups -name "*.tar.gz" -mtime +7 -delete
EOF

chmod +x /opt/supermon-ng/backup.sh

# Add to crontab
echo "0 2 * * * /opt/supermon-ng/backup.sh" | crontab -
```

### Recovery Procedures

```bash
# Restore from backup
BACKUP_FILE="/opt/supermon-ng/backups/20231201_020000.tar.gz"
RESTORE_DIR="/opt/supermon-ng/restore"

mkdir -p "$RESTORE_DIR"
tar -xzf "$BACKUP_FILE" -C "$RESTORE_DIR"

# Stop services
docker-compose down

# Restore database
docker-compose up -d db
sleep 30
docker-compose exec -T db mysql -u root -proot_password supermon < "$RESTORE_DIR/database.sql"

# Restore configuration
cp -r "$RESTORE_DIR/user_files/" ./
cp "$RESTORE_DIR/docker-compose.yml" ./
cp "$RESTORE_DIR/.env" ./

# Restart services
docker-compose up -d
```

## 🔄 CI/CD Pipeline

### GitHub Actions Setup

1. **Repository Secrets**: Configure the following secrets in your GitHub repository:
   - `PRODUCTION_HOST`: Production server IP
   - `PRODUCTION_USER`: SSH username
   - `PRODUCTION_SSH_KEY`: SSH private key
   - `STAGING_HOST`: Staging server IP
   - `STAGING_USER`: SSH username
   - `STAGING_SSH_KEY`: SSH private key
   - `SLACK_WEBHOOK`: Slack webhook URL (optional)

2. **Automatic Deployment**: Push to `main` branch triggers staging deployment
3. **Manual Deployment**: Use GitHub Actions UI to deploy to production

### Deployment Triggers

```bash
# Deploy to staging (automatic on main branch push)
git push origin main

# Deploy to production (manual via GitHub Actions)
# Go to Actions > Deploy to Production > Run workflow
```

## 🐛 Troubleshooting

### Common Issues

#### Service Won't Start
```bash
# Check logs
docker-compose logs [service-name]

# Check resource usage
docker stats

# Restart specific service
docker-compose restart [service-name]
```

#### Database Connection Issues
```bash
# Check database status
docker-compose exec db mysqladmin ping -h localhost

# Check database logs
docker-compose logs db

# Reset database (WARNING: Data loss)
docker-compose down
sudo rm -rf /opt/supermon-ng/data/mysql/*
docker-compose up -d
```

#### SSL Certificate Issues
```bash
# Check certificate validity
openssl x509 -in docker/nginx/ssl/supermon.crt -text -noout

# Regenerate self-signed certificate
rm docker/nginx/ssl/supermon.*
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout docker/nginx/ssl/supermon.key \
    -out docker/nginx/ssl/supermon.crt \
    -subj "/C=US/ST=State/L=City/O=Organization/CN=your-domain.com"
docker-compose restart nginx
```

### Performance Optimization

#### Database Optimization
```sql
-- Check slow queries
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'long_query_time';

-- Optimize tables
OPTIMIZE TABLE users, sessions, audit_log, node_status;
```

#### Application Optimization
```bash
# Enable OPcache
docker-compose exec supermon-ng php -m | grep opcache

# Check memory usage
docker-compose exec supermon-ng php -i | grep memory_limit

# Monitor performance
docker-compose exec supermon-ng php -r "echo 'Memory: ' . memory_get_usage(true) . PHP_EOL;"
```

## 📈 Scaling Considerations

### Horizontal Scaling

For high-traffic deployments:

1. **Load Balancer**: Use HAProxy or Nginx upstream
2. **Database**: Consider MySQL replication or clustering
3. **Caching**: Implement Redis clustering
4. **Storage**: Use shared storage (NFS, S3)

### Vertical Scaling

Increase server resources:
- **CPU**: 4+ cores for high concurrency
- **RAM**: 8GB+ for large datasets
- **Storage**: SSD with 100GB+ for logs and data

## 🔍 Maintenance

### Regular Maintenance Tasks

```bash
# Weekly tasks
docker system prune -f
docker image prune -f

# Monthly tasks
docker-compose pull
docker-compose up -d

# Quarterly tasks
Review and rotate logs
Update SSL certificates
Review security configurations
```

### Log Management

```bash
# Configure log rotation
sudo tee /etc/logrotate.d/supermon-ng << EOF
/opt/supermon-ng/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
EOF
```

## 📞 Support

For deployment issues:
1. Check the troubleshooting section above
2. Review application logs: `docker-compose logs -f`
3. Check system resources: `htop`, `df -h`
4. Verify network connectivity: `curl -I http://localhost/health.php`

For additional support, please refer to the main documentation or create an issue in the project repository.
