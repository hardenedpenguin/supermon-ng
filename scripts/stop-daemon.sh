#!/bin/bash

# Supermon-NG Daemon Stop Script
# This script stops both backend and frontend servers

set -e

# Configuration
BACKEND_PORT=8000
FRONTEND_PORT=4173

echo "🛑 Stopping Supermon-NG servers..."

# Stop Backend Server
echo "🔧 Stopping backend server..."
if pkill -f "php -S localhost:$BACKEND_PORT"; then
    echo "✅ Backend server stopped"
else
    echo "ℹ️  Backend server was not running"
fi

# Stop Frontend Server
echo "🎨 Stopping frontend server..."
if pkill -f "vite preview"; then
    echo "✅ Frontend server stopped"
else
    echo "ℹ️  Frontend server was not running"
fi

# Wait a moment for processes to fully stop
sleep 2

# Check if any processes are still running
if pgrep -f "php -S localhost:$BACKEND_PORT" > /dev/null; then
    echo "⚠️  Backend server is still running, force killing..."
    pkill -9 -f "php -S localhost:$BACKEND_PORT"
fi

if pgrep -f "vite preview" > /dev/null; then
    echo "⚠️  Frontend server is still running, force killing..."
    pkill -9 -f "vite preview"
fi

echo ""
echo "🎉 All Supermon-NG servers stopped successfully!"
echo ""
echo "To start servers again: ./scripts/start-daemon.sh"
