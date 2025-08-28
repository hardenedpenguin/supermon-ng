# Supermon-ng Project File Map

## Project Overview
Supermon-ng is a PHP-based web application for monitoring and managing AllStar Link nodes. The project follows a traditional PHP architecture with includes-based modularity.

## File Statistics
- **Total Files**: 200+ files
- **Total Lines**: ~79,826 lines
- **Largest File**: `astdb.txt` (40,368 lines - database file)
- **Main Technologies**: PHP, HTML, CSS, JavaScript, jQuery

## Directory Structure

### Root Level Files
```
├── index.php                    # Main entry point (30 lines)
├── login.php                    # Authentication page
├── logout.php                   # Logout functionality
├── server.php                   # Development server
├── supermon-ng-installer.sh     # Installation script (685 lines)
├── composer.lock                # PHP dependencies (2,646 lines)
├── README.md                    # Project documentation (241 lines)
├── SECURITY.md                  # Security documentation
├── manifest.json                # PWA manifest
├── offline.html                 # Offline page
├── favicon.ico                  # Favicon
├── allstarlink.jpg             # Logo image
├── allstarLogo.png             # Logo image
└── astdb.txt                   # AllStar database (40,368 lines)
```

### Main PHP Pages (Entry Points)
```
├── addfavorite.php             # Add favorite nodes
├── astaronoff.php              # Node on/off control
├── astdb.php                   # Database management
├── astlog.php                  # Asterisk log viewer
├── astlookup.php               # Node lookup functionality
├── astnodes.php                # Node management
├── ast_reload.php              # Reload Asterisk
├── authini.php                 # Authentication configuration
├── authusers.php               # User management
├── bubblechart.php             # Bubble chart visualization
├── cntrlini.php                # Control configuration
├── configeditor.php            # Configuration editor
├── connect.php                 # Node connection
├── controlpanel.php            # Control panel
├── controlserver.php           # Server control
├── controlserverfavs.php       # Server favorites
├── cpustats.php                # CPU statistics
├── database.php                # Database interface
├── deletefavorite.php          # Delete favorites
├── display-config.php          # Display configuration
├── donate.php                  # Donation page
├── dtmf.php                    # DTMF functionality
├── edit.php                    # Edit functionality
├── extnodes.php                # External nodes
├── fastrestart.php             # Fast restart
├── favini.php                  # Favorites configuration
├── favorites.php               # Favorites management
├── irlplog.php                 # IRLP log viewer
├── link.php                    # Link functionality
├── linuxlog.php                # Linux log viewer
├── node-ban-allow.php          # Node ban/allow
├── performance.php             # Performance monitoring
├── pi-gpio.php                 # Raspberry Pi GPIO
├── reboot.php                  # System reboot
├── rptstats.php                # Repeater statistics
├── save.php                    # Save functionality
├── smlog.php                   # System message log
├── stats.php                   # Statistics
├── system-info.php             # System information
├── voter.php                   # Voter functionality
├── voterserver.php             # Voter server
├── webacclog.php               # Web access log
└── weberrlog.php               # Web error log
```

### CSS Directory (`css/`)
```
├── base.css                    # Base styles (223 lines)
├── forms.css                   # Form styling (351 lines)
├── layout.css                  # Layout styles (223 lines)
├── menu.css                    # Menu styling
├── responsive.css              # Mobile responsiveness
├── tables.css                  # Table styling (335 lines)
├── widgets.css                 # Widget components (649 lines)
├── custom.css.example          # Custom CSS template
└── README.md                   # CSS documentation
```

### JavaScript Directory (`js/`)
```
├── app.js                      # Main application logic (283 lines)
├── auth.js                     # Authentication functions (238 lines)
├── chart.js                    # Charting library (57,378 lines)
├── jquery.min.js               # jQuery library (65,447 lines)
├── jquery-ui.css               # jQuery UI styles (1,314 lines)
├── jquery-ui.min.js            # jQuery UI library (64,399 lines)
├── modern-header.js            # Modern header functionality (240 lines)
├── modern-styles.css           # Modern styles (262 lines)
├── sweetalert2-config.js       # SweetAlert2 configuration
├── sweetalert2.min.css         # SweetAlert2 styles (30,378 lines)
├── sweetalert2.min.js          # SweetAlert2 library (48,078 lines)
├── sw.js                       # Service worker (255 lines)
└── utils.js                    # Utility functions
```

### Includes Directory (`includes/`) - Core Architecture

#### Core System Files
```
├── common.inc                  # Common configuration (258 lines)
├── init.inc                    # Initialization system (149 lines)
├── header.inc                  # Header template (335 lines)
├── footer.inc                  # Footer template
├── menu.inc                    # Menu system
├── session.inc                 # Session management
├── security.inc                # Security functions (468 lines)
├── csrf.inc                    # CSRF protection
├── cache.inc                   # Caching system (346 lines)
├── error-handler.inc           # Error handling (330 lines)
├── helpers.inc                 # Helper functions (461 lines)
├── plugin.inc                  # Plugin system (360 lines)
├── rate_limit.inc              # Rate limiting
├── table.inc                   # Table utilities
├── form.inc                    # Form utilities
├── form_field.inc              # Form field utilities
├── nodeinfo.inc                # Node information
├── amifunctions.inc            # AMI functions (475 lines)
└── README.md                   # Includes documentation (357 lines)
```

#### Modular Components (Each with auth/controller/ui pattern)
```
├── addfavorite/                # Add favorite functionality
│   ├── addfavorite-auth.inc
│   ├── addfavorite-controller.inc
│   └── addfavorite-ui.inc      # (273 lines)
├── astlog/                     # Asterisk log functionality
│   ├── astlog-auth.inc
│   ├── astlog-controller.inc
│   └── astlog-ui.inc
├── astnodes/                   # Node management
│   ├── astnodes-auth.inc
│   ├── astnodes-controller.inc
│   └── astnodes-ui.inc         # (272 lines)
├── bubblechart/                # Bubble chart functionality
│   ├── bubblechart-auth.inc
│   ├── bubblechart-controller.inc
│   └── bubblechart-ui.inc
├── configeditor/               # Configuration editor
│   ├── configeditor-auth.inc
│   ├── configeditor-controller.inc
│   └── configeditor-ui.inc
├── connect/                    # Connection functionality
│   ├── connect-auth.inc
│   ├── connect-controller.inc
│   └── connect-ui.inc
├── controlpanel/               # Control panel
│   ├── controlpanel-auth.inc
│   ├── controlpanel-controller.inc
│   └── controlpanel-ui.inc     # (413 lines)
├── controlserver/              # Server control
│   ├── controlserver-auth.inc
│   ├── controlserver-controller.inc
│   └── controlserver-ui.inc
├── controlserverfavs/          # Server favorites
│   ├── controlserverfavs-auth.inc
│   ├── controlserverfavs-controller.inc
│   └── controlserverfavs-ui.inc
├── cpustats/                   # CPU statistics
│   ├── cpustats-auth.inc
│   ├── cpustats-controller.inc
│   └── cpustats-ui.inc
├── database/                   # Database functionality
│   ├── database-auth.inc
│   ├── database-controller.inc
│   ├── database-processor.inc
│   └── database-ui.inc
├── deletefavorite/             # Delete favorites
│   ├── deletefavorite-auth.inc
│   ├── deletefavorite-controller.inc
│   └── deletefavorite-ui.inc   # (319 lines)
├── display-config/             # Display configuration
│   ├── display-config-auth.inc
│   ├── display-config-controller.inc
│   └── display-config-ui.inc
├── donate/                     # Donation functionality
│   ├── donate-config.inc
│   ├── donate-controller.inc
│   └── donate-ui.inc
├── dtmf/                       # DTMF functionality
│   ├── dtmf-auth.inc
│   ├── dtmf-controller.inc
│   └── dtmf-ui.inc
├── edit/                       # Edit functionality
│   ├── edit-auth.inc
│   ├── edit-controller.inc
│   └── edit-ui.inc
├── extnodes/                   # External nodes
│   ├── extnodes-auth.inc
│   ├── extnodes-controller.inc
│   └── extnodes-ui.inc
├── fastrestart/                # Fast restart
│   ├── fastrestart-auth.inc
│   ├── fastrestart-controller.inc
│   └── fastrestart-ui.inc
├── favorites/                  # Favorites management
│   ├── favorites-auth.inc
│   ├── favorites-controller.inc
│   └── favorites-ui.inc        # (372 lines)
├── irlplog/                    # IRLP log functionality
│   ├── irlplog-auth.inc
│   ├── irlplog-controller.inc
│   └── irlplog-ui.inc
├── link/                       # Link functionality
│   ├── link-config.inc
│   ├── link-functions.inc
│   ├── link-javascript.inc
│   ├── link-tables.inc
│   ├── link-ui.inc             # (258 lines)
│   └── README.md
├── linuxlog/                   # Linux log functionality
│   ├── linuxlog-auth.inc
│   ├── linuxlog-controller.inc
│   └── linuxlog-ui.inc
├── login/                      # Authentication system
│   ├── auth-functions.inc
│   ├── login-controller.inc
│   ├── login-processor.inc
│   └── login-ui.inc
├── logout/                     # Logout functionality
│   ├── logout-auth.inc
│   ├── logout-controller.inc
│   └── logout-ui.inc
├── node-ban-allow/             # Node ban/allow functionality
│   ├── ban-ami.inc
│   ├── ban-config.inc
│   ├── ban-display.inc
│   ├── ban-processor.inc
│   ├── ban-ui.inc
│   └── README.md
├── performance/                # Performance monitoring
│   ├── performance-controller.inc
│   └── performance-ui.inc      # (413 lines)
├── pi-gpio/                    # Raspberry Pi GPIO
│   ├── gpio-commands.inc
│   ├── gpio-config.inc
│   ├── gpio-processor.inc
│   ├── gpio-status.inc
│   ├── gpio-ui.inc
│   └── README.md
├── rptstats/                   # Repeater statistics
│   ├── rptstats-ami.inc
│   ├── rptstats-config.inc
│   ├── rptstats-processor.inc
│   ├── rptstats-ui.inc
│   └── README.md
├── save/                       # Save functionality
│   ├── save-auth.inc
│   ├── save-controller.inc
│   └── save-ui.inc
├── smlog/                      # System message log
│   ├── smlog-auth.inc
│   ├── smlog-controller.inc
│   └── smlog-ui.inc
├── sse/                        # Server-Sent Events
│   ├── server-ami.inc
│   ├── server-config.inc
│   ├── server-functions.inc    # (279 lines)
│   ├── server-monitor.inc
│   └── README.md
├── stats/                      # Statistics functionality
│   ├── stats-allstar.inc
│   ├── stats-channels.inc
│   ├── stats-config.inc
│   ├── stats-ui.inc
│   ├── stats-utils.inc
│   └── README.md
├── system-info/                # System information
│   ├── sysinfo-collectors.inc
│   ├── sysinfo-commands.inc
│   ├── sysinfo-config.inc
│   ├── sysinfo-status.inc
│   ├── sysinfo-ui.inc
│   └── README.md
├── voter/                      # Voter functionality
│   ├── voter-auth.inc
│   ├── voter-controller.inc
│   └── voter-ui.inc
├── voterserver/                # Voter server
│   ├── voter-config.inc
│   ├── voter-html.inc
│   ├── voter-parser.inc
│   ├── voter-sse.inc
│   ├── voter-status.inc
│   └── README.md
├── webacclog/                  # Web access log
│   ├── webacclog-auth.inc
│   ├── webacclog-controller.inc
│   └── webacclog-ui.inc
└── weberrlog/                  # Web error log
    ├── weberrlog-auth.inc
    ├── weberrlog-controller.inc
    └── weberrlog-ui.inc
```

#### Special Components
```
├── astlookup/                  # Node lookup system
│   ├── lookup-allstar.inc
│   ├── lookup-config.inc
│   ├── lookup-echolink.inc
│   ├── lookup-irlp.inc
│   ├── lookup-ui.inc
│   └── README.md
├── dashboard/                  # Dashboard functionality
│   ├── dashboard-content.inc
│   └── dashboard-controller.inc
└── bootstrap.inc               # Bootstrap functionality
```

### User Files Directory (`user_files/`)
```
├── allmon.ini                  # AllStar monitor configuration
├── allmon.ini.example          # Example configuration
├── authini.inc                 # Authentication configuration
├── authusers.inc               # User definitions
├── controlpanel.ini            # Control panel configuration
├── cyborg_hamradio.png         # Custom logo (977 lines)
├── favini.inc                  # Favorites configuration
├── favorites.ini               # Favorites data
├── global.inc                  # Global configuration
├── global.inc.example          # Example global configuration
├── IMPORTANT-README            # Important documentation
├── privatenodes.txt            # Private nodes list
├── set_password.sh             # Password setting script (373 lines)
└── sbin/                       # System binaries
    ├── ast_node_status_update.py
    ├── din                     # (274 lines)
    ├── get_temp                # (360 lines)
    ├── node_info.ini
    └── ssinfo                  # (274 lines)
```

### Scripts Directory (`scripts/`)
```
├── backup-config.sh            # Configuration backup (360 lines)
├── create-release.sh           # Release creation (466 lines)
├── dev-setup.sh                # Development setup (250 lines)
├── lint-code.sh                # Code linting (298 lines)
└── run-tests.sh                # Test runner (505 lines)
```

### Templates Directory (`templates/`)
```
├── new-api-endpoint-template.php    # API endpoint template (453 lines)
├── new-component-template.php       # Component template (389 lines)
├── new-page-template.php            # Page template (236 lines)
├── README.md                        # Template documentation (284 lines)
```

### Documentation Directory (`docs/`)
```
├── CONTRIBUTING.md             # Contribution guidelines (293 lines)
├── DEPLOYMENT_CONFIGURATION.md # Deployment guide (372 lines)
├── DEVELOPER_GUIDE.md          # Developer guide (448 lines)
├── IMPROVEMENTS_SUMMARY.md     # Improvements summary (316 lines)
├── INSTALLER_IMPROVEMENTS.md   # Installer improvements (296 lines)
├── RELEASE_PROCESS.md          # Release process (265 lines)
└── SIMPLIFICATION_SUMMARY.md   # Simplification summary (322 lines)
```

### Custom Directory (`custom/`)
```
├── index.html                  # Custom index page
└── iplog.txt                   # IP log file
```

## Architecture Patterns

### 1. MVC-like Pattern
Each feature follows a consistent pattern:
- `*-auth.inc`: Authentication and authorization
- `*-controller.inc`: Business logic and data processing
- `*-ui.inc`: User interface and presentation

### 2. Include-based Modularity
- Uses PHP includes for modularity
- No autoloading or modern PHP features
- Traditional procedural PHP approach

### 3. Configuration Management
- Centralized in `includes/common.inc`
- User configurations in `user_files/`
- Environment-specific settings

### 4. Security Features
- CSRF protection (`includes/csrf.inc`)
- Rate limiting (`includes/rate_limit.inc`)
- Security monitoring (`includes/security.inc`)
- Session management (`includes/session.inc`)

### 5. Frontend Architecture
- Traditional jQuery-based frontend
- No modern build system
- CSS and JS files served directly
- PWA capabilities with service worker

## Key Dependencies

### External Libraries
- **jQuery**: 65,447 lines (minified)
- **jQuery UI**: 64,399 lines (minified)
- **Chart.js**: 57,378 lines
- **SweetAlert2**: 48,078 lines (minified)

### Internal Systems
- **AMI Functions**: 475 lines (Asterisk Manager Interface)
- **Security System**: 468 lines
- **Helper Functions**: 461 lines
- **Configuration**: 433 lines

## Modernization Opportunities

### 1. Frontend Modernization
- Replace jQuery with modern JavaScript/Vue.js
- Implement proper build system (Vite/Webpack)
- Add TypeScript support
- Implement component-based architecture

### 2. Backend Modernization
- Implement proper autoloading (Composer)
- Add modern PHP features (namespaces, classes)
- Implement proper routing system
- Add API layer with JSON responses

### 3. Architecture Improvements
- Separate frontend and backend
- Implement proper MVC framework
- Add database abstraction layer
- Implement proper caching system

### 4. Development Experience
- Add proper testing framework
- Implement CI/CD pipeline
- Add code quality tools
- Improve documentation

## File Size Analysis

### Largest Files by Lines
1. `astdb.txt` - 40,368 lines (database)
2. `composer.lock` - 2,646 lines (dependencies)
3. `js/jquery-ui.css` - 1,314 lines (styles)
4. `supermon-ng-installer.sh` - 685 lines (installer)
5. `css/widgets.css` - 649 lines (widgets)
6. `scripts/run-tests.sh` - 505 lines (testing)
7. `includes/amifunctions.inc` - 475 lines (AMI)
8. `includes/security.inc` - 468 lines (security)
9. `includes/helpers.inc` - 461 lines (helpers)
10. `templates/new-api-endpoint-template.php` - 453 lines (template)

### Most Complex Components
1. **Security System**: 468 lines
2. **AMI Functions**: 475 lines
3. **Control Panel UI**: 413 lines
4. **Performance UI**: 413 lines
5. **Server Functions**: 279 lines

## Notes for Modernization

1. **Preserve Functionality**: The system has extensive functionality that must be preserved
2. **Gradual Migration**: Consider incremental modernization rather than complete rewrite
3. **Database Migration**: The `astdb.txt` file contains critical data that needs careful migration
4. **Configuration Preservation**: User configurations in `user_files/` must be preserved
5. **Backward Compatibility**: Consider maintaining compatibility with existing deployments

This file map provides a comprehensive overview of the supermon-ng project structure for future modernization efforts.


