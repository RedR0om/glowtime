# Smart Fallback Deep Linking - Implementation Complete ✅

## How It Works

Your QR codes now use a **smart fallback** approach that provides the best user experience:

### QR Code Format
- **New Format:** `https://glowtime.ct.ws/redirect.php?ref=BOOK-REF`
- **Old Format:** `glowtime://invoice?ref=BOOK-REF` (direct custom scheme)

### Smart Fallback Flow

1. **User scans QR code** → Opens `redirect.php?ref=BOOK-REF`
2. **redirect.php tries to open app** → Attempts `glowtime://invoice?ref=BOOK-REF`
3. **If app is installed:**
   - App opens immediately
   - User sees invoice in the app
4. **If app is NOT installed:**
   - After 2 seconds, automatically redirects to web invoice
   - User sees invoice in browser
   - No error messages or broken links

## Benefits

✅ **Works everywhere** - QR codes work in browsers and apps  
✅ **Better UX** - No broken links if app isn't installed  
✅ **Automatic detection** - Detects if app opened and cancels fallback  
✅ **Fast** - 2 second timeout for quick fallback  
✅ **Branded** - Uses Glowtime colors (#e91e63 gradient)

## Technical Details

### Files Updated

1. **`client_book.php`**
   - QR codes now point to `redirect.php?ref=BOOK-REF`
   - Still generates QR codes with the same format
   - No breaking changes to existing functionality

2. **`redirect.php`**
   - Enhanced smart fallback logic
   - Uses iframe method for better app detection
   - Listens for page visibility/blur events
   - 2 second timeout before fallback
   - Branded with Glowtime colors

### How redirect.php Works

```javascript
1. Try to open app via iframe (works better than window.location)
2. For Android: Also try direct window.location
3. Listen for visibility/blur events (indicates app opened)
4. If app doesn't open within 2 seconds → redirect to web
5. If app opens → cancel fallback timer
```

## Testing

### Test with App Installed:
1. Generate a booking QR code
2. Scan with phone camera
3. Should open in Expo app immediately

### Test without App:
1. Generate a booking QR code
2. Scan with phone camera
3. Should show "Opening Invoice..." for 2 seconds
4. Then redirect to web invoice page

### Test in Browser:
1. Visit: `https://glowtime.ct.ws/redirect.php?ref=BOOK-20241219-123`
2. Should try to open app, then redirect to web invoice

## Expo App Configuration

Your Expo app still needs to handle the `glowtime://` scheme. Make sure your `app.json` has:

```json
{
  "expo": {
    "scheme": "glowtime",
    "android": {
      "intentFilters": [
        {
          "action": "VIEW",
          "data": [
            {
              "scheme": "glowtime",
              "host": "invoice"
            }
          ],
          "category": ["BROWSABLE", "DEFAULT"]
        }
      ]
    }
  }
}
```

## Summary

✅ QR codes now use smart fallback  
✅ Works with and without app installed  
✅ Better user experience  
✅ No breaking changes  
✅ Ready to use!

The smart fallback is now active. All new QR codes will use this approach automatically!

