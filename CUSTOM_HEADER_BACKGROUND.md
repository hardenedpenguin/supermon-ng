# Custom Header Background

You can customize the header background image in Supermon-ng by placing your own image file in the `user_files` directory.

## How to Add a Custom Header Background

1. **Prepare your image**: 
   - Recommended size: 1200x164 pixels (or similar aspect ratio)
   - Supported formats: JPG, JPEG, PNG, GIF, WEBP

2. **Name your image file**: 
   - The file must be named `header-background` with the appropriate extension
   - Examples: `header-background.jpg`, `header-background.png`, etc.

3. **Upload to user_files directory**:
   ```bash
   # Copy your image to the user_files directory
   sudo cp /path/to/your/image.jpg /var/www/html/supermon-ng/user_files/header-background.jpg
   
   # Set proper permissions
   sudo chown www-data:www-data /var/www/html/supermon-ng/user_files/header-background.jpg
   sudo chmod 644 /var/www/html/supermon-ng/user_files/header-background.jpg
   ```

4. **Refresh the page**: The custom background will appear immediately after uploading.

## Removing Custom Background

To revert to the default background, simply delete the custom image file:

```bash
sudo rm /var/www/html/supermon-ng/user_files/header-background.*
```

## Notes

- The system will automatically detect and use the first `header-background.*` file it finds
- If multiple formats exist, the system will use the first one found (in the order: jpg, jpeg, png, gif, webp)
- Images are cached for 1 hour for better performance
- The image will be automatically scaled and positioned to cover the header area

## Tips for Best Results

- Use images with good contrast so the white text remains readable
- Consider the placement of text elements when choosing your background
- Test the appearance on both desktop and mobile devices
- Keep file sizes reasonable (under 1MB) for faster loading
