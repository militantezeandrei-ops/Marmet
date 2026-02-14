<?php
/**
 * Sync Products from Uploaded Image Folders
 * Run once via browser: http://localhost/MarMet/sync_products.php
 * 
 * Scans assets/images/products/<Category>/<image> and creates
 * categories + products + inventory rows in the database.
 */
require_once __DIR__ . '/includes/db.php';

// Prevent accidental re-run in production
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family: monospace; padding: 2rem;">';
}

$productsDir = __DIR__ . '/assets/images/products';
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!is_dir($productsDir)) {
    echo "ERROR: Products directory not found: $productsDir\n";
    exit;
}

// Map of folder names to nicer display names
$categoryDisplayNames = [
    'Appliances'     => 'Appliances',
    'Bag'            => 'Bags',
    'Bottomwear'     => 'Bottomwear',
    'Footwear'       => 'Footwear',
    'Gadgets'        => 'Gadgets',
    'Skin'           => 'Skincare',
    'Topwear'        => 'Topwear',
    'Underwear'      => 'Underwear',
    'food and drink' => 'Food & Drink',
    'others'         => 'Others',
];

// Helper: Turn a filename into a readable product name
function humanizeName($filename, $categoryName) {
    // Remove extension
    $name = pathinfo($filename, PATHINFO_FILENAME);
    
    // If it's a long hash-like FB filename, generate a name from category
    if (strlen($name) > 30 && preg_match('/^\d+_\d+/', $name)) {
        static $counters = [];
        if (!isset($counters[$categoryName])) $counters[$categoryName] = 0;
        $counters[$categoryName]++;
        return $categoryName . ' Item ' . $counters[$categoryName];
    }
    
    // Split camelCase & numbers: "blender1" -> "Blender 1", "Rubbershoes10" -> "Rubber Shoes 10"
    $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);      // camelCase
    $name = preg_replace('/([a-zA-Z])(\d)/', '$1 $2', $name);       // letter→number
    $name = preg_replace('/(\d)([a-zA-Z])/', '$1 $2', $name);       // number→letter
    $name = str_replace(['_', '-'], ' ', $name);                      // underscores/dashes
    $name = ucwords(strtolower(trim($name)));
    
    return $name;
}

// Price ranges per category (min, max in PHP pesos)
$priceRanges = [
    'Appliances'     => [799, 3999],
    'Bags'           => [499, 2499],
    'Bottomwear'     => [399, 1899],
    'Footwear'       => [599, 2999],
    'Gadgets'        => [4999, 29999],
    'Skincare'       => [149, 999],
    'Topwear'        => [299, 1499],
    'Underwear'      => [149, 699],
    'Food & Drink'   => [99, 799],
    'Others'         => [199, 1999],
];

$created = 0;
$skipped = 0;

echo "=== MARMET Product Sync ===\n\n";

// First, deactivate old sample categories that don't match folders
$existingCats = db()->fetchAll("SELECT id, name FROM categories");
$existingCatNames = array_column($existingCats, 'name');

// Scan each folder
$folders = array_filter(glob($productsDir . '/*'), 'is_dir');

foreach ($folders as $folderPath) {
    $folderName = basename($folderPath);
    $displayName = $categoryDisplayNames[$folderName] ?? ucfirst($folderName);
    
    // Find or create category
    $cat = db()->fetch("SELECT id FROM categories WHERE name = ?", [$displayName]);
    
    if (!$cat) {
        $catId = db()->insert(
            "INSERT INTO categories (name, description, is_active) VALUES (?, ?, 1)",
            [$displayName, "Browse our $displayName collection"]
        );
        echo "[+] Created category: $displayName (ID: $catId)\n";
    } else {
        $catId = $cat['id'];
        echo "[=] Category exists: $displayName (ID: $catId)\n";
    }
    
    // Scan images in this folder
    $files = scandir($folderPath);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) continue;
        
        // Relative path for DB storage
        $imageUrl = 'assets/images/products/' . $folderName . '/' . $file;
        
        // Check if already exists 
        $existing = db()->fetch("SELECT id FROM products WHERE image_url = ?", [$imageUrl]);
        if ($existing) {
            $skipped++;
            continue;
        }
        
        $productName = humanizeName($file, $displayName);
        $priceRange = $priceRanges[$displayName] ?? [199, 1999];
        $price = rand($priceRange[0], $priceRange[1]);
        $costPrice = round($price * 0.5, 2); // 50% margin
        $sku = strtoupper(substr($displayName, 0, 3)) . '-' . substr(md5($file), 0, 5);
        
        // Insert product
        $productId = db()->insert(
            "INSERT INTO products (category_id, name, description, price, cost_price, sku, image_url, is_active) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $catId,
                $productName,
                "Quality $displayName product from Marmen Marketing.",
                $price,
                $costPrice,
                $sku,
                $imageUrl
            ]
        );
        
        // Insert inventory
        $stock = rand(15, 100);
        db()->insert(
            "INSERT INTO inventory (product_id, quantity, low_stock_threshold) VALUES (?, ?, 10)",
            [$productId, $stock]
        );
        
        echo "    [+] $productName (₱$price, stock: $stock) -> $imageUrl\n";
        $created++;
    }
    
    echo "\n";
}

echo "=== Done! ===\n";
echo "Created: $created products\n";
echo "Skipped: $skipped (already exist)\n";

if (php_sapi_name() !== 'cli') {
    echo "\n<a href='index.php'>← Go to Homepage</a> | <a href='catalog.php'>View Catalog →</a>";
    echo '</pre>';
}
