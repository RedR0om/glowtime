# Android App Links Setup Guide

## Problem
When scanning QR codes with HTTPS URLs, Android phones show "open with browser" options instead of directly opening the app.

## Solution
Android App Links allow HTTPS URLs to open your app directly without showing browser options.

## Setup Instructions

### 1. Android App Links Configuration

**File:** `.well-known/assetlinks.json`

1. **Your app's package name:** `com.kelvind.glowtime` ✅
   - Already configured in your `app.json`

2. **Get your app's SHA-256 fingerprint**:
   
   **For Expo managed workflow:**
   ```bash
   # If using EAS Build
   eas credentials
   # Or check your keystore
   keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android
   ```
   
   **For production builds:**
   ```bash
   keytool -list -v -keystore your-release-key.keystore -alias your-key-alias
   ```
   
   Look for `SHA256:` in the output and copy the fingerprint (without spaces/colons).

3. **Update `assetlinks.json`**:
   - ✅ Package name is already set to `com.kelvind.glowtime`
   - ⚠️ Replace `YOUR_SHA256_FINGERPRINT_HERE` with your actual SHA-256 fingerprint

### 2. App Configuration (Expo)

**Update your existing `intentFilters` in `app.json`:**

You already have intentFilters for the custom scheme. You need to **add HTTPS App Links support** to the same intentFilter. Update your `android.intentFilters` to:

```json
"intentFilters": [
  {
    "action": "VIEW",
    "autoVerify": true,
    "data": [
      {
        "scheme": "glowtime",
        "host": "*"
      },
      {
        "scheme": "https",
        "host": "glowtime.ct.ws",
        "pathPrefix": "/redirect.php"
      }
    ],
    "category": ["BROWSABLE", "DEFAULT"]
  }
]
```

**Important:** 
- Keep `autoVerify: true` (you already have this ✅)
- Add the HTTPS entry to the `data` array (alongside your existing `glowtime://` scheme)
- This allows both `glowtime://` and `https://glowtime.ct.ws/redirect.php` to open your app

### 3. Server Configuration

The `assetlinks.json` file is already created in `.well-known/` directory. Make sure your web server:

- Serves it with `Content-Type: application/json`
- Allows access without authentication
- Serves over HTTPS
- No redirects (must be direct access)

**For Apache**, add to `.htaccess`:
```apache
<Files "assetlinks.json">
    Header set Content-Type "application/json"
</Files>
```

**For Nginx**, add to config:
```nginx
location /.well-known/assetlinks.json {
    default_type application/json;
    add_header Access-Control-Allow-Origin *;
}
```

### 4. Testing

1. **Verify the file is accessible:**
   - Visit: `https://glowtime.ct.ws/.well-known/assetlinks.json`
   - Should show JSON (not HTML error)

2. **Test App Links:**
   ```bash
   # On Android device with ADB
   adb shell pm get-app-links com.kelvind.glowtime
   ```

3. **Test QR Code:**
   - Scan QR code with Android camera
   - Should open app directly (no browser choice dialog)
   - If app not installed, should open web page

4. **Manual test:**
   - Long-press a link to `https://glowtime.ct.ws/redirect.php?ref=TEST`
   - Should show "Open in GlowTime" option

### 5. Troubleshooting

**If App Links don't work:**

1. **Check file accessibility:**
   ```bash
   curl https://glowtime.ct.ws/.well-known/assetlinks.json
   ```

2. **Verify JSON is valid:**
   - Use JSON validator online
   - Make sure no trailing commas

3. **Check package name matches:**
   - Must exactly match `android.package` in app.json: `com.kelvind.glowtime` ✅
   - Case-sensitive

4. **Verify SHA-256 fingerprint:**
   - Must match the keystore used to sign the app
   - For debug builds, use debug keystore fingerprint
   - For release builds, use release keystore fingerprint

5. **Clear app data and reinstall:**
   - Android caches App Links verification
   - Uninstall app, clear browser cache, reinstall

6. **Check Android App Links settings:**
   - Settings → Apps → GlowTime → Open by default
   - Should show "glowtime.ct.ws" as verified

## Current Workaround

Until App Links are configured, the JavaScript in `redirect.php` will:
- Try to open the app immediately on page load (Android-optimized)
- Show an "Open in App" button as fallback
- Redirect to web invoice after 1.5 seconds if app doesn't open

This works but still shows browser options when scanning QR codes. App Links eliminate this completely.

## Notes

- App Links require the app to be installed
- They work best when the app is published to Play Store
- For development, use your debug keystore fingerprint
- The file must be accessible without authentication
- Android verifies App Links automatically when app is installed

