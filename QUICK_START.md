# Quick Start Guide - Inventory Management System

## 🚀 Getting Started

### 1. Prerequisites
- XAMPP installed (Apache + MySQL + PHP 7.4+)
- Web browser (Chrome, Firefox, Edge)
- SMS provider account (Semaphore/Twilio) - Optional

### 2. Database Setup
```bash
# Run initial setup
mysql -u root < setup.sql

# Run optional migrations
mysql -u root < migration_sales.sql
mysql -u root < migration_dashboard.sql
mysql -u root < migration_forecast.sql
mysql -u root < migration_sms.sql
```

Or use PHP scripts:
```bash
php run_migration_sales.php
php run_migration_dashboard.php
php run_migration_forecast.php
php run_migration_sms.php
```

### 3. Configuration

#### Database Connection (`db.php`)
```php
$conn = new mysqli("localhost", "root", "", "login_system");
```

#### SMS Service (`sms_service.php`)
```php
$this->enabled = true;
$this->provider = 'semaphore';
$this->apiKey = 'YOUR_SEMAPHORE_API_KEY';
$this->defaultRecipient = '+639123456789';
```

### 4. Access System
1. Navigate to `http://localhost/Inventory_Dispeedway/`
2. Register first user (becomes Owner)
3. Login and start using

---

## 📋 System Features

### Core Modules

#### 1. Dashboard
- Real-time KPIs
- Sales charts
- Top products
- Low stock alerts
- Labor revenue tracking

#### 2. Inventory Management
- Product CRUD operations
- Real-time stock tracking
- Category management
- Stock transactions
- Restock recording

#### 3. Sales System
- Create sales transactions
- Parts and labor tracking
- Automatic inventory updates
- Customer information

#### 4. Forecasting
- Weekly demand forecast
- Monthly demand forecast
- Seasonal analysis
- Reorder recommendations

#### 5. SMS Notifications
- Low stock alerts
- Multiple recipients
- SMS history
- Statistics dashboard
- Test SMS function

---

## 🔧 Common Tasks

### Add a Product
1. Go to **Inventory** → **Current Stock** tab
2. Click **Add Part**
3. Fill in product details
4. Set reorder threshold
5. Click **Add Product**

### Record a Sale
1. Go to **Sales**
2. Enter customer information
3. Add parts and/or labor items
4. Click **Save Sale**
5. Inventory automatically updates

### Restock Inventory
1. Go to **Inventory** → **Current Stock**
2. Find product
3. Click **Restock** button
4. Enter quantity received
5. Click **Record Restock**

### Configure SMS
1. Go to **SMS Management**
2. Add recipient phone numbers
3. Click **Save Settings**
4. Test with **Send Test SMS**

### View Forecasts
1. Go to **Inventory** → **Forecasting** tab
2. View weekly/monthly predictions
3. Check seasonal trends
4. Review reorder recommendations

---

## 📊 Key Workflows

### Sale → Inventory Update → SMS Alert
```
Sale Created
    ↓
Inventory Deducted
    ↓
Stock Checked
    ↓
If Stock ≤ Threshold
    ↓
SMS Alert Sent
    ↓
Logged to History
```

### Restock → Stock Update
```
Restock Recorded
    ↓
Inventory Transaction Created
    ↓
Stock Updated (via view)
    ↓
Low Stock Check (other items)
    ↓
SMS Alert (if needed)
```

---

## 🗂️ File Structure

```
Inventory_Dispeedway/
├── Core Files
│   ├── index.php              # Login page
│   ├── dashboard.php          # Dashboard UI
│   ├── inventory.php          # Inventory management
│   ├── sales.php              # Sales interface
│   └── sidebar.php            # Navigation sidebar
│
├── API Endpoints
│   ├── add_product.php        # Create product
│   ├── update_product.php     # Update product
│   ├── save_sale.php          # Save sale transaction
│   ├── add_stock.php          # Record restock
│   └── fetch_*.php            # Data fetching endpoints
│
├── Services
│   └── sms_service.php        # SMS notification service
│
├── Database
│   ├── db.php                 # Database connection
│   ├── setup.sql              # Initial schema
│   └── migration_*.sql        # Feature migrations
│
├── Documentation
│   ├── SYSTEM_DOCUMENTATION.md # Complete system docs
│   ├── SYSTEM_DIAGRAMS.md      # Visual diagrams
│   ├── SMS_SETUP.md            # SMS setup guide
│   └── QUICK_START.md          # This file
│
└── JavaScript
    ├── inventory.js           # Inventory frontend logic
    └── inventory-forecast.js  # Forecasting logic
```

---

## 🔐 User Roles

### Owner
- Full system access
- User management
- All features enabled

### Manager
- Inventory management
- Sales operations
- Reports access
- Cannot manage users

---

## 📱 SMS Configuration

### Semaphore (Philippines)
1. Sign up at https://semaphore.co/
2. Get API key from dashboard
3. Update `sms_service.php`:
   ```php
   $this->provider = 'semaphore';
   $this->apiKey = 'YOUR_API_KEY';
   ```

### Twilio (Global)
1. Sign up at https://www.twilio.com/
2. Get Account SID and Auth Token
3. Get Twilio phone number
4. Update `sms_service.php`:
   ```php
   $this->provider = 'twilio';
   $this->apiKey = 'YOUR_ACCOUNT_SID';
   $this->apiSecret = 'YOUR_AUTH_TOKEN';
   $this->fromNumber = 'YOUR_TWILIO_NUMBER';
   ```

---

## 🐛 Troubleshooting

### SMS Not Sending
- Check API credentials
- Verify phone number format (+country code)
- Check PHP error logs
- Verify SMS provider account balance

### Database Connection Error
- Check MySQL service is running
- Verify credentials in `db.php`
- Ensure database exists

### Stock Not Updating
- Check `inventory_transactions` table exists
- Verify transaction is committed
- Check `product_stock` view

### Page Not Loading
- Check Apache service is running
- Verify file permissions
- Check PHP error logs

---

## 📚 Documentation

- **Complete Documentation**: See `SYSTEM_DOCUMENTATION.md`
- **Visual Diagrams**: See `SYSTEM_DIAGRAMS.md`
- **SMS Setup**: See `SMS_SETUP.md`

---

## 🎯 Next Steps

1. ✅ Complete database setup
2. ✅ Configure SMS provider (optional)
3. ✅ Add initial products
4. ✅ Set reorder thresholds
5. ✅ Start recording sales
6. ✅ Monitor dashboard
7. ✅ Review forecasts
8. ✅ Manage SMS recipients

---

*Happy Inventory Managing! 🎉*
