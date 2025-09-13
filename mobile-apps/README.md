# SupermonNG Mobile Apps

This directory contains mobile applications for controlling AllStar nodes through the SupermonNG system.

## Android App

A native Android application built with:
- **Kotlin** - Primary programming language
- **Jetpack Compose** - Modern UI toolkit
- **Hilt** - Dependency injection
- **Retrofit** - HTTP client for API communication
- **Coroutines** - Asynchronous programming
- **DataStore** - Local data persistence

### Features

- **Authentication** - Secure login with session management
- **Node Management** - View, connect, disconnect, and monitor AllStar nodes
- **Real-time Status** - Live node status updates
- **Offline Support** - Cached data for offline viewing
- **Modern UI** - Material Design 3 with dark/light themes

### Project Structure

```
android/
├── app/
│   ├── src/main/java/com/supermonng/mobile/
│   │   ├── data/           # Data layer (API, repository, local storage)
│   │   ├── domain/         # Domain layer (models, use cases, repository interfaces)
│   │   ├── ui/             # UI layer (screens, components, view models)
│   │   └── di/             # Dependency injection modules
│   └── build.gradle        # App-level build configuration
├── build.gradle            # Project-level build configuration
└── settings.gradle         # Project settings
```

### Architecture

The app follows **Clean Architecture** principles:

- **Presentation Layer** (UI) - Compose screens, ViewModels, and components
- **Domain Layer** - Business logic, use cases, and repository interfaces
- **Data Layer** - API services, repository implementations, and local storage

### API Integration

The app integrates with the SupermonNG REST API:

- **Authentication**: `/api/auth/login`, `/api/auth/logout`
- **Nodes**: `/api/nodes/*` for node management
- **System**: `/api/system/*` for system information
- **Configuration**: `/api/config/*` for user preferences

### Development Setup

1. **Prerequisites**:
   - Android Studio Arctic Fox or later
   - JDK 8 or later
   - Android SDK 24+ (minimum), 34 (target)

2. **Build the project**:
   ```bash
   cd android
   ./gradlew build
   ```

3. **Run on device/emulator**:
   ```bash
   ./gradlew installDebug
   ```

### Configuration

The app can be configured to connect to different SupermonNG servers:

- Default server: `https://sm.w5gle.us/api/`
- Configurable through app settings (future feature)

### Future Enhancements

- **iOS App** - Native iOS application using SwiftUI
- **Push Notifications** - Real-time alerts for node status changes
- **Audio Streaming** - Direct audio monitoring from nodes
- **Widgets** - Home screen widgets for quick node control
- **Biometric Authentication** - Fingerprint/Face ID login
- **Voice Commands** - Voice-activated node control

### Contributing

1. Follow the existing code style and architecture patterns
2. Add unit tests for new features
3. Update documentation for API changes
4. Test on multiple Android versions and screen sizes

### License

This project is part of the SupermonNG system and follows the same licensing terms.
