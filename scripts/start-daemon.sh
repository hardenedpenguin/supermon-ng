#!/bin/bash

# Supermon-NG Daemon Startup Script
# This script starts both backend and frontend servers in daemon mode

set -e

# Configuration
PROJECT_ROOT="/var/www/html/supermon-ng"
BACKEND_PORT=8000
FRONTEND_PORT=4173
LOG_DIR="$PROJECT_ROOT/logs"

# Create logs directory if it doesn't exist
mkdir -p "$LOG_DIR"

echo "🚀 Starting Supermon-NG servers in daemon mode..."

# Kill any existing processes on these ports
echo "🔄 Stopping any existing processes..."
pkill -f "php -S localhost:$BACKEND_PORT" || true
pkill -f "vite preview" || true

# Start Backend Server
echo "🔧 Starting backend server on port $BACKEND_PORT..."
cd "$PROJECT_ROOT"
nohup php -S localhost:$BACKEND_PORT -t public public/index.php > "$LOG_DIR/backend.log" 2>&1 &
BACKEND_PID=$!
echo "✅ Backend started with PID: $BACKEND_PID"

# Wait a moment for backend to initialize
sleep 2

# Start Frontend Server
echo "🎨 Starting frontend server on port $FRONTEND_PORT..."
cd "$PROJECT_ROOT/frontend"
nohup npm run preview > "$LOG_DIR/frontend.log" 2>&1 &
FRONTEND_PID=$!
echo "✅ Frontend started with PID: $FRONTEND_PID"

# Wait for servers to start
echo "⏳ Waiting for servers to initialize..."
sleep 5

# Check if servers are running
if pgrep -f "php -S localhost:$BACKEND_PORT" > /dev/null; then
    echo "✅ Backend server is running on http://localhost:$BACKEND_PORT"
else
    echo "❌ Backend server failed to start"
    exit 1
fi

if pgrep -f "vite preview" > /dev/null; then
    echo "✅ Frontend server is running on http://localhost:$FRONTEND_PORT"
else
    echo "❌ Frontend server failed to start"
    exit 1
fi

echo ""
echo "🎉 Supermon-NG servers started successfully!"
echo "📊 Backend:  http://localhost:$BACKEND_PORT"
echo "🎨 Frontend: http://localhost:$FRONTEND_PORT"
echo "📝 Logs:     $LOG_DIR/"
echo ""
echo "To stop servers: ./scripts/stop-daemon.sh"
echo "To view logs:    tail -f $LOG_DIR/backend.log"
echo "                 tail -f $LOG_DIR/frontend.log"
