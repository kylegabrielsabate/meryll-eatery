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
.header h1{font-size:1.8rem;color:var(--brand-red);display:flex;align-items:center;gap:10px}
.header a{color:var(--text-muted);text-decoration:none;font-weight:500;display:flex;align-items:center;gap:5px}
.header a:hover{color:var(--brand-red)}
.checkout-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:2rem}
@media(max-width:768px){.checkout-grid{grid-template-columns:1fr}}
.card{background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.05);border:1px solid var(--border-color)}
.card h2{font-size:1.2rem;margin-bottom:1.5rem;color:var(--text-dark);border-bottom:2px solid var(--brand-light);padding-bottom:0.5rem}
.form-group{margin-bottom:1.2rem}
.form-group label{display:block;font-weight:600;margin-bottom:0.5rem;font-size:0.9rem}
.form-control{width:100%;padding:10px;border:1px solid var(--border-color);border-radius:8px;font-family:inherit;font-size:1rem}
.form-control:focus{outline:none;border-color:var(--brand-red)}
textarea.form-control{resize:vertical;min-height:80px}
.order-types{display:grid;grid-template-columns:repeat(3, 1fr);gap:10px}
.type-option{position:relative}
.type-option input{position:absolute;opacity:0}
.type-label{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:15px 10px;border:2px solid var(--border-color);border-radius:10px;cursor:pointer;transition:all 0.2s;text-align:center}
.type-label i{font-size:1.5rem;margin-bottom:5px;color:var(--text-muted)}
.type-label .type-title{font-size:0.9rem;font-weight:700}
.type-option input:checked + .type-label{border-color:var(--brand-red);background:var(--brand-light);color:var(--brand-red)}
.type-option input:checked + .type-label i{color:var(--brand-red)}
.cart-item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border-color)}
.cart-item-info{flex:1}
.cart-item-name{font-weight:600;font-size:0.95rem}
.cart-item-price{color:var(--brand-red);font-weight:700;font-size:0.9rem}
.cart-item-controls{display:flex;align-items:center;gap:8px}
.qty-btn{width:28px;height:28px;border:1px solid var(--border-color);background:#fff;border-radius:6px;cursor:pointer;color:var(--brand-red);font-weight:700}
.remove-btn{background:none;border:none;color:#dc3545;cursor:pointer;font-size:1.1rem;margin-left:8px}
.total-row{display:flex;justify-content:space-between;font-size:1.3rem;font-weight:700;color:var(--brand-red);margin-top:1rem;padding-top:1rem;border-top:2px solid var(--border-color)}
.btn-checkout{width:100%;padding:14px;background:var(--brand-red);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:1.1rem;cursor:pointer;margin-top:1.5rem}
.btn-checkout:disabled{background:#ccc;cursor:not-allowed}

/* ✅ BEAUTIFUL NOTIFICATION MODAL */
.notif-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9998;justify-content:center;align-items:center;backdrop-filter:blur(4px)}
.notif-overlay.show{display:flex}
.notif-modal{background:#fff;border-radius:20px;padding:2.5rem;max-width:420px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:popIn 0.4s ease}
.notif-modal.success{border-top:5px solid #2ecc71}
.notif-modal.error{border-top:5px solid #e63946}
.notif-icon{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2.5rem}
.notif-modal.success .notif-icon{background:#d4edda;color:#2ecc71}
.notif-modal.error .notif-icon{background:#ffe6e6;color:#e63946}
.notif-title{font-size:1.4rem;font-weight:700;margin-bottom:0.5rem;color:var(--text-dark)}
.notif-msg{font-size:0.95rem;color:var(--text-muted);margin-bottom:1.5rem;line-height:1.5}
.notif-order-num{background:var(--brand-light);color:var(--brand-red);padding:10px 20px;border-radius:8px;font-weight:700;font-size:1.1rem;display:inline-block;margin-bottom:1.5rem}
.notif-btn{background:var(--brand-red);color:#fff;border:none;padding:12px 32px;border-radius:10px;cursor:pointer;font-weight:600;font-size:1rem;transition:background 0.2s}
.notif-btn:hover{background:#6d0000}
@keyframes popIn{from{opacity:0;transform:scale(0.8) translateY(20px)}to{opacity:1;transform:scale(1) translateY(0)}}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="bi bi-bag-check"></i> Checkout</h1>
        <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Menu</a>
    </div>
    <div class="checkout-grid">
        <div class="card">
            <h2>Order Details</h2>
            <div class="form-group">
                <label>Order Type</label>
                <div class="order-types">
                    <div class="type-option"><input type="radio" name="order_type" id="type_dinein" value="Dine-in" onchange="updateTimeLabel()"><label for="type_dinein" class="type-label"><i class="bi bi-cup-hot"></i><span class="type-title">Dine-in</span></label></div>
                    <div class="type-option"><input type="radio" name="order_type" id="type_pickup" value="Pick-up" checked onchange="updateTimeLabel()"><label for="type_pickup" class="type-label"><i class="bi bi-bag"></i><span class="type-title">Pick-up</span></label></div>
                    <div class="type-option"><input type="radio" name="order_type" id="type_delivery" value="Delivered" onchange="updateTimeLabel()"><label for="type_delivery" class="type-label"><i class="bi bi-bicycle"></i><span class="type-title">Delivery</span></label></div>
                </div>
            </div>
            <div class="form-group">
                <label for="pickup_time" id="timeLabel">What time will you pick it up?</label>
                <input type="time" id="pickup_time" name="pickup_time" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <div class="order-types" style="grid-template-columns:repeat(2, 1fr)">
                    <div class="type-option"><input type="radio" name="payment_method" id="pay_cash" value="Cash" checked><label for="pay_cash" class="type-label"><i class="bi bi-cash"></i><span class="type-title">Cash</span></label></div>
                    <div class="type-option"><input type="radio" name="payment_method" id="pay_cashless" value="Cashless"><label for="pay_cashless" class="type-label"><i class="bi bi-credit-card"></i><span class="type-title">Cashless (GCash)</span></label></div>
                </div>
            </div>
            <div class="form-group">
                <label for="order_note">Order Notes (Optional)</label>
                <textarea id="order_note" name="order_note" class="form-control" placeholder="e.g. No onions, extra spicy..."></textarea>
            </div>
        </div>

        <div class="card">
            <h2>Your Cart</h2>
            <div id="cartItemsList"><p style="text-align:center;color:var(--text-muted)">Loading cart...</p></div>
            <div class="total-row"><span>Total</span><span id="cartTotalDisplay">₱0.00</span></div>
            <button type="button" class="btn-checkout" id="btnPlaceOrder" onclick="placeOrder()">Place Order</button>
        </div>
    </div>
</div>

<!-- ✅ BEAUTIFUL NOTIFICATION MODAL -->
<div class="notif-overlay" id="notifOverlay">
    <div class="notif-modal" id="notifModal">
        <div class="notif-icon" id="notifIcon"><i class="bi bi-check-circle-fill"></i></div>
        <div class="notif-title" id="notifTitle">Order Placed!</div>
        <div class="notif-order-num" id="notifOrderNum" style="display:none"></div>
        <div class="notif-msg" id="notifMsg">Your order has been placed successfully.</div>
        <button class="notif-btn" id="notifBtn" onclick="handleNotifAction()">View Receipt</button>
    </div>
</div>

<script>
let redirectUrl = '';

document.addEventListener('DOMContentLoaded', loadCartSummary);

function updateTimeLabel() {
    const type = document.querySelector('input[name="order_type"]:checked').value;
    const label = document.getElementById('timeLabel');
    if (type === 'Dine-in') label.textContent = 'What time will you eat?';
    else if (type === 'Pick-up') label.textContent = 'What time will you pick it up?';
    else label.textContent = 'What time should we deliver?';
}

function loadCartSummary() {
    fetch('cart_actions.php?action=get').then(r => r.json()).then(data => {
        const list = document.getElementById('cartItemsList');
        if (!data.success || data.items.length === 0) {
            list.innerHTML = '<p style="text-align:center;color:var(--text-muted)">Your cart is empty.</p>';
            document.getElementById('btnPlaceOrder').disabled = true;
            return;
        }
        let html = '';
        data.items.forEach(item => {
            html += `<div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">₱${item.price} x ${item.quantity} = ₱${item.subtotal.toFixed(2)}</div>
                </div>
                <div class="cart-item-controls">
                    <button class="qty-btn" onclick="updateQty('${item.cart_key}', -1)">−</button>
                    <span>${item.quantity}</span>
                    <button class="qty-btn" onclick="updateQty('${item.cart_key}', 1)">+</button>
                    <button class="remove-btn" onclick="removeItem('${item.cart_key}')"><i class="bi bi-trash"></i></button>
                </div>
            </div>`;
        });
        list.innerHTML = html;
        document.getElementById('cartTotalDisplay').textContent = '₱' + data.total_price.toFixed(2);
        document.getElementById('btnPlaceOrder').disabled = false;
        
        let now = new Date();
        now.setMinutes(now.getMinutes() + 30);
        let hours = String(now.getHours()).padStart(2, '0');
        let minutes = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('pickup_time').value = `${hours}:${minutes}`;
        updateTimeLabel();
    });
}

function updateQty(key, change) { fetch('cart_actions.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=update&cart_key='+key+'&change='+change}).then(()=>loadCartSummary()); }
function removeItem(key) { fetch('cart_actions.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=remove&cart_key='+key}).then(()=>loadCartSummary()); }

// ✅ BEAUTIFUL NOTIFICATION FUNCTION
function showNotif(type, title, msg, orderNum, redirect) {
    const overlay = document.getElementById('notifOverlay');
    const modal = document.getElementById('notifModal');
    const icon = document.getElementById('notifIcon');
    const orderNumEl = document.getElementById('notifOrderNum');
    const btn = document.getElementById('notifBtn');
    
    modal.className = 'notif-modal ' + type;
    icon.innerHTML = type === 'success' ? '<i class="bi bi-check-circle-fill"></i>' : '<i class="bi bi-exclamation-circle-fill"></i>';
    document.getElementById('notifTitle').textContent = title;
    document.getElementById('notifMsg').textContent = msg;
    
    if (orderNum) {
        orderNumEl.style.display = 'inline-block';
        orderNumEl.textContent = 'Order #' + orderNum;
    } else {
        orderNumEl.style.display = 'none';
    }
    
    if (redirect) {
        redirectUrl = redirect;
        btn.textContent = 'View Receipt';
        btn.style.display = 'inline-block';
    } else {
        btn.textContent = 'OK';
        btn.style.display = 'inline-block';
        redirectUrl = '';
    }
    
    overlay.classList.add('show');
}

function handleNotifAction() {
    document.getElementById('notifOverlay').classList.remove('show');
    if (redirectUrl) {
        window.location.href = redirectUrl;
    }
}

function placeOrder() {
    const time = document.getElementById('pickup_time').value;
    if (!time) { showNotif('error', 'Missing Info', 'Please select a pickup time!'); return; }
    
    const btn = document.getElementById('btnPlaceOrder');
    btn.innerHTML = 'Processing...'; 
    btn.disabled = true;

    const orderType = document.querySelector('input[name="order_type"]:checked').value;
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const orderNote = document.getElementById('order_note').value;

    const formData = `action=checkout&order_type=${encodeURIComponent(orderType)}&payment_method=${encodeURIComponent(paymentMethod)}&pickup_time=${encodeURIComponent(time)}&order_note=${encodeURIComponent(orderNote)}`;

    fetch('cart_actions.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: formData})
    .then(r => r.json()).then(data => {
        if (data.success) {
            // ✅ Show beautiful notification instead of alert
            showNotif('success', 'Order Placed Successfully!', 'A receipt has been sent to your email. Please keep it for your records.', data.order_number, 'receipt.php?order_id=' + data.order_id);
        } else {
            showNotif('error', 'Order Failed', data.message || 'Something went wrong.');
            btn.innerHTML = 'Place Order'; 
            btn.disabled = false;
        }
    }).catch(err => { 
        showNotif('error', 'Network Error', 'Please check your connection and try again.');
        btn.innerHTML = 'Place Order'; 
        btn.disabled = false; 
    });
}
</script>
</body>
</html>