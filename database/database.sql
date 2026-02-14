-- MARMET Database Schema
-- Fashion & Lifestyle Retail Management System

CREATE DATABASE IF NOT EXISTS marmet;
USE marmet;

-- Disable foreign key checks for easier table dropping
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables in reverse order of dependencies
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS sales_history;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Users table (Customers, Staff, Administrators)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('customer', 'staff', 'admin') DEFAULT 'customer',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers table
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    supplier_id INT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2),
    sku VARCHAR(50) UNIQUE,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

-- Inventory table
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    last_restocked TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cod', 'gcash') NOT NULL,
    payment_status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    order_status ENUM('pending', 'verified', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    notes TEXT,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cod', 'gcash') NOT NULL,
    reference_number VARCHAR(100),
    status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    confirmed_by INT NULL,
    confirmed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Sales history for forecasting
CREATE TABLE sales_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    quantity_sold INT NOT NULL,
    revenue DECIMAL(10,2) NOT NULL,
    sale_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_sale_date (sale_date),
    INDEX idx_product_date (product_id, sale_date)
);

-- Cart table (for persistent carts)
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, product_id)
);

-- Stock movement log
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    quantity_change INT NOT NULL,
    movement_type ENUM('sale', 'restock', 'adjustment', 'return') NOT NULL,
    reference_id INT NULL,
    notes TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default admin user (password: admin123)
-- Hash generated using password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (email, password, first_name, last_name, role) VALUES
('admin@marmet.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Admin', 'admin');

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Women\'s Fashion', 'Trendy clothing and accessories for women'),
('Men\'s Fashion', 'Stylish apparel and accessories for men'),
('Footwear', 'Shoes, sandals, and boots for all occasions'),
('Accessories', 'Bags, jewelry, watches, and more'),
('Lifestyle', 'Home decor and lifestyle products');

-- Insert sample supplier
INSERT INTO suppliers (name, contact_person, email, phone, address) VALUES
('Fashion Trends Co.', 'Maria Santos', 'maria@fashiontrends.ph', '+63 912 345 6789', 'Manila, Philippines'),
('Style Hub Inc.', 'Juan Cruz', 'juan@stylehub.ph', '+63 917 123 4567', 'Cebu City, Philippines');

-- Insert sample products
INSERT INTO products (category_id, supplier_id, name, description, price, cost_price, sku, image_url) VALUES
(1, 1, 'Floral Summer Dress', 'Beautiful floral print dress perfect for summer outings', 1299.00, 650.00, 'WF-001', '/assets/images/products/dress1.jpg'),
(1, 1, 'Classic Denim Jacket', 'Timeless denim jacket with modern fit', 1899.00, 950.00, 'WF-002', '/assets/images/products/jacket1.jpg'),
(2, 2, 'Slim Fit Polo Shirt', 'Premium cotton polo shirt in various colors', 899.00, 450.00, 'MF-001', '/assets/images/products/polo1.jpg'),
(2, 2, 'Chino Pants', 'Comfortable chino pants for casual and semi-formal wear', 1499.00, 750.00, 'MF-002', '/assets/images/products/pants1.jpg'),
(3, 1, 'Canvas Sneakers', 'Versatile sneakers for everyday wear', 1599.00, 800.00, 'FW-001', '/assets/images/products/sneakers1.jpg'),
(4, 2, 'Leather Crossbody Bag', 'Elegant leather bag with adjustable strap', 2499.00, 1250.00, 'AC-001', '/assets/images/products/bag1.jpg'),
(5, 1, 'Scented Candle Set', 'Set of 3 premium scented candles', 799.00, 400.00, 'LS-001', '/assets/images/products/candle1.jpg');

-- Insert inventory for sample products
INSERT INTO inventory (product_id, quantity, low_stock_threshold) VALUES
(1, 50, 10),
(2, 35, 8),
(3, 100, 15),
(4, 45, 10),
(5, 60, 12),
(6, 25, 5),
(7, 40, 8);

-- Insert sample sales history for forecasting (last 30 days)
INSERT INTO sales_history (product_id, quantity_sold, revenue, sale_date) VALUES
(1, 5, 6495.00, DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
(1, 3, 3897.00, DATE_SUB(CURDATE(), INTERVAL 25 DAY)),
(1, 7, 9093.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
(1, 4, 5196.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY)),
(1, 6, 7794.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
(1, 8, 10392.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(2, 3, 5697.00, DATE_SUB(CURDATE(), INTERVAL 28 DAY)),
(2, 2, 3798.00, DATE_SUB(CURDATE(), INTERVAL 21 DAY)),
(2, 4, 7596.00, DATE_SUB(CURDATE(), INTERVAL 14 DAY)),
(2, 5, 9495.00, DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(3, 10, 8990.00, DATE_SUB(CURDATE(), INTERVAL 29 DAY)),
(3, 8, 7192.00, DATE_SUB(CURDATE(), INTERVAL 22 DAY)),
(3, 12, 10788.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY)),
(3, 15, 13485.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY)),
(3, 9, 8091.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY));
