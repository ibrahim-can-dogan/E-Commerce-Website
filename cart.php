<?php
// Include configuration file
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/security.php';

// Check if user is logged in as consumer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'consumer') {
    // Redirect to login
    header('Location: login.php');
    exit;
}

// Set page title
$pageTitle = 'Shopping Cart';

// Initialize variables
$userId = $_SESSION['user_id'];
$profileId = $_SESSION['profile_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$csrfTokenGet = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
$cartItems = [];
$subtotal = 0;
$error = '';
$success = '';

// Connect to database
$conn = getConnection();

// Process add to cart action
if ($action === 'add' && $productId > 0) {
    // Verify CSRF token for GET request
    if (!verifyCSRFToken($csrfTokenGet)) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Check if product exists and is not expired
        $stmt = $conn->prepare("
            SELECT p.*, m.city, m.district 
            FROM products p
            JOIN market_profiles m ON p.market_id = m.id
            WHERE p.id = ? AND p.expiration_date >= CURDATE()
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Get consumer location
            $stmt = $conn->prepare("SELECT city FROM consumer_profiles WHERE id = ?");
            $stmt->execute([$profileId]);
            $consumer = $stmt->fetch();
            
            // Check if product is in the same city as consumer
            if ($consumer['city'] === $product['city']) {
                // Check if product is already in cart - use product ID explicitly from products table
                $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE consumer_id = ? AND product_id = ?");
                $stmt->execute([$profileId, $product['id']]);
                $cartItem = $stmt->fetch();
                
                if ($cartItem) {
                    // Update quantity if already in cart
                    $newQuantity = $cartItem['quantity'] + 1;
                    
                    // Check if new quantity exceeds stock
                    if ($newQuantity <= $product['stock']) {
                        $stmt = $conn->prepare("UPDATE cart SET quantity = ?, added_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$newQuantity, $cartItem['id']]);
                        $success = 'Product quantity updated in your cart.';
                    } else {
                        $error = 'Cannot add more of this product. Stock limit reached.';
                    }
                } else {
                    // Add to cart if not already in cart - use product ID explicitly from products table
                    $stmt = $conn->prepare("INSERT INTO cart (consumer_id, product_id, quantity) VALUES (?, ?, 1)");
                    $stmt->execute([$profileId, $product['id']]);
                    $success = 'Product added to your cart.';
                }
            } else {
                $error = 'You can only purchase products from markets in your city.';
            }
        } else {
            $error = 'Product not found or has expired.';
        }
        
        // Redirect to cart page without the action parameters to prevent resubmission on refresh
        header("Location: cart.php" . (!empty($success) ? "?success=" . urlencode($success) : (!empty($error) ? "?error=" . urlencode($error) : ""))); 
        exit;
    }
}

// Get success and error messages from URL parameters (for PRG pattern)
if (empty($success) && isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (empty($error) && isset($_GET['error'])) {
    $error = $_GET['error'];
}

// IMPORTANT: Clear cart items array before fetching
$cartItems = array();
$productDetails = array();

// First, get all cart items for this user
$stmt = $conn->prepare("
    SELECT id as cart_id, product_id, quantity
    FROM cart 
    WHERE consumer_id = ?
    ORDER BY added_at DESC
");
$stmt->execute([$profileId]);
$cartRows = $stmt->fetchAll();

// Now load each product individually to ensure correct information
foreach ($cartRows as $cartRow) {
    $productId = $cartRow['product_id'];
    
    // Get complete product information
    $stmt = $conn->prepare("
        SELECT 
            p.id as product_id,
            p.title, 
            p.discounted_price, 
            p.normal_price, 
            p.image_path, 
            p.expiration_date, 
            p.stock,
            m.market_name
        FROM products p
        JOIN market_profiles m ON p.market_id = m.id
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        // Combine cart and product info
        $cartItem = array_merge($cartRow, $product);
        $cartItem['subtotal'] = $cartItem['quantity'] * $cartItem['discounted_price'];
        $subtotal += $cartItem['subtotal'];
        
        $cartItems[] = $cartItem;
    }
}

// Generate CSRF token for AJAX operations
$csrfToken = generateCSRFToken();

// Include header
include 'templates/header.php';
?>

<div class="container">
    <h1 class="my-4">Shopping Cart</h1>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <!-- Alert container for AJAX messages -->
    <div id="alert-container"></div>
    
    <!-- Hidden CSRF token input for AJAX operations -->
    <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo $csrfToken; ?>">
    
    <?php if (empty($cartItems)): ?>
        <div class="alert alert-info">Your shopping cart is empty.</div>
        <p><a href="search.php" class="btn btn-success">Browse Products</a></p>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <div id="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item mb-3" id="cart-item-<?php echo $item['product_id']; ?>" data-cart-id="<?php echo $item['cart_id']; ?>">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <?php if (!empty($item['image_path'])): ?>
                                        <img src="<?php echo SITE_URL . '/assets/images/products/' . htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="img-fluid rounded">
                                    <?php else: ?>
                                         <div class="img-fluid rounded bg-light d-flex align-items-center justify-content-center" style="height: 80px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <h5><?php echo htmlspecialchars($item['title']); ?></h5>
                                    <p class="mb-0">
                                        <small class="text-muted">
                                            <i class="fas fa-store me-1"></i> <?php echo htmlspecialchars($item['market_name']); ?>
                                        </small>
                                    </p>
                                    <p class="mb-0">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-alt me-1"></i> Expires: <?php echo date('d M Y', strtotime($item['expiration_date'])); ?>
                                        </small>
                                    </p>

                                </div>
                                <div class="col-md-2">
                                    <span class="product-price"><?php echo number_format($item['discounted_price'], 2); ?> TL</span><br>
                                    <small class="original-price"><?php echo number_format($item['normal_price'], 2); ?> TL</small>
                                </div>
                                <div class="col-md-2 text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <button class="btn btn-sm btn-outline-secondary btn-quantity" data-action="decrease" data-product-id="<?php echo $item['product_id']; ?>">-</button>
                                        <span class="mx-2" id="quantity-<?php echo $item['product_id']; ?>"><?php echo $item['quantity']; ?></span>
                                        <button class="btn btn-sm btn-outline-secondary btn-quantity" data-action="increase" data-product-id="<?php echo $item['product_id']; ?>">+</button>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <div>
                                        <span id="subtotal-<?php echo $item['product_id']; ?>"><?php echo number_format($item['subtotal'], 2); ?> TL</span>
                                    </div>
                                    <button class="btn btn-sm btn-danger mt-2 btn-delete-cart-item" data-product-id="<?php echo $item['product_id']; ?>" data-cart-id="<?php echo $item['cart_id']; ?>">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="col-lg-4" id="cart-summary">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">Order Summary</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal:</span>
                            <span id="cart-total"><?php echo number_format($subtotal, 2); ?> TL</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Savings:</span>
                            <span id="cart-savings" class="text-success">
                                <?php 
                                    $savings = 0;
                                    foreach ($cartItems as $item) {
                                        $savings += ($item['normal_price'] - $item['discounted_price']) * $item['quantity'];
                                    }
                                    echo number_format($savings, 2);
                                ?> TL
                            </span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3 cart-total">
                            <span>Total:</span>
                            <span id="cart-total-final"><?php echo number_format($subtotal, 2); ?> TL</span>
                        </div>
                        <div class="d-grid">
                            <button id="btn-purchase" class="btn btn-success btn-lg">Complete Purchase</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Include cart JS -->
<script src="assets/js/cart.js"></script>

<?php
// Include footer
include 'templates/footer.php';
?> 