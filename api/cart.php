<?php
/**
 * Cart API Endpoint
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn() || !hasRole('customer')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'unauthorized' => true]);
    exit;
}

$userId = getCurrentUserId();
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

switch ($action) {
    case 'add':
        $productId = (int)($data['product_id'] ?? 0);
        $quantity = max(1, (int)($data['quantity'] ?? 1));
        
        // Check product exists and has stock
        $product = db()->fetch("
            SELECT p.*, i.quantity as stock 
            FROM products p 
            LEFT JOIN inventory i ON p.id = i.product_id 
            WHERE p.id = ? AND p.is_active = 1
        ", [$productId]);
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        
        if ($product['stock'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock']);
            exit;
        }
        
        // Check existing cart item
        $existing = db()->fetch("SELECT * FROM cart WHERE user_id = ? AND product_id = ?", [$userId, $productId]);
        
        if ($existing) {
            // Always move to active cart if it was saved for later
            $newQty = min($existing['quantity'] + $quantity, $product['stock']);
            db()->execute("UPDATE cart SET quantity = ?, is_saved_for_later = 0 WHERE id = ?", [$newQty, $existing['id']]);
        } else {
            db()->insert("INSERT INTO cart (user_id, product_id, quantity, is_saved_for_later) VALUES (?, ?, ?, 0)", [$userId, $productId, $quantity]);
        }
        
        $cartCount = db()->count("SELECT SUM(quantity) FROM cart WHERE user_id = ? AND is_saved_for_later = 0", [$userId]) ?? 0;
        
        echo json_encode(['success' => true, 'cart_count' => (int)$cartCount]);
        break;
        
    case 'update':
        $productId = (int)($data['product_id'] ?? 0);
        $quantity = max(1, (int)($data['quantity'] ?? 1));
        
        // Get max stock
        $product = db()->fetch("SELECT i.quantity as stock FROM inventory i WHERE i.product_id = ?", [$productId]);
        $maxQty = $product ? $product['stock'] : 1;
        $quantity = min($quantity, $maxQty);
        
        db()->execute("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?", [$quantity, $userId, $productId]);
        
        echo json_encode(['success' => true]);
        break;
        
    case 'remove':
        $productId = (int)($data['product_id'] ?? 0);
        db()->execute("DELETE FROM cart WHERE user_id = ? AND product_id = ?", [$userId, $productId]);
        echo json_encode(['success' => true]);
        break;
        
    case 'save_for_later':
        $productId = (int)($data['product_id'] ?? 0);
        db()->execute("UPDATE cart SET is_saved_for_later = 1 WHERE user_id = ? AND product_id = ?", [$userId, $productId]);
        echo json_encode(['success' => true]);
        break;
        
    case 'move_to_cart':
        $productId = (int)($data['product_id'] ?? 0);
        db()->execute("UPDATE cart SET is_saved_for_later = 0 WHERE user_id = ? AND product_id = ?", [$userId, $productId]);
        echo json_encode(['success' => true]);
        break;
        
    case 'clear':
        db()->execute("DELETE FROM cart WHERE user_id = ? AND is_saved_for_later = 0", [$userId]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
