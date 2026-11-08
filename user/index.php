<?php
session_start(); 
include '../connect.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$username = $_SESSION['username'];
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }
if (!isset($_SESSION['fried_rice_stock'])) { $_SESSION['fried_rice_stock'] = 20; }

$cart_count = 0;
foreach ($_SESSION['cart'] as $item) $cart_count += $item['quantity'];

function slugify($s){ return trim(preg_replace('/[^a-z0-9]+/','_', strtolower($s)), '_'); }

// ✅ SMART IMAGE FINDER: Searches all folders to find the image
function imgTag($folder, $image){ 
    if (empty($image) || $image === 'placeholder.jpg') {
        return "https://via.placeholder.com/200x160/8B0000/ffffff?text=No+Image";
    }
    
    $folders = [$folder, 'meryenda', 'snacks', 'candies', 'drinks', 'noodles', 'essentials', 'ulam', ''];
    
    foreach ($folders as $f) {
        $path = __DIR__ . "/../images/" . ($f ? $f . "/" : "") . $image;
        if (file_exists($path)) {
            return "../images/" . ($f ? $f . "/" : "") . $image;
        }
    }
    
    return "https://via.placeholder.com/200x160/8B0000/ffffff?text=" . urlencode(str_replace(' ', '+', $image));
}

// --- RICE MEALS (hardcoded combos) ---
$rice_meals_list = [
    ['name'=>'Meat to meat with rice & drinks','price'=>95,'has_drink'=>true,'has_rice'=>true,'type'=>'meat'],
    ['name'=>'Meat to meat with rice','price'=>85,'has_drink'=>false,'has_rice'=>true,'type'=>'meat'],
    ['name'=>'Meat to meat w/o rice','price'=>75,'has_drink'=>false,'has_rice'=>false,'type'=>'meat'],
    ['name'=>'Meat to gulay with rice & drinks','price'=>85,'has_drink'=>true,'has_rice'=>true,'type'=>'gulay'],
    ['name'=>'Meat to gulay with rice','price'=>75,'has_drink'=>false,'has_rice'=>true,'type'=>'gulay'],
    ['name'=>'Meat to gulay w/o rice','price'=>65,'has_drink'=>false,'has_rice'=>false,'type'=>'gulay'],
];

// --- BREAKFAST SIDES (hardcoded) ---
$breakfast_sides = [
    ['name'=>'Hotdog','price'=>15,'image'=>'hotdog.jpg','folder'=>'ulam'],
    ['name'=>'Egg','price'=>15,'image'=>'egg.jpg','folder'=>'ulam'],
    ['name'=>'Spam','price'=>25,'image'=>'spam.jpg','folder'=>'ulam'],
    ['name'=>'Longganisa','price'=>15,'image'=>'longganisa.jpg','folder'=>'ulam'],
];
$RICE_PRICE = 15;

// --- ACCESORIES (hardcoded) ---
$accesories_items = [
    ['name'=>'Ribbon','price'=>5,'image'=>'ribbon.jpg','folder'=>'accesories'],
];

// ==========================================
// ✅ DYNAMIC FETCH FROM DATABASE
// ==========================================
$ulam_items = [];
$meryenda_items = [];
$candies_items = [];
$coffee_items = [];
$drinks_items = [];
$noodles_items = [];
$essentials_items = [];

$sql = "SELECT productname, price, productImage, categoryID FROM products WHERE is_available = 1 ORDER BY productID ASC";
$res = $con->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $item = [
            'name' => $row['productname'],
            'price' => (float)$row['price'],
            'image' => $row['productImage'] ?: 'placeholder.jpg',
            'folder' => ''
        ];
        
        switch ($row['categoryID']) {
            case 1: $item['folder'] = 'ulam'; $ulam_items[] = $item; break;
            case 2: $item['folder'] = 'meryenda'; $meryenda_items[] = $item; break; 
            case 3: $item['folder'] = 'candies'; $candies_items[] = $item; break;
            case 4: $item['folder'] = 'drinks'; $coffee_items[] = $item; break;
            case 5: $item['folder'] = 'drinks'; $drinks_items[] = $item; break;
            case 6: $item['folder'] = 'noodles'; $noodles_items[] = $item; break;
            case 7: $item['folder'] = 'essentials'; $essentials_items[] = $item; break;
        }
    }
}

$active_cat = isset($_GET['cat']) ? $_GET['cat'] : 'all';

function renderGrid($items, $clickAction = 'qty') {
    if (empty($items)) {
        return '<p style="color:var(--text-muted);padding:1rem;">No items available in this category yet.</p>';
    }
    $html = '<div class="image-grid">';
    foreach ($items as $item) {
        $slug = slugify($item['name']);
        $img = imgTag($item['folder'], $item['image']);
        $ph = 'https://via.placeholder.com/200?text=' . urlencode($item['name']);
        $onclick = ($clickAction === 'amount') ? "openCustomAmountModal(this.closest('.img-card'))" : "openQtyModal(this.closest('.img-card'))";
        $html .= '
        <div class="img-card" data-slug="'.$slug.'" data-name="'.htmlspecialchars($item['name']).'" data-price="'.$item['price'].'" data-img="'.$img.'">
            <div class="img-card-img-wrapper" onclick="'.$onclick.'">
                <img src="'.$img.'" alt="'.htmlspecialchars($item['name']).'" onerror="this.src=\''.$ph.'\'">
            </div>
            <div class="img-card-info">
                <div class="img-card-name" onclick="'.$onclick.'">'.htmlspecialchars($item['name']).'</div>
                <div class="img-card-price">₱'.$item['price'].'</div>
                <div class="qty-controls">
                    <button type="button" class="qty-btn" onclick="changeCardQty(this.closest(\'.img-card\'), -1)">−</button>
                    <span class="qty-display">1</span>
                    <button type="button" class="qty-btn" onclick="changeCardQty(this.closest(\'.img-card\'), 1)">+</button>
                </div>
                <button type="button" class="add-btn" onclick="addFromCard(this.closest(\'.img-card\'))">
                    <i class="bi bi-cart-plus"></i> Add to Cart
                </button>
            </div>
        </div>';
    }
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--brand-red:#8B0000;--brand-light:#fff0f0;--bg-light:#f8f9fa;--text-dark:#333;--text-muted:#6c757d;--sidebar-width:260px;--border-color:#e9ecef}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg-light);display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-width);background:#fff;border-right:1px solid var(--border-color);display:flex;flex-direction:column;height:100vh;position:fixed;left:0;top:0;z-index:100}
.logo{font-size:1.4rem;font-weight:700;color:var(--brand-red);padding:1.2rem 1.5rem;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:10px}
.nav-section{padding:1rem 0;flex:1;overflow-y:auto}
.nav-link{display:flex;align-items:center;gap:12px;padding:12px 1.5rem;color:var(--text-dark);text-decoration:none;font-weight:500;font-size:.95rem;transition:all .2s;border-left:3px solid transparent}
.nav-link:hover{background:#f8f9fa}
.nav-link.active{background:var(--brand-light);color:var(--brand-red);border-left:3px solid var(--brand-red);font-weight:600}
.nav-link i{font-size:1.1rem;width:20px;text-align:center;color:var(--text-muted)}
.nav-link.active i{color:var(--brand-red)}
.user-section{padding:1rem 1.5rem;border-top:1px solid var(--border-color);font-size:.9rem;display:flex;align-items:center;gap:8px}
.user-section a{color:var(--brand-red);text-decoration:none;font-weight:600;margin-left:auto}
.main-content{flex:1;margin-left:var(--sidebar-width);display:flex;flex-direction:column}
.top-header{background:#fff;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);position:sticky;top:0;z-index:50}
.search-bar{flex:1;max-width:500px;position:relative}
.search-bar input{width:100%;padding:10px 15px 10px 40px;border:1px solid var(--border-color);border-radius:8px;font-size:.95rem;outline:none;background:var(--bg-light)}
.search-bar i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--text-muted)}
.cart-btn{background:var(--brand-red);color:#fff;border:none;width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;font-size:1.2rem;text-decoration:none}
.cart-badge{position:absolute;top:-6px;right:-6px;background:#ffc107;color:#000;font-size:.7rem;font-weight:700;min-width:20px;height:20px;border-radius:10px;display:flex;align-items:center;justify-content:center;padding:0 5px}
.content-area{flex:1;padding:2rem;overflow-y:auto}
.page-header{margin-bottom:2rem}
.page-header h1{font-size:1.8rem;font-weight:700;color:var(--text-dark);margin-bottom:.5rem}
.category-section{margin-bottom:3rem}
.category-title{font-size:1.3rem;font-weight:700;color:var(--brand-red);margin-bottom:1.5rem;display:flex;align-items:center;gap:10px;border-bottom:2px solid var(--border-color);padding-bottom:.5rem}
.rice-list{display:flex;flex-direction:column;gap:1rem;max-width:600px}
.rice-item{display:flex;justify-content:space-between;align-items:center;padding:1.2rem;background:#fff;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,.05);border:1px solid var(--border-color);cursor:pointer;transition:all .2s}
.rice-item:hover{border-color:var(--brand-red);transform:translateX(5px)}
.rice-item-name{font-weight:600;font-size:1.05rem;color:var(--text-dark)}
.rice-item-price{font-weight:700;color:var(--brand-red);font-size:1.2rem}
.btn-custom-order{margin-top:2rem;background:var(--brand-red);color:#fff;border:none;padding:12px 24px;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem;display:inline-flex;align-items:center;gap:8px}
.btn-custom-order:hover{background:#6d0000}
.image-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1.5rem}
.img-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);transition:transform .2s;border:1px solid var(--border-color);display:flex;flex-direction:column}
.img-card:hover{transform:translateY(-4px);box-shadow:0 8px 20px rgba(0,0,0,.12)}
.img-card-img-wrapper{width:100%;height:160px;background:#eee;overflow:hidden;cursor:pointer}
.img-card img{width:100%;height:100%;object-fit:cover}
.img-card-info{padding:10px;text-align:center;flex:1;display:flex;flex-direction:column;justify-content:space-between}
.img-card-name{font-size:.9rem;font-weight:600;color:var(--text-dark);margin-bottom:5px;cursor:pointer}
.img-card-price{font-size:1rem;font-weight:700;color:var(--brand-red);margin-bottom:8px}
.qty-controls{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:5px}
.qty-btn{width:28px;height:28px;border:1px solid var(--border-color);background:#fff;border-radius:6px;cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;color:var(--brand-red);font-weight:700}
.qty-btn:hover{background:var(--brand-light)}
.qty-display{font-weight:700;min-width:24px;text-align:center;font-size:.95rem}
.add-btn{margin-top:8px;width:100%;padding:8px 10px;background:var(--brand-red);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:background .2s}
.add-btn:hover{background:#6d0000}
.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:1000;justify-content:center;align-items:center}
.modal-overlay.active{display:flex}
.modal-content{background:#fff;border-radius:16px;padding:2rem;max-width:700px;width:90%;max-height:90vh;overflow-y:auto}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:2px solid var(--border-color)}
.modal-header h2{font-size:1.3rem;color:var(--brand-red)}
.modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted)}
.modal-section{margin-bottom:1.5rem}
.modal-section h3{font-size:1rem;font-weight:600;color:var(--text-dark);margin-bottom:.8rem}
.modal-images{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:.8rem}
.modal-img-option{border:3px solid var(--border-color);border-radius:10px;overflow:hidden;cursor:pointer;transition:all .2s}
.modal-img-option:hover{border-color:var(--brand-red)}
.modal-img-option.selected{border-color:var(--brand-red);box-shadow:0 0 0 3px rgba(139,0,0,.2)}
.modal-img-option img{width:100%;height:90px;object-fit:cover}
.modal-img-name{text-align:center;padding:5px;font-size:.75rem;font-weight:600;background:#f8f9fa}
.rice-type-selector{display:flex;gap:1rem}
.rice-type-option{flex:1;padding:1rem;border:2px solid var(--border-color);border-radius:10px;cursor:pointer;text-align:center;transition:all .2s;background:#fff}
.rice-type-option:hover{border-color:var(--brand-red)}
.rice-type-option.selected{border-color:var(--brand-red);background:var(--brand-light)}
.rice-type-option .rice-icon{font-size:2rem;margin-bottom:.5rem}
.rice-type-option .rice-name{font-weight:600;font-size:.95rem;color:var(--text-dark)}
.rice-type-option .rice-stock{font-size:.75rem;color:var(--text-muted);margin-top:.3rem}
.rice-price-note{margin-top:.6rem;font-size:.85rem;color:var(--brand-red);font-weight:600}
.modal-footer{display:flex;justify-content:space-between;align-items:center;padding-top:1rem;border-top:2px solid var(--border-color);margin-top:1rem;flex-wrap:wrap;gap:1rem}
.modal-total{font-size:1.2rem;font-weight:700;color:var(--brand-red)}
.btn-add-cart{background:var(--brand-red);color:#fff;border:none;padding:12px 24px;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem}
.btn-add-cart:disabled{background:#ccc;cursor:not-allowed}
.custom-amount-input{width:100%;padding:12px;border:2px solid var(--border-color);border-radius:8px;font-size:1.1rem;font-weight:600;outline:none;margin-top:.5rem}
.custom-amount-input:focus{border-color:var(--brand-red)}
.amount-preview{margin-top:.8rem;padding:10px;background:var(--brand-light);border-radius:8px;font-size:.9rem;color:var(--brand-red);font-weight:600}
.toast{position:fixed;bottom:30px;right:30px;background:var(--brand-red);color:#fff;padding:14px 24px;border-radius:10px;font-weight:600;transform:translateY(100px);opacity:0;transition:all .3s;z-index:9999}
.toast.show{transform:translateY(0);opacity:1}

/* ✅ MOBILE SIDEBAR & OVERLAY FIX */
.sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99; transition: opacity 0.3s; }
.sidebar-overlay.active { display: block; }

@media(max-width:768px){
    .sidebar{transform:translateX(-100%);transition:transform .3s}
    .sidebar.open{transform:translateX(0)}
    .main-content{margin-left:0}
    .mobile-toggle{display:block!important}
}
.mobile-toggle{display:none;background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-dark)}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="logo"><i class="bi bi-building"></i> Meryll Eatery</div>
    <div class="nav-section">
        <a href="index.php" class="nav-link <?=$active_cat=='all'?'active':''?>"><i class="bi bi-calendar-day"></i> Today's Menu</a>
        <a href="my_orders.php" class="nav-link"><i class="bi bi-receipt"></i> My Orders</a>
        <a href="?cat=rice" class="nav-link <?=$active_cat=='rice'?'active':''?>"><i class="bi bi-bowl-hot"></i> Rice Meals</a>
        <a href="?cat=breakfast" class="nav-link <?=$active_cat=='breakfast'?'active':''?>"><i class="bi bi-cup-hot"></i> Breakfast</a>
        <a href="?cat=meryenda" class="nav-link <?=$active_cat=='meryenda'?'active':''?>"><i class="bi bi-cookie"></i> Meryenda & Snacks</a>
        <a href="?cat=coffee" class="nav-link <?=$active_cat=='coffee'?'active':''?>"><i class="bi bi-cup-fill"></i> Coffee</a>
        <a href="?cat=drinks" class="nav-link <?=$active_cat=='drinks'?'active':''?>"><i class="bi bi-cup-straw"></i> Drinks</a>
        <a href="?cat=candies" class="nav-link <?=$active_cat=='candies'?'active':''?>"><i class="bi bi-heart"></i> Candies</a>
        <a href="?cat=noodles" class="nav-link <?=$active_cat=='noodles'?'active':''?>"><i class="bi bi-egg"></i> Noodles</a>
        <a href="?cat=essentials" class="nav-link <?=$active_cat=='essentials'?'active':''?>"><i class="bi bi-box-seam"></i> Essentials</a>
        <a href="?cat=accesories" class="nav-link <?=$active_cat=='accesories'?'active':''?>"><i class="bi bi-gift"></i> Accesories</a>
        <a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i> My Profile</a>
    </div>
    <div class="user-section">
        <i class="bi bi-person-circle"></i> <span><?=htmlspecialchars($username)?></span>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</aside>

<!-- ✅ NEW: Dark overlay that closes sidebar when clicked -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<main class="main-content">
    <header class="top-header">
        <!-- ✅ UPDATED: Opens sidebar AND overlay -->
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.add('open'); document.getElementById('sidebarOverlay').classList.add('active');"><i class="bi bi-list"></i></button>
        <div class="search-bar"><i class="bi bi-search"></i><input type="text" placeholder="Search menu..." id="searchInput"></div>
        <a href="cart.php" class="cart-btn"><i class="bi bi-basket3-fill"></i><span class="cart-badge" id="cartBadge"><?=$cart_count>0?$cart_count:'0'?></span></a>
    </header>

    <div class="content-area">
        <div class="page-header"><h1>Today's Available Menu</h1><p style="color:var(--text-muted)">Here is what's available today! Use the sidebar to place your order.</p></div>

        <?php if($active_cat=='rice'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-bowl-hot"></i> Rice Meals</div>
            <div class="rice-list">
                <?php foreach($rice_meals_list as $i=>$item): ?>
                <div class="rice-item" onclick="openRiceMealModal(<?=$i?>)">
                    <span class="rice-item-name"><?=htmlspecialchars($item['name'])?></span>
                    <span class="rice-item-price">₱<?=$item['price']?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="btn-custom-order" onclick="openCustomOrderModal()">
                <i class="bi bi-plus-circle"></i> Create Custom Rice Meal (Mix Ulam)
            </button>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-egg-fried"></i> Available Ulam Today</div>
            <div class="image-grid">
                <?php foreach($ulam_items as $item): ?>
                <div class="img-card" onclick="openCustomOrderModal()" style="cursor:pointer">
                    <div class="img-card-img-wrapper"><img src="<?=imgTag($item['folder'],$item['image'])?>" alt="<?=htmlspecialchars($item['name'])?>"></div>
                    <div class="img-card-info"><div class="img-card-name"><?=htmlspecialchars($item['name'])?></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='breakfast'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-cup-hot"></i> Breakfast</div>
            <div class="image-grid">
                <div class="img-card" onclick="openBreakfastModal()" style="cursor:pointer">
                    <div class="img-card-img-wrapper"><img src="../images/ulam/breakfast.jpg" alt="Breakfast Set" onerror="this.src='https://via.placeholder.com/200?text=Breakfast'"></div>
                    <div class="img-card-info">
                        <div class="img-card-name">Breakfast Set</div>
                        <div class="img-card-price">₱15 rice + sides</div>
                        <div style="font-size:.75rem;color:var(--text-muted)">Click to customize</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='meryenda'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-cookie"></i> Meryenda & Snacks</div>
            <?=renderGrid($meryenda_items,'amount')?>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='coffee'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-cup-fill"></i> Coffee</div>
            <?=renderGrid($coffee_items)?>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='drinks'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-cup-straw"></i> Drinks</div>
            <?=renderGrid($drinks_items)?>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='candies'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-heart"></i> Candies</div>
            <?=renderGrid($candies_items,'amount')?>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='noodles'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-egg"></i> Noodles</div>
            <?=renderGrid($noodles_items)?>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='essentials'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-box-seam"></i> Essentials</div>
            <?=renderGrid($essentials_items)?>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='accesories'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-gift"></i> Accesories</div>
            <?=renderGrid($accesories_items)?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- ============ RICE MEAL MODAL ============ -->
<div class="modal-overlay" id="riceMealModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="riceMealTitle">Select Your Rice Meal</h2>
            <button class="modal-close" onclick="closeModal('riceMealModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-section">
            <h3 id="ulamStepTitle">1. Choose 1-3 Ulam</h3>
            <div class="modal-images" id="ulamSelection">
                <?php foreach($ulam_items as $item): ?>
                <div class="modal-img-option ulam-option" data-name="<?=htmlspecialchars($item['name'])?>" data-type="<?=$item['type'] ?? 'meat'?>" onclick="toggleUlam(this)">
                    <img src="<?=imgTag($item['folder'],$item['image'])?>" alt="<?=htmlspecialchars($item['name'])?>">
                    <div class="modal-img-name"><?=htmlspecialchars($item['name'])?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-section" id="riceTypeSection">
            <h3 id="riceStepTitle">2. Choose Rice Type</h3>
            <div class="rice-type-selector">
                <div class="rice-type-option selected" data-type="white" onclick="selectRiceType(this,'riceMealModal')">
                    <div class="rice-icon">🍚</div><div class="rice-name">White Rice</div><div class="rice-stock">Unlimited</div>
                </div>
                <div class="rice-type-option" data-type="fried" onclick="selectRiceType(this,'riceMealModal')">
                    <div class="rice-icon">🍳</div><div class="rice-name">Fried Rice</div><div class="rice-stock">Limited: <?=$_SESSION['fried_rice_stock']?> left</div>
                </div>
            </div>
        </div>
        <div class="modal-section" id="drinkSelectionSection" style="display:none">
            <h3 id="drinkStepTitle">3. Choose 1 Drink (Included)</h3>
            <div class="modal-images" id="drinkSelection">
                <?php foreach($drinks_items as $item): ?>
                <div class="modal-img-option" data-name="<?=htmlspecialchars($item['name'])?>" onclick="toggleDrink(this)">
                    <img src="<?=imgTag($item['folder'],$item['image'])?>" alt="<?=htmlspecialchars($item['name'])?>">
                    <div class="modal-img-name"><?=htmlspecialchars($item['name'])?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer">
            <div class="modal-total" id="riceMealTotal">₱0.00</div>
            <button class="btn-add-cart" id="btnConfirmRiceMeal" onclick="confirmRiceMeal()" disabled><i class="bi bi-cart-plus"></i> Add to Cart</button>
        </div>
    </div>
</div>

<!-- ============ CUSTOM ORDER MODAL ============ -->
<div class="modal-overlay" id="customOrderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-basket2"></i> Create Custom Rice Meal</h2>
            <button class="modal-close" onclick="closeModal('customOrderModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-section">
            <h3>1. Select 1-3 Ulam (Meat ₱40 / Gulay ₱35 each)</h3>
            <div class="modal-images" id="customUlamSelection">
                <?php foreach($ulam_items as $item): ?>
                <div class="modal-img-option" data-name="<?=htmlspecialchars($item['name'])?>" data-type="<?=$item['type'] ?? 'meat'?>" onclick="toggleCustomUlam(this)">
                    <img src="<?=imgTag($item['folder'],$item['image'])?>" alt="<?=htmlspecialchars($item['name'])?>">
                    <div class="modal-img-name"><?=htmlspecialchars($item['name'])?> - ₱<?=($item['type'] ?? 'meat')=='meat'?40:35?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-section">
            <h3>2. Choose Rice Type</h3>
            <div class="rice-type-selector">
                <div class="rice-type-option selected" data-type="white" onclick="selectCustomRiceType(this)">
                    <div class="rice-icon">🍚</div><div class="rice-name">White Rice</div>
                </div>
                <div class="rice-type-option" data-type="fried" onclick="selectCustomRiceType(this)">
                    <div class="rice-icon">🍳</div><div class="rice-name">Fried Rice</div><div class="rice-stock">Limited: <?=$_SESSION['fried_rice_stock']?> left</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="modal-total" id="customOrderTotal">₱0.00</div>
            <button class="btn-add-cart" id="btnConfirmCustom" onclick="confirmCustomOrder()" disabled><i class="bi bi-cart-plus"></i> Add to Cart</button>
        </div>
    </div>
</div>

<!-- ============ BREAKFAST MODAL ============ -->
<div class="modal-overlay" id="breakfastModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="bi bi-cup-hot"></i> Customize Breakfast</h2>
            <button class="modal-close" onclick="closeModal('breakfastModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-section">
            <h3>1. Choose Your Sides</h3>
            <div class="modal-images" id="breakfastSides">
                <?php foreach($breakfast_sides as $item): ?>
                <div class="modal-img-option" data-name="<?=htmlspecialchars($item['name'])?>" data-price="<?=$item['price']?>" onclick="toggleBreakfastSide(this)">
                    <img src="<?=imgTag($item['folder'],$item['image'])?>" alt="<?=htmlspecialchars($item['name'])?>">
                    <div class="modal-img-name"><?=htmlspecialchars($item['name'])?> - ₱<?=$item['price']?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-section">
            <h3>2. Choose Rice Type (₱15)</h3>
            <div class="rice-type-selector">
                <div class="rice-type-option selected" data-type="white" onclick="selectBreakfastRice(this)">
                    <div class="rice-icon">🍚</div><div class="rice-name">White Rice</div><div class="rice-stock">₱15</div>
                </div>
                <div class="rice-type-option" data-type="fried" onclick="selectBreakfastRice(this)">
                    <div class="rice-icon">🍳</div><div class="rice-name">Fried Rice</div><div class="rice-stock">₱15 • Limited: <?=$_SESSION['fried_rice_stock']?> left</div>
                </div>
            </div>
            <div class="rice-price-note">Rice is ₱15 and is automatically added to your total.</div>
        </div>
        <div class="modal-footer">
            <div class="modal-total" id="breakfastTotal">₱15.00</div>
            <button class="btn-add-cart" id="btnConfirmBreakfast" onclick="confirmBreakfast()" disabled><i class="bi bi-cart-plus"></i> Add to Cart</button>
        </div>
    </div>
</div>

<!-- ============ QTY MODAL ============ -->
<div class="modal-overlay" id="qtyModal">
    <div class="modal-content" style="max-width:400px">
        <div class="modal-header">
            <h2 id="qtyModalTitle">Item</h2>
            <button class="modal-close" onclick="closeModal('qtyModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div style="text-align:center;margin-bottom:1.5rem">
            <img id="qtyModalImg" src="" style="width:100%;max-width:250px;height:200px;object-fit:cover;border-radius:12px;margin-bottom:1rem">
            <div style="font-size:1.1rem;font-weight:700;color:var(--brand-red)" id="qtyModalPrice">₱0</div>
        </div>
        <div class="modal-section">
            <h3 style="text-align:center">Quantity</h3>
            <div class="qty-controls" style="justify-content:center;gap:15px">
                <button type="button" class="qty-btn" style="width:40px;height:40px;font-size:1.2rem" onclick="changeQtyModal(-1)">−</button>
                <span class="qty-display" id="qtyModalQty" style="font-size:1.5rem;min-width:40px">1</span>
                <button type="button" class="qty-btn" style="width:40px;height:40px;font-size:1.2rem" onclick="changeQtyModal(1)">+</button>
            </div>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <div class="modal-total" id="qtyModalTotal">₱0.00</div>
            <button class="btn-add-cart" onclick="confirmQtyModal()"><i class="bi bi-cart-plus"></i> Add to Cart</button>
        </div>
    </div>
</div>

<!-- ============ CUSTOM AMOUNT MODAL ============ -->
<div class="modal-overlay" id="customAmountModal">
    <div class="modal-content" style="max-width:400px">
        <div class="modal-header">
            <h2 id="customAmountTitle">Item</h2>
            <button class="modal-close" onclick="closeModal('customAmountModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div style="text-align:center;margin-bottom:1.5rem">
            <img id="customAmountImg" src="" style="width:100%;max-width:250px;height:200px;object-fit:cover;border-radius:12px;margin-bottom:1rem">
            <div style="font-size:.9rem;color:var(--text-muted)" id="customAmountUnitPrice">₱0/pc</div>
        </div>
        <div class="modal-section">
            <h3>How much do you want to spend? (Min ₱15)</h3>
            <input type="number" class="custom-amount-input" id="customAmountInput" min="15" value="15" oninput="updateCustomAmountPreview()">
            <div class="amount-preview" id="customAmountPreview"></div>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-add-cart" onclick="confirmCustomAmount()"><i class="bi bi-cart-plus"></i> Add to Cart</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const RICE_PRICE = 15;
const ULAM_PRICE = { meat: 40, gulay: 35 };
const riceMeals = <?=json_encode($rice_meals_list)?>;

let selectedUlam=[], selectedRiceType='white', selectedDrink='', currentMeal=null;
let selectedCustomUlam=[], customRiceType='white';
let selectedSides=[], breakfastRice='white';
let qtyModalCard=null, qtyModalName='', qtyModalPrice=0, qtyModalQty=1;
let customAmountCard=null, customAmountName='', customAmountPrice=0;

// ✅ NEW: Function to close sidebar when clicking overlay
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2500)}
function closeModal(id){document.getElementById(id).classList.remove('active')}
function slugify(s){return s.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'')}

function addToCart(name, price, qty){
    qty = qty || 1;
    return fetch('cart_actions.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=add&name='+encodeURIComponent(name)+'&price='+price+'&quantity='+qty})
    .then(r=>r.json()).then(d=>{ if(d.success){ refreshBadge(); } else showToast('❌ '+(d.message||'Error')); });
}
function refreshBadge(){
    fetch('cart_actions.php?action=get').then(r=>r.json()).then(d=>{
        document.getElementById('cartBadge').textContent = d.total_items||0;
    });
}
function deductFriedRice(){
    fetch('cart_actions.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=deduct_fried_rice'});
}

function changeCardQty(card, delta){
    const el = card.querySelector('.qty-display');
    let q = parseInt(el.textContent) || 1;
    q = Math.max(1, Math.min(99, q + delta));
    el.textContent = q;
}

function addFromCard(card){
    const name = card.dataset.name;
    const price = parseFloat(card.dataset.price);
    const qty = parseInt(card.querySelector('.qty-display').textContent) || 1;
    addToCart(name, price, qty).then(()=>{
        showToast('✅ ' + qty + ' x ' + name + ' added to cart!');
        card.querySelector('.qty-display').textContent = 1;
    });
}

function openRiceMealModal(index){
    currentMeal = riceMeals[index];
    selectedUlam=[]; selectedRiceType='white'; selectedDrink='';
    document.getElementById('riceMealTitle').textContent = currentMeal.name;
    document.getElementById('riceMealTotal').textContent = '₱'+currentMeal.price.toFixed(2);
    document.getElementById('btnConfirmRiceMeal').disabled = true;
    document.querySelectorAll('#ulamSelection .ulam-option').forEach(el=>{
        el.classList.remove('selected');
        el.style.display = (currentMeal.type==='gulay' || el.dataset.type==='meat') ? '' : 'none';
    });
    document.getElementById('ulamStepTitle').textContent = (currentMeal.type==='gulay') ? '1. Choose your Ulam (Meat + Gulay)' : '1. Choose your Meat Ulam';
    const riceSection = document.getElementById('riceTypeSection');
    if (currentMeal.has_rice) {
        riceSection.style.display = 'block';
        document.getElementById('riceStepTitle').textContent = '2. Choose Rice Type';
    } else {
        riceSection.style.display = 'none';
    }
    const drinkSection = document.getElementById('drinkSelectionSection');
    if (currentMeal.has_drink) {
        drinkSection.style.display = 'block';
        document.getElementById('drinkStepTitle').textContent = (currentMeal.has_rice ? '3' : '2') + '. Choose 1 Drink (Included)';
    } else {
        drinkSection.style.display = 'none';
    }
    document.querySelectorAll('#drinkSelection .modal-img-option').forEach(el=>el.classList.remove('selected'));
    document.querySelectorAll('#riceMealModal .rice-type-option').forEach(el=>el.classList.remove('selected'));
    document.querySelector('#riceMealModal .rice-type-option[data-type="white"]').classList.add('selected');
    document.getElementById('riceMealModal').classList.add('active');
}
function toggleUlam(el){
    const name = el.dataset.name;
    if(el.classList.contains('selected')){ el.classList.remove('selected'); selectedUlam=selectedUlam.filter(n=>n!==name); }
    else{
        if(selectedUlam.length<3){ el.classList.add('selected'); selectedUlam.push(name); }
        else showToast('Max 3 ulam!');
    }
    checkRiceReady();
}
function toggleDrink(el){
    document.querySelectorAll('#drinkSelection .modal-img-option').forEach(e=>e.classList.remove('selected'));
    el.classList.add('selected'); selectedDrink = el.dataset.name; checkRiceReady();
}
function selectRiceType(el, modalId){
    if(el.dataset.type==='fried' && <?=$_SESSION['fried_rice_stock']?><=0){ showToast('Fried rice sold out!'); return; }
    selectedRiceType = el.dataset.type;
    document.querySelectorAll('#'+modalId+' .rice-type-option').forEach(e=>e.classList.remove('selected'));
    el.classList.add('selected');
}
function checkRiceReady(){
    let ready = selectedUlam.length>0;
    if(currentMeal && currentMeal.has_drink) ready = ready && selectedDrink!=='';
    document.getElementById('btnConfirmRiceMeal').disabled = !ready;
}
function confirmRiceMeal(){
    if(selectedUlam.length===0) return;
    let desc = currentMeal.name+' ('+selectedUlam.join(', ');
    if (currentMeal.has_rice) desc += ', '+(selectedRiceType==='fried'?'Fried Rice':'White Rice');
    if (currentMeal.has_drink && selectedDrink) desc += ', '+selectedDrink;
    desc += ')';
    addToCart(desc, currentMeal.price);
    if (currentMeal.has_rice && selectedRiceType==='fried') deductFriedRice();
    closeModal('riceMealModal');
    showToast('✅ Added to cart!');
}

// ✅ FIXED: Custom Order Modal now includes Rice Price in total
function openCustomOrderModal(){
    selectedCustomUlam=[]; customRiceType='white';
    document.getElementById('customOrderTotal').textContent='₱'+RICE_PRICE.toFixed(2); // Shows base rice price
    document.getElementById('btnConfirmCustom').disabled=true;
    document.querySelectorAll('#customUlamSelection .modal-img-option').forEach(e=>e.classList.remove('selected'));
    document.querySelectorAll('#customOrderModal .rice-type-option').forEach(e=>e.classList.remove('selected'));
    document.querySelector('#customOrderModal .rice-type-option[data-type="white"]').classList.add('selected');
    document.getElementById('customOrderModal').classList.add('active');
}

function toggleCustomUlam(el){
    const name = el.dataset.name, type = el.dataset.type || 'meat';
    if(el.classList.contains('selected')){ el.classList.remove('selected'); selectedCustomUlam=selectedCustomUlam.filter(u=>u.name!==name); }
    else{
        if(selectedCustomUlam.length<3){ el.classList.add('selected'); selectedCustomUlam.push({name,type}); }
        else showToast('Max 3 ulam!');
    }
    // ✅ FIXED: Added RICE_PRICE to the calculation
    let total = RICE_PRICE + selectedCustomUlam.reduce((s,u)=>s+ULAM_PRICE[u.type],0);
    document.getElementById('customOrderTotal').textContent='₱'+total.toFixed(2);
    document.getElementById('btnConfirmCustom').disabled = selectedCustomUlam.length===0;
}

function selectCustomRiceType(el){
    if(el.dataset.type==='fried' && <?=$_SESSION['fried_rice_stock']?><=0){ showToast('Fried rice sold out!'); return; }
    customRiceType = el.dataset.type;
    document.querySelectorAll('#customOrderModal .rice-type-option').forEach(e=>e.classList.remove('selected'));
    el.classList.add('selected');
}

function confirmCustomOrder(){
    if(selectedCustomUlam.length===0) return;
    // ✅ FIXED: Added RICE_PRICE to the final total
    let total = RICE_PRICE + selectedCustomUlam.reduce((s,u)=>s+ULAM_PRICE[u.type],0);
    let desc = 'Custom Rice ('+selectedCustomUlam.map(u=>u.name).join(', ')+', '+(customRiceType==='fried'?'Fried Rice':'White Rice')+')';
    addToCart(desc, total);
    if(customRiceType==='fried') deductFriedRice();
    closeModal('customOrderModal');
    showToast('✅ Added to cart!');
}

function openBreakfastModal(){
    selectedSides=[]; breakfastRice='white';
    document.getElementById('breakfastTotal').textContent='₱'+RICE_PRICE.toFixed(2);
    document.getElementById('btnConfirmBreakfast').disabled=true;
    document.querySelectorAll('#breakfastSides .modal-img-option').forEach(e=>e.classList.remove('selected'));
    document.querySelectorAll('#breakfastModal .rice-type-option').forEach(e=>e.classList.remove('selected'));
    document.querySelector('#breakfastModal .rice-type-option[data-type="white"]').classList.add('selected');
    document.getElementById('breakfastModal').classList.add('active');
}
function toggleBreakfastSide(el){
    const name = el.dataset.name, price = parseFloat(el.dataset.price);
    if(el.classList.contains('selected')){ el.classList.remove('selected'); selectedSides=selectedSides.filter(s=>s.name!==name); }
    else{ el.classList.add('selected'); selectedSides.push({name,price}); }
    let total = RICE_PRICE + selectedSides.reduce((s,x)=>s+x.price,0);
    document.getElementById('breakfastTotal').textContent='₱'+total.toFixed(2);
    document.getElementById('btnConfirmBreakfast').disabled = selectedSides.length===0;
}
function selectBreakfastRice(el){
    if(el.dataset.type==='fried' && <?=$_SESSION['fried_rice_stock']?><=0){ showToast('Fried rice sold out!'); return; }
    breakfastRice = el.dataset.type;
    document.querySelectorAll('#breakfastModal .rice-type-option').forEach(e=>e.classList.remove('selected'));
    el.classList.add('selected');
}
function confirmBreakfast(){
    if(selectedSides.length===0) return;
    let total = RICE_PRICE + selectedSides.reduce((s,x)=>s+x.price,0);
    let desc = 'Breakfast ('+selectedSides.map(s=>s.name).join(', ')+', '+(breakfastRice==='fried'?'Fried Rice':'White Rice')+')';
    addToCart(desc, total);
    if(breakfastRice==='fried') deductFriedRice();
    closeModal('breakfastModal');
    showToast('✅ Added to cart!');
}

function openQtyModal(card){
    qtyModalCard = card;
    qtyModalName = card.dataset.name; qtyModalPrice = parseFloat(card.dataset.price); qtyModalQty = 1;
    document.getElementById('qtyModalTitle').textContent = qtyModalName;
    document.getElementById('qtyModalImg').src = card.dataset.img;
    document.getElementById('qtyModalPrice').textContent = '₱'+qtyModalPrice;
    document.getElementById('qtyModalQty').textContent = '1';
    document.getElementById('qtyModalTotal').textContent = '₱'+qtyModalPrice.toFixed(2);
    document.getElementById('qtyModal').classList.add('active');
}
function changeQtyModal(d){
    qtyModalQty = Math.max(1, qtyModalQty+d);
    document.getElementById('qtyModalQty').textContent = qtyModalQty;
    document.getElementById('qtyModalTotal').textContent = '₱'+(qtyModalPrice*qtyModalQty).toFixed(2);
}
function confirmQtyModal(){
    addToCart(qtyModalName, qtyModalPrice, qtyModalQty).then(()=>{
        if(qtyModalCard) qtyModalCard.querySelector('.qty-display').textContent = qtyModalQty;
        closeModal('qtyModal');
        showToast('✅ Added to cart!');
    });
}

function openCustomAmountModal(card){
    customAmountCard = card;
    customAmountName = card.dataset.name; customAmountPrice = parseFloat(card.dataset.price);
    document.getElementById('customAmountTitle').textContent = customAmountName;
    document.getElementById('customAmountImg').src = card.dataset.img;
    document.getElementById('customAmountUnitPrice').textContent = '₱'+customAmountPrice+'/pc';
    document.getElementById('customAmountInput').value = 15;
    updateCustomAmountPreview();
    document.getElementById('customAmountModal').classList.add('active');
}
function updateCustomAmountPreview(){
    let amount = parseInt(document.getElementById('customAmountInput').value)||0;
    if(amount<15){ document.getElementById('customAmountPreview').textContent='Minimum spend is ₱15'; return; }
    let qty = Math.floor(amount/customAmountPrice);
    let actual = qty*customAmountPrice, change = amount-actual;
    document.getElementById('customAmountPreview').textContent = 'You will get '+qty+' pc(s) for ₱'+actual+'.00'+(change>0?' (Change: ₱'+change+'.00)':'');
}
function confirmCustomAmount(){
    let amount = parseInt(document.getElementById('customAmountInput').value)||0;
    if(amount<15){ showToast('Minimum spend is ₱15'); return; }
    let qty = Math.floor(amount/customAmountPrice);
    if(qty<1){ showToast('Amount too low for this item'); return; }
    addToCart(customAmountName, customAmountPrice, qty).then(()=>{
        if(customAmountCard) customAmountCard.querySelector('.qty-display').textContent = qty;
        closeModal('customAmountModal');
        showToast('✅ Added to cart!');
    });
}

document.querySelectorAll('.modal-overlay').forEach(m=>{ m.addEventListener('click',function(e){ if(e.target===this) this.classList.remove('active'); }); });
document.getElementById('searchInput').addEventListener('input',function(e){
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.img-card,.rice-item').forEach(c=>{ c.style.display = c.textContent.toLowerCase().includes(term)?'':'none'; });
});
refreshBadge();
</script>
</body>
</html>