<?php

function uploadProductImage($file) {
    return handleFileUpload($file, PRODUCT_IMAGE_UPLOAD_DIR);
}

function handleFileUpload($file, $uploadDir) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $maxFileSize = 2097152; // 2MB in bytes

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo "Failed to create upload directory: " . $uploadDir;
        return false;
    }

    // Check for file upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        // Optionally log error code: $file['error']
        return false;
    }

    // Check file size
    if ($file['size'] > $maxFileSize) {
        return false;
    }

    // Check file extension
    $fileInfo = pathinfo($file['name']);
    $extension = isset($fileInfo['extension']) ? strtolower($fileInfo['extension']) : '';

    if (!in_array($extension, $allowedExtensions)) {
        return false;
    }

    // Generate a unique filename
    $filename = uniqid('img_', true) . '.' . $extension; // More unique prefix
    $targetPath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Optionally log error here
        echo "Failed to move uploaded file to: " . $targetPath;
        return false;
    }

    return $filename;
}

function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded.";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk.";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload.";
        default:
            return "Unknown upload error.";
    }
} 