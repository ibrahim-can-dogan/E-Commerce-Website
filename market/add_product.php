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
$pageTitle = 'Add Product';

// Initialize variables
$userId = $_SESSION['user_id'];
$profileId = $_SESSION['profile_id'];
$title = '';
$stock = '';
$normalPrice = '';
$discountedPrice = '';
$expirationDate = '';
$error = '';
$success = '';

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
    elseif (strtotime($expirationDate) <= strtotime(date('Y-m-d'))) {
        $error = 'Expiration date must be in the future.';
    }
    elseif (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please upload a product image.';
    }
    else {
        // Upload product image
        $imageName = uploadProductImage($_FILES['image']);
        
        if (!$imageName) {
            $error = 'Failed to upload image. Please try again.';
        } else {
            // Connect to database
            $conn = getConnection();
            
            // Insert product
            $stmt = $conn->prepare("
                INSERT INTO products (market_id, title, stock, normal_price, discounted_price, expiration_date, image_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$profileId, $title, $stock, $normalPrice, $discountedPrice, $expirationDate, $imageName])) {
                $success = 'Product added successfully.';
                
                // Clear form data on success
                $title = '';
                $stock = '';
                $normalPrice = '';
                $discountedPrice = '';
                $expirationDate = '';
            } else {
                $error = 'Failed to add product. Please try again.';
            }
        }
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include header
include '../templates/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="form-container my-5">
                <h2 class="text-center mb-4">Add New Product</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form action="add_product.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
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
                                   value="<?php echo htmlspecialchars($expirationDate); ?>" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            <div class="invalid-feedback">Please select a future date.</div>
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
                        <label for="image" class="form-label">Product Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                        <div class="form-text">Upload an image of the product (max 2MB, formats: JPG, PNG, GIF).</div>
                        <div class="invalid-feedback">Please upload a product image.</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">Add Product</button>
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