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
$pageTitle = 'Search Products';

// Initialize variables
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;
$products = [];
$totalProducts = 0;
$totalPages = 0;

// Get consumer location
$userId = $_SESSION['user_id'];
$consumerCity = '';
$consumerDistrict = '';

// Connect to database
$conn = getConnection();

// Get consumer location
$stmt = $conn->prepare("SELECT city, district FROM consumer_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$consumer = $stmt->fetch();

if ($consumer) {
    $consumerCity = $consumer['city'];
    $consumerDistrict = $consumer['district'];
    
    // If keyword is provided, search for products
    if (!empty($keyword)) {
        // Prepare the search keyword with wildcards
        $searchKeyword = '%' . $keyword . '%';
        
        // Count total products matching the search in the same city (for pagination)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM products p
            JOIN market_profiles m ON p.market_id = m.id
            WHERE p.title LIKE ? 
            AND m.city = ?
            AND p.expiration_date >= CURDATE()
        ");
        $stmt->execute([$searchKeyword, $consumerCity]);
        $row = $stmt->fetch();
        $totalProducts = $row['total'];
        $totalPages = ceil($totalProducts / ITEMS_PER_PAGE);
        
        // Adjust page number if out of range
        if ($page < 1) $page = 1;
        if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        
        // Make sure offset and limit are integers
        $intOffset = (int)$offset;
        $intLimit = (int)ITEMS_PER_PAGE;
        
        // Create the query with hardcoded LIMIT values
        $sql = "
            (SELECT p.*, m.market_name, m.city, m.district, 1 as priority
            FROM products p
            JOIN market_profiles m ON p.market_id = m.id
            WHERE p.title LIKE ? 
            AND m.city = ? 
            AND m.district = ?
            AND p.expiration_date >= CURDATE())
            
            UNION
            
            (SELECT p.*, m.market_name, m.city, m.district, 2 as priority
            FROM products p
            JOIN market_profiles m ON p.market_id = m.id
            WHERE p.title LIKE ? 
            AND m.city = ? 
            AND m.district != ?
            AND p.expiration_date >= CURDATE())
            
            ORDER BY priority, expiration_date ASC
            LIMIT $intOffset, $intLimit
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $searchKeyword, $consumerCity, $consumerDistrict, 
            $searchKeyword, $consumerCity, $consumerDistrict
        ]);
        
        $products = $stmt->fetchAll();
    }
}

// Generate CSRF token for add to cart
$csrfToken = generateCSRFToken();

// Include header
include 'templates/header.php';
?>

<div class="container">
    <h1 class="my-4">Search Products</h1>
    
    <!-- Search Form -->
    <div class="row mb-4">
        <div class="col-md-8 mx-auto">
            <form action="search.php" method="GET" class="d-flex">
                <input type="text" name="keyword" class="form-control me-2" placeholder="Search products..." value="<?php echo htmlspecialchars($keyword); ?>" required>
                <button type="submit" class="btn btn-success">Search</button>
            </form>
        </div>
    </div>
    
    <?php if (!empty($keyword)): ?>
        <!-- Search Results -->
        <h2 class="mb-4">Search Results for "<?php echo htmlspecialchars($keyword); ?>"</h2>
        
        <?php if (empty($products)): ?>
            <div class="alert alert-info">No products found matching your search criteria.</div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-4">
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
                            <div class="card-footer bg-transparent border-top-0 d-grid">
                                <a href="cart.php?action=add&product_id=<?php echo $product['id']; ?>&csrf_token=<?php echo $csrfToken; ?>" class="btn btn-outline-success">
                                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?keyword=<?php echo urlencode($keyword); ?>&page=<?php echo $page-1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?keyword=<?php echo urlencode($keyword); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?keyword=<?php echo urlencode($keyword); ?>&page=<?php echo $page+1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">Enter a keyword to search for products.</div>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'templates/footer.php';
?> 