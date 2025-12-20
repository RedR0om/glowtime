<?php
/**
 * Deep Link Redirect Page
 * 
 * This page tries to open the GlowTime app first, then falls back to the web invoice page
 * if the app is not installed.
 * 
 * Usage: redirect.php?ref=BOOK-REF
 */

// Auto-detect environment based on current host
$isProduction = isset($_SERVER['HTTP_HOST']) && (
    $_SERVER['HTTP_HOST'] === 'glowtime.ct.ws' || 
    $_SERVER['HTTP_HOST'] === 'www.glowtime.ct.ws'
);

$ref = isset($_GET['ref']) ? htmlspecialchars($_GET['ref']) : '';

if (empty($ref)) {
    // No reference provided, redirect to home
    $webUrl = $isProduction 
        ? "https://glowtime.ct.ws/"
        : "http://glowtime.test/";
    header("Location: " . $webUrl);
    exit;
}

// Build URLs
$webUrl = $isProduction 
    ? "https://glowtime.ct.ws/invoice.php?ref=" . urlencode($ref)
    : "http://glowtime.test/invoice.php?ref=" . urlencode($ref);

$appUrl = "glowtime://invoice?ref=" . urlencode($ref);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opening Invoice...</title>
    <meta http-equiv="refresh" content="3;url=<?php echo htmlspecialchars($webUrl); ?>">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #e91e63 0%, #f06292 100%);
            color: white;
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        p {
            margin: 10px 0;
            font-size: 16px;
        }
        .app-button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: white;
            color: #e91e63;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s;
        }
        .app-button:hover {
            transform: scale(1.05);
        }
        .app-button:active {
            transform: scale(0.95);
        }
    </style>
    <script>
        // Android app launch - optimized for Android devices
        (function() {
            var appUrl = "<?php echo $appUrl; ?>";
            var webUrl = "<?php echo $webUrl; ?>";
            var appOpened = false;
            var fallbackTimer;
            var isAndroid = /Android/i.test(navigator.userAgent);
            
            // Android-specific app opening
            function tryOpenApp() {
                if (!isAndroid) {
                    // Not Android - redirect to web
                    window.location.href = webUrl;
                    return;
                }
                
                // Android: Use iframe method (most reliable for Android)
                var iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.style.width = '1px';
                iframe.style.height = '1px';
                iframe.src = appUrl;
                document.body.appendChild(iframe);
                
                // Also try direct location as backup (Android Chrome)
                setTimeout(function() {
                    window.location = appUrl;
                }, 100);
                
                // Remove iframe after delay
                setTimeout(function() {
                    if (iframe.parentNode) {
                        document.body.removeChild(iframe);
                    }
                }, 2000);
            }
            
            // Try to open app IMMEDIATELY (before DOM ready)
            tryOpenApp();
            
            // Also try when DOM is ready (in case first attempt was too early)
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', tryOpenApp);
            } else {
                tryOpenApp();
            }
            
            // Set fallback timer - redirect to web if app doesn't open
            fallbackTimer = setTimeout(function() {
                if (!appOpened) {
                    window.location.href = webUrl;
                }
            }, 1500); // 1.5 seconds for faster fallback
            
            // Listen for page visibility change (indicates app might have opened)
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    appOpened = true;
                    clearTimeout(fallbackTimer);
                }
            });
            
            // Listen for blur event (indicates app might have opened)
            window.addEventListener('blur', function() {
                appOpened = true;
                clearTimeout(fallbackTimer);
            });
            
            // Make app button work
            window.openInApp = function() {
                window.location = appUrl;
                setTimeout(function() {
                    if (!appOpened) {
                        window.location.href = webUrl;
                    }
                }, 500);
            };
        })();
    </script>
</head>
<body>
    <div class="spinner"></div>
    <h1>Opening Invoice...</h1>
    <p style="font-size: 12px; opacity: 0.6; margin-top: 20px;">Reference: <?php echo htmlspecialchars($ref); ?></p>
    
    <!-- Fallback: Direct link if JavaScript is disabled -->
    <noscript>
        <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($webUrl); ?>">
        <p><a href="<?php echo htmlspecialchars($webUrl); ?>" style="color: white; text-decoration: underline;">Click here if you're not redirected</a></p>
    </noscript>
</body>
</html>

