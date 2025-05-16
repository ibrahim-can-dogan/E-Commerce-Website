document.addEventListener('DOMContentLoaded', function() {
    // CSRF Token
    const csrfToken = document.getElementById('csrf_token').value;

    // Update quantity in the cart
    document.querySelectorAll('.btn-quantity').forEach(button => {
        button.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const productId = this.getAttribute('data-product-id');
            const cartItem = document.getElementById('cart-item-' + productId);
            const cartId = cartItem ? cartItem.getAttribute('data-cart-id') : null;
            
            updateCartQuantity(action, productId, cartId);
        });
    });

    // Delete cart item
    document.querySelectorAll('.btn-delete-cart-item').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const cartId = this.getAttribute('data-cart-id');
            deleteCartItem(productId, cartId);
        });
    });

    // Complete purchase
    const purchaseButton = document.getElementById('btn-purchase');
    if (purchaseButton) {
        purchaseButton.addEventListener('click', function() {
            completePurchase();
        });
    }

    // Function to update cart quantity
    function updateCartQuantity(action, productId, cartId) {
        const quantityElement = document.getElementById('quantity-' + productId);
        let currentQuantity = parseInt(quantityElement.textContent);

        if (action === 'increase') {
            currentQuantity++;
        } else if (action === 'decrease' && currentQuantity > 1) {
            currentQuantity--;
        }

        const data = {
            action: 'update',
            product_id: productId,
            cart_id: cartId,
            quantity: currentQuantity,
            csrf_token: csrfToken
        };

        sendAjaxRequest(data, function(response) {
            if (response.success) {
                // Update quantity on the page
                quantityElement.textContent = currentQuantity;
                // Update subtotal
                document.getElementById('subtotal-' + productId).textContent = response.subtotal + ' TL';
                // Update cart total
                document.getElementById('cart-total').textContent = response.cart_total + ' TL';
                document.getElementById('cart-total-final').textContent = response.cart_total + ' TL';
                document.getElementById('cart-savings').textContent = response.cart_savings + ' TL';
            } else {
                showAlert('Error: ' + response.error, 'danger');
            }
        });
    }

    // Function to delete cart item
    function deleteCartItem(productId, cartId) {
        const data = {
            action: 'delete',
            product_id: productId,
            cart_id: cartId,
            csrf_token: csrfToken
        };

        sendAjaxRequest(data, function(response) {
            if (response.success) {
                // Remove item from the DOM
                const cartItem = document.getElementById('cart-item-' + productId);
                cartItem.remove();
                // Update cart total
                document.getElementById('cart-total').textContent = response.cart_total + ' TL';
                document.getElementById('cart-savings').textContent = response.cart_savings + ' TL';
                document.getElementById('cart-total-final').textContent = response.cart_total + ' TL';
                showAlert('Product removed from your cart.', 'success');
                
                // Check if cart is empty now
                const cartItems = document.querySelectorAll('.cart-item');
                if (cartItems.length === 0) {
                    // Refresh the page to show empty cart message
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                showAlert('Error: ' + response.error, 'danger');
            }
        });
    }

    // Function to complete the purchase
    function completePurchase() {
        const data = {
            action: 'purchase',
            csrf_token: csrfToken
        };

        sendAjaxRequest(data, function(response) {
            if (response.success) {
                // Empty the cart UI and display success message
                document.getElementById('cart-items').innerHTML = '';
                document.getElementById('cart-summary').innerHTML = '<div class="alert alert-success">Your purchase was successful!</div>';
                showAlert('Purchase completed successfully!', 'success');
                
                // Reload the page after 2 seconds
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                showAlert('Error: ' + response.error, 'danger');
            }
        });
    }

    // Function to send AJAX request
    function sendAjaxRequest(data, callback) {
        const xhr = new XMLHttpRequest();
        // Ensure we're using the correct path for ajax/cart.php
        const ajaxUrl = 'ajax/cart.php';
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        callback(response);
                    } catch (e) {
                        console.error('Invalid JSON response:', xhr.responseText, e);
                        showAlert('Error: Unable to process the request.', 'danger');
                    }
                } else {
                    console.error('AJAX request failed with status:', xhr.status);
                    showAlert('Error: Server communication failed.', 'danger');
                }
            }
        };

        // Format data to URL encoded
        const urlEncodedData = Object.keys(data).map(key => encodeURIComponent(key) + '=' + encodeURIComponent(data[key])).join('&');
        xhr.send(urlEncodedData);
    }

    // Function to show alert messages
    function showAlert(message, type) {
        const alertContainer = document.getElementById('alert-container');
        if (!alertContainer) return;
        
        const alert = document.createElement('div');
        alert.classList.add('alert', type === 'success' ? 'alert-success' : 'alert-danger');
        alert.textContent = message;
        alertContainer.innerHTML = ''; // Clear any previous alerts
        alertContainer.appendChild(alert);
        setTimeout(() => alert.remove(), 5000); // Remove alert after 5 seconds
    }
});
