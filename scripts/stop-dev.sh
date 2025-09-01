#!/bin/bash

# Supermon-NG Development Stop Script
# This script stops both backend and frontend development servers

set -e

# Configuration
BACKEND_PORT=8000
FRONTEND_PORT=5179

echo "🛑 Stopping Supermon-NG development servers..."

# Stop Backend Server
echo "🔧 Stopping backend server..."
if pkill -f "php -S localhost:$BACKEND_PORT"; then
    echo "✅ Backend server stopped"
else
    echo "ℹ️  Backend server was not running"
fi

# Stop Frontend Development Server
echo "🎨 Stopping frontend development server..."
if pkill -f "vite"; then
    echo "✅ Frontend development server stopped"
else
    echo "ℹ️  Frontend development server was not running"
fi

# Wait a moment for processes to fully stop
sleep 2

# Check if any processes are still running
if pgrep -f "php -S localhost:$BACKEND_PORT" > /dev/null; then
    echo "⚠️  Backend server is still running, force killing..."
    pkill -9 -f "php -S localhost:$BACKEND_PORT"
fi

if pgrep -f "vite" > /dev/null; then
    echo "⚠️  Frontend development server is still running, force killing..."
    pkill -9 -f "vite"
fi

echo ""
echo "🎉 All Supermon-NG development servers stopped successfully!"
echo ""
echo "To start development servers: ./scripts/start-dev.sh"
echo "To start production servers: ./scripts/start-daemon.sh"
