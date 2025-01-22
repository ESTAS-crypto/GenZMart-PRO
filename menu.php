<?php
require_once 'config.php';

// Inisialisasi session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fungsi format rupiah
function formatRupiah($angka) {
    if ($angka === null || $angka === '') return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Rupiah format untuk JavaScript global
function rupiah($number) {
    return formatRupiah($number);
}

// Cek autentikasi
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fungsi untuk mengambil data user
function getUserData($userId) {
    global $conn;
    $userData = null;
    
    $sql = "SELECT u.id, u.username, u.role, u.status, u.last_login,
                   p.full_name, p.profile_image, p.email as profile_email, p.phone, 
                   p.address, p.points,
                   COUNT(o.id) as pending_orders
            FROM users u 
            LEFT JOIN user_profiles p ON u.id = p.user_id 
            LEFT JOIN orders o ON u.id = o.user_id AND o.status = 'pending'
            WHERE u.id = ?
            GROUP BY u.id";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $userData = [
                'id' => $row['id'],
                'username' => $row['username'],
                'role' => $row['role'],
                'status' => $row['status'],
                'last_login' => $row['last_login'],
                'full_name' => $row['full_name'] ?? $row['username'],
                'profile_image' => $row['profile_image'],
                'email' => $_SESSION['email'] ?? $row['profile_email'],
                'phone' => $row['phone'],
                'address' => $row['address'],
                'points' => (int)$row['points'],
                'pending_orders' => (int)$row['pending_orders']
            ];
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error getting user data: " . $e->getMessage());
    }
    
    return $userData;
}

// Get user data
$userId = $_SESSION['user_id'];
$userData = getUserData($userId);

$userProfileData = [
    'full_name' => $userData['full_name'] ?? '',
    'email' => $userData['email'] ?? '',
    'phone' => $userData['phone'] ?? '',
    'address' => $userData['address'] ?? ''
];

// Penanganan username dan user data
$userName = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$userRole = $_SESSION['role'] ?? '';

// Format tampilan role
function formatRoleDisplay($role) {
    if (is_array($role)) {
        $roles = array_map(function($r) {
            switch(trim($r)) {
                case 'admin': return 'Admin';
                case 'GOD': return 'CODER👑';
                case 'customer': return 'Customer';
                default: return ucfirst($r);
            }
        }, $role);
        return implode(' | ', $roles);
    } else {
        switch($role) {
            case 'admin': return 'Admin';
            case 'GOD': return 'CODER👑';
            case 'customer': return 'Customer';
            default: return ucfirst($role);
        }
    }
}

// Fungsi untuk mengambil data menu dari database
function getMenuItems($category_id) {
    global $conn;
    $items = [];
    
    $sql = "SELECT * FROM items WHERE category_id = ? AND status = 'active' ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'img' => $row['image_url'],
            'price' => (float)$row['price'],
            'description' => $row['description'],
            'stock' => (int)$row['stock'],
            'category_id' => $category_id
        ];
    }
    
    return $items;
}

// Ambil data kategori aktif
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY id");

// Simpan data menu ke dalam JavaScript
$menu_data = [];
while($category = $categories->fetch_assoc()) {
    $menu_data[$category['id']] = getMenuItems($category['id']);
}

// Reset pointer kategori untuk penggunaan di HTML
$categories->data_seek(0);

$displayRole = $userRole ? formatRoleDisplay($userRole) : 'Customer';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>GenZMart</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,300;0,400;0,700;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Styles -->
    <link rel="stylesheet" href="css/style.css" />

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.12.0/dist/cdn.min.js"></script>

    <!-- Initialize menu data -->
    <script>
    const menuData = <?= json_encode($menu_data) ?>;
    window.rupiah = function(number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number);
    };
    </script>

    <!-- Custom scripts -->
    <script src="js/script.js"></script>
    <script src="src/app.js"></script>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body>
    <!-- User Info Section -->
    <?php if (!empty($userName)): ?>
    <div class="user-info-section">
        <div class="admin-info">
            <button class="info-button" onclick="toggleUserMenu()">
                <i class="fas fa-user"></i>
                <?= $userName ?>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu" id="userDropdown">
                <div class="user-role">
                    <i class="fas fa-id-badge"></i>
                    Role: <?= $displayRole ?>
                </div>

                <?php if (in_array(strtolower($userRole), ['admin', 'god'])): ?>
                <!-- Menu Khusus Admin -->
                <div onclick="window.location.href='admin/dashboard.php'">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard Admin</span>
                </div>
                <div onclick="window.location.href='admin/categories.php'">
                    <i class="fas fa-box"></i>
                    <span>Kelola kategori</span>
                </div>
                <div onclick="window.location.href='admin/items.php'">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Kelola menu</span>
                </div>
                <div onclick="window.location.href='admin/users.php'">
                    <i class="fas fa-users"></i>
                    <span>Kelola Users</span>
                </div>
                <?php endif; ?>

                <!-- Menu untuk Semua User -->
                <div onclick="window.location.href='profile.php'">
                    <i class="fas fa-user-cog"></i>
                    <span>Edit Profil</span>
                </div>
                <div onclick="window.location.href='orders.php'">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Histori pesanan</span>
                    <?php if (!empty($userData['pending_orders'])): ?>
                    <span class="badge"><?= $userData['pending_orders'] ?></span>
                    <?php endif; ?>
                </div>

                <?php if (isset($userData['points'])): ?>
                <div class="points-info">
                    <i class="fas fa-star"></i>
                    <span>Points: <?= number_format($userData['points']) ?></span>
                </div>
                <?php endif; ?>

                <!-- Logout -->
                <div onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navbar -->
    <nav class="navbar">
        <div id="particles-js"></div>
        <a href="#" class="navbar-logo">GenZ<span>Mart</span></a>

        <div class="navbar-nav">
            <a href="#home">Home</a>
            <a href="#about">Tentang kami</a>
            <div class="dropdown">
                <a href="#" class="dropbtn">Kategori</a>
                <div class="dropdown-content">
                    <?php while($category = $categories->fetch_assoc()): ?>
                    <a href="#menu<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></a>
                    <?php endwhile; ?>
                </div>
            </div>
            <a href="#contact">pelayanan pelanggan</a>
        </div>


        <div class="navbar-extra">
            <a href="#" id="search-button"><i data-feather="search"></i></a>
            <a href="#" id="shopping-cart-button">
                <i data-feather="shopping-cart"></i>
                <span class="quantity-badge" x-show="$store.cart.quantity" x-text="$store.cart.quantity"></span>
            </a>
            <a href="#" id="hamburger-menu"><i data-feather="menu"></i></a>
        </div>
        <!-- Search Form -->
        <div class="search-form">
            <input type="search" id="search-box" placeholder="search here..." />
            <label for="search-box"><i data-feather="search"></i></label>
        </div>

        <!-- Shopping Cart -->
        <div class="shopping-cart">
            <div class="cart-header">
                <h3>Shopping Cart</h3>
                <button class="clear-cart-btn" @click="$store.cart.clear()" x-show="$store.cart.items.length">
                    <i data-feather="trash-2"></i> Clear Cart
                </button>
            </div>

            <div class="shopping-cart-items">
                <!-- Empty cart message -->
                <div class="empty-cart" x-show="!$store.cart.items.length">
                    <i data-feather="shopping-cart"></i>
                    <p>Keranjang belanja masih kosong</p>
                </div>

                <!-- Cart items -->
                <template x-for="(item,index) in $store.cart.items" :key="index">
                    <div class="cart-items" :data-item-id="item.id">
                        <img :src="item.img" :alt="item.name" onerror="this.src='img/default.jpg'" />
                        <div class="items-detail">
                            <h3 class="item-name" x-text="item.name"></h3>
                            <div class="items-price">
                                <div class="price-info">
                                    <span class="unit-price" x-text="rupiah(item.price)"></span>
                                    <span class="quantity-controls">
                                        <button class="qty-btn minus"
                                            @click="$store.cart.remove(item.id)">&minus;</button>
                                        <span class="qty-display" x-text="item.quantity"></span>
                                        <button class="qty-btn plus" @click="$store.cart.add(item)"
                                            :disabled="!$store.cart.isStockAvailable(item)">&plus;</button>
                                    </span>
                                </div>
                                <div class="total-price">
                                    = <span x-text="rupiah(item.price * item.quantity)"></span>
                                </div>
                            </div>
                        </div>
                        <button class="remove-item" @click="$store.cart.removeAll(item.id)">
                            <i data-feather="x"></i>
                        </button>
                    </div>
                </template>
            </div>

            <!-- Cart total -->
            <div class="cart-total" x-show="$store.cart.items.length">
                <h4>Total: <span x-text="rupiah($store.cart.total)"></span></h4>
            </div>

            <!-- Checkout form -->
            <div class="checkout-section" x-show="$store.cart.items.length">
                <form id="checkoutForm" class="checkout-form" x-data="checkoutForm()" @submit.prevent="handleCheckout"
                    data-user-profile='<?= htmlspecialchars(json_encode($userProfileData), ENT_QUOTES, 'UTF-8') ?>'>
                    <h5>Data Pemesan</h5>

                    <div class="form-group">
                        <label for="name">
                            <i data-feather="user"></i>
                            <span>Nama</span>
                        </label>
                        <input type="text" id="name" name="name" required x-model="formData.name"
                            @input="validateCheckoutForm" />
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i data-feather="mail"></i>
                            <span>Email</span>
                        </label>
                        <input type="email" id="email" name="email" required x-model="formData.email"
                            @input="validateCheckoutForm" />
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            <i data-feather="phone"></i>
                            <span>No. HP</span>
                        </label>
                        <input type="tel" id="phone" name="phone" required x-model="formData.phone"
                            @input="validateCheckoutForm" />
                    </div>

                    <div class="form-group">
                        <label for="alamat">
                            <i data-feather="map-pin"></i>
                            <span>Alamat</span>
                        </label>
                        <textarea id="alamat" name="alamat" required x-model="formData.alamat"
                            @input="validateCheckoutForm"></textarea>
                    </div>

                    <button type="submit" class="checkout-button" :class="{ 'disabled': !isCheckoutValid }"
                        :disabled="!isCheckoutValid || isProcessing">
                        <i data-feather="shopping-bag"></i>
                        <span x-text="isProcessing ? 'Memproses...' : 'Checkout via WhatsApp'"></span>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="mask-container">
            <main class="content">
                <h1>Selamat Datang di GenZ <span>Mart</span></h1>
                <p>Belanja Semua yang Kamu Butuhkan dengan Harga Terbaik! Dari Sembako Hingga Elektronik, Semuanya
                    Ada di Sini!</p>
            </main>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <h2><span>Kenali GenZ</span> Mart</h2>
        <div class="row">
            <div class="about-img">
                <img src="img/okee.jpeg" alt="Tentang Kami" />
            </div>
            <div class="content">
                <h3>Kenapa kalian harus memilih GenZ Mart?</h3>
                <p>Di GenZ Mart, kami tidak hanya menyediakan sembako berkualitas, tetapi juga berbagai kebutuhan
                    lainnya, mulai dari elektronik hingga peralatan rumah tangga. Semua yang kamu butuhkan ada di
                    sini!</p>
                <p>Dedikasi kami adalah memberikan pengalaman belanja yang mudah dan nyaman, dengan produk-produk
                    berkualitas dan harga terbaik. Kami selalu berupaya memberikan layanan terbaik untuk setiap
                    pelanggan, kapanpun dan di manapun!</p>
            </div>
        </div>
    </section>

    <!-- Menu Sections -->
    <?php 
    $categories->data_seek(0);
    while($category = $categories->fetch_assoc()): 
        $categoryId = $category['id'];
    ?>
    <!-- Menu Section in menu.php -->
    <section id="menu<?= $categoryId ?>" class="menu">
        <h2><span><?= substr($category['name'], 0, 4) ?></span><?= substr($category['name'], 4) ?></h2>
        <p><?= htmlspecialchars($category['description']) ?></p>

        <div class="row">
            <?php foreach(getMenuItems($categoryId) as $item): ?>
            <div class="menu-card" data-item-id="<?= $item['id'] ?>">
                <div class="product-icons">
                    <?php if ($item['stock'] > 0): ?>
                    <a href="#" onclick="return addToCart({
                        id: <?= $item['id'] ?>,
                        name: '<?= addslashes($item['name']) ?>',
                        price: <?= $item['price'] ?>,
                        img: '<?= addslashes($item['img']) ?>',
                        stock: <?= $item['stock'] ?>,
                        description: '<?= addslashes($item['description']) ?>'
                    })">
                        <i data-feather="shopping-cart"></i>
                    </a>
                    <?php endif; ?>
                    <a href="#" onclick="return showItemDetails({
                        id: <?= $item['id'] ?>,
                        name: '<?= addslashes($item['name']) ?>',
                        price: <?= $item['price'] ?>,
                        img: '<?= addslashes($item['img']) ?>',
                        stock: <?= $item['stock'] ?>,
                        description: '<?= addslashes($item['description']) ?>'
                    })">
                        <i data-feather="eye"></i>
                    </a>
                </div>

                <img src="<?= $item['img'] ?>" alt="<?= $item['name'] ?>" class="menu-card-img"
                    onerror="this.src='img/default.jpg'" />
                <h3 class="menu-card-title"><?= $item['name'] ?></h3>

                <div class="product-stars">
                    <?php for($i = 0; $i < 5; $i++): ?>
                    <i data-feather="star" class="star-full"></i>
                    <?php endfor; ?>
                </div>

                <div class="menu-card-price">
                    <div class="price-amount"><?= rupiah($item['price']) ?></div>
                    <div class="stock-status">
                        <?php if ($item['stock'] <= 0): ?>
                        <span class="out-of-stock">Stok Habis</span>
                        <?php elseif ($item['stock'] <= 5): ?>
                        <span class="low-stock">Sisa Stok: <?= $item['stock'] ?></span>
                        <?php else: ?>
                        <span class="in-stock">Stok: <?= $item['stock'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endwhile; ?>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <h2><span>pelayanan</span> pelanggan</h2>
        <p>Kami di GenZMart selalu siap membantu Anda dengan pertanyaan apa pun, mulai dari informasi produk hingga
            bantuan pemesanan. Hubungi tim Customer Service kami yang ramah dan profesional untuk pengalaman belanja
            yang lebih mudah.</p>

        <div class="row">
            <form id="contactForm">
                <div class="input-group">
                    <i data-feather="user"></i>
                    <input type="text" id="contact-nama" placeholder="Nama" required />
                </div>
                <div class="input-group">
                    <i data-feather="mail"></i>
                    <input type="email" id="contact-email" placeholder="Email" required />
                </div>
                <div class="input-group2">
                    <i data-feather="send"></i>
                    <textarea id="pesan" placeholder="Pesan" required></textarea>
                </div>
                <button class="btn" type="submit" id="sendEmail">
                    Kirim Pesan
                </button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="socials">
            <a href="#"><i data-feather="instagram"></i></a>
            <a href="#"><i data-feather="phone"></i></a>
            <a href="#"><i data-feather="facebook"></i></a>
        </div>

        <div class="links">
            <a href="#home">Home</a>
            <a href="#about">Tentang Kami</a>
            <a href="#contact">Kontak</a>
        </div>

        <div class="credit">
            <p>Created by <a href="">GenZMart</a> | &copy; 2024.</p>
        </div>
    </footer>

    <!-- Item Detail Modal -->
    <div class="modal" id="items-detail-modal">
        <div class="modal-container">
            <button class="close-icon"><i data-feather="x"></i></button>
            <div class="modal-content"></div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const navbarNav = document.querySelector('.navbar-nav');
        const hamburgerMenu = document.querySelector('#hamburger-menu');
        const searchForm = document.querySelector('.search-form');
        const searchBox = document.querySelector('#search-box');
        const searchButton = document.querySelector('#search-button');
        const shoppingCart = document.querySelector('.shopping-cart');
        const shoppingCartButton = document.querySelector('#shopping-cart-button');

        // Toggle class active untuk hamburger menu
        document.querySelector('#hamburger-menu').onclick = (e) => {
            e.preventDefault();
            navbarNav.classList.toggle('active');
        };

        // Toggle class active untuk search form
        document.querySelector('#search-button').onclick = (e) => {
            e.preventDefault();
            searchForm.classList.toggle('active');
            searchBox.focus();
        };

        // Toggle shopping cart
        document.querySelector('#shopping-cart-button').onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            shoppingCart.classList.toggle('active');
        };

        // Tutup cart saat klik di luar
        document.addEventListener('click', function(e) {
            if (!shoppingCart.contains(e.target) &&
                !shoppingCartButton.contains(e.target) &&
                shoppingCart.classList.contains('active')) {
                shoppingCart.classList.remove('active');
            }
        });

        // Klik di luar hamburger menu
        document.addEventListener('click', function(e) {
            if (!hamburgerMenu.contains(e.target) && !navbarNav.contains(e.target)) {
                navbarNav.classList.remove('active');
            }
            if (!searchButton.contains(e.target) && !searchForm.contains(e.target)) {
                searchForm.classList.remove('active');
            }
        });
    });
    </script>
</body>

</html>