# App.json Update Instructions

## Your Current IntentFilters

You currently have:
```json
"intentFilters": [
  {
    "action": "VIEW",
    "autoVerify": true,
    "data": [
      {
        "scheme": "glowtime",
        "host": "*"
      }
    ],
    "category": ["BROWSABLE", "DEFAULT"]
  }
]
```

## Updated IntentFilters (Add HTTPS Support)

**Replace your `intentFilters` with this:**

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

## What Changed?

✅ **Added HTTPS App Links support** - Now your app can handle:
- `glowtime://*` (your existing custom scheme) ✅
- `https://glowtime.ct.ws/redirect.php?ref=*` (new App Links) ✅

## Complete Android Section (for reference)

Your complete `android` section should look like:

```json
"android": {
  "adaptiveIcon": {
    "backgroundColor": "#4CAF50",
    "foregroundImage": "./assets/images/android-icon-foreground.png",
    "backgroundImage": "./assets/images/android-icon-background.png",
    "monochromeImage": "./assets/images/android-icon-monochrome.png"
  },
  "edgeToEdgeEnabled": true,
  "predictiveBackGestureEnabled": false,
  "package": "com.kelvind.glowtime",
  "statusBar": {
    "hidden": true
  },
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
}
```

## Next Steps

1. ✅ Update `app.json` with the new `intentFilters` (add HTTPS entry)
2. ⚠️ Get your SHA-256 fingerprint (see `UNIVERSAL_LINKS_SETUP.md`)
3. ⚠️ Update `.well-known/assetlinks.json` with your SHA-256 fingerprint
4. ✅ Rebuild your app with `eas build` or `expo build`
5. ✅ Test by scanning a QR code

## Getting SHA-256 Fingerprint

**For EAS Build (recommended):**
```bash
eas credentials
# Select Android → View credentials → Copy SHA-256 fingerprint
```

**For local debug builds:**
```bash
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android
# Look for "SHA256:" and copy the value (remove colons/spaces)
```

**For production builds:**
```bash
keytool -list -v -keystore your-release-key.keystore -alias your-key-alias
# Look for "SHA256:" and copy the value (remove colons/spaces)
```

## Important Notes

- The fingerprint must match the keystore used to sign your app
- For development: use debug keystore fingerprint
- For production: use release keystore fingerprint
- After updating `assetlinks.json`, rebuild and reinstall the app
- Android verifies App Links when the app is installed

