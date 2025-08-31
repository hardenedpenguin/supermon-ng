# Supermon-ng Modern Interface

A modern Vue 3 + Slim PHP 4 interface for Supermon-ng, providing a clean, responsive, and customizable user experience.

## Features

- **Modern Vue 3 Frontend**: Built with Vue 3 Composition API and TypeScript
- **Slim PHP 4 Backend**: RESTful API with proper authentication and middleware
- **Real-time Updates**: Live status updates for nodes and system information
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Theme Customization**: Multiple built-in themes and custom theme support
- **Modal-based Interface**: Modern modal components instead of page redirects
- **User Authentication**: Secure login and session management
- **API-driven**: Clean separation between frontend and backend

## Theme Customization

Supermon-ng now includes a comprehensive theme system that allows users to customize the appearance of the interface.

### Built-in Themes

The interface comes with 8 pre-built themes:

1. **Dark** - Default dark theme with high contrast
2. **Light** - Clean light theme for daytime use
3. **Blue** - Professional blue theme
4. **Green** - Nature-inspired green theme
5. **Purple** - Elegant purple theme
6. **Red** - Bold red theme
7. **Orange** - Warm orange theme
8. **Custom** - User-defined custom theme

### How to Change Themes

1. Click the 🎨 theme button in the top-right corner of the interface
2. Select from the available themes in the theme selector
3. The theme will be applied immediately and saved to your browser

### Custom Theme Creation

You can create your own custom theme in several ways:

#### Method 1: Using the Theme Editor
1. Select "Custom" theme in the theme selector
2. Use the color picker controls to customize colors
3. Your changes are applied in real-time
4. Export your theme to save it for later use

#### Method 2: CSS File Customization
1. Edit the `custom/custom-theme.css` file
2. Modify the CSS variables to your preference
3. Save the file and refresh the interface
4. Select "Custom" theme to apply your changes

#### Method 3: Import/Export
1. Export themes as JSON files for sharing
2. Import themes from files or clipboard
3. Share custom themes with other users

### Available CSS Variables

You can customize these CSS variables in your custom theme:

```css
--primary-color: Main accent color
--text-color: Primary text color
--background-color: Main background color
--container-bg: Container/card background color
--border-color: Border color
--input-bg: Input field background
--input-text: Input field text color
--table-header-bg: Table header background
--success-color: Success/online status color
--warning-color: Warning status color
--error-color: Error/offline status color
--link-color: Link color
--menu-background: Menu background color
--modal-bg: Modal background color
--modal-overlay: Modal overlay color
--button-bg: Button background color
--button-hover: Button hover state
--button-active: Button active state
--card-bg: Card background color
--card-border: Card border color
--tooltip-bg: Tooltip background
--tooltip-text: Tooltip text color
```

### Example Custom Theme

```css
[data-theme="custom"] {
  --primary-color: #00d4ff;
  --text-color: #ffffff;
  --background-color: #0a0a0a;
  --container-bg: #1a1a1a;
  --border-color: #333333;
  --success-color: #00ff88;
  --warning-color: #ffaa00;
  --error-color: #ff4444;
}
```

## Installation

### Prerequisites

- PHP 8.1 or higher
- Node.js 18 or higher
- Composer
- npm or yarn

### Backend Setup

1. Install PHP dependencies:
   ```bash
   composer install
   ```

2. Configure your environment:
   ```bash
   cp .env.example .env
   # Edit .env with your configuration
   ```

3. Start the development server:
   ```bash
   composer dev
   ```

### Frontend Setup

1. Install Node.js dependencies:
   ```bash
   cd frontend
   npm install
   ```

2. Start the development server:
   ```bash
   npm run dev
   ```

3. Open your browser to `http://localhost:5179`

### Production Build

For production deployment, use the build command that skips TypeScript checking:

```bash
cd frontend
npm run build
```

**Note**: The production build skips TypeScript checking to avoid compilation issues. For development with full type checking, use `npm run build:check`.

## Development

### Project Structure

```
supermon-ng/
├── frontend/              # Vue 3 frontend application
│   ├── src/
│   │   ├── components/    # Vue components
│   │   ├── views/         # Page components
│   │   ├── composables/   # Vue composables
│   │   ├── stores/        # Pinia stores
│   │   ├── styles/        # CSS files including themes
│   │   └── utils/         # Utility functions
│   └── public/            # Static assets
├── src/                   # Slim PHP 4 backend
│   ├── Application/       # Application logic
│   ├── Config/           # Configuration files
│   └── Infrastructure/   # Infrastructure code
├── includes/             # Original Supermon-ng includes
├── user_files/           # User configuration files
└── custom/               # Custom theme files
```

### Available Scripts

#### Backend
- `composer dev` - Start development server
- `composer test` - Run tests
- `composer build` - Build for production

#### Frontend
- `npm run dev` - Start development server
- `npm run build` - Build for production (skips TypeScript checking)
- `npm run build:check` - Build with TypeScript checking (for development)
- `npm run preview` - Preview production build

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.
