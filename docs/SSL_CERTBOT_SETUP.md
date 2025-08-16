# SSL/Certbot Setup Guide for Supermon-ng

This guide shows how to set up SSL certificates using Let's Encrypt/Certbot for Supermon-ng.

## 🚀 Quick SSL Setup

### Prerequisites
- Domain name pointing to your server
- Ports 80 and 443 open on your firewall
- Supermon-ng running on your server

## 📋 Step-by-Step SSL Setup

### Step 1: Install Certbot

#### On Ubuntu/Debian:
```bash
# Install Certbot
sudo apt update
sudo apt install certbot python3-certbot-apache

# For Nginx (if using Nginx instead of Apache)
sudo apt install python3-certbot-nginx
```

#### On CentOS/RHEL:
```bash
# Install EPEL repository
sudo yum install epel-release

# Install Certbot
sudo yum install certbot python3-certbot-apache
```

### Step 2: Stop Services Temporarily

```bash
# Stop Supermon-ng services to free up port 80
sudo systemctl stop apache2  # If using Apache
sudo systemctl stop nginx     # If using Nginx
docker-compose down          # If using Docker
```

### Step 3: Generate SSL Certificate

#### Option A: Standalone Mode (Recommended for Docker)
```bash
# Generate certificate using standalone mode
sudo certbot certonly --standalone -d your-domain.com -d www.your-domain.com

# For multiple domains
sudo certbot certonly --standalone -d your-domain.com -d www.your-domain.com -d api.your-domain.com
```

#### Option B: Webroot Mode (For Traditional LAMP)
```bash
# Create webroot directory
sudo mkdir -p /var/www/html/.well-known/acme-challenge

# Generate certificate
sudo certbot certonly --webroot -w /var/www/html -d your-domain.com -d www.your-domain.com
```

### Step 4: Copy Certificates to Docker Directory

```bash
# Create SSL directory
sudo mkdir -p docker/nginx/ssl

# Copy certificates
sudo cp /etc/letsencrypt/live/your-domain.com/fullchain.pem docker/nginx/ssl/supermon.crt
sudo cp /etc/letsencrypt/live/your-domain.com/privkey.pem docker/nginx/ssl/supermon.key

# Set proper permissions
sudo chown -R $USER:$USER docker/nginx/ssl/
sudo chmod 600 docker/nginx/ssl/supermon.key
sudo chmod 644 docker/nginx/ssl/supermon.crt
```

### Step 5: Update Environment Configuration

Edit your `.env` file:
```env
# SSL/TLS Configuration
SSL_CERT_PATH=/etc/nginx/ssl/supermon.crt
SSL_KEY_PATH=/etc/nginx/ssl/supermon.key
SSL_PROTOCOLS=TLSv1.2,TLSv1.3

# Application URL (update to HTTPS)
APP_URL=https://your-domain.com
```

### Step 6: Configure Nginx for SSL

Update your Nginx configuration in `docker/nginx/nginx.conf`:

```nginx
# Update the server_name in the HTTPS server block
server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;  # Update this line

    # SSL Configuration (already configured)
    ssl_certificate /etc/nginx/ssl/supermon.crt;
    ssl_certificate_key /etc/nginx/ssl/supermon.key;
    # ... rest of configuration
}
```

### Step 7: Restart Services

```bash
# If using Docker
docker-compose down
docker-compose up -d

# If using traditional setup
sudo systemctl restart apache2
sudo systemctl restart nginx
```

### Step 8: Test SSL Configuration

```bash
# Test SSL certificate
openssl s_client -connect your-domain.com:443 -servername your-domain.com

# Check SSL Labs grade
curl -s "https://api.ssllabs.com/api/v3/analyze?host=your-domain.com" | jq '.endpoints[0].grade'

# Test HTTPS access
curl -I https://your-domain.com
```

## 🔄 Automatic Certificate Renewal

### Set Up Auto-Renewal

```bash
# Test renewal process
sudo certbot renew --dry-run

# Add to crontab for automatic renewal
sudo crontab -e

# Add this line to run twice daily
0 12 * * * /usr/bin/certbot renew --quiet
```

### Create Renewal Script for Docker

Create a script to handle certificate renewal with Docker:

```bash
# Create renewal script
sudo tee /opt/supermon-ng/renew-ssl.sh << 'EOF'
#!/bin/bash

# Renew certificates
certbot renew --quiet

# Copy renewed certificates
cp /etc/letsencrypt/live/your-domain.com/fullchain.pem /opt/supermon-ng/docker/nginx/ssl/supermon.crt
cp /etc/letsencrypt/live/your-domain.com/privkey.pem /opt/supermon-ng/docker/nginx/ssl/supermon.key

# Set permissions
chown -R $USER:$USER /opt/supermon-ng/docker/nginx/ssl/
chmod 600 /opt/supermon-ng/docker/nginx/ssl/supermon.key
chmod 644 /opt/supermon-ng/docker/nginx/ssl/supermon.crt

# Reload Nginx container
cd /opt/supermon-ng
docker-compose exec nginx nginx -s reload

echo "SSL certificates renewed and reloaded"
EOF

# Make script executable
sudo chmod +x /opt/supermon-ng/renew-ssl.sh

# Update crontab to use the script
sudo crontab -e
# Add: 0 12 * * * /opt/supermon-ng/renew-ssl.sh
```

## 🔧 SSL Configuration Options

### Strong SSL Configuration

Update your Nginx SSL configuration for better security:

```nginx
# SSL Configuration
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;
ssl_session_tickets off;

# Security headers
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Frame-Options DENY always;
add_header X-Content-Type-Options nosniff always;
add_header X-XSS-Protection "1; mode=block" always;
```

### OCSP Stapling

Add OCSP stapling for better performance:

```nginx
# OCSP Stapling
ssl_stapling on;
ssl_stapling_verify on;
ssl_trusted_certificate /etc/nginx/ssl/supermon.crt;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
```

## 🐛 Troubleshooting SSL Issues

### Common Issues and Solutions

#### Certificate Not Found
```bash
# Check certificate location
sudo ls -la /etc/letsencrypt/live/your-domain.com/

# Verify certificate validity
sudo certbot certificates

# Check certificate expiration
openssl x509 -in /etc/letsencrypt/live/your-domain.com/cert.pem -text -noout | grep "Not After"
```

#### Nginx SSL Errors
```bash
# Test Nginx configuration
docker-compose exec nginx nginx -t

# Check Nginx logs
docker-compose logs nginx

# Verify SSL certificate in container
docker-compose exec nginx openssl x509 -in /etc/nginx/ssl/supermon.crt -text -noout
```

#### Port 80/443 Issues
```bash
# Check if ports are in use
sudo netstat -tlnp | grep :80
sudo netstat -tlnp | grep :443

# Check firewall
sudo ufw status
sudo iptables -L
```

#### Certificate Renewal Issues
```bash
# Test renewal manually
sudo certbot renew --dry-run

# Check renewal logs
sudo journalctl -u certbot.timer

# Force renewal
sudo certbot renew --force-renewal
```

## 📊 SSL Monitoring

### Monitor Certificate Expiration

Create a monitoring script:

```bash
# Create monitoring script
sudo tee /opt/supermon-ng/check-ssl.sh << 'EOF'
#!/bin/bash

DOMAIN="your-domain.com"
CERT_FILE="/etc/letsencrypt/live/$DOMAIN/cert.pem"
DAYS_WARNING=30

if [ -f "$CERT_FILE" ]; then
    EXPIRY=$(openssl x509 -in "$CERT_FILE" -enddate -noout | cut -d= -f2)
    EXPIRY_EPOCH=$(date -d "$EXPIRY" +%s)
    CURRENT_EPOCH=$(date +%s)
    DAYS_LEFT=$(( ($EXPIRY_EPOCH - $CURRENT_EPOCH) / 86400 ))

    if [ $DAYS_LEFT -lt $DAYS_WARNING ]; then
        echo "WARNING: SSL certificate for $DOMAIN expires in $DAYS_LEFT days"
        # Add notification logic here
    else
        echo "SSL certificate for $DOMAIN is valid for $DAYS_LEFT days"
    fi
else
    echo "ERROR: SSL certificate not found for $DOMAIN"
fi
EOF

# Make executable and add to crontab
sudo chmod +x /opt/supermon-ng/check-ssl.sh
sudo crontab -e
# Add: 0 8 * * * /opt/supermon-ng/check-ssl.sh
```

## 🔒 Security Best Practices

### SSL Security Checklist

- [ ] Use TLS 1.2+ only
- [ ] Enable HSTS headers
- [ ] Configure secure cipher suites
- [ ] Enable OCSP stapling
- [ ] Set up automatic renewal
- [ ] Monitor certificate expiration
- [ ] Use strong private keys
- [ ] Implement proper redirects

### Additional Security Headers

Add these headers to your Nginx configuration:

```nginx
# Security Headers
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header X-Frame-Options DENY always;
add_header X-Content-Type-Options nosniff always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' ws: wss:;" always;
```

## 📚 Additional Resources

- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [Certbot Documentation](https://certbot.eff.org/docs/)
- [SSL Labs SSL Test](https://www.ssllabs.com/ssltest/)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)

This setup will give you a secure, automatically-renewing SSL certificate for your Supermon-ng installation!
