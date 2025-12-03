# Email Setup Guide for Glowtime Salon

## Overview
The application now uses **PHPMailer with SMTP** for reliable email delivery. It supports Gmail, Outlook, and other SMTP providers. The system automatically falls back to PHP's `mail()` function if SMTP is not configured.

## Quick Setup (Gmail)

### Step 1: Create a Gmail App Password

1. Go to your Google Account: https://myaccount.google.com/
2. Enable **2-Step Verification** (required for App Passwords)
3. Go to **Security** → **2-Step Verification** → **App passwords**
4. Create a new app password:
   - Select "Mail" as the app
   - Select "Other" as the device
   - Name it "Glowtime Salon"
   - Click "Generate"
5. **Copy the 16-character password** (you'll need this)

### Step 2: Configure Email Settings

Create or edit your `.env` file in the project root:

```env
# Email Configuration
EMAIL_ENABLED=true
EMAIL_METHOD=smtp

# SMTP Settings (Gmail)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-16-character-app-password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME=Glowtime Salon
```

**Important:** 
- Use your **Gmail address** for `SMTP_USERNAME` and `SMTP_FROM_EMAIL`
- Use the **16-character App Password** (not your regular Gmail password) for `SMTP_PASSWORD`
- Never commit your `.env` file to Git (it's already in `.gitignore`)

### Step 3: Test Email

1. Make a test booking
2. Check the recipient's inbox (and spam folder)
3. Check PHP error logs if email doesn't arrive:
   - XAMPP: `C:\xampp\php\logs\php_error_log`
   - Or enable debug mode (see below)

## Alternative Email Providers

### Outlook/Hotmail

```env
SMTP_HOST=smtp-mail.outlook.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-email@outlook.com
SMTP_PASSWORD=your-password
SMTP_FROM_EMAIL=your-email@outlook.com
SMTP_FROM_NAME=Glowtime Salon
```

### SendGrid

```env
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=apikey
SMTP_PASSWORD=your-sendgrid-api-key
SMTP_FROM_EMAIL=noreply@yourdomain.com
SMTP_FROM_NAME=Glowtime Salon
```

### Mailgun

```env
SMTP_HOST=smtp.mailgun.org
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-mailgun-username
SMTP_PASSWORD=your-mailgun-password
SMTP_FROM_EMAIL=noreply@yourdomain.com
SMTP_FROM_NAME=Glowtime Salon
```

## Configuration Options

### Email Settings

| Setting | Description | Default |
|---------|-------------|---------|
| `EMAIL_ENABLED` | Enable/disable email sending | `true` |
| `EMAIL_METHOD` | `smtp` or `mail` | `smtp` |
| `SMTP_HOST` | SMTP server address | `smtp.gmail.com` |
| `SMTP_PORT` | SMTP port (587 for TLS, 465 for SSL) | `587` |
| `SMTP_SECURE` | `tls` or `ssl` | `tls` |
| `SMTP_USERNAME` | SMTP username/email | (empty) |
| `SMTP_PASSWORD` | SMTP password/app password | (empty) |
| `SMTP_FROM_EMAIL` | Sender email address | `noreply@glowtime.com` |
| `SMTP_FROM_NAME` | Sender display name | `Glowtime Salon` |

### Fallback to mail()

If SMTP credentials are not configured, the system automatically falls back to PHP's `mail()` function. This requires proper server mail configuration.

## Debugging

### Enable Debug Mode

To see detailed email sending logs, you can temporarily enable debug mode in the code:

In `inc/bootstrap.php`, change the `sendEmail()` call to include debug:

```php
$emailResult = sendEmail($to, $subject, $message, true); // true = debug mode
```

This will log detailed information to your PHP error log.

### Check Email Status

The `sendEmail()` function now returns an array:

```php
$result = sendEmail($to, $subject, $message);
if ($result['success']) {
    echo "Email sent!";
} else {
    echo "Error: " . $result['error'];
}
```

### Common Issues

1. **"Authentication failed"**
   - Check your SMTP username and password
   - For Gmail, make sure you're using an App Password, not your regular password
   - Verify 2-Step Verification is enabled

2. **"Connection timeout"**
   - Check firewall settings
   - Verify SMTP host and port are correct
   - Try changing `SMTP_SECURE` from `tls` to `ssl` and port to `465`

3. **"Emails going to spam"**
   - Add SPF/DKIM records to your domain
   - Use a professional email address (not a free Gmail)
   - Consider using a dedicated email service (SendGrid, Mailgun)

4. **"PHPMailer not found"**
   - Run: `composer require phpmailer/phpmailer`
   - Make sure `vendor/autoload.php` is being loaded

## Email Notifications Sent

The system sends the following emails:

1. **Booking Confirmation** - When a client successfully books an appointment
   - Includes: Booking reference, service details, date/time, stylist, payment info, status

2. **Payment Verification** - When admin verifies payment
   - Confirms booking is approved

3. **Payment Rejection** - When admin rejects payment
   - Notifies client of cancellation

4. **Admin Appointment Creation** - When admin creates appointment
   - Confirms appointment to client

## Security Notes

- ✅ Never commit `.env` file to Git (already in `.gitignore`)
- ✅ Use App Passwords for Gmail (not regular passwords)
- ✅ Keep SMTP credentials secure
- ✅ Consider using environment variables in production
- ✅ Use a dedicated email account for the application

## Production Recommendations

1. **Use a dedicated email service** (SendGrid, Mailgun, Amazon SES) for better deliverability
2. **Set up SPF/DKIM records** for your domain
3. **Monitor email delivery rates**
4. **Use a professional email address** (e.g., `noreply@glowtime.com` instead of Gmail)
5. **Set up email logging** to track sent emails

## Testing

1. Create a test booking with your own email
2. Check inbox and spam folder
3. Verify all booking details are correct
4. Test different email providers if needed

## Support

If emails are not working:
1. Check PHP error logs
2. Enable debug mode
3. Verify SMTP credentials
4. Test with a simple email script
5. Check server firewall/port restrictions
