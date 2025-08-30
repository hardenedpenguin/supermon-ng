# Supermon-ng Modular CSS Structure

This directory contains the modularized CSS files for Supermon-ng, replacing the monolithic `supermon-ng.css` file.

## File Structure

### Core CSS Files (Load in Order)

1. **`base.css`** - Base styles, CSS variables, resets, typography, and links
2. **`layout.css`** - Layout, header, and container styles  
3. **`menu.css`** - Menu navigation styles
4. **`tables.css`** - All table-related styles (grid tables, RTCM, web logs, lookup tables)
5. **`forms.css`** - Form elements, buttons, login, and message styles
6. **`widgets.css`** - Component-specific styles (voter, GPIO, ban/allow, edit pages, etc.)
7. **`responsive.css`** - Mobile responsive and print styles

### User Customization

8. **`custom.css`** - Template for user customizations (loads last to override defaults)

## Benefits

✅ **Better Organization** - Related styles are grouped together  
✅ **Easier Maintenance** - Find and modify specific styles quickly  
✅ **User Customization** - Users can override styles via `custom.css`  
✅ **Selective Loading** - Can load only needed CSS modules  
✅ **Version Control** - Easier to track changes to specific components  
✅ **Team Development** - Multiple developers can work on different modules  

## Usage

### For Users

To customize the appearance:

1. Edit `css/custom.css`
2. Uncomment and modify the example styles
3. Add your own custom styles at the bottom of the file
4. Save the file - changes are applied immediately

### For Developers

To modify specific components:

- **Layout changes**: Edit `layout.css`
- **Menu changes**: Edit `menu.css`
- **Table changes**: Edit `tables.css`
- **Form changes**: Edit `forms.css`
- **Widget changes**: Edit `widgets.css`
- **Responsive changes**: Edit `responsive.css`

## CSS Variables

The main color scheme and styling variables are defined in `base.css`:

```css
:root {
  --primary-color: #E0E0E0;      /* Main accent color */
  --background-color: #121212;   /* Page background */
  --container-bg: #1E1E1E;       /* Container backgrounds */
  --menu-background: #1C1C1C;    /* Menu background */
  --text-color: #CCCCCC;         /* Main text color */
  --table-header-bg: #333333;    /* Table header background */
  --error-color: #D32F2F;        /* Error messages */
  --success-color: #4CAF50;      /* Success messages */
  --warning-color: #FFA000;      /* Warning messages */
  --border-color: #333333;       /* Border color */
  --input-bg: #2a2a2a;           /* Input background */
  --input-text: #CCCCCC;         /* Input text color */
  --link-color: #64B5F6;         /* Link color */
  --link-hover: #E0E0E0;         /* Link hover color */
}
```

## Migration from Old CSS

The old `supermon-ng.css` file has been completely modularized. All HTML/PHP files have been updated to load the new modular CSS files in the correct order.

## Browser Compatibility

The modular CSS maintains the same browser compatibility as the original CSS:
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers
- Print media support

## Troubleshooting

If styles are not loading correctly:

1. Check that all CSS files are present in the `css/` directory
2. Verify that the HTML/PHP files are loading the CSS files in the correct order
3. Check browser developer tools for any CSS loading errors
4. Ensure the web server has read permissions for the CSS files

## Future Enhancements

- CSS minification for production
- CSS bundling for faster loading
- Theme system with multiple color schemes
- Component-specific CSS loading for better performance 