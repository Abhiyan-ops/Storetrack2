# StoreTrack2 🏪

A full-stack store management system with separate admin and staff portals, built with PHP and MySQL.

## Live Demo
🌐 [storetrack.great-site.net](http://storetrack.great-site.net)

**Admin login:** use your registered email + password  
**Staff login:** /staff/login.php — name + 6-digit PIN

---

## What It Does

StoreTrack2 is a multi-user inventory and sales tracking system designed for small retail stores. The owner (admin) has full control, while staff members get a limited portal to record sales and view their own performance.

### Admin Can
- Manage inventory (add, edit, delete items with cost & selling prices)
- Record sales and view all transactions
- See full profit reports with charts (revenue, profit, top items)
- Add and manage employees (set PINs, activate/deactivate)
- Compare employee performance on a sales leaderboard

### Staff Can
- View inventory (selling price only — cost price hidden)
- Record sales (automatically tagged to their account)
- View only their own sales history
- See their personal performance stats

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2 |
| Database | MySQL |
| Frontend | HTML, CSS (Flexbox + Grid), Vanilla JavaScript |
| Charts | Chart.js |
| Auth | PHP Sessions + password_hash() |
| Hosting | InfinityFree |

---

## Features

- **Role-Based Access Control** — admin and staff see completely different data
- **Two login systems** — email/password for admin, name/PIN for staff
- **Employee sales tracking** — every sale tagged with employee_id
- **Profit analytics** — revenue, profit, and category breakdown charts
- **Staff leaderboard** — compare employee performance by period (7/30/90 days)
- **Low stock alerts** — automatic warnings when stock drops below threshold
- **Responsive design** — works on desktop and mobile

---

## Project Structure

```
StoreTrack2/
├── index.php              # Admin dashboard
├── login.php              # Admin login
├── signup.php             # Admin registration
├── logout.php
├── config.php             # Database config
├── includes/
│   ├── db.php             # Database connection
│   └── auth_check.php     # Admin session guard
├── assets/css/
│   └── dashboard.css      # Shared stylesheet
├── pages/                 # Admin pages
│   ├── inventory.php
│   ├── record_sales.php
│   ├── sales_history.php
│   ├── profit_report.php
│   ├── employees.php
│   └── employee_stats.php
└── staff/                 # Staff portal
    ├── login.php
    ├── staff_auth.php
    ├── dashboard.php
    ├── inventory.php
    ├── record_sale.php
    ├── my_sales.php
    └── logout.php
```

---

## Database Schema

```sql
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100),
    email      VARCHAR(150) UNIQUE,
    password   VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE employees (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    owner_id  INT,           -- links to users.id
    name      VARCHAR(100),
    pin       VARCHAR(10),
    is_active TINYINT DEFAULT 1
);

CREATE TABLE inventory (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT,
    name          VARCHAR(150),
    category      VARCHAR(100),
    cost_price    DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    quantity      INT
);

CREATE TABLE sales (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT,
    employee_id   INT DEFAULT NULL,  -- NULL = admin sale
    item_name     VARCHAR(150),
    quantity      INT,
    cost_price    DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    customer_name VARCHAR(100),
    sale_date     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Setup — Local (XAMPP)

1. Clone or download the project into `htdocs/StoreTrack2/`
2. Open phpMyAdmin and create a database called `storetrack2`
3. Run the SQL above to create the tables
4. Update `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'storetrack2');
```
5. Visit `localhost/StoreTrack2/signup.php` to create your admin account
6. Log in at `localhost/StoreTrack2/login.php`

---

## Setup — InfinityFree (Live Hosting)

1. Create a database in your InfinityFree control panel
2. Update `config.php` with your InfinityFree credentials:
```php
define('DB_HOST', 'sql202.infinityfree.com');
define('DB_USER', 'if0_xxxxxxx');
define('DB_PASS', 'yourpassword');
define('DB_NAME', 'if0_xxxxxxx_storetrack');
```
3. Import your table structure via phpMyAdmin
4. Upload all files via File Manager or FTP into `htdocs/`
5. Visit your domain to sign up and log in

---

## Security Notes

- Passwords hashed with `password_hash()` (bcrypt)
- All database queries use prepared statements (prevents SQL injection)
- Cost prices are never exposed to staff — not in HTML, not in JavaScript
- Session-based authentication with role checking on every protected page
- Input sanitized with `trim()`, `(int)`, `(float)`, and `htmlspecialchars()`

---

## Screenshots

| Admin Dashboard | Staff Portal | Employee Stats |
|:-:|:-:|:-:|
| Full sales overview with profit metrics | Limited view, no cost prices | Leaderboard with performance detail |

---

## Built By

**Abhiyan Limbu** — 2026
