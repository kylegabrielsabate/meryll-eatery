<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$username = $_SESSION['username'];
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

$cart_count = 0;
foreach ($_SESSION['cart'] as $item) $cart_count += $item['quantity'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cart - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--brand-red:#8B0000;--brand-light:#fff0f0;--bg-light:#f8f9fa;--text-dark:#333;--text-muted:#6c757d;--sidebar-width:260px;--border-color:#e9ecef}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg-light);min-height:100vh}
.container{max-width:900px;margin:0 auto;padding:2rem}
.cart-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem}
.cart-header h1{font-size:1.8rem;font-weight:700;color:var(--text-dark)}
.cart-header a{color:var(--brand-red);text-decoration:none;font-weight:600}
.cart-item{background:#fff;border-radius:12px;padding:1.2rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 5px rgba(0,0,0,.05);border:1px solid var(--border-color)}
.cart-item-info{flex:1}
.cart-item-name{font-weight:600;font-size:1.05rem;color:var(--text-dark);margin-bottom:4px}
.cart-item-price{color:var(--brand-red);font-weight:700}
.cart-item-controls{display:flex;align-items:center;gap:12px}
.qty-btn{width:32px;height:32px;border:1px solid var(--border-color);background:#fff;border-radius:6px;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;color:var(--brand-red);font-weight:700}
.qty-btn:hover{background:var(--brand-light)}
.qty-display{font-weight:700;min-width:24px;text-align:center;font-size:1rem}
.remove-btn{background:none;border:none;color:#dc3545;cursor:pointer;font-size:1.2rem;margin-left:10px}
.cart-summary{background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 2px 5px rgba(0,0,0,.05);border:1px solid var(--border-color);margin-top:1.5rem}
.cart-total{display:flex;justify-content:space-between;font-size:1.3rem;font-weight:700;color:var(--brand-red);margin-bottom:1rem}
.checkout-btn{width:100%;padding:14px;background:var(--brand-red);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:1.1rem;cursor:pointer;transition:background .2s}
.checkout-btn:hover{background:#6d0000}
.checkout-btn:disabled{background:#ccc;cursor:not-allowed}
.empty-cart{text-align:center;padding:3rem;color:var(--text-muted)}
.empty-cart i{font-size:4rem;margin-bottom:1rem;color:var(--border-color)}

/* Modal Styles */
.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:1000;justify-content:center;align-items:center}
.modal-overlay.active{display:flex}
.modal-content{background:#fff;border-radius:16px;padding:2rem;max-width:500px;width:90%;max-height:90vh;overflow-y:auto}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:2px solid var(--border-color)}
.modal-header h2{font-size:1.3rem;color:var(--brand-red)}
.modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted)}
.modal-section{margin-bottom:1.5rem}
.modal-section h3{font-size:1rem;font-weight:600;color:var(--text-dark);margin-bottom:.8rem}
.modal-footer{display:flex;justify-content:flex-end;gap:10px;padding-top:1rem;border-top:2px solid var(--border-color)}
.btn-cancel{background:#6c757d;color:#fff;border:none;padding:12px 24px;border-radius:8px;font-weight:600;cursor:pointer}
.btn-place-order{background:var(--brand-red);color:#fff;border:none;padding:12px 24px;border-radius:8px;font-weight:600;cursor:pointer}
.btn-place-order:disabled{background:#ccc;cursor:not-allowed}
.toast{position:fixed;bottom:30px;right:30px;background:var(--brand-red);color:#fff;padding:14px 24px;border-radius:10px;font-weight:600;transform:translateY(100px);opacity:0;transition:all .3s;z-index:9999}
.toast.show{transform:translateY(0);opacity:1}
</style>
</head>
<body>

<div class="container">
    <div class="cart-header">
        <h1><i class="bi bi-basket3"></i> Your Cart</h1>
        <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Menu</a>
    </div>

    <div id="cartItems">
        <!-- Cart items will be loaded here -->
    </div>

    <div class="cart-summary" id="cartSummary" style="display:none">
        <div class="cart-total">
            <span>Total:</span>
            <span id="cartTotal">₱0.00</span>
        </div>
        <button class="checkout-btn" onclick="openCheckoutModal()">
            <i class="bi bi-bag-check"></i> Proceed to Checkout
        </button>
    </div>
</div>

<!-- ============ CHECKOUT MODAL ============ -->
<div class="modal-overlay" id="checkoutModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-bag-check"></i> Checkout</h2>
            <button class="modal-close" onclick="closeModal('checkoutModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        
        <div class="modal-section">
            <h3>Order Summary</h3>
            <div id="checkoutSummary" style="background: #f8f9fa; padding: 15px; border-radius: 10px; max-height: 200px; overflow-y: auto;">
                <p style="color: #6c757d; text-align: center;">Loading cart...</p>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 15px; font-weight: 700; font-size: 1.2rem; color: var(--brand-red);">
                <span>Total:</span>
                <span id="checkoutTotal">₱0.00</span>
            </div>
        </div>

        <div class="modal-section">
            <h3><i class="bi bi-clock"></i> Set Pickup Time</h3>
            <p style="font-size: 0.85rem; color: #6c757d; margin-bottom: 10px;">Choose when you'd like to pick up your order.</p>
            <input type="time" id="pickupTimeInput" style="width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 10px; font-size: 1.1rem; font-weight: 600; color: #333; cursor: pointer; outline: none; transition: border 0.2s;" onfocus="this.style.borderColor='var(--brand-red)'" onblur="this.style.borderColor='#e9ecef'">
        </div>

        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('checkoutModal')">Cancel</button>
            <button class="btn-place-order" id="btnPlaceOrder" onclick="placeOrder()">
                <i class="bi bi-check-circle"></i> Place Order
            </button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
// Load cart on page load
loadCart();

function loadCart() {
    fetch('cart_actions.php?action=get')
        .then(r => r.json())
        .then(data => {
            const cartItemsDiv = document.getElementById('cartItems');
            const cartSummary = document.getElementById('cartSummary');
            
            if (data.items.length === 0) {
                cartItemsDiv.innerHTML = `
                    <div class="empty-cart">
                        <i class="bi bi-basket3"></i>
                        <h2>Your cart is empty</h2>
                        <p>Add some delicious items from our menu!</p>
                        <a href="index.php" style="color:var(--brand-red);font-weight:600;margin-top:1rem;display:inline-block">Browse Menu</a>
                    </div>`;
                cartSummary.style.display = 'none';
                return;
            }

            let html = '';
            data.items.forEach(item => {
                html += `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">₱${item.price.toFixed(2)} x ${item.quantity} = ₱${item.subtotal.toFixed(2)}</div>
                    </div>
                    <div class="cart-item-controls">
                        <button class="qty-btn" onclick="updateQty('${item.cart_key}', -1)">−</button>
                        <span class="qty-display">${item.quantity}</span>
                        <button class="qty-btn" onclick="updateQty('${item.cart_key}', 1)">+</button>
                        <button class="remove-btn" onclick="removeItem('${item.cart_key}')"><i class="bi bi-trash"></i></button>
                    </div>
                </div>`;
            });
            cartItemsDiv.innerHTML = html;
            cartSummary.style.display = 'block';
            document.getElementById('cartTotal').textContent = '₱' + data.total_price.toFixed(2);
        });
}

function updateQty(cartKey, change) {
    fetch('cart_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update&cart_key=' + cartKey + '&change=' + change
    })
    .then(r => r.json())
    .then(() => loadCart());
}

function removeItem(cartKey) {
    fetch('cart_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=remove&cart_key=' + cartKey
    })
    .then(r => r.json())
    .then(() => loadCart());
}

function openCheckoutModal() {
    fetch('cart_actions.php?action=get')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let html = '';
                if (data.items.length === 0) {
                    html = '<p style="text-align:center; color:#6c757d;">Your cart is empty.</p>';
                    document.getElementById('btnPlaceOrder').disabled = true;
                } else {
                    data.items.forEach(item => {
                        html += `<div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid #e9ecef;">
                                    <span>${item.name} x${item.quantity}</span>
                                    <span style="font-weight:600;">₱${item.subtotal.toFixed(2)}</span>
                                 </div>`;
                    });
                    document.getElementById('btnPlaceOrder').disabled = false;
                }
                document.getElementById('checkoutSummary').innerHTML = html;
                document.getElementById('checkoutTotal').textContent = '₱' + data.total_price.toFixed(2);
                
                // Set default time to 30 mins from now
                let now = new Date();
                now.setMinutes(now.getMinutes() + 30);
                let hours = String(now.getHours()).padStart(2, '0');
                let minutes = String(now.getMinutes()).padStart(2, '0');
                document.getElementById('pickupTimeInput').value = `${hours}:${minutes}`;
                
                document.getElementById('checkoutModal').classList.add('active');
            }
        });
}

function placeOrder() {
    const time = document.getElementById('pickupTimeInput').value;
    if (!time) {
        showToast('❌ Please select a pickup time!');
        return;
    }

    const btn = document.getElementById('btnPlaceOrder');
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    btn.disabled = true;

    fetch('cart_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=checkout&pickup_time=' + encodeURIComponent(time)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('✅ ' + data.message);
            closeModal('checkoutModal');
            loadCart();
        } else {
            showToast('❌ ' + data.message);
        }
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Place Order';
        btn.disabled = false;
    })
    .catch(err => {
        showToast('❌ Network error. Please try again.');
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Place Order';
        btn.disabled = false;
    });
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function showToast(m) {
    const t = document.getElementById('toast');
    t.textContent = m;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
</body>
</html>