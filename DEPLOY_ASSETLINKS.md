# Deploying assetlinks.json to InfinityFree

## Where to Upload

Upload the `.well-known/assetlinks.json` file to your InfinityFree server in the **root directory** of your website.

### File Structure on Server

Your server should have this structure:
```
/
├── .well-known/
│   └── assetlinks.json
├── redirect.php
├── index.php
├── inc/
└── ... (other files)
```

## Upload Methods

### Method 1: Using InfinityFree File Manager

1. **Log into InfinityFree Control Panel**
   - Go to: https://infinityfree.net/
   - Log in to your account

2. **Open File Manager**
   - Navigate to your website's file manager
   - Go to the root directory (where `index.php` is located)

3. **Create `.well-known` folder** (if it doesn't exist)
   - Click "New Folder" or "Create Directory"
   - Name it: `.well-known`
   - Make sure it starts with a dot (`.`)

4. **Upload `assetlinks.json`**
   - Navigate into the `.well-known` folder
   - Click "Upload File"
   - Select the `assetlinks.json` file from your local `.well-known/` folder
   - Upload it

### Method 2: Using FTP

1. **Connect via FTP**
   - Use FTP client (FileZilla, WinSCP, etc.)
   - Connect to your InfinityFree FTP server
   - Navigate to the root directory

2. **Create `.well-known` folder**
   - Create a new folder named `.well-known`

3. **Upload `assetlinks.json`**
   - Navigate into `.well-known/`
   - Upload `assetlinks.json` from your local `.well-known/` folder

## Verify File is Accessible

After uploading, test that the file is accessible:

1. **Visit in browser:**
   ```
   https://glowtime.ct.ws/.well-known/assetlinks.json
   ```

2. **Should see JSON content:**
   ```json
   [
     {
       "relation": ["delegate_permission/common.handle_all_urls"],
       "target": {
         "namespace": "android_app",
         "package_name": "com.kelvind.glowtime",
         "sha256_cert_fingerprints": [
           "58528725abf24e5851353c6c15b210bd7f831c441674dc652105ee98189ce910"
         ]
       }
     }
   ]
   ```

3. **Check Content-Type:**
   - Open browser developer tools (F12)
   - Go to Network tab
   - Reload the page
   - Check the `assetlinks.json` request
   - Headers should show: `Content-Type: application/json`

## Server Configuration (if needed)

If the file doesn't return the correct Content-Type, you may need to add a `.htaccess` file.

### Create `.htaccess` in `.well-known/` folder:

```apache
<Files "assetlinks.json">
    Header set Content-Type "application/json"
</Files>
```

Or create `.htaccess` in root directory with:

```apache
<FilesMatch "\.well-known/assetlinks\.json$">
    Header set Content-Type "application/json"
</FilesMatch>
```

## Testing with cURL

Test from command line:
```bash
curl -I https://glowtime.ct.ws/.well-known/assetlinks.json
```

Should return:
```
HTTP/1.1 200 OK
Content-Type: application/json
...
```

## Important Notes

✅ **File must be accessible over HTTPS** (not HTTP)  
✅ **No redirects** - must be direct access  
✅ **Correct Content-Type** - must be `application/json`  
✅ **Valid JSON** - no syntax errors  
✅ **Exact path** - must be at `/.well-known/assetlinks.json`

## Troubleshooting

### File not found (404 error)
- Check the file is in the correct location: `/.well-known/assetlinks.json`
- Make sure the folder name is `.well-known` (with the dot)
- Check file permissions (should be readable)

### Wrong Content-Type
- Add `.htaccess` file (see above)
- Or contact InfinityFree support

### JSON syntax error
- Validate JSON at: https://jsonlint.com/
- Make sure no trailing commas
- Check quotes are properly escaped

## Next Steps

After uploading:
1. ✅ Verify file is accessible at the URL above
2. ✅ Update your `app.json` with HTTPS App Links (see `APP_JSON_UPDATE.md`)
3. ✅ Rebuild your app with `eas build`
4. ✅ Install and test - scan QR code should open app directly!

