#!/bin/bash

# SupermonNG Android App - APK Installation Script
# This script helps you install the APK on your Android device

echo "🚀 SupermonNG Android App - APK Installation"
echo "=============================================="

# Check if ADB is available
if ! command -v adb &> /dev/null; then
    echo "❌ ADB not found. Please install Android SDK Platform Tools:"
    echo "   https://developer.android.com/studio/releases/platform-tools"
    echo ""
    echo "📱 Alternative: Manual Installation"
    echo "1. Copy the APK to your Android device"
    echo "2. Enable 'Install from Unknown Sources' in Settings"
    echo "3. Tap the APK file to install"
    echo ""
    echo "📁 APK Location: $(pwd)/supermon-ng-android-debug.apk"
    exit 1
fi

# Check for connected devices
echo "🔍 Checking for connected Android devices..."
DEVICES=$(adb devices | grep -v "List of devices" | grep -v "^$" | wc -l)

if [ $DEVICES -eq 0 ]; then
    echo "❌ No Android devices found!"
    echo ""
    echo "📱 Please:"
    echo "1. Connect your Android device via USB"
    echo "2. Enable USB Debugging in Developer Options"
    echo "3. Allow USB Debugging when prompted"
    echo "4. Run this script again"
    echo ""
    echo "📁 APK Location: $(pwd)/supermon-ng-android-debug.apk"
    exit 1
fi

echo "✅ Found $DEVICES connected device(s)"
adb devices

# Check if APK exists
APK_FILE="supermon-ng-android-debug.apk"
if [ ! -f "$APK_FILE" ]; then
    echo "❌ APK file not found: $APK_FILE"
    echo "Please make sure you're in the correct directory"
    exit 1
fi

echo "📱 Installing SupermonNG Android App..."
echo "APK: $APK_FILE"

# Install the APK
if adb install -r "$APK_FILE"; then
    echo ""
    echo "🎉 SUCCESS! SupermonNG Android App installed!"
    echo ""
    echo "📱 To launch the app:"
    echo "1. Find 'SupermonNG' in your app drawer"
    echo "2. Tap to open"
    echo "3. Login with:"
    echo "   Username: admin"
    echo "   Password: password"
    echo ""
    echo "🔧 App Features:"
    echo "• Login authentication"
    echo "• Node dashboard with sample data"
    echo "• Connect/Disconnect/Monitor buttons"
    echo "• Modern Material Design UI"
    echo ""
    echo "📋 Sample Nodes:"
    echo "• Node 12345 - W5GLE (Main Repeater) - Austin, TX - Online"
    echo "• Node 67890 - W5ABC (Backup Repeater) - Houston, TX - Offline"
    echo ""
    echo "✨ Enjoy testing your SupermonNG Android app!"
else
    echo ""
    echo "❌ Installation failed!"
    echo ""
    echo "🔧 Troubleshooting:"
    echo "1. Make sure USB Debugging is enabled"
    echo "2. Try: adb kill-server && adb start-server"
    echo "3. Check device storage space"
    echo "4. Try manual installation (copy APK to device)"
    echo ""
    echo "📁 APK Location: $(pwd)/$APK_FILE"
fi
