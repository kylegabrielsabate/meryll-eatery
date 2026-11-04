<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - Meryll Eatery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --brand-red: #8B0000;
            --bg-light: #f8f9fa;
            --text-dark: #333;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); min-height: 100vh; padding: 2rem; }
        
        .cart-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .cart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); }
        .cart-header h1 { font-size: 1.8rem; color: var(--brand-red); }
        .back-btn { color: var(--brand-red); text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid var(--border-color); }
        .cart-item-info { flex: 1; }
        .cart-item-name { font-weight: 600; font-size: 1rem; color: var(--text-dark); margin-bottom: 0.3rem; }
        .cart-item-price { font-size: 0.9rem; color: var(--text-muted); }
        .cart-item-controls { display: flex; align-items: center; gap: 1rem; }
        .qty-btn { width: 32px; height: 32px; border: 1px solid var(--border-color); background: white; border-radius: 6px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; }
        .qty-btn:hover { background: var(--bg-light); }
        .qty-display { font-weight: 700; min-width: 30px; text-align: center; }
        .remove-btn { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.2rem; margin-left: 1rem; }
        
        .cart-summary { margin-top: 2rem; padding-top: 1rem; border-top: 2px solid var(--border-color); }
        .cart-total { display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 700; color: var(--brand-red); margin-bottom: 1rem; }
        .checkout-btn { width: 100%; padding: 14px; background: var(--brand-red); color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; }
        .checkout-btn:hover { background: #6d0000; }
        .checkout-btn:disabled { background: #ccc; cursor: not-allowed; }
        
        .empty-cart { text-align: center; padding: 3rem; color: var(--text-muted); }
        .empty-cart i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.3; }
        
        .toast { position: fixed; bottom: 30px; right: 30px; background: var(--brand-red); color: white; padding: 14px 24px; border-radius: 10px; font-weight: 600; transform: translateY(100px); opacity: 0; transition: all 0.3s; z-index: 9999; }
        .toast.show { transform: translateY(0); opacity: 1; }
    </style>
</head>
<body>
    <div class="cart-container">
        <div class="cart-header">
            <h1><i class="bi bi-basket3-fill"></i> My Cart</h1>
            <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back to Menu</a>
        </div>
        
        <div id="cartItems"></div>
        
        <div class="cart-summary" id="cartSummary" style="display: none;">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotal">₱0.00</span>
            </div>
            <button class="checkout-btn" onclick="checkout()">Proceed to Checkout</button>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2500);
        }

        function loadCart() {
            fetch('cart_actions.php?action=get')
                .then(res => res.json())
                .then(data => {
                    const cartItemsDiv = document.getElementById('cartItems');
                    const cartSummary = document.getElementById('cartSummary');
                    
                    if (data.items.length === 0) {
                        cartItemsDiv.innerHTML = `
                            <div class="empty-cart">
                                <i class="bi bi-basket3"></i>
                                <p>Your cart is empty</p>
                                <a href="index.php" style="color: var(--brand-red); text-decoration: none; font-weight: 600;">Browse Menu</a>
                            </div>
                        `;
                        cartSummary.style.display = 'none';
                        return;
                    }
                    
                    let html = '';
                    data.items.forEach(item => {
                        html += `
                            <div class="cart-item">
                                <div class="cart-item-info">
                                    <div class="cart-item-name">${item.name}</div>
                                    <div class="cart-item-price">${item.price.toFixed(2)} each</div>
                                </div>
                                <div class="cart-item-controls">
                                    <button class="qty-btn" onclick="updateQty('${item.cart_key}', -1)"><i class="bi bi-dash"></i></button>
                                    <span class="qty-display">${item.quantity}</span>
                                    <button class="qty-btn" onclick="updateQty('${item.cart_key}', 1)"><i class="bi bi-plus"></i></button>
                                    <button class="remove-btn" onclick="removeItem('${item.cart_key}')"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        `;
                    });
                    
                    cartItemsDiv.innerHTML = html;
                    document.getElementById('cartTotal').textContent = '₱' + data.total_price.toFixed(2);
                    cartSummary.style.display = 'block';
                });
        }

        function updateQty(cartKey, change) {
            fetch('cart_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=update&cart_key=' + cartKey + '&change=' + change
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadCart();
                    showToast('Cart updated!');
                }
            });
        }

        function removeItem(cartKey) {
            fetch('cart_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=remove&cart_key=' + cartKey
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadCart();
                    showToast('Item removed!');
                }
            });
        }

        function checkout() {
            showToast('Redirecting to checkout...');
            setTimeout(() => {
                window.location.href = 'checkout.php';
            }, 1000);
        }

        // Load cart on page load
        loadCart();
    </script>
</body>
</html>