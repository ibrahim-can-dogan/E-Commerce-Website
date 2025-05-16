<?php
// Include configuration file
require_once 'config/config.php';
require_once 'includes/database.php';

// Set page title
$pageTitle = 'Home';

// Connect to database
$conn = getConnection();

// Fetch products for display
$sql = "SELECT p.*, m.market_name, m.city, m.district 
        FROM products p
        JOIN market_profiles m ON p.market_id = m.id
        WHERE p.expiration_date >= CURDATE()
        ORDER BY p.expiration_date ASC
        LIMIT 8";

$result = $conn->query($sql);
$products = [];

if ($result && $result->rowCount() > 0) {
    while ($row = $result->fetch()) {
        $products[] = $row;
    }
}


// Include header
include 'templates/header.php';
?>

<!-- Hero Section -->
<div class="hero">
    <div class="container text-center">
        <h1>Reduce Waste, Save Money</h1>
        <p class="lead mb-4">Find discounted products nearing their expiration date from local markets.</p>
        <?php if (!$isLoggedIn): ?>
            <div>
                <a href="register.php" class="btn btn-success btn-lg me-2">Register Now</a>
                <a href="login.php" class="btn btn-outline-light btn-lg">Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Features Section -->
<div class="container mb-5">
    <div class="row g-4 py-4">
        <div class="col-md-4">
            <div class="card text-center h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-leaf text-success fa-3x"></i>
                    </div>
                    <h3 class="card-title">Sustainable Shopping</h3>
                    <p class="card-text">Help reduce food waste by purchasing products before they expire.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-tags text-success fa-3x"></i>
                    </div>
                    <h3 class="card-title">Great Discounts</h3>
                    <p class="card-text">Enjoy significant savings on products nearing their expiration date.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-store text-success fa-3x"></i>
                    </div>
                    <h3 class="card-title">Support Local Markets</h3>
                    <p class="card-text">Help local businesses reduce inventory loss due to expired products.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Products Section -->
<div class="container mb-5">
    <h2 class="mb-4">Featured Products</h2>
    
    <?php if (empty($products)): ?>
        <div class="alert alert-info">No products available at the moment. Please check back later.</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php foreach ($products as $product): ?>
                <div class="col">
                    <div class="card product-card h-100">
                        <?php if (!empty($product['image_path'])): ?>
                            <img src="<?php echo SITE_URL . '/assets/images/products/' . htmlspecialchars($product['image_path']); ?>" class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['title']); ?>">
                        <?php else: ?>
                            <div class="card-img-top product-image-placeholder d-flex align-items-center justify-content-center bg-light">
                                <i class="fas fa-image fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['title']); ?></h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-store me-1"></i> <?php echo htmlspecialchars($product['market_name']); ?>
                                </small><br>
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($product['city'] . ', ' . $product['district']); ?>
                                </small>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="product-price"><?php echo number_format($product['discounted_price'], 2); ?> TL</span><br>
                                    <small class="original-price"><?php echo number_format($product['normal_price'], 2); ?> TL</small>
                                </div>
                                <div class="text-end">
                                    <span class="expiry-date">
                                        Expires: <?php echo date('d M Y', strtotime($product['expiration_date'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if ($isLoggedIn && $userType == 'consumer'): ?>
                        <div class="card-footer bg-transparent border-top-0 d-grid">
                            <a href="cart.php?action=add&product_id=<?php echo $product['id']; ?>&csrf_token=<?php echo $csrfToken; ?>" class="btn btn-outline-success">
                                <i class="fas fa-cart-plus me-2"></i>Add to Cart
                            </a>
                        </div>
                        <?php elseif (!$isLoggedIn): ?>
                        <div class="card-footer bg-transparent border-top-0 d-grid">
                            <a href="login.php" class="btn btn-outline-success">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Purchase
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <?php if ($isLoggedIn && $userType == 'consumer'): ?>
                <a href="search.php" class="btn btn-success">View More Products</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-success">Register to View More</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- How It Works Section -->
<div class="container mb-5">
    <h2 class="text-center mb-4">How It Works</h2>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="text-center">
                <div class="mb-3">
                    <i class="fas fa-search fa-3x text-success"></i>
                </div>
                <h4>Find Products</h4>
                <p>Browse and search for discounted products from markets in your area.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center">
                <div class="mb-3">
                    <i class="fas fa-cart-shopping fa-3x text-success"></i>
                </div>
                <h4>Add to Cart</h4>
                <p>Add items to your cart and proceed to checkout when you're ready.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center">
                <div class="mb-3">
                    <i class="fas fa-hand-holding-dollar fa-3x text-success"></i>
                </div>
                <h4>Save Money</h4>
                <p>Enjoy significant discounts while helping reduce product waste.</p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'templates/footer.php';
?> 