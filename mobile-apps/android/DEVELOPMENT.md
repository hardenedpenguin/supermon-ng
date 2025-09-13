# SupermonNG Android App - Development Guide

## Quick Start

### Prerequisites
- Android Studio Arctic Fox (2020.3.1) or later
- JDK 8 or later
- Android SDK 24+ (minimum), 34 (target)
- Git

### Setup
1. Open Android Studio
2. Open the `mobile-apps/android` directory as a project
3. Wait for Gradle sync to complete
4. Connect an Android device or start an emulator
5. Click "Run" to build and install the app

### Project Structure

```
app/src/main/java/com/supermonng/mobile/
├── data/                    # Data layer
│   ├── api/                # API service interfaces
│   ├── local/              # Local data storage
│   └── repository/         # Repository implementations
├── domain/                 # Domain layer
│   ├── model/              # Data models
│   ├── repository/         # Repository interfaces
│   └── usecase/            # Business logic use cases
├── ui/                     # UI layer
│   ├── components/         # Reusable UI components
│   ├── navigation/         # Navigation setup
│   ├── screens/            # App screens
│   ├── theme/              # UI theme and styling
│   └── viewmodel/          # ViewModels for state management
└── di/                     # Dependency injection modules
```

## Key Features Implemented

### Authentication
- Login screen with username/password
- Session management
- Secure credential storage
- Logout functionality

### Node Management
- List all configured nodes
- View node status (online/offline/connecting)
- Connect/disconnect nodes
- Monitor node activity
- Real-time status updates

### Architecture
- **Clean Architecture** with clear separation of concerns
- **MVVM** pattern with ViewModels and Compose UI
- **Dependency Injection** with Hilt
- **Repository Pattern** for data access
- **Use Cases** for business logic

## API Integration

The app connects to the SupermonNG REST API at `https://sm.w5gle.us/api/`:

### Authentication Endpoints
- `POST /auth/login` - User login
- `POST /auth/logout` - User logout
- `GET /auth/me` - Get current user
- `GET /auth/check` - Check authentication status

### Node Endpoints
- `GET /nodes` - List all nodes
- `GET /nodes/{id}` - Get specific node
- `GET /nodes/{id}/status` - Get node status
- `POST /nodes/connect` - Connect to node
- `POST /nodes/disconnect` - Disconnect from node
- `POST /nodes/monitor` - Monitor node
- `POST /nodes/local-monitor` - Local monitor node

## Development Workflow

### Adding New Features
1. Create data models in `domain/model/`
2. Add API endpoints in `data/api/SupermonApiService.kt`
3. Implement repository methods in `data/repository/`
4. Create use cases in `domain/usecase/`
5. Add ViewModels in `ui/viewmodel/`
6. Create UI screens in `ui/screens/`
7. Add navigation routes in `ui/navigation/`

### Testing
```bash
# Run unit tests
./gradlew test

# Run instrumented tests
./gradlew connectedAndroidTest

# Run lint checks
./gradlew lint
```

### Building
```bash
# Debug build
./gradlew assembleDebug

# Release build
./gradlew assembleRelease

# Install on device
./gradlew installDebug
```

## Configuration

### Server URL
The default server URL is `https://sm.w5gle.us/api/`. To change it:

1. Update `NetworkModule.kt` in the `di/` package
2. Or implement a settings screen for user configuration

### Local Node ID
Currently hardcoded to `"546051"` in `NodesViewModel.kt`. This should be:
1. Made configurable per user
2. Retrieved from user preferences
3. Or selected from available local nodes

## Common Issues

### Build Errors
- Ensure Android SDK is properly installed
- Check that all dependencies are resolved
- Clean and rebuild: `./gradlew clean build`

### API Connection Issues
- Verify server URL is correct
- Check network connectivity
- Ensure server is running and accessible
- Check API endpoint paths match backend

### Authentication Issues
- Verify credentials are correct
- Check session management
- Ensure CSRF tokens are handled properly

## Next Steps

### Immediate Improvements
1. Add error handling for network failures
2. Implement retry mechanisms
3. Add loading states for all operations
4. Create settings screen for configuration

### Future Features
1. Push notifications for node status changes
2. Audio streaming for node monitoring
3. Offline mode with data synchronization
4. Widget support for quick node control
5. Biometric authentication
6. Voice commands

### iOS App
Consider creating a companion iOS app using:
- SwiftUI for UI
- Combine for reactive programming
- URLSession for networking
- Core Data for local storage

## Contributing

1. Follow existing code style and patterns
2. Add unit tests for new features
3. Update documentation
4. Test on multiple devices and Android versions
5. Ensure accessibility compliance

## Resources

- [Android Developer Documentation](https://developer.android.com/)
- [Jetpack Compose](https://developer.android.com/jetpack/compose)
- [Hilt Documentation](https://dagger.dev/hilt/)
- [Retrofit Documentation](https://square.github.io/retrofit/)
- [SupermonNG API Documentation](../README.md)
