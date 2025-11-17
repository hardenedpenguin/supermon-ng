# Apache WebSocket Configuration - Simple Fix

## What to Remove

Remove ANY existing WebSocket proxy rules like:
```apache
RewriteEngine On
RewriteCond %{HTTP:Upgrade} =websocket [NC]
RewriteRule ^/supermon-ng/ws/(\d+)$ ws://localhost:81$1 [P,L]
ProxyPassReverse /supermon-ng/ws/ ws://localhost:8105
```

## What to Add

Add these FOUR lines in your `<VirtualHost *:443>` block, **AFTER** the API ProxyPass and **BEFORE** the Alias directives:

```apache
RewriteEngine On
RewriteCond %{HTTP:Upgrade} =websocket [NC]
RewriteCond %{HTTP:Connection} =Upgrade [NC]
RewriteRule ^/supermon-ng/ws/(.+)$ ws://localhost:8105/supermon-ng/ws/$1 [P,L]
ProxyPassReverse /supermon-ng/ws/ ws://localhost:8105/supermon-ng/ws/
```

**IMPORTANT**: Use `RewriteRule` with `[P]` flag, NOT `ProxyPass` with `upgrade=websocket`. The `ProxyPass` method doesn't work reliably for WebSocket connections.

## Complete Example

```apache
<VirtualHost *:443>
    ServerName sm.w5gle.us
    DocumentRoot /var/www/html
    
    ProxyPreserveHost On
    
    # API proxy
    ProxyPass /supermon-ng/api http://localhost:8000/api
    ProxyPassReverse /supermon-ng/api http://localhost:8000/api
    
    # WebSocket proxy (ADD THESE FOUR LINES)
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteCond %{HTTP:Connection} =Upgrade [NC]
    RewriteRule ^/supermon-ng/ws/(.+)$ ws://localhost:8105/supermon-ng/ws/$1 [P,L]
    ProxyPassReverse /supermon-ng/ws/ ws://localhost:8105/supermon-ng/ws/
    
    # Alias (must come after ProxyPass)
    Alias /supermon-ng /var/www/html/supermon-ng/public
    
    # ... rest of config ...
</VirtualHost>
```

## Apply Changes

```bash
# Enable required modules
sudo a2enmod proxy
sudo a2enmod proxy_wstunnel
sudo a2enmod rewrite

# Test configuration
sudo apache2ctl configtest

# Reload Apache
sudo systemctl reload apache2
```

**Note**: The `RewriteRule` with `[P]` flag requires `mod_proxy` and `mod_rewrite` to be enabled. The `[P]` flag tells Apache to proxy the request instead of redirecting it.

