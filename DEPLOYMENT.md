# Deployment Guide for InfinityFree

## Step-by-Step Deployment Instructions

### 1. Prepare Your Configuration File

**For InfinityFree (Production):**

1. Copy `inc/config.example.php` to `inc/config.php`
2. Fill in your actual values in `inc/config.php`:
   - Get your database credentials from InfinityFree control panel
   - Add your OpenAI API key
   - Add your Cloudinary configuration

```php
// Example inc/config.php for InfinityFree
define('OPENAI_API_KEY', 'your_actual_openai_key_here');
define('DB_HOST', 'localhost'); // or mysql.epizy.com (check InfinityFree panel)
define('DB_NAME', 'epiz_xxxxx_glowtime'); // From InfinityFree database panel
define('DB_USER', 'epiz_xxxxx'); // From InfinityFree database panel
define('DB_PASS', 'your_database_password'); // From InfinityFree database panel
define('CLOUDINARY_URL', 'your_cloudinary_url');
define('CLOUDINARY_UPLOAD_PRESET', 'your_preset');
```

### 2. Upload Files to InfinityFree

1. **Upload via FTP or File Manager:**
   - Upload all files EXCEPT:
     - `.env` (not needed, using config.php instead)
     - `vendor/` folder (if using Composer)
     - `.git/` folder

2. **Important Files to Upload:**
   - All PHP files
   - All CSS/JS/image assets
   - `inc/config.php` (with your actual credentials)

3. **Files to Skip (already gitignored):**
   - `.env`
   - `inc/config.php` (will be created on server)
   - `.git/`

### 3. Set File Permissions

Make sure these files/folders are writable if needed:
- No special permissions required for basic PHP files
- Database connection will use credentials from `inc/config.php`

### 4. Database Setup

1. Create your database in InfinityFree control panel
2. Import your database schema (SQL dump)
3. Update `inc/config.php` with the correct database credentials

### 5. Test Your Deployment

1. Visit your website
2. Test the chatbot - it should load the OpenAI API key from `inc/config.php`
3. Test database connections
4. Verify all features work

## Configuration Priority

The system loads configuration in this order (highest to lowest priority):

1. **`inc/config.php`** - Recommended for InfinityFree
2. **`.env` file** - For local development
3. **System environment variables** - If set on server
4. **Fallback defaults** - Hardcoded values

## Security Notes

- ✅ `inc/config.php` is in `.gitignore` - won't be committed
- ✅ `.env` is in `.gitignore` - won't be committed
- ⚠️ Never commit actual API keys or passwords
- ⚠️ Always use `inc/config.example.php` as a template

## Troubleshooting

### OpenAI Not Working?
- Check that `OPENAI_API_KEY` is defined in `inc/config.php`
- Verify the API key is correct
- Check InfinityFree allows outgoing HTTPS requests

### Database Connection Failed?
- Verify database credentials in InfinityFree control panel
- Check `DB_HOST` - InfinityFree might use `mysql.epizy.com` instead of `localhost`
- Ensure database name, user, and password match InfinityFree panel

### File Not Found Errors?
- Ensure all files are uploaded correctly
- Check file paths are relative (not absolute)
- Verify `inc/config.php` exists on the server

## Alternative: Using .env on InfinityFree

If InfinityFree supports `.env` files:

1. Upload `.env` file to the root directory
2. The system will automatically load it
3. `inc/config.php` will take precedence if both exist

## Support

For InfinityFree-specific issues:
- Check InfinityFree documentation
- Contact InfinityFree support for database/hosting issues
