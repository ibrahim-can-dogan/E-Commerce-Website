<?php
// Include configuration file
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/upload.php';

// Check if user is logged in as market
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'market') {
    // Redirect to login
    header('Location: ../login.php');
    exit;
}

// Set page title
$pageTitle = 'Edit Product';

// Initialize variables
$userId = $_SESSION['user_id'];
$profileId = $_SESSION['profile_id'];
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$title = '';
$stock = '';
$normalPrice = '';
$discountedPrice = '';
$expirationDate = '';
$imagePath = '';
$currentImagePath = '';
$error = '';
$success = '';

// Validate product ID
if ($productId <= 0) {
    // Redirect to products page if invalid ID
    header('Location: products.php');
    exit;
}

// Connect to database
$conn = getConnection();

// Get product information
$stmt = $conn->prepare("SELECT * FROM products WHERE id = :product_id AND market_id = :profile_id");
$stmt->bindParam(':product_id', $productId);
$stmt->bindParam(':profile_id', $profileId);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    // Product not found or does not belong to this market
    header('Location: products.php');
    exit;
}

$title = $product['title'];
$stock = $product['stock'];
$normalPrice = $product['normal_price'];
$discountedPrice = $product['discounted_price'];
$expirationDate = $product['expiration_date'];
$imagePath = $product['image_path'];
$currentImagePath = $imagePath; // Store current image path for potential deletion

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $normalPrice = isset($_POST['normal_price']) ? (float)$_POST['normal_price'] : 0;
    $discountedPrice = isset($_POST['discounted_price']) ? (float)$_POST['discounted_price'] : 0;
    $expirationDate = isset($_POST['expiration_date']) ? $_POST['expiration_date'] : '';
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    // Sanitize inputs
    $title = sanitizeInput($title);
    
    // Validate CSRF token
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    }
    // Validate required fields
    elseif (empty($title)) {
        $error = 'Please enter the product title.';
    }
    elseif ($stock <= 0) {
        $error = 'Please enter a valid stock amount (greater than 0).';
    }
    elseif ($normalPrice <= 0) {
        $error = 'Please enter a valid normal price (greater than 0).';
    }
    elseif ($discountedPrice <= 0) {
        $error = 'Please enter a valid discounted price (greater than 0).';
    }
    elseif ($discountedPrice > $normalPrice) {
        $error = 'Discounted price must be less than normal price.';
    }
    elseif (empty($expirationDate)) {
        $error = 'Please select an expiration date.';
    }
    else {
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Store the path of the image to potentially delete
            $oldImagePathToDelete = $currentImagePath;
            
            // Check if a new image was uploaded
            $newImageName = null;
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Upload new product image
                $newImageName = uploadProductImage($_FILES['image']);
                
                if (!$newImageName) {
                    throw new Exception(getUploadErrorMessage($_FILES['image']['error']));
                }
            }
            
            // Update product
            if ($newImageName !== null) {
                // Update with new image
                $stmt = $conn->prepare("
                    UPDATE products 
                    SET title = :title, stock = :stock, normal_price = :normal_price, 
                        discounted_price = :discounted_price, expiration_date = :expiration_date, 
                        image_path = :image_path
                    WHERE id = :product_id AND market_id = :profile_id
                ");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':stock', $stock);
                $stmt->bindParam(':normal_price', $normalPrice);
                $stmt->bindParam(':discounted_price', $discountedPrice);
                $stmt->bindParam(':expiration_date', $expirationDate);
                $stmt->bindParam(':image_path', $newImageName);
                $stmt->bindParam(':product_id', $productId);
                $stmt->bindParam(':profile_id', $profileId);
            } else {
                // Update without changing image
                $stmt = $conn->prepare("
                    UPDATE products 
                    SET title = :title, stock = :stock, normal_price = :normal_price, 
                        discounted_price = :discounted_price, expiration_date = :expiration_date
                    WHERE id = :product_id AND market_id = :profile_id
                ");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':stock', $stock);
                $stmt->bindParam(':normal_price', $normalPrice);
                $stmt->bindParam(':discounted_price', $discountedPrice);
                $stmt->bindParam(':expiration_date', $expirationDate);
                $stmt->bindParam(':product_id', $productId);
                $stmt->bindParam(':profile_id', $profileId);
            }
            
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $success = 'Product updated successfully.';


            
            // If a new image was uploaded, delete the old one
            if ($newImageName !== null && !empty($oldImagePathToDelete)) {
                $oldImageFullPath = PRODUCT_IMAGE_UPLOAD_DIR . $oldImagePathToDelete;
                if (file_exists($oldImageFullPath)) {
                    // Attempt to delete the old file
                    if (!unlink($oldImageFullPath)) {
                        // Log error if deletion fails, but don't stop the success message
                        error_log("Failed to delete old product image: " . $oldImageFullPath);
                    }
                }
                // Update the displayed image path
                $imagePath = $newImageName;
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Close database connection
$conn = null;

// Include header
include '../templates/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="form-container my-5">
                <h2 class="text-center mb-4">Edit Product</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'products.php';
                        }, 1000);
                    </script>
                <?php endif; ?>
                
                <div class="text-center mb-4">
                    <?php if (!empty($imagePath)): ?>
                        <img src="<?php echo SITE_URL . '/assets/images/products/' . htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($title); ?>" class="img-thumbnail" style="max-height: 200px;">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center img-thumbnail" style="max-height: 200px; height: 200px; width: auto;">
                             <span class="text-muted">No Image</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form action="edit_product.php?id=<?php echo $productId; ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Product Title</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                        <div class="invalid-feedback">Please enter the product title.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="stock" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="stock" name="stock" value="<?php echo htmlspecialchars($stock); ?>" min="1" required>
                            <div class="invalid-feedback">Please enter a valid stock amount.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="expiration_date" class="form-label">Expiration Date</label>
                            <input type="date" class="form-control" id="expiration_date" name="expiration_date" 
                                   value="<?php echo htmlspecialchars($expirationDate); ?>" required>
                            <div class="invalid-feedback">Please select an expiration date.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="normal_price" class="form-label">Normal Price (TL)</label>
                            <input type="number" class="form-control" id="normal_price" name="normal_price" 
                                   value="<?php echo htmlspecialchars($normalPrice); ?>" min="0.01" step="0.01" required>
                            <div class="invalid-feedback">Please enter a valid price.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="discounted_price" class="form-label">Discounted Price (TL)</label>
                            <input type="number" class="form-control" id="discounted_price" name="discounted_price" 
                                   value="<?php echo htmlspecialchars($discountedPrice); ?>" min="0.01" step="0.01" required>
                            <div class="invalid-feedback">Please enter a valid discounted price.</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="image" class="form-label">Product Image (Optional)</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <div class="form-text">Upload a new image only if you want to change the current one (max 2MB, formats: JPG, PNG, GIF).</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">Update Product</button>
                        <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../templates/footer.php';
?> 