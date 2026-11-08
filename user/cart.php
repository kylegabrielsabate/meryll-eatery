<?php
session_start();
include '../connect.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--brand-red:#8B0000;--brand-light:#fff0f0;--bg-light:#f8f9fa;--text-dark:#333;--text-muted:#6c757d;--border-color:#e9ecef}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg-light);color:var(--text-dark);min-height:100vh}
.container{max-width:1000px;margin:0 auto;padding:2rem}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem}
.header h1{font-size:1.8rem;color:var(--brand-red)}
.header a{color:var(--text-muted);text-decoration:none;font-weight:500}
.checkout-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:2rem}
@media(max-width:768px){.checkout-grid{grid-template-columns:1fr}}
.card{background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.05);border:1px solid var(--border-color)}
.card h2{font-size:1.2rem;margin-bottom:1.5rem;color:var(--text-dark);border-bottom:2px solid var(--brand-light);padding-bottom:0.5rem}
.form-group{margin-bottom:1.2rem}
.form-group label{display:block;font-weight:600;margin-bottom:0.5rem;font-size:0.9rem}
.form-control{width:100%;padding:10px;border:1px solid var(--border-color);border-radius:8px;font-family:inherit;font-size:1rem}
.form-control:focus{outline:none;border-color:var(--brand-red)}
textarea.form-control{resize:vertical;min-height:80px}

/* Order Type Cards */
.order-types{display:grid;grid-template-columns:repeat(3, 1fr);gap:10px}
.type-option{position:relative}
.type-option input{position:absolute;opacity:0}
.type-label{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:15px 10px;border:2px solid var(--border-color);border-radius:10px;cursor:pointer;transition:all 0.2s;text-align:center}
.type-label i{font-size:1.5rem;margin-bottom:5px;color:var(--text-muted)}
.type-label span{font-size:0.85rem;font-weight:600}
.type-option input:checked + .type-label{border-color:var(--brand-red);background:var(--brand-light);color:var(--brand-red)}
.type-option input:checked + .type-label i{color:var(--brand-red)}

/* Cart Summary Items */
.cart-item{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-color);font-size:0.95rem}
.cart-item:last-child{border-bottom:none}
.item-qty{color:var(--text-muted);font-size:0.85rem}
.total-row{display:flex;justify-content:space-between;font-size:1.3rem;font-weight:700;color:var(--brand-red);margin-top:1rem;padding-top:1rem;border-top:2px solid var(--border-color)}
.btn-checkout{width:100%;padding:14px;background:var(--brand-red);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:1.1rem;cursor:pointer;margin-top:1.5rem;transition:background 0.2s}
.btn-checkout:hover{background:#6d0000}
.btn-checkout:disabled{background:#ccc;cursor:not-allowed}
.empty-msg{text-align:center;padding:2rem;color:var(--text-muted)}
.toast{position:fixed;bottom:30px;right:30px;background:var(--brand-red);color:#fff;padding:14px 24px;border-radius:10px;font-weight:600;transform:translateY(100px);opacity:0;transition:all .3s;z-index:9999}
.toast.show{transform:translateY(0);opacity:1}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="bi bi-bag-check"></i> Checkout</h1>
        <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Menu</a>
    </div>

    <div class="checkout-grid">
        <!-- LEFT SIDE: FORM -->
        <div class="card">
            <h2>Order Details</h2>
            <form id="checkoutForm">
                
                <div class="form-group">
                    <label>Order Type</label>
                    <div class="order-types">
                        <div class="type-option">
                            <input type="radio" name="order_type" id="type_dinein" value="Dine-in">
                            <label for="type_dinein" class="type-label"><i class="bi bi-cup-hot"></i><span>Dine-in</span></label>
                        </div>
                        <div class="type-option">
                            <input type="radio" name="order_type" id="type_pickup" value="Pick-up" checked>
                            <label for="type_pickup" class="type-label"><i class="bi bi-bag"></i><span>Pick-up</span></label>
                        </div>
                        <div class="type-option">
                            <input type="radio" name="order_type" id="type_delivery" value="Delivery">
                            <label for="type_delivery" class="type-label"><i class="bi bi-bicycle"></i><span>Delivery</span></label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="pickup_time">Preferred Time</label>
                    <input type="time" id="pickup_time" name="pickup_time" class="form-control" required>
                    <small style="color:var(--text-muted);font-size:0.8rem">When should we have your order ready?</small>
                </div>

                <div class="form-group">
                    <label for="order_note">Order Notes (Optional)</label>
                    <textarea id="order_note" name="order_note" class="form-control" placeholder="e.g. No onions, extra spicy, gate code is 1234..."></textarea>
                </div>

            </form>
        </div>

        <!-- RIGHT SIDE: SUMMARY -->
        <div class="card">
            <h2>Your Cart</h2>
            <div id="cartItemsList">
                <p class="empty-msg">Loading cart...</p>
            </div>
            
            <div class="total-row">
                <span>Total</span>
                <span id="cartTotalDisplay">₱0.00</span>
            </div>

            <button type="button" class="btn-checkout" id="btnPlaceOrder" onclick="placeOrder()">
                Place Order
            </button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
// Load cart items on page load
document.addEventListener('DOMContentLoaded', loadCartSummary);

function loadCartSummary() {
    fetch('cart_actions.php?action=get')
    .then(r => r.json())
    .then(data => {
        const list = document.getElementById('cartItemsList');
        if (!data.success || data.items.length === 0) {
            list.innerHTML = '<p class="empty-msg">Your cart is empty. <br><a href="index.php" style="color:var(--brand-red)">Go to Menu</a></p>';
            document.getElementById('btnPlaceOrder').disabled = true;
            return;
        }
        
        let html = '';
        data.items.forEach(item => {
            html += `
            <div class="cart-item">
                <div>
                    <div style="font-weight:600">${item.name}</div>
                    <div class="item-qty">₱${item.price} x ${item.quantity}</div>
                </div>
                <div style="font-weight:700">₱${item.subtotal.toFixed(2)}</div>
            </div>`;
        });
        list.innerHTML = html;
        document.getElementById('cartTotalDisplay').textContent = '₱' + data.total_price.toFixed(2);
        
        // Set default time to 30 mins from now
        let now = new Date();
        now.setMinutes(now.getMinutes() + 30);
        let timeStr = now.toTimeString().substring(0,5);
        document.getElementById('pickup_time').value = timeStr;
    });
}

function placeOrder() {
    const time = document.getElementById('pickup_time').value;
    if (!time) { alert('Please select a pickup time!'); return; }

    const btn = document.getElementById('btnPlaceOrder');
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    btn.disabled = true;

    // Gather form data
    const orderType = document.querySelector('input[name="order_type"]:checked').value;
    const orderNote = document.getElementById('order_note').value;

    const formData = `action=checkout&order_type=${encodeURIComponent(orderType)}&pickup_time=${encodeURIComponent(time)}&order_note=${encodeURIComponent(orderNote)}`;

    fetch('cart_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('✅ ' + data.message);
            setTimeout(() => { window.location.href = 'my_orders.php'; }, 2000);
        } else {
            showToast('❌ ' + data.message);
            btn.innerHTML = 'Place Order';
            btn.disabled = false;
        }
    })
    .catch(err => {
        showToast('❌ Network error.');
        btn.innerHTML = 'Place Order';
        btn.disabled = false;
    });
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
</body>
</html>