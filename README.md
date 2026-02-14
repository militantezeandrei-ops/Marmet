# MARMET — E-Commerce Platform

A modern PHP-based e-commerce platform specializing in fashion and lifestyle products. Pushed from the **Marmen** local development environment.

## 🚀 Quick Start Guide

To get this project running locally on your machine, follow these steps:

### 1. Clone the Repository
```bash
git clone https://github.com/militantezeandrei-ops/Marmet.git
cd Marmet
```

### 2. Move to Web Root
Move the project folder into your web server's root directory (e.g., `C:\laragon\www\MarMet` or `/var/www/html/MarMet`).

### 3. Database Setup
1. Open your database management tool (like HeidiSQL or phpMyAdmin).
2. Create a new database named `marmet_db`.
3. Import the following SQL files from the `database/` directory in order:
   - `database.sql` (Schema and initial data)
   - `update_categories.sql` (Modern category assignments)

### 4. Configuration
Rename or edit `includes/config.php` to match your local database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'marmet_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Your database password
```
Update the `APP_URL` if your local environment uses a different port or folder:
```php
define('APP_URL', 'http://localhost/MarMet');
```

### 5. Synchronize Images
Run the synchronization script to ensure the database matches the actual folders in `assets/images/products/`:
1. Navagate to `http://localhost/MarMet/sync_products.php` in your browser.
2. Click the "Synchronize Database" button.

## ✨ Features
- **Modern UI**: Streamlined homepage and pill-shaped search bar.
- **Premium Interactions**: Global "pop" animations on all buttons.
- **Product Gallery**: Automated image synchronization for easy product management.
- **Smart Checkout**: Integrated "Buy Now" and "Add to Cart" flow with guest redirection.

## 🛠️ Tech Stack
- **Frontend**: Vanilla HTML/CSS, JavaScript, FontAwesome 6+, SweetAlert2.
- **Backend**: PHP 8 (PDO for database interactions).
- **Database**: MySQL.
- **Environment**: Optimized for Laragon on Windows.

---
*Developed by Antigravity AI for Militante Zeandrei.*
