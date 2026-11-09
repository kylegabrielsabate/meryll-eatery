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
function imgTag($folder, $image){ 
    if (empty($image) || $image === 'placeholder.jpg') return "https://via.placeholder.com/200x160/8B0000/ffffff?text=No+Image";
    $folders = [$folder, 'meryenda', 'snacks', 'candies', 'drinks', 'noodles', 'essentials', 'ulam', ''];
    foreach ($folders as $f) {
        $path = __DIR__ . "/../images/" . ($f ? $f . "/" : "") . $image;
        if (file_exists($path)) return "../images/" . ($f ? $f . "/" : "") . $image;
    }
    return "https://via.placeholder.com/200x160/8B0000/ffffff?text=" . urlencode(str_replace(' ', '+', $image));
}

// --- RICE MEALS (Added Ulam Only) ---
$rice_meals_list = [
    ['name'=>'Meat to meat with rice & drinks','price'=>95,'has_drink'=>true,'has_rice'=>true,'type'=>'meat'],
    ['name'=>'Meat to meat with rice','price'=>85,'has_drink'=>false,'has_rice'=>true,'type'=>'meat'],
    ['name'=>'Meat to meat w/o rice (Ulam Only)','price'=>75,'has_drink'=>false,'has_rice'=>false,'type'=>'meat'],
    ['name'=>'Meat to gulay with rice & drinks','price'=>85,'has_drink'=>true,'has_rice'=>true,'type'=>'gulay'],
    ['name'=>'Meat to gulay with rice','price'=>75,'has_drink'=>false,'has_rice'=>true,'type'=>'gulay'],
    ['name'=>'Meat to gulay w/o rice (Ulam Only)','price'=>65,'has_drink'=>false,'has_rice'=>false,'type'=>'gulay'],
];

$breakfast_sides = [
    ['name'=>'Hotdog','price'=>15,'image'=>'hotdog.jpg','folder'=>'ulam'],
    ['name'=>'Egg','price'=>15,'image'=>'egg.jpg','folder'=>'ulam'],
    ['name'=>'Spam','price'=>25,'image'=>'spam.jpg','folder'=>'ulam'],
    ['name'=>'Longganisa','price'=>15,'image'=>'longganisa.jpg','folder'=>'ulam'],
];
$RICE_PRICE = 15;
$accesories_items = [['name'=>'Ribbon','price'=>5,'image'=>'ribbon.jpg','folder'=>'accesories']];

// --- DYNAMIC FETCH ---
$ulam_items = []; $meryenda_items = []; $candies_items = []; $coffee_items = []; $drinks_items = []; $noodles_items = []; $essentials_items = [];
$sql = "SELECT productID, productname, price, productImage, categoryID FROM products WHERE is_available = 1 ORDER BY productID ASC";
$res = $con->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $item = ['productID' => $row['productID'], 'name' => $row['productname'], 'price' => (float)$row['price'], 'image' => $row['productImage'] ?: 'placeholder.jpg', 'folder' => ''];
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

// Filter out breakfast items from custom ulam
$custom_ulam_items = array_filter($ulam_items, function($item) {
    return !in_array($item['name'], ['Hotdog', 'Egg', 'Spam', 'Longganisa', 'Breakfast Silog', 'Fried Rice']);
});

// Separate Meat and Gulay for the modal (Adjust names based on your actual DB)
$meat_items = array_filter($custom_ulam_items, function($i) { return preg_match('/chicken|pork|beef|meat|adobo|bbq|tocino/i', $i['name']); });
$gulay_items = array_filter($custom_ulam_items, function($i) { return preg_match('/gulay|talong|sitaw|ampalaya|sayote/i', $i['name']); });
// If filter fails, fallback to all
if(empty($meat_items)) $meat_items = $custom_ulam_items;
if(empty($gulay_items)) $gulay_items = [];

$active_cat = isset($_GET['cat']) ? $_GET['cat'] : 'all';

// Items that use "Amount" modal (piece-based)
$amount_items_names = ['Siomai', 'Candies', 'Assorted Candies'];

function renderGrid($items, $clickAction = 'qty') {
    if (empty($items)) return '<p style="color:var(--text-muted);padding:1rem;">No items available.</p>';
    $html = '<div class="image-grid">';
    foreach ($items as $item) {
        $slug = slugify($item['name']);
        $img = imgTag($item['folder'], $item['image']);
        $ph = 'https://via.placeholder.com/200?text=' . urlencode($item['name']);
        // Use 'amount' for siomai/candies, 'qty' for others
        $action = (in_array($item['name'], ['Siomai', 'Assorted Candies (4pcs)'])) ? 'amount' : $clickAction;
        $onclick = ($action === 'amount') ? "openCustomAmountModal(this.closest('.img-card'))" : "openQtyModal(this.closest('.img-card'))";
        
        $html .= '
        <div class="img-card" data-slug="'.$slug.'" data-productid="'.$item['productID'].'" data-name="'.htmlspecialchars($item['name']).'" data-price="'.$item['price'].'" data-img="'.$img.'">
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
                <button type="button" class="add-btn" onclick="addFromCard(this.closest(\'.img-card\'))"><i class="bi bi-cart-plus"></i> Add to Cart</button>
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
/* ✅ LOGO REMOVED FROM SIDEBAR AS REQUESTED */
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
.sub-category-title{font-size:1.1rem;font-weight:600;color:var(--text-dark);margin:1.5rem 0 1rem 0;display:flex;align-items:center;gap:8px}
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
.warning-banner{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-bottom:15px;color:#856404;font-size:0.9rem;display:flex;align-items:center;gap:10px}
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
.amount-preview{margin-top:.8rem;padding:10px;background:var(--brand-light);border-radius:8px;font-size:.9rem;color:var(--brand-red);font-weight:600}
.toast{position:fixed;bottom:30px;right:30px;background:var(--brand-red);color:#fff;padding:14px 24px;border-radius:10px;font-weight:600;transform:translateY(100px);opacity:0;transition:all .3s;z-index:9999}
.toast.show{transform:translateY(0);opacity:1}
.sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99; }
.sidebar-overlay.active { display: block; }
@media(max-width:768px){.sidebar{transform:translateX(-100%);transition:transform .3s}.sidebar.open{transform:translateX(0)}.main-content{margin-left:0}.mobile-toggle{display:block!important}}
.mobile-toggle{display:none;background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-dark)}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <!-- ✅ LOGO REMOVED -->
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

<div class="sidebar-overlay" id="sidebarOverlay" onclick="document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('active')"></div>

<main class="main-content">
    <header class="top-header">
        <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.add('open'); document.getElementById('sidebarOverlay').classList.add('active');"><i class="bi bi-list"></i></button>
        <div class="search-bar"><i class="bi bi-search"></i><input type="text" placeholder="Search menu..." id="searchInput"></div>
        <a href="cart.php" class="cart-btn"><i class="bi bi-basket3-fill"></i><span class="cart-badge" id="cartBadge"><?=$cart_count>0?$cart_count:'0'?></span></a>
    </header>

    <div class="content-area">
        <div class="page-header"><h1>Today's Available Menu</h1><p style="color:var(--text-muted)">Here is what's available today!</p></div>

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
            <button class="btn-custom-order" onclick="openCustomOrderModal()"><i class="bi bi-plus-circle"></i> Create Custom Rice Meal (Mix Ulam)</button>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-egg-fried"></i> Available Ulam Today</div>
            <div class="image-grid">
                <?php foreach($custom_ulam_items as $item): ?>
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
            <div class="category-title"><i class="bi bi-cup-hot"></i> Breakfast Silog</div>
            <div class="image-grid">
                <div class="img-card" onclick="openBreakfastModal()" style="cursor:pointer">
                    <div class="img-card-img-wrapper"><img src="../images/ulam/breakfast.jpg" alt="Breakfast Silog" onerror="this.src='https://via.placeholder.com/200?text=Breakfast'"></div>
                    <div class="img-card-info"><div class="img-card-name">Breakfast Silog</div><div class="img-card-price">₱15 rice + sides</div></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='meryenda'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-cookie"></i> Meryenda & Snacks</div>
            
            <div class="sub-category-title"><i class="bi bi-egg-fried"></i> Lumpia & Siomai</div>
            <?=renderGrid([
                ['productID'=>0,'name'=>'Lumpiang Gulay','price'=>15,'image'=>'lumpia_gulay.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Lumpiang Shanghai','price'=>20,'image'=>'lumpia_shanghai.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Lumpiang Ube','price'=>20,'image'=>'lumpia_ube.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Siomai','price'=>5,'image'=>'siomai.jpg','folder'=>'meryenda']
            ],'amount')?>

            <div class="sub-category-title"><i class="bi bi-circle"></i> Biscuits</div>
            <?=renderGrid([
                ['productID'=>0,'name'=>'Skyflakes','price'=>10,'image'=>'skyflakes.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Snacku','price'=>20,'image'=>'snacku.jpg','folder'=>'meryenda']
            ])?>

            <div class="sub-category-title"><i class="bi bi-stars"></i> Chichirya</div>
            <?=renderGrid([
                ['productID'=>0,'name'=>'Nova','price'=>20,'image'=>'nova.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Piattos','price'=>20,'image'=>'piattos.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Vcut','price'=>20,'image'=>'vcut.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Mr. Chips Mais','price'=>12,'image'=>'mr_chips.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Mang Juan','price'=>12,'image'=>'mang_juan.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Loaded','price'=>12,'image'=>'loaded.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Xtreme','price'=>10,'image'=>'xtreme.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Crossini','price'=>15,'image'=>'crossini.jpg','folder'=>'meryenda'],
                ['productID'=>0,'name'=>'Pillows','price'=>12,'image'=>'pillows.jpg','folder'=>'meryenda']
            ])?>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='coffee'): ?><div class="category-section"><div class="category-title"><i class="bi bi-cup-fill"></i> Coffee</div><?=renderGrid($coffee_items)?></div><?php endif; ?>
        <?php if($active_cat=='all'||$active_cat=='drinks'): ?><div class="category-section"><div class="category-title"><i class="bi bi-cup-straw"></i> Drinks</div><?=renderGrid($drinks_items)?></div><?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='candies'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-heart"></i> Candies</div>
            <p style="color:var(--brand-red);font-weight:600;margin-bottom:1rem;">🍬 All Candies: 4 pieces for ₱5.00</p>
            <?=renderGrid([['productID'=>0,'name'=>'Assorted Candies (4pcs)','price'=>5,'image'=>'candies.jpg','folder'=>'candies']],'amount')?>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='noodles'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-egg"></i> Noodles</div>
            <?=renderGrid([
                ['productID'=>0,'name'=>'Pancit Canton (Regular)','price'=>20,'image'=>'pancit.jpg','folder'=>'noodles'],
                ['productID'=>0,'name'=>'Pancit Canton (Large)','price'=>25,'image'=>'pancit.jpg','folder'=>'noodles'],
                ['productID'=>0,'name'=>'Pancit Canton (Overload)','price'=>35,'image'=>'pancit.jpg','folder'=>'noodles']
            ])?>
        </div>
        <?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='essentials'): ?><div class="category-section"><div class="category-title"><i class="bi bi-box-seam"></i> Essentials</div><?=renderGrid($essentials_items)?></div><?php endif; ?>

        <?php if($active_cat=='all'||$active_cat=='accesories'): ?>
        <div class="category-section">
            <div class="category-title"><i class="bi bi-gift"></i> Accesories</div>
            <div class="warning-banner"><i class="bi bi-exclamation-triangle-fill"></i> <strong>Warning:</strong> Accessories are non-refundable once purchased.</div>
            <?=renderGrid($accesories_items)?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- ============ MODALS (Rice, Custom, Breakfast, Qty, Amount) ============ -->
<!-- (Keep the exact same modal HTML structure from your previous index.php, just ensure the JS below matches) -->
<!-- For brevity, I am including the JS logic which is the most important part to update -->

<div class="toast" id="toast"></div>

<script>
const RICE_PRICE = 15;
const riceMeals = <?=json_encode($rice_meals_list)?>;
let selectedUlam=[], selectedRiceType='white', selectedDrink='', currentMeal=null;
let selectedCustomMeat=[], selectedCustomGulay=[], customRiceType='white', selectedCustomDrink='';
let selectedSides=[], breakfastRice='white', selectedBreakfastDrink='';
let qtyModalCard=null, qtyModalName='', qtyModalPrice=0, qtyModalQty=1, qtyModalProductID=0;
let customAmountCard=null, customAmountName='', customAmountPrice=0, customAmountProductID=0;

function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2500)}
function closeModal(id){document.getElementById(id).classList.remove('active')}

function addToCart(name, price, qty, productID){
    qty = qty || 1; productID = productID || 0;
    return fetch('cart_actions.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=add&name='+encodeURIComponent(name)+'&price='+price+'&quantity='+qty+'&productID='+productID})
    .then(r=>r.json()).then(d=>{ if(d.success){ refreshBadge(); } else showToast('❌ '+(d.message||'Error')); });
}
function refreshBadge(){ fetch('cart_actions.php?action=get').then(r=>r.json()).then(d=>{ document.getElementById('cartBadge').textContent = d.total_items||0; }); }
function deductFriedRice(){ fetch('cart_actions.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=deduct_fried_rice'}); }

function changeCardQty(card, delta){ const el = card.querySelector('.qty-display'); let q = parseInt(el.textContent) || 1; el.textContent = Math.max(1, Math.min(99, q + delta)); }
function addFromCard(card){
    const name = card.dataset.name, price = parseFloat(card.dataset.price), productID = parseInt(card.dataset.productid) || 0;
    const qty = parseInt(card.querySelector('.qty-display').textContent) || 1;
    addToCart(name, price, qty, productID).then(()=>{ showToast('✅ Added!'); card.querySelector('.qty-display').textContent = 1; });
}

// --- RICE MEAL MODAL ---
function openRiceMealModal(index){
    currentMeal = riceMeals[index]; selectedUlam=[]; selectedRiceType='white'; selectedDrink='';
    document.getElementById('riceMealTitle').textContent = currentMeal.name;
    document.getElementById('riceMealTotal').textContent = '₱'+currentMeal.price.toFixed(2);
    document.getElementById('btnConfirmRiceMeal').disabled = true;
    document.querySelectorAll('#ulamSelection .ulam-option').forEach(el=>el.classList.remove('selected'));
    document.getElementById('riceTypeSection').style.display = currentMeal.has_rice ? 'block' : 'none';
    document.getElementById('drinkSelectionSection').style.display = currentMeal.has_drink ? 'block' : 'none';
    document.getElementById('riceMealModal').classList.add('active');
}
function toggleUlam(el){
    const name = el.dataset.name;
    if(el.classList.contains('selected')){ el.classList.remove('selected'); selectedUlam=selectedUlam.filter(n=>n!==name); }
    else{ if(selectedUlam.length<3){ el.classList.add('selected'); selectedUlam.push(name); } else showToast('Max 3 ulam!'); }
    checkRiceReady();
}
function toggleDrink(el){ document.querySelectorAll('#drinkSelection .modal-img-option').forEach(e=>e.classList.remove('selected')); el.classList.add('selected'); selectedDrink = el.dataset.name; checkRiceReady(); }
function selectRiceType(el, modalId){ selectedRiceType = el.dataset.type; document.querySelectorAll('#'+modalId+' .rice-type-option').forEach(e=>e.classList.remove('selected')); el.classList.add('selected'); }
function checkRiceReady(){ let ready = selectedUlam.length>0; if(currentMeal && currentMeal.has_drink) ready = ready && selectedDrink!==''; document.getElementById('btnConfirmRiceMeal').disabled = !ready; }
function confirmRiceMeal(){
    if(selectedUlam.length===0) return;
    let desc = currentMeal.name+' ('+selectedUlam.join(', ');
    if (currentMeal.has_rice) desc += ', '+(selectedRiceType==='fried'?'Fried Rice':'White Rice');
    if (currentMeal.has_drink && selectedDrink) desc += ', '+selectedDrink; desc += ')';
    addToCart(desc, currentMeal.price, 1, 0);
    if (currentMeal.has_rice && selectedRiceType==='fried') deductFriedRice();
    closeModal('riceMealModal'); showToast('✅ Added!');
}

// --- CUSTOM ORDER (SEPARATED MEAT & GULAY) ---
function openCustomOrderModal(){
    selectedCustomMeat=[]; selectedCustomGulay=[]; customRiceType='white'; selectedCustomDrink='';
    document.getElementById('customOrderTotal').textContent='₱'+RICE_PRICE.toFixed(2);
    document.getElementById('btnConfirmCustom').disabled=true;
    document.querySelectorAll('#customOrderModal .modal-img-option').forEach(e=>e.classList.remove('selected'));
    document.getElementById('customDrinkSection').style.display='none';
    document.getElementById('customOrderModal').classList.add('active');
}
function toggleCustomUlam(el, type){
    const name = el.dataset.name, price = parseFloat(el.dataset.price), productID = parseInt(el.dataset.productid) || 0;
    let list = type === 'meat' ? selectedCustomMeat : selectedCustomGulay;
    
    if(el.classList.contains('selected')){ 
        el.classList.remove('selected'); 
        if(type==='meat') selectedCustomMeat = selectedCustomMeat.filter(u=>u.name!==name);
        else selectedCustomGulay = selectedCustomGulay.filter(u=>u.name!==name);
    } else {
        if(list.length < 2){ // Max 2 per category so total is max 3-4
            el.classList.add('selected'); 
            list.push({name, price, productID}); 
        } else showToast('Max 2 items per category!');
    }
    if(selectedCustomMeat.length > 0 || selectedCustomGulay.length > 0) document.getElementById('customDrinkSection').style.display='block';
    else { document.getElementById('customDrinkSection').style.display='none'; selectedCustomDrink = ''; }
    updateCustomTotal();
    document.getElementById('btnConfirmCustom').disabled = (selectedCustomMeat.length + selectedCustomGulay.length) === 0;
}
function toggleCustomDrink(el){ document.querySelectorAll('#customDrinkSelection .modal-img-option').forEach(e=>e.classList.remove('selected')); el.classList.add('selected'); selectedCustomDrink = { name: el.dataset.name, price: parseFloat(el.dataset.price) }; updateCustomTotal(); }
function skipCustomDrink(){ selectedCustomDrink = ''; updateCustomTotal(); confirmCustomOrder(); }
function updateCustomTotal(){ let total = RICE_PRICE + [...selectedCustomMeat, ...selectedCustomGulay].reduce((s,u)=>s+u.price,0); if(selectedCustomDrink) total += selectedCustomDrink.price; document.getElementById('customOrderTotal').textContent='₱'+total.toFixed(2); }
function selectCustomRiceType(el){ customRiceType = el.dataset.type; document.querySelectorAll('#customOrderModal .rice-type-option').forEach(e=>e.classList.remove('selected')); el.classList.add('selected'); }
function confirmCustomOrder(){
    if((selectedCustomMeat.length + selectedCustomGulay.length)===0) return;
    let allItems = [...selectedCustomMeat, ...selectedCustomGulay];
    let total = RICE_PRICE + allItems.reduce((s,u)=>s+u.price,0);
    let desc = 'Custom Rice ('+allItems.map(u=>u.name).join(', ')+', '+(customRiceType==='fried'?'Fried Rice':'White Rice')+')';
    if(selectedCustomDrink){ desc += ' + ' + selectedCustomDrink.name; total += selectedCustomDrink.price; }
    addToCart(desc, total, 1, 0);
    if(customRiceType==='fried') deductFriedRice();
    closeModal('customOrderModal'); showToast('✅ Added!');
}

// --- BREAKFAST, QTY, AMOUNT MODALS (Keep existing logic) ---
function openBreakfastModal(){ /* ... keep existing ... */ }
function toggleBreakfastSide(el){ /* ... keep existing ... */ }
function toggleBreakfastDrink(el){ /* ... keep existing ... */ }
function skipBreakfastDrink(){ /* ... keep existing ... */ }
function updateBreakfastTotal(){ /* ... keep existing ... */ }
function selectBreakfastRice(el){ /* ... keep existing ... */ }
function confirmBreakfast(){ /* ... keep existing ... */ }

function openQtyModal(card){
    qtyModalCard = card; qtyModalName = card.dataset.name; qtyModalPrice = parseFloat(card.dataset.price); 
    qtyModalProductID = parseInt(card.dataset.productid) || 0; qtyModalQty = 1;
    document.getElementById('qtyModalTitle').textContent = qtyModalName;
    document.getElementById('qtyModalImg').src = card.dataset.img;
    document.getElementById('qtyModalPrice').textContent = '₱'+qtyModalPrice;
    document.getElementById('qtyModalQty').textContent = '1';
    document.getElementById('qtyModalTotal').textContent = '₱'+qtyModalPrice.toFixed(2);
    document.getElementById('qtyModal').classList.add('active');
}
function changeQtyModal(d){ qtyModalQty = Math.max(1, qtyModalQty+d); document.getElementById('qtyModalQty').textContent = qtyModalQty; document.getElementById('qtyModalTotal').textContent = '₱'+(qtyModalPrice*qtyModalQty).toFixed(2); }
function confirmQtyModal(){ addToCart(qtyModalName, qtyModalPrice, qtyModalQty, qtyModalProductID).then(()=>{ closeModal('qtyModal'); showToast('✅ Added!'); }); }

function openCustomAmountModal(card){
    customAmountCard = card; customAmountName = card.dataset.name; customAmountPrice = parseFloat(card.dataset.price);
    customAmountProductID = parseInt(card.dataset.productid) || 0;
    document.getElementById('customAmountTitle').textContent = customAmountName;
    document.getElementById('customAmountImg').src = card.dataset.img;
    document.getElementById('customAmountUnitPrice').textContent = '₱'+customAmountPrice+'/pc';
    document.getElementById('customAmountInput').value = customAmountPrice;
    updateCustomAmountPreview();
    document.getElementById('customAmountModal').classList.add('active');
}
function updateCustomAmountPreview(){
    let amount = parseInt(document.getElementById('customAmountInput').value)||0;
    if(amount<customAmountPrice){ document.getElementById('customAmountPreview').textContent='Minimum is ₱'+customAmountPrice; return; }
    let qty = Math.floor(amount/customAmountPrice);
    document.getElementById('customAmountPreview').textContent = 'You will get '+qty+' pc(s) for ₱'+(qty*customAmountPrice)+'.00';
}
function confirmCustomAmount(){
    let amount = parseInt(document.getElementById('customAmountInput').value)||0;
    let qty = Math.floor(amount/customAmountPrice);
    if(qty<1){ showToast('Amount too low'); return; }
    addToCart(customAmountName, customAmountPrice, qty, customAmountProductID).then(()=>{ closeModal('customAmountModal'); showToast('✅ Added!'); });
}

document.getElementById('searchInput').addEventListener('input',function(e){
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.img-card,.rice-item').forEach(c=>{ c.style.display = c.textContent.toLowerCase().includes(term)?'':'none'; });
});
refreshBadge();
</script>
</body>
</html>