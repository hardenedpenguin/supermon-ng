#!/bin/bash

# Android SDK Setup Script for SupermonNG Mobile App
set -e

echo "🚀 Setting up Android SDK for SupermonNG Mobile App..."

# Create Android SDK directory
ANDROID_HOME="$HOME/android-sdk"
mkdir -p "$ANDROID_HOME"

# Download Android command line tools
echo "📥 Downloading Android command line tools..."
cd "$ANDROID_HOME"
wget -q https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip

# Extract command line tools
echo "📦 Extracting command line tools..."
unzip -q commandlinetools-linux-11076708_latest.zip
rm commandlinetools-linux-11076708_latest.zip

# Create proper directory structure
mkdir -p cmdline-tools/latest
mv cmdline-tools/* cmdline-tools/latest/ 2>/dev/null || true

# Set environment variables
export ANDROID_HOME="$ANDROID_HOME"
export PATH="$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools"

# Accept licenses
echo "📋 Accepting Android SDK licenses..."
yes | "$ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager" --licenses > /dev/null 2>&1 || true

# Install required SDK components
echo "🔧 Installing Android SDK components..."
"$ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager" \
    "platform-tools" \
    "platforms;android-34" \
    "platforms;android-33" \
    "platforms;android-24" \
    "build-tools;34.0.0" \
    "build-tools;33.0.2"

# Download Gradle
echo "📥 Downloading Gradle..."
cd /tmp
wget -q https://services.gradle.org/distributions/gradle-8.4-bin.zip
unzip -q gradle-8.4-bin.zip
sudo mv gradle-8.4 /opt/gradle
rm gradle-8.4-bin.zip

# Add Gradle to PATH
export PATH="$PATH:/opt/gradle/bin"

echo "✅ Android SDK setup complete!"
echo ""
echo "Environment variables to add to your shell profile:"
echo "export ANDROID_HOME=\"$ANDROID_HOME\""
echo "export PATH=\"\$PATH:\$ANDROID_HOME/cmdline-tools/latest/bin:\$ANDROID_HOME/platform-tools:/opt/gradle/bin\""
echo ""
echo "To build the app, run:"
echo "cd /var/www/html/supermon-ng/mobile-apps/android"
echo "export ANDROID_HOME=\"$ANDROID_HOME\""
echo "export PATH=\"\$PATH:\$ANDROID_HOME/cmdline-tools/latest/bin:\$ANDROID_HOME/platform-tools:/opt/gradle/bin\""
echo "./gradlew assembleDebug"
