<?php
// Include configuration file
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';

// Check if user is logged in as market
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'market') {
    // Redirect to login
    header('Location: ../login.php');
    exit;
}

// Set page title
$pageTitle = 'My Products';

// Initialize variables
$userId = $_SESSION['user_id'];
$profileId = $_SESSION['profile_id'];
$products = [];
$error = '';
$success = '';

// Connect to database
$conn = getConnection();

// Handle product deletion if requested
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    $csrfToken = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Check if product belongs to this market and get its image path
        $stmt = $conn->prepare("SELECT id, image_path FROM products WHERE id = ? AND market_id = ?");
        $stmt->execute([$productId, $profileId]);
        $productToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($productToDelete) {
            $imageToDelete = $productToDelete['image_path'];
            
            // Delete product record
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            if ($stmt->execute([$productId])) {
                $success = 'Product deleted successfully.';
                
                // Delete the associated image file
                if (!empty($imageToDelete)) {
                    $imagePathToDelete = PRODUCT_IMAGE_UPLOAD_DIR . $imageToDelete;
                    if (file_exists($imagePathToDelete)) {
                        if (!unlink($imagePathToDelete)) {
                            // Log error if deletion fails
                             error_log("Failed to delete product image file: " . $imagePathToDelete);
                             $error .= ' Failed to delete product image file.'; // Append to user message
                        }
                    }
                }
            } else {
                 $error = 'Failed to delete product record.';
            }
        } else {
            $error = 'Product not found or you do not have permission to delete it.';
        }
    }
}

// Get all products for this market
$stmt = $conn->prepare("
    SELECT * FROM products
    WHERE market_id = ?
    ORDER BY expiration_date ASC
");
$stmt->execute([$profileId]);
$products = $stmt->fetchAll();

// Generate CSRF token for delete operations
$csrfToken = generateCSRFToken();

// Include header
include '../templates/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center my-4">
        <h1>My Products</h1>
        <a href="add_product.php" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>Add New Product
        </a>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (empty($products)): ?>
        <div class="alert alert-info">You haven't added any products yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-success">
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Stock</th>
                        <th>Normal Price</th>
                        <th>Discounted Price</th>
                        <th>Expiration Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php 
                            $isExpired = strtotime($product['expiration_date']) < strtotime(date('Y-m-d'));
                            $statusClass = $isExpired ? 'text-danger' : 'text-success';
                            $statusText = $isExpired ? 'Expired' : 'Active';
                        ?>
                        <tr class="<?php echo $isExpired ? 'table-danger' : ''; ?>">
                            <td>
                                <?php if (!empty($product['image_path'])): ?>
                                    <img src="<?php echo SITE_URL . '/assets/images/products/' . htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" width="50" height="50" class="img-thumbnail">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center img-thumbnail" style="width: 50px; height: 50px;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['title']); ?></td>
                            <td><?php echo $product['stock']; ?></td>
                            <td><?php echo number_format($product['normal_price'], 2); ?> TL</td>
                            <td><?php echo number_format($product['discounted_price'], 2); ?> TL</td>
                            <td><?php echo date('d M Y', strtotime($product['expiration_date'])); ?></td>
                            <td><span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                            <td>
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary me-1">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="products.php?action=delete&id=<?php echo $product['id']; ?>&csrf_token=<?php echo $csrfToken; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this product?');">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include '../templates/footer.php';
?> 