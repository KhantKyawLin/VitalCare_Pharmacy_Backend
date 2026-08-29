# 🏥 Vital Care Pharmacy - Backend Engine & API

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![WebSockets](https://img.shields.io/badge/WebSockets-Laravel_Reverb-6366F1?style=for-the-badge&logo=socketdotio&logoColor=white)](https://reverb.laravel.com)
[![Architecture](https://img.shields.io/badge/Architecture-Action_Pattern-0ea5e9?style=for-the-badge)](#-system-architecture--engineering-highlights)

A robust, enterprise-grade pharmaceutical backend system engineered to handle hybrid **Online E-Commerce** and in-store **Walk-in Point of Sale (POS)** operations. Built with a focus on high concurrency, audit compliance, FEFO (First-Expired-First-Out) batch inventory tracking, real-time alerting, and decoupled domain action patterns.

---

## 🌟 Key Domain & Architectural Features

### 1. 🛡️ Concurrency-Safe Checkout & Action Architecture
* **Decoupled Action Classes:** Business logic is extracted from fat controllers into single-responsibility actions (`CheckoutAction`, `AllocateFefoStockAction`, `ApplyPromotionsAction`).
* **Race-Condition Protection:** Utilizes database-level pessimistic locking (`lockForUpdate()`) inside atomic database transactions (`DB::beginTransaction()`) to prevent double-selling inventory under concurrent load.
* **Audit-Proof Inventory Movement:** Every stock deduction, restock, disposal, and return is permanently recorded in `product_movements` with negative/positive deltas and associated batch IDs.

### 2. 💊 FEFO (First-Expired-First-Out) Batch Allocation
* Automatic identification and depletion of stock from the earliest-expiring batch first.
* Granular batch tracking stored in `order_product_batches`, enabling full traceability in the event of manufacturer drug recalls.

### 3. 📑 Digital Prescription Review Queue
* Identifies restricted medications via `requires_prescription` flags.
* Secure image handling with automatic compression.
* **Automated Reversal Workflow:** Pharmacist rejection of a prescription automatically cancels restricted items from the order, restores inventory to stock via a reverse movement, and calculates refund adjustments.

### 4. ⏰ Automated Chronic Medication Refill Scanner
* Custom Artisan CLI command (`pharmacy:send-refill-reminders`) that scans past completed orders for chronic medications.
* Generates persistent records in `refill_reminders` 3 days before medication depletion and broadcasts live notification events via **Laravel Reverb WebSockets** to the customer's private channel.

### 5. 🏷️ Dynamic Promotional Engine
* Tiered promo calculations supporting percentage discounts, fixed reductions, Buy-One-Get-One (BOGO) / gift items, minimum spend thresholds, and maximum discount caps.

### 6. 📊 High-Performance Financial Reporting
* High-speed SQL `UNION` pagination combining online orders, POS walk-in sales, purchase expenses, and operational overheads directly at the database engine level for instant Profit & Loss generation.

---

## 🏗️ System Architecture

```
[ React Client (Web / POS) ]
              │  (REST API + JWT Bearer Tokens)
              ▼
    [ Laravel API Router & Middleware (Throttling / RBAC) ]
              │
    ┌─────────┴─────────────────────────────────────────┐
    ▼                                                   ▼
[ Controllers (Thin) ]                         [ WebSocket Broadcast ]
    │                                                   │
    ▼                                                   ▼
[ Domain Action Services ]                      [ Laravel Reverb ]
    ├── CheckoutAction                                  │
    ├── AllocateFefoStockAction                         ▼
    └── ApplyPromotionsAction               [ Real-Time Client Push ]
    │
    ▼ (Pessimistic Lock / Transactions)
[ MySQL Database Engine ]
    ├── orders & order_products
    ├── product_movements (FEFO Batches)
    ├── refill_reminders
    └── activity_logs
```

---

## 🛠️ Technology Stack

* **Framework:** Laravel 11.x
* **Language:** PHP 8.2+
* **Database:** MySQL 8.0+
* **Real-Time Broadcasting:** Laravel Reverb & Laravel Echo
* **Authentication:** JWT (JSON Web Tokens) with Role-Based Access Control (RBAC)
* **Image Processing:** Native GD Library automated compression pipeline

---

## 🚀 Local Installation & Setup

### Prerequisites
* PHP >= 8.2 with `pdo_mysql`, `gd`, `bcmath`, `mbstring` extensions enabled.
* Composer 2.x
* MySQL Server (or XAMPP / Docker)

### 1. Clone the repository
```bash
git clone https://github.com/KhantKyawLin/VitalCare_Pharmacy_Backend.git
cd VitalCare_Pharmacy_Backend
```

### 2. Install PHP dependencies
```bash
composer install
```

### 3. Configure Environment
```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edit your `.env` configuration for database and websocket settings:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vitalcare_db
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=vitalcare_app
REVERB_APP_KEY=vitalcare_key
REVERB_APP_SECRET=vitalcare_secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 4. Run Migrations & Storage Link
```bash
php artisan migrate
php artisan storage:link
```

### 5. Start the Development Servers

In terminal 1 (API Server):
```bash
php artisan serve
```

In terminal 2 (WebSocket Server):
```bash
php artisan reverb:start
```

In terminal 3 (Optional: Refill Reminder Cron Simulator):
```bash
php artisan pharmacy:send-refill-reminders
```

---

## 🔒 Security & Performance Features

* **Rate Limiting:** Granular throttling on sensitive endpoints (Checkout: 3/min, Login/Registration: 5/min, Contact forms: 10/min).
* **Sensitive Data Redaction:** Passwords, tokens, and payment secrets are automatically redacted from activity logs.
* **MIME Validation:** Multi-step MIME type and magic-byte inspection on prescription and payment proof image uploads.

---

## 📄 License
This project is open-source under the [MIT License](LICENSE).
