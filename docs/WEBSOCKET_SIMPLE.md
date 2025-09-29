# WebSocket Real-time Updates - Simple Guide

## What is WebSocket?

WebSocket provides **real-time updates** to your Supermon-NG interface without needing to refresh the page or wait for polling. Think of it like getting instant notifications instead of checking your email every few minutes.

## How It Works

### Before WebSocket (Old Way):
```
Your Browser → Asks Server "Any Updates?" → Server → Checks All Nodes → Response
(Every 5-10 seconds, even when nothing changed)
```

### With WebSocket (New Way):
```
Your Browser → Connects Once → Server → Only Sends Updates When Something Changes
(Instant updates only when needed)
```

## What You Get

✅ **Instant Updates** - See node status changes immediately  
✅ **Faster Interface** - No more waiting for page refreshes  
✅ **Less Server Load** - 80-95% reduction in server requests  
✅ **Better Experience** - Real-time monitoring like professional systems  

## How to Use

### 1. Start WebSocket Server
```bash
# Enable and start the WebSocket server
sudo systemctl enable supermon-ng-websocket
sudo systemctl start supermon-ng-websocket

# Check if it's running
sudo systemctl status supermon-ng-websocket
```

### 2. Access Your Interface
- Open your Supermon-NG web interface as usual
- The browser will automatically connect to WebSocket
- You'll see real-time updates immediately

### 3. No Configuration Needed
- **Remote nodes work exactly as before**
- **No changes needed on remote nodes**
- **No additional setup required**

## What Updates in Real-time

- 🔴 **Node Status** - Online/Offline changes
- 📡 **Key Status** - When nodes are keyed/unkeyed  
- 🌡️ **Temperature** - CPU temperature changes
- 📊 **System Info** - Server status updates
- 📋 **Menu Changes** - Configuration updates
- ⚠️ **Alerts** - System alerts and notifications

## Troubleshooting

### WebSocket Not Working?
```bash
# Check if WebSocket server is running
sudo systemctl status supermon-ng-websocket

# Check logs
sudo journalctl -u supermon-ng-websocket -f

# Restart if needed
sudo systemctl restart supermon-ng-websocket
```

### Still See Old Behavior?
- **Clear browser cache** and refresh the page
- **Check browser console** for WebSocket connection errors
- **Verify port 9091** is not blocked by firewall

### Connection Issues?
```bash
# Test WebSocket connection
curl -I http://your-server:9091

# Check if port is open
netstat -tlnp | grep 9091
```

## Technical Details (For Advanced Users)

### WebSocket Server
- **Port:** 9091 (configurable)
- **Protocol:** WebSocket (ws:// or wss://)
- **Authentication:** Uses same login as web interface
- **Security:** Same security as your web interface

### Browser Compatibility
- ✅ **Chrome** - Full support
- ✅ **Firefox** - Full support  
- ✅ **Safari** - Full support
- ✅ **Edge** - Full support
- ✅ **Mobile browsers** - Full support

### Network Requirements
- **Same network** as your Supermon-NG server
- **Port 9091** must be accessible
- **No firewall blocking** WebSocket connections

## Benefits Summary

| Feature | Before | With WebSocket |
|---------|--------|----------------|
| Update Speed | 5-10 seconds | Instant |
| Server Load | High (constant polling) | Low (event-driven) |
| User Experience | Wait for updates | Real-time monitoring |
| Network Usage | High | Low |
| Remote Node Setup | None | None (same as before) |

## That's It!

The WebSocket system is designed to be **simple and automatic**. Once you start the WebSocket server, everything works seamlessly without any additional configuration.

**Remote nodes continue to work exactly as they always have** - no changes needed on the remote side. The WebSocket system just makes your interface faster and more responsive!
