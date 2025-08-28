# Supermon-ng Frontend

A modern Vue 3 frontend for the Supermon-ng AllStar Link Node Monitoring System.

## Features

### 🔐 Integrated Authentication System

The application now includes a comprehensive authentication system that integrates seamlessly with the dashboard, matching the original Supermon-ng behavior:

#### Dashboard Integration
- **Always Accessible**: Users are always directed to the dashboard, regardless of authentication status
- **Header Integration**: Login/logout functionality is integrated into the dashboard header
- **Modal Login**: Modern login form appears in a modal overlay when needed
- **Seamless Experience**: No page redirects or separate login pages

#### Login Modal Features
- **Modern Design**: Beautiful, responsive login interface with glassmorphism effects
- **Form Validation**: Real-time validation with helpful error messages
- **Password Toggle**: Show/hide password functionality
- **Loading States**: Smooth loading animations during authentication
- **Error Handling**: Clear error messages for failed login attempts
- **Modal Interface**: Appears as an overlay without leaving the dashboard

#### Authentication Flow
- **Header Login Button**: Click to open login modal
- **User Display**: Shows current user name in the header when authenticated
- **Modern Logout Button**: Integrated into the dashboard header with loading states
- **Automatic Updates**: UI updates automatically after login/logout

#### Security Features
- **CSRF Protection**: Built-in CSRF token handling
- **Session Management**: Secure session handling with cookies
- **Permission-Based Access**: UI elements hidden based on user permissions
- **Input Validation**: Client and server-side validation

## Getting Started

### Prerequisites
- Node.js 18+ 
- npm or yarn
- Backend API running on localhost:8000

### Installation
```bash
cd frontend
npm install
```

### Development
```bash
npm run dev
```

The application will be available at `http://localhost:5174` (or next available port)

### Building for Production
```bash
npm run build
```

## Authentication Flow

1. **Initial Load**: App loads dashboard immediately, checks authentication status
2. **Unauthenticated**: Dashboard shows with login button in header
3. **Login**: Click login button to open modal, enter credentials with validation
4. **Success**: Modal closes, header updates to show user and logout button
5. **Logout**: Click logout button in header, returns to unauthenticated state

## User Interface

### Dashboard Integration
- Seamless integration with existing dashboard
- Login/logout functionality in header
- User information displayed when authenticated
- Permission-based UI elements
- Modern modal-based login experience

### Login Modal Features
- Responsive design that works on all devices
- Form validation with visual feedback
- Password visibility toggle
- Loading states and animations
- Error message display
- Professional styling consistent with dashboard

## Technical Implementation

### Vue 3 Composition API
- Modern reactive state management
- TypeScript support for type safety
- Pinia stores for state management

### Authentication Store
- Centralized authentication state
- Permission management
- Session handling
- Error management

### Modal System
- Reusable modal component
- Login form component
- Event-driven communication
- Smooth animations

## Styling

### Design System
- Consistent color scheme with CSS variables
- Modern glassmorphism effects
- Smooth animations and transitions
- Responsive design patterns

### CSS Features
- CSS Grid and Flexbox layouts
- Custom animations and keyframes
- Backdrop filters for modern effects
- Mobile-first responsive design

## Security Considerations

- All authentication requests use HTTPS in production
- CSRF tokens included in requests
- Session cookies with secure flags
- Input validation on both client and server
- XSS protection through proper escaping

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Contributing

1. Follow the existing code style
2. Add TypeScript types for new features
3. Test authentication flows thoroughly
4. Ensure responsive design works on all devices
