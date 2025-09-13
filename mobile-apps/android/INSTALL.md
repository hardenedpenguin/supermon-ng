# SupermonNG Android App - Installation Guide

## 📱 **APK Ready for Testing!**

The SupermonNG Android app has been successfully built and is ready for installation on your Android device.

### **APK Details:**
- **File**: `app-debug.apk`
- **Size**: 8.3MB
- **Location**: `app/build/outputs/apk/debug/app-debug.apk`
- **Version**: 1.0.0 (Debug)
- **Target SDK**: Android 14 (API 34)
- **Minimum SDK**: Android 7.0 (API 24)

### **Installation Instructions:**

#### **Method 1: Direct Transfer**
1. **Copy the APK to your Android device:**
   ```bash
   # From the server, copy to your computer first
   scp anarchy@mustang:/var/www/html/supermon-ng/mobile-apps/android/app/build/outputs/apk/debug/app-debug.apk ./
   
   # Then transfer to your Android device via USB, email, or cloud storage
   ```

2. **Enable Unknown Sources:**
   - Go to **Settings** > **Security** > **Unknown Sources** (or **Install Unknown Apps**)
   - Enable installation from your file manager or browser

3. **Install the APK:**
   - Open the APK file on your Android device
   - Tap **Install** when prompted
   - Grant any necessary permissions

#### **Method 2: ADB Installation (if you have ADB setup)**
```bash
# Connect your Android device via USB with USB debugging enabled
adb install app/build/outputs/apk/debug/app-debug.apk
```

### **App Features:**

#### **✅ Working Features:**
- **Login Screen** - Username/password authentication
- **Node Dashboard** - View configured AllStar nodes
- **Node Control** - Connect, disconnect, monitor nodes
- **Status Display** - Real-time node status indicators
- **Modern UI** - Material Design with dark/light themes

#### **🔧 Test Credentials:**
- **Username**: `admin`
- **Password**: `password`

#### **📋 Sample Data:**
The app currently shows sample nodes for testing:
- **Node 12345** - W5GLE (Main Repeater) - Austin, TX - Online
- **Node 67890** - W5ABC (Backup Repeater) - Houston, TX - Offline

### **Testing the App:**

1. **Launch the app** on your Android device
2. **Login** with the test credentials (admin/password)
3. **View nodes** - You should see the sample nodes listed
4. **Test controls** - Try the Connect/Disconnect/Monitor buttons
5. **Check status** - Verify the online/offline status indicators

### **Next Steps:**

#### **For Real API Integration:**
1. **Update server URL** in the app configuration
2. **Implement actual API calls** to your SupermonNG server
3. **Add real authentication** with your server credentials
4. **Configure node data** from your actual AllStar setup

#### **For Production Release:**
1. **Sign the APK** with a release key
2. **Optimize the build** (remove debug code, enable ProGuard)
3. **Test on multiple devices** and Android versions
4. **Submit to Google Play Store** (optional)

### **Troubleshooting:**

#### **Installation Issues:**
- **"App not installed"**: Check if you have enough storage space
- **"Unknown sources blocked"**: Enable installation from unknown sources
- **"Package appears to be corrupt"**: Re-download the APK file

#### **App Issues:**
- **Login fails**: Use the test credentials (admin/password)
- **No nodes shown**: This is expected - the app shows sample data
- **Buttons don't work**: This is expected - they show success messages for now

### **Development Notes:**

This is a **debug build** with the following characteristics:
- **Sample data** instead of real API calls
- **Simplified authentication** (hardcoded credentials)
- **Mock responses** for node control operations
- **Full logging enabled** for debugging

The app demonstrates the complete UI/UX flow and is ready for integration with the actual SupermonNG API.

### **Support:**

If you encounter any issues:
1. Check the Android device logs for error messages
2. Verify the APK file integrity
3. Ensure your device meets the minimum requirements (Android 7.0+)
4. Try reinstalling the app

---

**🎉 Congratulations! Your SupermonNG Android app is ready for testing!**
