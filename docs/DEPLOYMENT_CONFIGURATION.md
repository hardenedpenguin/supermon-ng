# Supermon-ng Deployment & Configuration Guide

This guide covers various deployment scenarios and configuration options for Supermon-ng, including reverse proxy setups and integration with other services.

## 🚀 Deployment Options

### 1. Traditional LAMP Stack
See [TRADITIONAL_INSTALL.md](TRADITIONAL_INSTALL.md) for detailed instructions.

### 2. Docker Deployment
See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for Docker-based deployment.

### 3. Development Setup
See [DEVELOPMENT_SETUP.md](DEVELOPMENT_SETUP.md) for local development environment.

## 🔧 Configuration Guides

### Apache Configuration

#### Basic Apache Virtual Host
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/supermon-ng
    
    <Directory /var/www/html/supermon-ng>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/supermon-ng_error.log
    CustomLog ${APACHE_LOG_DIR}/supermon-ng_access.log combined
</VirtualHost>
```

#### SSL Configuration with Let's Encrypt
```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/html/supermon-ng
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem
    
    <Directory /var/www/html/supermon-ng>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/supermon-ng_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/supermon-ng_ssl_access.log combined
</VirtualHost>
```

### Nginx Configuration

#### Basic Nginx Reverse Proxy
```nginx
server {
    listen 80;
    server_name your-domain.com;
    
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## 🔗 Reverse Proxy Integrations

### HamClock Integration

HamClock is a popular ham radio clock application that can be integrated with Supermon-ng using Apache's reverse proxy capabilities.

#### Prerequisites
- HamClock running on a local network device (e.g., Raspberry Pi)
- Apache with `mod_proxy` and `mod_proxy_wstunnel` modules enabled
- Network access to the HamClock device

#### Apache Configuration

Add the following configuration to your Apache virtual host file (typically `/etc/apache2/sites-available/000-default.conf`):

```apache
# Enable required modules
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so

<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/supermon-ng
    
    # Supermon-ng application
    <Directory /var/www/html/supermon-ng>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Proxy for HamClock content
    <Location /hamclock/>
        ProxyPass http://10.0.0.5:8082/
        ProxyPassReverse http://10.0.0.5:8082/
    </Location>
    
    # Proxy for WebSocket
    <Location /hamclock/live-ws>
        ProxyPass ws://10.0.0.5:8082/live-ws
        ProxyPassReverse ws://10.0.0.5:8082/live-ws
    </Location>
    
    ErrorLog ${APACHE_LOG_DIR}/supermon-ng_error.log
    CustomLog ${APACHE_LOG_DIR}/supermon-ng_access.log combined
</VirtualHost>
```

#### Configuration Steps

1. **Enable Required Apache Modules:**
   ```bash
   sudo a2enmod proxy
   sudo a2enmod proxy_http
   sudo a2enmod proxy_wstunnel
   sudo systemctl reload apache2
   ```

2. **Update IP Address:**
   - Replace `10.0.0.5` with your HamClock device's actual IP address
   - Replace `8082` with the port HamClock is running on (default is usually 8082)

3. **Add Configuration to Apache:**
   - Edit `/etc/apache2/sites-available/000-default.conf`
   - Add the HamClock proxy configuration above
   - Save the file

4. **Test Configuration:**
   ```bash
   sudo apache2ctl configtest
   ```

5. **Reload Apache:**
   ```bash
   sudo systemctl reload apache2
   ```

#### Accessing HamClock

After configuration, you can access HamClock through:
- **Main Interface**: `http://your-domain.com/hamclock/`
- **WebSocket**: Automatically handled for live updates

#### SSL Configuration (Recommended)

For secure access, add the HamClock proxy to your SSL virtual host:

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/html/supermon-ng
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem
    
    # Supermon-ng application
    <Directory /var/www/html/supermon-ng>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Proxy for HamClock content (HTTPS)
    <Location /hamclock/>
        ProxyPass http://10.0.0.5:8082/
        ProxyPassReverse http://10.0.0.5:8082/
    </Location>
    
    # Proxy for WebSocket (WSS)
    <Location /hamclock/live-ws>
        ProxyPass ws://10.0.0.5:8082/live-ws
        ProxyPassReverse ws://10.0.0.5:8082/live-ws
    </Location>
    
    ErrorLog ${APACHE_LOG_DIR}/supermon-ng_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/supermon-ng_ssl_access.log combined
</VirtualHost>
```

#### Troubleshooting

**Common Issues:**

1. **Connection Refused:**
   - Verify HamClock is running on the specified IP and port
   - Check firewall settings on the HamClock device
   - Ensure network connectivity between web server and HamClock device

2. **WebSocket Connection Fails:**
   - Verify `mod_proxy_wstunnel` is enabled
   - Check that HamClock supports WebSocket connections
   - Review Apache error logs for specific error messages

3. **403 Forbidden:**
   - Check Apache proxy permissions
   - Verify the `Location` directive is properly configured
   - Ensure HamClock allows proxy connections

**Debugging Commands:**
```bash
# Check Apache configuration
sudo apache2ctl configtest

# View Apache error logs
sudo tail -f /var/log/apache2/error.log

# Test network connectivity to HamClock
ping 10.0.0.5
telnet 10.0.0.5 8082

# Check if modules are loaded
apache2ctl -M | grep proxy
```

### Other Reverse Proxy Examples

#### Grafana Integration
```apache
<Location /grafana/>
    ProxyPass http://localhost:3000/
    ProxyPassReverse http://localhost:3000/
</Location>
```

#### Prometheus Integration
```apache
<Location /prometheus/>
    ProxyPass http://localhost:9090/
    ProxyPassReverse http://localhost:9090/
</Location>
```

#### Node-RED Integration
```apache
<Location /nodered/>
    ProxyPass http://localhost:1880/
    ProxyPassReverse http://localhost:1880/
</Location>
```

## 🔒 Security Considerations

### Reverse Proxy Security

1. **Access Control:**
   ```apache
   <Location /hamclock/>
       ProxyPass http://10.0.0.5:8082/
       ProxyPassReverse http://10.0.0.5:8082/
       
       # Restrict access to specific IPs
       Require ip 192.168.1.0/24
       Require ip 10.0.0.0/8
   </Location>
   ```

2. **Authentication:**
   ```apache
   <Location /hamclock/>
       ProxyPass http://10.0.0.5:8082/
       ProxyPassReverse http://10.0.0.5:8082/
       
       # Require authentication
       AuthType Basic
       AuthName "HamClock Access"
       AuthUserFile /etc/apache2/.htpasswd
       Require valid-user
   </Location>
   ```

3. **Rate Limiting:**
   ```apache
   <Location /hamclock/>
       ProxyPass http://10.0.0.5:8082/
       ProxyPassReverse http://10.0.0.5:8082/
       
       # Rate limiting
       SetEnvIf Request_URI "^/hamclock/" hamclock_request
       LimitRequestBody 1048576
   </Location>
   ```

## 📝 Configuration Files

### Apache Configuration Files

| File | Purpose |
|------|---------|
| `/etc/apache2/sites-available/000-default.conf` | Default virtual host |
| `/etc/apache2/sites-available/supermon-ng.conf` | Custom virtual host |
| `/etc/apache2/mods-available/proxy.conf` | Proxy module configuration |
| `/etc/apache2/ports.conf` | Port configuration |

### Nginx Configuration Files

| File | Purpose |
|------|---------|
| `/etc/nginx/sites-available/default` | Default server block |
| `/etc/nginx/sites-available/supermon-ng` | Custom server block |
| `/etc/nginx/nginx.conf` | Main configuration |

## 🚀 Performance Optimization

### Apache Optimization

1. **Enable Compression:**
   ```apache
   LoadModule deflate_module modules/mod_deflate.so
   
   <Location />
       SetOutputFilter DEFLATE
       SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png)$ no-gzip dont-vary
   </Location>
   ```

2. **Enable Caching:**
   ```apache
   LoadModule expires_module modules/mod_expires.so
   
   <Location />
       ExpiresActive On
       ExpiresByType text/css "access plus 1 month"
       ExpiresByType application/javascript "access plus 1 month"
       ExpiresByType image/png "access plus 1 month"
       ExpiresByType image/jpg "access plus 1 month"
   </Location>
   ```

### Nginx Optimization

1. **Enable Gzip:**
   ```nginx
   gzip on;
   gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
   ```

2. **Enable Caching:**
   ```nginx
   location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
       expires 1M;
       add_header Cache-Control "public, immutable";
   }
   ```

## 📚 Additional Resources

- [Apache Proxy Documentation](https://httpd.apache.org/docs/2.4/mod/mod_proxy.html)
- [Nginx Proxy Documentation](https://nginx.org/en/docs/http/ngx_http_proxy_module.html)
- [HamClock Documentation](https://github.com/k3ng/k3ng_hamclock)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)

## 🤝 Support

For additional help with deployment and configuration:

1. Check the [main README.md](../README.md) for basic setup
2. Review other documentation files in this directory
3. Search existing issues on GitHub
4. Create a new issue with detailed information about your setup
