# Stock Management System Documentation

## Database Table Structure

### products
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INT | Primary Key |
| name | VARCHAR | Product name |
| price | DECIMAL | Unit price |

### inventory
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INT | Primary Key |
| product_id | INT | Foreign Key to products |
| quantity | INT | Current stock level |

### orders
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INT | Primary Key |
| user_id | INT | Foreign Key to users |
| order_status | ENUM | Pending, Confirmed, Processing, Completed, Cancelled |

### order_items
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INT | Primary Key |
| order_id | INT | Foreign Key to orders |
| product_id | INT | Foreign Key to products |
| quantity | INT | Units purchased |

## SQL Logic

### Stock Deduction (Checkout)
```sql
START TRANSACTION;
-- 1. Check & Lock for Update
SELECT quantity FROM inventory WHERE product_id = ? FOR UPDATE;
-- 2. Deduct Quantity
UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?;
-- 3. Log Movement
INSERT INTO stock_movements (product_id, quantity_change, movement_type, reference_id) 
VALUES (?, ?, 'sale', ?);
COMMIT;
```

### Stock Restoration (Cancellation)
```sql
START TRANSACTION;
-- 1. Verify Status
SELECT order_status FROM orders WHERE id = ? FOR UPDATE;
-- 2. Update Status
UPDATE orders SET order_status = 'Cancelled' WHERE id = ?;
-- 3. Restore Stock
UPDATE inventory i 
JOIN order_items oi ON i.product_id = oi.product_id
SET i.quantity = i.quantity + oi.quantity
WHERE oi.order_id = ?;
COMMIT;
```

## Backend Pseudocode (PHP/PDO)

### Checkout
```php
try {
    $db->beginTransaction();
    foreach ($cart_items as $item) {
        $stock = $db->query("SELECT quantity FROM inventory WHERE product_id = ? FOR UPDATE", [$item['id']]);
        if ($stock < $item['qty']) throw new Exception("Out of Stock");
        $db->execute("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?", [$item['qty'], $item['id']]);
    }
    $db->execute("INSERT INTO orders (...) VALUES (...)");
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    return "Error: " . $e->getMessage();
}
```

## Best Practices
1. **Atomic Transactions**: Always wrap stock updates and order creation in a single transaction.
2. **Pessimistic Locking**: Use `FOR UPDATE` to prevent race conditions (two users buying the last item at the same time).
3. **Audit Logging**: Use `stock_movements` to track every change for troubleshooting.
4. **Soft Statuses**: Never delete orders. Use statuses like `Cancelled` to preserve history while freeing up stock.
