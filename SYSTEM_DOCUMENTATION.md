# Inventory Management System - Complete Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [Architecture Diagram](#architecture-diagram)
3. [Core Components](#core-components)
4. [SMS Notification System](#sms-notification-system)
5. [Database Schema](#database-schema)
6. [Workflow Diagrams](#workflow-diagrams)
7. [API Endpoints](#api-endpoints)
8. [User Roles & Permissions](#user-roles--permissions)
9. [Installation & Setup](#installation--setup)

---

## System Overview

The Inventory Management System is a comprehensive PHP-based web application for managing automotive parts inventory, sales, forecasting, and automated SMS notifications. The system provides real-time stock tracking, demand forecasting, reorder recommendations, and automated low-stock alerts via SMS.

### Key Features
- **Product Management**: Add, edit, delete products with categories
- **Inventory Tracking**: Real-time stock levels with transaction history
- **Sales Management**: Record sales with automatic inventory updates
- **Demand Forecasting**: Weekly and monthly usage predictions
- **Reorder Recommendations**: Smart suggestions based on stock levels and usage
- **SMS Alerts**: Automated low-stock notifications via SMS
- **Dashboard Analytics**: Real-time KPIs, charts, and insights
- **Workload Planning**: Peak workload predictions for staffing

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │   Browser    │  │   Mobile     │  │   Tablet     │         │
│  │   (Chrome)   │  │   Browser    │  │   Browser    │         │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘         │
└─────────┼──────────────────┼──────────────────┼────────────────┘
          │                  │                  │
          └──────────────────┼──────────────────┘
                             │
                    ┌────────▼────────┐
                    │   Web Server     │
                    │   (Apache/XAMPP) │
                    └────────┬─────────┘
                             │
          ┌──────────────────┼──────────────────┐
          │                  │                  │
    ┌─────▼─────┐    ┌───────▼──────┐   ┌───────▼──────┐
    │   PHP     │    │   JavaScript │   │   CSS/UI    │
    │  Backend  │    │   Frontend   │   │   Styling   │
    └─────┬─────┘    └──────────────┘   └─────────────┘
          │
          │
    ┌─────▼─────────────────────────────────────┐
    │         APPLICATION LAYER                  │
    │  ┌──────────────┐  ┌──────────────┐      │
    │  │  Controllers │  │   Services   │      │
    │  │              │  │              │      │
    │  │ • inventory  │  │ • SMS Service│      │
    │  │ • sales      │  │ • Forecasting│      │
    │  │ • dashboard  │  │ • Analytics  │      │
    │  └──────┬───────┘  └──────┬───────┘      │
    └─────────┼──────────────────┼──────────────┘
              │                  │
              │                  │
    ┌─────────▼──────────────────▼─────────┐
    │         DATA LAYER                    │
    │  ┌──────────────────────────────┐   │
    │  │    MySQL Database             │   │
    │  │                               │   │
    │  │ • users                       │   │
    │  │ • products                    │   │
    │  │ • categories                  │   │
    │  │ • inventory_transactions      │   │
    │  │ • sales / sale_items          │   │
    │  │ • work_orders                 │   │
    │  │ • sms_history                 │   │
    │  │ • reorder_preparations        │   │
    │  └───────────────────────────────┘   │
    └───────────────────────────────────────┘
              │
              │
    ┌─────────▼─────────┐
    │   EXTERNAL APIs    │
    │                   │
    │ • Semaphore SMS   │
    │ • Twilio SMS      │
    │ • Custom SMS API  │
    └───────────────────┘
```

---

## Core Components

### 1. Authentication System
- **Files**: `index.php`, `login_process.php`, `signup_process.php`
- **Features**: User registration, login, session management
- **Roles**: Owner, Manager, User

### 2. Product Management
- **Files**: `inventory.php`, `add_product.php`, `update_product.php`, `fetch_products.php`
- **Features**: CRUD operations for products, category management, stock tracking

### 3. Sales System
- **Files**: `sales.php`, `save_sale.php`
- **Features**: Create sales transactions, automatic inventory deduction, labor tracking

### 4. Inventory Tracking
- **Files**: `inventory.php`, `add_stock.php`, `inventory.js`
- **Features**: Real-time stock levels, transaction history, restock recording

### 5. Forecasting & Analytics
- **Files**: `fetch_forecast_data.php`, `fetch_dashboard_data.php`, `inventory-forecast.js`
- **Features**: Weekly/monthly demand forecasting, seasonal analysis, workload prediction

### 6. SMS Notification System
- **Files**: `sms_service.php`, `SMS_SETUP.md`
- **Features**: Automated low-stock alerts, multi-provider support (Semaphore, Twilio, Custom)

### 7. Dashboard
- **Files**: `dashboard.php`, `fetch_dashboard_data.php`
- **Features**: Real-time KPIs, charts, top products, low stock alerts

---

## SMS Notification System

### Architecture Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    SMS Notification Flow                     │
└─────────────────────────────────────────────────────────────┘

1. TRIGGER EVENT
   │
   ├─ Sale Transaction (save_sale.php)
   │  └─> Stock decreases below threshold
   │
   ├─ Restock Transaction (add_stock.php)
   │  └─> Check if other items still low
   │
   └─ Manual Check (sms_service.php)
      └─> Admin-triggered check
   │
   ▼
2. CHECK LOW STOCK
   │
   └─> SMSService::checkAndSendLowStockAlerts()
       │
       ├─ Query products WHERE stock <= reorder_threshold
       ├─ Filter by affected product IDs (if provided)
       └─ Limit to 10 items per alert
   │
   ▼
3. BUILD SMS MESSAGE
   │
   └─> Format message with:
       ├─ Product name and code
       ├─ Current stock level
       ├─ Reorder threshold
       └─ Critical status (if stock ≤ 2)
   │
   ▼
4. SEND SMS
   │
   └─> SMSService::sendSMS()
       │
       ├─ Validate phone number format
       ├─ Select provider (Semaphore/Twilio/Custom)
       └─ Send via HTTP API
   │
   ▼
5. LOG RESULT
   │
   └─> Record in sms_history table
       ├─ Success/Failure status
       ├─ Timestamp
       ├─ Message content
       └─ Recipient phone number
```

### SMS Provider Comparison

| Provider | Best For | Setup Complexity | Cost | API Format |
|----------|----------|------------------|------|------------|
| **Semaphore** | Philippines | Easy | Low | Simple HTTP POST |
| **Twilio** | Global | Medium | Medium | REST API with Auth |
| **Custom** | Enterprise | High | Varies | Custom JSON/XML |

### SMS Service Class Structure

```php
SMSService
├── __construct()
│   ├── enabled (bool)
│   ├── provider (string)
│   ├── apiKey (string)
│   ├── apiSecret (string) [Twilio only]
│   ├── fromNumber (string) [Twilio only]
│   ├── apiUrl (string)
│   └── defaultRecipient (string)
│
├── sendSMS($message, $toPhoneNumber)
│   ├── Validate phone number
│   ├── Route to provider method
│   └── Return success/failure
│
├── sendViaSemaphore($message, $phone)
│   └── HTTP POST to Semaphore API
│
├── sendViaTwilio($message, $phone)
│   └── HTTP POST with Basic Auth
│
├── sendViaCustom($message, $phone)
│   └── HTTP POST with JSON payload
│
└── checkAndSendLowStockAlerts($conn, $affectedProductIds)
    ├── Query low stock items
    ├── Build alert message
    ├── Send SMS
    └── Return list of alerted products
```

---

## Database Schema

### Entity Relationship Diagram

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   users     │         │  categories  │         │  products   │
├─────────────┤         ├──────────────┤         ├─────────────┤
│ id (PK)     │         │ category_id  │◄────────│ product_id  │
│ name        │         │   (PK)       │         │   (PK)       │
│ email       │         │ category_name│         │ category_id  │
│ password    │         └──────────────┘         │   (FK)       │
│ role        │                                  │ description  │
└──────┬──────┘                                  │ unit        │
       │                                         │ unit_cost   │
       │                                         │ selling_price│
       │                                         │ code        │
       │                                         │ initial_qty │
       │                                         │ reorder_threshold│
       │                                         └──────┬───────┘
       │                                                │
       │                                         ┌──────▼──────────────┐
       │                                         │ inventory_transactions│
       │                                         ├─────────────────────┤
       │                                         │ transaction_id (PK) │
       │                                         │ product_id (FK)     │
       │                                         │ transaction_date   │
       │                                         │ quantity_change    │
       │                                         │ transaction_type   │
       │                                         │ remarks            │
       │                                         │ created_by (FK)    │
       │                                         └─────────────────────┘
       │
       │                                         ┌─────────────┐
       │                                         │    sales    │
       │                                         ├─────────────┤
       │                                         │ id (PK)     │
       │                                         │ sale_date   │
       │                                         │ customer_name│
       │                                         │ plate_number│
       │                                         │ parts_total │
       │                                         │ labor_total │
       │                                         │ created_by (FK)│
       └─────────────────────────────────────────┘
       │
       │                                         ┌─────────────┐
       │                                         │ sale_items  │
       │                                         ├─────────────┤
       │                                         │ id (PK)     │
       │                                         │ sale_id (FK)│
       │                                         │ line_type   │
       │                                         │ product_id (FK)│
       │                                         │ description │
       │                                         │ quantity    │
       │                                         │ unit_price  │
       │                                         │ amount      │
       │                                         └─────────────┘
       │
       │                                         ┌─────────────┐
       │                                         │ sms_history │
       │                                         ├─────────────┤
       │                                         │ id (PK)     │
       │                                         │ sent_at     │
       │                                         │ recipient   │
       │                                         │ message     │
       │                                         │ status      │
       │                                         │ provider    │
       │                                         │ error_msg   │
       │                                         └─────────────┘
```

### Key Tables

#### products
- Stores product information
- Links to categories
- Contains pricing and stock threshold data

#### inventory_transactions
- Records all stock movements (sales, restocks, adjustments)
- Calculates current stock via SUM(quantity_change)

#### sales & sale_items
- Sales header and line items
- Supports both parts and labor line types
- Automatically creates inventory transactions

#### sms_history
- Logs all SMS sent by the system
- Tracks success/failure status
- Useful for debugging and audit trail

---

## Workflow Diagrams

### 1. Sale Transaction Workflow

```
┌─────────────────────────────────────────────────────────────┐
│              Sale Transaction Complete Flow                  │
└─────────────────────────────────────────────────────────────┘

START: User creates sale
│
├─> Validate sale data
│   ├─ Sale date
│   ├─ Customer info
│   └─ Items (parts/labor)
│
├─> BEGIN TRANSACTION
│
├─> Insert into sales table
│   └─> Get sale_id
│
├─> For each item:
│   ├─> Insert into sale_items
│   └─> If parts item:
│       └─> Create inventory_transaction (negative qty)
│
├─> If labor > 0:
│   └─> Create work_order record
│
├─> COMMIT TRANSACTION
│
├─> Check affected products for low stock
│   └─> SMSService::checkAndSendLowStockAlerts()
│       ├─> Query products WHERE stock <= threshold
│       ├─> Build SMS message
│       └─> Send SMS alert
│
└─> Return success response
```

### 2. Restock Workflow

```
┌─────────────────────────────────────────────────────────────┐
│                  Restock Transaction Flow                     │
└─────────────────────────────────────────────────────────────┘

START: User records restock
│
├─> Validate input
│   ├─ Product ID
│   ├─ Quantity (must be positive)
│   └─ Remarks
│
├─> Create inventory_transaction
│   ├─ transaction_type = 'restock'
│   ├─ quantity_change = +quantity
│   └─ remarks = user input
│
├─> Stock automatically updated
│   └─> (via product_stock view)
│
├─> Check for remaining low stock items
│   └─> SMSService::checkAndSendLowStockAlerts()
│       └─> Alert if other products still low
│
└─> Return success response
```

### 3. Low Stock Detection & Alert Flow

```
┌─────────────────────────────────────────────────────────────┐
│            Low Stock Detection & SMS Alert Flow               │
└─────────────────────────────────────────────────────────────┘

TRIGGER: Stock change detected
│
├─> Get affected product IDs
│   └─> From sale or restock transaction
│
├─> Query database
│   └─> SELECT products WHERE:
│       ├─ current_stock <= reorder_threshold
│       └─ product_id IN (affected IDs)
│
├─> Build alert list
│   ├─ Limit to 10 items
│   └─ Sort by stock level (lowest first)
│
├─> Format SMS message
│   ├─ Header: "LOW STOCK ALERT"
│   ├─ For each item:
│   │   ├─ Status (CRITICAL if ≤ 2, else Low)
│   │   ├─ Product name (code)
│   │   ├─ Stock: X | Threshold: Y
│   └─ Footer: "Please reorder soon."
│
├─> Send SMS
│   ├─ Validate phone number
│   ├─ Select provider (Semaphore/Twilio/Custom)
│   ├─ Make HTTP request
│   └─ Handle response
│
├─> Log to sms_history
│   ├─ Timestamp
│   ├─ Recipient
│   ├─ Message
│   ├─ Status (success/failed)
│   └─ Error message (if failed)
│
└─> Return alert results
```

### 4. Forecasting Workflow

```
┌─────────────────────────────────────────────────────────────┐
│                  Demand Forecasting Flow                      │
└─────────────────────────────────────────────────────────────┘

START: User views forecast tab
│
├─> Fetch sales data
│   └─> GROUP BY product_id, week/month
│
├─> Calculate averages
│   ├─ Weekly: SUM(quantity) / COUNT(DISTINCT week)
│   └─ Monthly: SUM(quantity) / COUNT(DISTINCT month)
│
├─> Predict future demand
│   ├─ Weekly: avg_weekly * 4
│   └─ Monthly: avg_monthly * 3
│
├─> Calculate seasonal trends
│   └─> Group by month for last 12 months
│
├─> Display results
│   ├─ Forecast tables
│   └─ Seasonal chart
│
└─> Generate reorder recommendations
    └─> Based on forecast + current stock
```

---

## API Endpoints

### Product Management
- `GET /fetch_products.php` - Get all products with filters
- `POST /add_product.php` - Create new product
- `POST /update_product.php` - Update existing product
- `GET /get_product.php?id=X` - Get single product
- `POST /delete_product.php` - Delete product

### Inventory
- `POST /add_stock.php` - Record restock transaction
- `GET /inventory.php` - Inventory management UI

### Sales
- `POST /save_sale.php` - Create new sale transaction
- `GET /sales.php` - Sales management UI

### Forecasting
- `GET /fetch_forecast_data.php` - Get forecast data
- `GET /fetch_dashboard_data.php` - Get dashboard KPIs

### SMS
- `POST /sms_service.php` - Send SMS (internal)
- `GET /sms_history.php` - View SMS history (if implemented)

---

## User Roles & Permissions

### Owner
- Full system access
- User management (create manager accounts)
- All inventory operations
- View all reports and analytics

### Manager
- Inventory management
- Sales operations
- View reports
- Cannot manage users

### User
- View inventory
- View reports
- Limited access (if implemented)

---

## Installation & Setup

### Prerequisites
- XAMPP (Apache + MySQL + PHP 7.4+)
- Web browser
- SMS provider account (Semaphore/Twilio)

### Step 1: Database Setup
```sql
-- Run setup.sql to create database and tables
mysql -u root < setup.sql

-- Run migration scripts (optional features)
mysql -u root < migration_sales.sql
mysql -u root < migration_dashboard.sql
mysql -u root < migration_forecast.sql
```

### Step 2: Configuration
1. Edit `db.php`:
   ```php
   $conn = new mysqli("localhost", "root", "", "login_system");
   ```

2. Edit `sms_service.php`:
   ```php
   $this->enabled = true;
   $this->provider = 'semaphore';
   $this->apiKey = 'YOUR_API_KEY';
   $this->defaultRecipient = '+639123456789';
   ```

### Step 3: Access System
- Navigate to `http://localhost/Inventory_Dispeedway/`
- Register first user (becomes Owner)
- Login and start using the system

---

## System Features Summary

### ✅ Implemented Features
- [x] User authentication & authorization
- [x] Product CRUD operations
- [x] Category management
- [x] Real-time inventory tracking
- [x] Sales transactions
- [x] Automatic inventory updates
- [x] Low stock SMS alerts
- [x] Demand forecasting
- [x] Reorder recommendations
- [x] Dashboard analytics
- [x] Workload planning
- [x] Multi-provider SMS support

### 🔄 Enhanced Features (New)
- [x] SMS history tracking
- [x] Multiple recipient support
- [x] SMS management UI
- [x] Comprehensive documentation

---

## Troubleshooting

### SMS Not Sending
1. Check `sms_service.php` configuration
2. Verify API credentials
3. Check PHP error logs
4. Ensure phone number format (+country code)
5. Verify SMS provider account balance

### Database Connection Issues
1. Check MySQL service is running
2. Verify credentials in `db.php`
3. Ensure database exists
4. Check table structure matches migrations

### Stock Not Updating
1. Verify `inventory_transactions` table exists
2. Check transaction is committed
3. Verify `product_stock` view is correct
4. Check for JavaScript errors in browser console

---

## Future Enhancements

- [ ] Email notifications
- [ ] Barcode scanning
- [ ] Multi-location inventory
- [ ] Supplier management
- [ ] Purchase orders
- [ ] Advanced reporting
- [ ] Mobile app
- [ ] API for third-party integrations

---

*Last Updated: February 2026*
