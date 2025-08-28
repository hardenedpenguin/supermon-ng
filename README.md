# Supermon-ng Modern Frontend

A modern Vue 3 frontend for the Supermon-ng AllStar Link monitoring system, built with Vite and integrated with a PHP Slim backend.

## 🚀 Quick Start

### Prerequisites

- PHP 8.1+
- Node.js 18+
- Composer
- npm

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd supermon-ng
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install frontend dependencies**
   ```bash
   cd frontend
   npm install
   ```

4. **Configure authentication**
   ```bash
   # List current users
   php scripts/manage_users.php list
   
   # Add a new user
   php scripts/manage_users.php add yourusername yourpassword
   
   # Change password for existing user
   php scripts/manage_users.php change yourusername newpassword
   ```

5. **Start the development servers**
   ```bash
   # Terminal 1: Start backend server
   composer dev
   
   # Terminal 2: Start frontend server
   cd frontend
   npm run dev
   ```

6. **Access the application**
   - Backend API: http://localhost:8000
   - Frontend: http://localhost:5173 (or next available port)

## 🔐 Modern Authentication System

### Overview

The application now uses a **modern session-based authentication system** that provides:

- **Secure password verification** against `.htpasswd` files
- **Session management** with automatic expiration (24 hours)
- **Multiple hash format support** (bcrypt, Apache MD5, SHA1, MD5, plain text)
- **Permission-based access control** using the existing `authusers.inc` system
- **User-specific configuration** via `authini.inc` mapping

### Authentication Features

#### 🔒 **Secure Password Storage**
- **bcrypt hashing** (recommended) with configurable cost factor
- **Legacy hash support** for existing `.htpasswd` files
- **Multiple hash formats** supported for migration

#### 🛡️ **Session Security**
- **Automatic session expiration** (24 hours)
- **Session regeneration** on refresh for security
- **Secure cookie handling** with proper cleanup
- **CSRF protection** via session tokens

#### 👥 **User Management**
- **Command-line user management** via `scripts/manage_users.php`
- **Permission-based access control** using existing `authusers.inc`
- **User-specific INI file mapping** via `authini.inc`

### User Management Commands

```bash
# List all users
php scripts/manage_users.php list

# Add a new user
php scripts/manage_users.php add username password

# Remove a user
php scripts/manage_users.php remove username

# Change user password
php scripts/manage_users.php change username newpassword
```

### Authentication Flow

1. **Login Process**
   - User submits credentials via login modal
   - Backend verifies against `.htpasswd` file
   - Creates secure session with user data
   - Returns user permissions and configuration source

2. **Session Management**
   - Sessions persist for 24 hours
   - Automatic session validation on each request
   - Secure session cleanup on logout

3. **Permission System**
   - Integrates with existing `authusers.inc` permission arrays
   - User-specific INI file mapping via `authini.inc`
   - Granular control over features and buttons

### API Endpoints

#### Authentication Endpoints
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/me` - Get current user info
- `GET /api/auth/check` - Check authentication status
- `POST /api/auth/refresh` - Refresh session

#### Response Format
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {"name": "username"},
    "authenticated": true,
    "permissions": {
      "CONNECTUSER": true,
      "DISCUSER": true,
      // ... other permissions
    },
    "config_source": "allmon.ini"
  },
  "timestamp": "2025-08-27T23:55:40+00:00"
}
```

### Migration from Legacy System

The new authentication system is **fully backward compatible** with existing Supermon-ng installations:

1. **Existing `.htpasswd` files** are automatically supported
2. **Current `authusers.inc` permissions** work without changes
3. **User-specific INI mappings** via `authini.inc` are preserved
4. **Multiple hash formats** are supported for gradual migration

### Security Features

- **Password hashing** with modern algorithms (bcrypt recommended)
- **Session security** with automatic expiration and regeneration
- **Input validation** and sanitization
- **CSRF protection** via session tokens
- **Secure cookie handling** with proper flags
- **Logging** of authentication events for security monitoring

## 🏗️ Architecture

### Backend (PHP Slim)
- **Modern PHP 8.1+** with strict typing
- **Slim Framework 4** for API routing
- **PHP-DI** for dependency injection
- **Session-based authentication** with secure password verification
- **Legacy integration** with existing Supermon-ng configuration files

### Frontend (Vue 3)
- **Vue 3 Composition API** with TypeScript
- **Vite** for fast development and building
- **Pinia** for state management
- **Vue Router** for navigation
- **Axios** for API communication
- **Modern UI/UX** with responsive design

### Key Components

#### Backend Services
- `AuthController` - Session-based authentication
- `NodeController` - AMI integration and node management
- `ConfigController` - Configuration and menu management
- `DatabaseController` - AllStar database operations
- `SystemController` - System control operations

#### Frontend Stores
- `appStore` - Authentication and user state
- `realTimeStore` - Real-time node data via API polling

#### Frontend Components
- `Dashboard` - Main application interface
- `Menu` - Navigation menu with node selection
- `NodeTable` - Individual node status display
- `LoginForm` - Modal-based authentication
- `Modal` - Reusable modal component

## 🔧 Configuration

### Backend Configuration
- **Session settings** in `src/Config/Middleware.php`
- **Authentication settings** in `src/Application/Controllers/AuthController.php`
- **Legacy file paths** in respective controllers

### Frontend Configuration
- **API base URL** in `frontend/src/utils/api.ts`
- **Environment variables** in `frontend/.env`
- **Vite configuration** in `frontend/vite.config.ts`

### Legacy Integration
- **User permissions** via `user_files/authusers.inc`
- **User-specific INI files** via `user_files/authini.inc`
- **Node configuration** via `user_files/allmon.ini`
- **Global settings** via `user_files/global.inc`

## 🚀 Development

### Backend Development
```bash
# Start development server
composer dev

# Run tests
composer test

# Code formatting
composer format
```

### Frontend Development
```bash
# Start development server
cd frontend
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

### API Testing
```bash
# Test authentication
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"testpass123"}'

# Test user info
curl http://localhost:8000/api/auth/me

# Test logout
curl -X POST http://localhost:8000/api/auth/logout
```

## 📁 Project Structure

```
supermon-ng/
├── src/                          # Backend PHP source
│   ├── Application/
│   │   └── Controllers/          # API controllers
│   ├── Config/                   # Configuration files
│   └── Services/                 # Business logic services
├── frontend/                     # Vue 3 frontend
│   ├── src/
│   │   ├── components/           # Vue components
│   │   ├── stores/               # Pinia stores
│   │   ├── views/                # Page components
│   │   └── utils/                # Utility functions
│   └── public/                   # Static assets
├── user_files/                   # Legacy configuration
│   ├── .htpasswd                 # User credentials
│   ├── authusers.inc            # User permissions
│   ├── authini.inc              # User-specific INI mapping
│   └── allmon.ini               # Node configuration
├── scripts/                      # Utility scripts
│   └── manage_users.php         # User management utility
└── public/                       # Backend public files
```

## 🔄 Real-time Features

### AMI Integration
- **Real-time node monitoring** via Asterisk Manager Interface
- **Automatic data polling** every 5 seconds
- **Node status updates** including connected nodes
- **HTML rendering** of node information and alerts

### API Polling
- **Replaced SSE** with reliable API polling
- **Configurable polling intervals**
- **Error handling** and automatic retry
- **Efficient data updates** with change detection

## 🎯 Key Features

### Modern Authentication
- ✅ **Session-based authentication** with secure password verification
- ✅ **Multiple hash format support** for legacy compatibility
- ✅ **Permission-based access control** using existing system
- ✅ **User-specific configuration** mapping
- ✅ **Automatic session management** with expiration

### Real-time Monitoring
- ✅ **AMI integration** for live node data
- ✅ **API polling** for reliable updates
- ✅ **Node status display** with HTML rendering
- ✅ **Connected nodes** monitoring
- ✅ **Alert and weather** information display

### User Interface
- ✅ **Modern Vue 3 interface** with responsive design
- ✅ **Modal-based login** integrated in dashboard
- ✅ **Permission-based UI** showing/hiding features
- ✅ **Node selection** via menu system
- ✅ **Real-time updates** without page refresh

### Legacy Integration
- ✅ **Backward compatibility** with existing Supermon-ng
- ✅ **Existing configuration files** supported
- ✅ **Permission system** preserved
- ✅ **User management** via command-line tools
- ✅ **Gradual migration** path from legacy system

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the same terms as the original Supermon-ng project.

## 🙏 Acknowledgments

- Original Supermon-ng developers for the excellent foundation
- Vue.js team for the amazing frontend framework
- Slim Framework team for the robust PHP backend
- AllStar Link community for continued support and feedback
