<?php
// Include necessary files
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';

header('Content-Type: application/json; charset=utf-8');

// Check if the user is logged in and is a consumer

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'consumer') {
    echo json_encode(['success' => false, 'error' => 'You must be logged in as a consumer.']);
    exit;
}

$userId = $_SESSION['user_id'];
$profileId = $_SESSION['profile_id'];
$csrfToken = $_POST['csrf_token'] ?? '';
$action = $_POST['action'] ?? '';

// Verify CSRF token
if (!verifyCSRFToken($csrfToken)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

// Connect to the database
$conn = getConnection();

switch ($action) {
    case 'update':
        $productId = (int)$_POST['product_id'];
        $cartId = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
        $quantity = (int)$_POST['quantity'];

        if ($quantity <= 0) {
            echo json_encode(['success' => false, 'error' => 'Quantity must be greater than 0.']);
            exit;
        }
        
        // Verify the cart item belongs to this user and exists
        $stmt = $conn->prepare("
            SELECT c.id as cart_id, c.product_id, p.stock, p.discounted_price, p.normal_price 
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.consumer_id = ? AND c.id = ?
        ");
        $stmt->execute([$profileId, $cartId]);
        $cartItem = $stmt->fetch();

        if (!$cartItem) {
            echo json_encode(['success' => false, 'error' => 'Cart item not found.']);
            exit;
        }

        // Double check the productId matches
        if ($cartItem['product_id'] != $productId) {
            echo json_encode(['success' => false, 'error' => 'Product ID mismatch.']);
            exit;
        }

        // Check if the quantity is available in stock
        if ($quantity > $cartItem['stock']) {
            echo json_encode(['success' => false, 'error' => 'Insufficient stock available.']);
            exit;
        }

        // Update the quantity in the cart
        $stmt = $conn->prepare("UPDATE cart SET quantity = ?, added_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$quantity, $cartId]);

        // Recalculate the cart total and savings
        $cartTotal = 0;
        $cartSavings = 0;
        $stmt = $conn->prepare("SELECT c.quantity, p.discounted_price, p.normal_price FROM cart c
            JOIN products p ON c.product_id = p.id WHERE c.consumer_id = ?");
        $stmt->execute([$profileId]);
        $cartItems = $stmt->fetchAll();

        foreach ($cartItems as $item) {
            $cartTotal += $item['quantity'] * $item['discounted_price'];
            $cartSavings += ($item['normal_price'] - $item['discounted_price']) * $item['quantity'];
        }

        $response = [
            'success' => true,
            'cart_total' => number_format($cartTotal, 2),
            'cart_savings' => number_format($cartSavings, 2),
            'subtotal' => number_format($quantity * $cartItem['discounted_price'], 2)
        ];

        echo json_encode($response);
        break;

    case 'delete':
        $productId = (int)$_POST['product_id'];
        $cartId = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;

        // Verify the cart item belongs to this user
        $stmt = $conn->prepare("SELECT id FROM cart WHERE consumer_id = ? AND id = ?");
        $stmt->execute([$profileId, $cartId]);
        $cartItem = $stmt->fetch();

        if (!$cartItem) {
            // Try with product ID as fallback
            $stmt = $conn->prepare("SELECT id FROM cart WHERE consumer_id = ? AND product_id = ?");
            $stmt->execute([$profileId, $productId]);
            $cartItem = $stmt->fetch();
            
            if (!$cartItem) {
                echo json_encode(['success' => false, 'error' => 'Cart item not found.']);
                exit;
            }
            
            $cartId = $cartItem['id'];
        }

        // Remove the item from the cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
        $stmt->execute([$cartId]);

        // Recalculate the cart total and savings
        $cartTotal = 0;
        $cartSavings = 0;
        $stmt = $conn->prepare("SELECT c.quantity, p.discounted_price, p.normal_price FROM cart c
            JOIN products p ON c.product_id = p.id WHERE c.consumer_id = ?");
        $stmt->execute([$profileId]);
        $cartItems = $stmt->fetchAll();

        foreach ($cartItems as $item) {
            $cartTotal += $item['quantity'] * $item['discounted_price'];
            $cartSavings += ($item['normal_price'] - $item['discounted_price']) * $item['quantity'];
        }

        $response = [
            'success' => true,
            'cart_total' => number_format($cartTotal, 2),
            'cart_savings' => number_format($cartSavings, 2)
        ];

        echo json_encode($response);
        break;

    case 'purchase':
        // Get all products in the cart
        $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.stock FROM cart c
            JOIN products p ON c.product_id = p.id WHERE c.consumer_id = ?");
        $stmt->execute([$profileId]);
        $cartItems = $stmt->fetchAll();

        // Process each item
        foreach ($cartItems as $item) {
            // Check stock before purchase
            if ($item['quantity'] > $item['stock']) {
                echo json_encode(['success' => false, 'error' => 'Insufficient stock for product ' . $item['product_id']]);
                exit;
            }

            // Reduce stock
            $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['product_id']]);

            $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch();

            if ($product['stock'] == 0) {
                $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$item['product_id']]);
            }

            // Remove the item from the cart
            $stmt = $conn->prepare("DELETE FROM cart WHERE consumer_id = ? AND product_id = ?");
            $stmt->execute([$profileId, $item['product_id']]);
        }

        // Return success
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
