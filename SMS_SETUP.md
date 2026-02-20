# SMS Notification Setup Guide

## Overview
The system now automatically sends SMS alerts when product stock falls below the reorder threshold. The SMS system includes history tracking, multiple recipient support, and a management interface.

## New Features
- ✅ SMS History Tracking - View all sent SMS messages
- ✅ Multiple Recipients - Send alerts to multiple phone numbers
- ✅ SMS Management UI - Manage settings and view statistics
- ✅ Statistics Dashboard - Track success/failure rates
- ✅ Test SMS Function - Test your SMS configuration

## Configuration

### Step 1: Edit `sms_service.php`
Open `sms_service.php` and configure your SMS provider settings:

```php
// Enable/disable SMS notifications
$this->enabled = true;  // Set to false to disable

// Choose your provider: 'semaphore', 'twilio', or 'custom'
$this->provider = 'semaphore';

// Set your phone number (format: +639123456789)
$this->defaultRecipient = '+639123456789';  // CHANGE THIS!
```

### Step 2: Configure Your SMS Provider

#### Option A: Semaphore SMS (Recommended for Philippines)
1. Sign up at https://semaphore.co/
2. Get your API key from the dashboard
3. In `sms_service.php`, set:
   ```php
   $this->provider = 'semaphore';
   $this->apiKey = 'YOUR_SEMAPHORE_API_KEY';
   ```

#### Option B: Twilio
1. Sign up at https://www.twilio.com/
2. Get your Account SID and Auth Token
3. Get a Twilio phone number
4. In `sms_service.php`, set:
   ```php
   $this->provider = 'twilio';
   $this->apiKey = 'YOUR_TWILIO_ACCOUNT_SID';
   $this->apiSecret = 'YOUR_TWILIO_AUTH_TOKEN';
   $this->fromNumber = 'YOUR_TWILIO_PHONE_NUMBER';
   ```

#### Option C: Custom HTTP API
1. Configure your custom API endpoint
2. In `sms_service.php`, set:
   ```php
   $this->provider = 'custom';
   $this->apiUrl = 'https://your-sms-api.com/send';
   $this->apiKey = 'YOUR_API_KEY';
   ```

## How It Works

1. **Automatic Detection**: When a sale is made or stock is updated, the system checks if any products are below their reorder threshold.

2. **SMS Alert**: If low stock is detected, an SMS is automatically sent with:
   - Product name and code
   - Current stock level
   - Reorder threshold
   - Critical status (if stock ≤ 2)

3. **Alert Frequency**: SMS is sent immediately when stock drops below threshold. To prevent spam, only products affected by the current transaction are checked.

## Testing

To test SMS functionality:
1. Configure your SMS provider settings
2. Set a product's reorder threshold (e.g., 10)
3. Make a sale that reduces stock below the threshold
4. Check your phone for the SMS alert

## Troubleshooting

- **SMS not sending**: Check PHP error logs for SMS service errors
- **API errors**: Verify your API credentials are correct
- **Phone number format**: Ensure phone numbers include country code (e.g., +63 for Philippines)

## SMS Management Interface

Access the SMS Management page from the sidebar to:
- View SMS history and statistics
- Add/remove recipients
- Send test SMS messages
- Monitor success/failure rates

Navigate to: **SMS Management** in the sidebar menu

## SMS History

All SMS messages are automatically logged to the `sms_history` table with:
- Timestamp
- Recipient phone number
- Message content
- Status (success/failed/pending)
- Provider used
- Error messages (if failed)
- Product IDs that triggered the alert

## Multiple Recipients

You can configure multiple recipients in two ways:

### Method 1: Via SMS Management UI
1. Go to SMS Management page
2. Add recipients using the phone number input
3. Click "Save Settings"

### Method 2: Via Database
```sql
UPDATE sms_settings 
SET setting_value = '["+639123456789", "+639987654321"]' 
WHERE setting_key = 'recipients';
```

## Database Migration

Run the SMS migration to enable history tracking:
```bash
php run_migration_sms.php
```

Or manually:
```sql
-- Run migration_sms.sql
mysql -u root login_system < migration_sms.sql
```

## Disabling SMS

To temporarily disable SMS without removing code:
```php
$this->enabled = false;
```

Or via SMS Management UI (coming soon)

## API Endpoints

- `GET /fetch_sms_settings.php` - Get current SMS settings
- `POST /save_sms_settings.php` - Save SMS settings
- `GET /fetch_sms_history.php` - Get SMS history
- `GET /fetch_sms_statistics.php` - Get SMS statistics
- `POST /send_test_sms.php` - Send test SMS
