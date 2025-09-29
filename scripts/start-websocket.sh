#!/bin/bash

# Supermon-ng WebSocket Server Startup Script
# Starts the WebSocket server for real-time updates

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
WEBSOCKET_PORT=${WEBSOCKET_PORT:-9091}
WEBSOCKET_HOST=${WEBSOCKET_HOST:-localhost}
APP_DIR=${APP_DIR:-/var/www/html/supermon-ng}
PID_FILE="$APP_DIR/logs/supermon-ng-websocket.pid"
LOG_FILE="$APP_DIR/logs/supermon-ng-websocket.log"

echo -e "${BLUE}🚀 Starting Supermon-ng WebSocket Server${NC}"
echo "========================================"

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    echo -e "${YELLOW}⚠️  Warning: Running as root. Consider using a non-root user.${NC}"
fi

# Ensure logs directory exists and has proper permissions
mkdir -p "$APP_DIR/logs"
chmod 755 "$APP_DIR/logs"

# Check if WebSocket server is already running
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    if ps -p "$PID" > /dev/null 2>&1; then
        echo -e "${YELLOW}⚠️  WebSocket server is already running (PID: $PID)${NC}"
        echo "To stop it, run: sudo systemctl stop supermon-ng-websocket"
        exit 1
    else
        echo -e "${BLUE}ℹ️  Removing stale PID file${NC}"
        rm -f "$PID_FILE"
    fi
fi

# Check if port is already in use
if lsof -i :$WEBSOCKET_PORT > /dev/null 2>&1; then
    echo -e "${YELLOW}⚠️  Port $WEBSOCKET_PORT is already in use${NC}"
    echo "   Killing existing processes on port $WEBSOCKET_PORT..."
    
    # Kill any processes using the port
    PIDS=$(lsof -ti :$WEBSOCKET_PORT)
    if [ -n "$PIDS" ]; then
        echo "$PIDS" | xargs kill -9 2>/dev/null || true
        sleep 2
    fi
    
    # Verify port is free
    if lsof -i :$WEBSOCKET_PORT > /dev/null 2>&1; then
        echo -e "${RED}❌ Failed to free port $WEBSOCKET_PORT${NC}"
        echo "   Please manually stop the process using this port"
        exit 1
    else
        echo -e "${GREEN}✅ Port $WEBSOCKET_PORT is now free${NC}"
    fi
fi

# Check if required directories exist
if [ ! -d "$APP_DIR" ]; then
    echo -e "${RED}❌ Application directory not found: $APP_DIR${NC}"
    exit 1
fi

# Check if composer dependencies are installed
if [ ! -d "$APP_DIR/vendor" ]; then
    echo -e "${RED}❌ Composer dependencies not found. Run 'composer install' first.${NC}"
    exit 1
fi

# Check if Ratchet is installed
if ! php -r "require '$APP_DIR/vendor/autoload.php'; class_exists('Ratchet\Server\IoServer');" 2>/dev/null; then
    echo -e "${RED}❌ Ratchet WebSocket library not found.${NC}"
    echo "Install it with: composer require cboden/ratchet"
    exit 1
fi

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Change to application directory
cd "$APP_DIR"

# Set environment variables
export WEBSOCKET_PORT="$WEBSOCKET_PORT"
export WEBSOCKET_HOST="$WEBSOCKET_HOST"
export WEBSOCKET_SECURE="false"

echo -e "${BLUE}📋 Configuration:${NC}"
echo "   Host: $WEBSOCKET_HOST"
echo "   Port: $WEBSOCKET_PORT"
echo "   App Directory: $APP_DIR"
echo "   Log File: $LOG_FILE"
echo "   PID File: $PID_FILE"
echo ""

# Start WebSocket server in background
echo -e "${BLUE}🚀 Starting WebSocket server...${NC}"

# Create PHP script to start WebSocket server
cat > /tmp/start-websocket.php << EOF
<?php
require_once '$APP_DIR/vendor/autoload.php';

use SupermonNg\Services\WebSocketService;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create logger
\$logger = new Logger('websocket');
\$logger->pushHandler(new StreamHandler('$LOG_FILE', Logger::INFO));

// Create WebSocket service
\$webSocketService = new WebSocketService(\$logger);

// Start server
\$webSocketService->start($WEBSOCKET_PORT);
EOF

# Start the server
nohup php /tmp/start-websocket.php > "$LOG_FILE" 2>&1 &
SERVER_PID=$!

# Save PID
echo $SERVER_PID > "$PID_FILE"

# Wait a moment for server to start
sleep 2

# Check if server started successfully
if ps -p "$SERVER_PID" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ WebSocket server started successfully${NC}"
    echo "   PID: $SERVER_PID"
    echo "   URL: ws://$WEBSOCKET_HOST:$WEBSOCKET_PORT"
    echo "   Log: $LOG_FILE"
    echo ""
    echo -e "${BLUE}📋 Management Commands:${NC}"
    echo "   Stop:  sudo systemctl stop supermon-ng-websocket"
    echo "   Status: sudo systemctl status supermon-ng-websocket"
    echo "   Logs:  tail -f $LOG_FILE"
    echo ""
    echo -e "${GREEN}🎉 WebSocket server is running and ready for connections!${NC}"
else
    echo -e "${RED}❌ Failed to start WebSocket server${NC}"
    echo "Check the log file for errors: $LOG_FILE"
    rm -f "$PID_FILE"
    exit 1
fi

# Clean up temporary file
rm -f /tmp/start-websocket.php
