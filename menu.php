<?php
require_once 'config.php';

// Inisialisasi session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    const menuData = <?php echo json_encode($menu_data); ?>;
    window.rupiah = (number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number);
    };
    </script>

    <!-- Custom scripts -->
    <script src="src/app.js"></script>
    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
</head>

<body>
    <!-- User Info Section -->
    <?php if (!empty($userName)): ?>
    <div class="user-info-section">
        <div class="admin-info">
            <button class="info-button" onclick="toggleUserMenu()">
                <i class="fas fa-user"></i>
                <?php echo $userName; ?>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu" id="userDropdown">
                <div class="user-role">
                    <i class="fas fa-id-badge"></i>
                    Role: <?php echo $displayRole; ?>
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
                    <span class="badge"><?php echo $userData['pending_orders']; ?></span>
                    <?php endif; ?>
                </div>

                <?php if (isset($userData['points'])): ?>
                <div class="points-info">
                    <i class="fas fa-star"></i>
                    <span>Points: <?php echo number_format($userData['points']); ?></span>
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
                    <a href="#menu<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></a>
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
                <template x-for="(item,index) in $store.cart.items" :key="index">
                    <div class="cart-items" :data-item-id="item.id">
                        <img :src="item.img" :alt="item.name" />
                        <div class="items-detail">
                            <h3 class="item-name" x-text="item.name"></h3>
                            <div class="items-price">
                                <div class="price-info">
                                    <span class="unit-price" x-text="rupiah(item.price)"></span>
                                    <span class="quantity-controls">
                                        <button class="qty-btn" @click="$store.cart.remove(item.id)">&minus;</button>
                                        <span class="qty-display" x-text="item.quantity"></span>
                                        <button class="qty-btn" @click="$store.cart.add(item)"
                                            :disabled="!$store.cart.isStockAvailable(item)"
                                            :class="{ 'disabled': !$store.cart.isStockAvailable(item) }">
                                            &plus;
                                        </button>
                                    </span>
                                </div>
                                <div class="total-price">
                                    = <span x-text="rupiah(item.total)"></span>
                                </div>
                            </div>
                            <div class="stock-info" x-show="item.stock <= 5">
                                <small class="stock-warning">
                                    Sisa stok: <span x-text="item.stock - item.quantity"></span>
                                </small>
                            </div>
                        </div>
                        <button class="remove-item" @click="$store.cart.removeAll(item.id)">
                            <i data-feather="x"></i>
                        </button>
                    </div>
                </template>

                <!-- Empty cart message -->
                <div class="empty-cart" x-show="!$store.cart.items.length">
                    <i data-feather="shopping-cart"></i>
                    <p>Keranjang belanja masih kosong</p>
                </div>
            </div>

            <!-- Cart total -->
            <div class="cart-total" x-show="$store.cart.items.length">
                <h4>Total: <span x-text="rupiah($store.cart.total)"></span></h4>
            </div>

            <!-- Checkout form -->
            <div class="checkout-section" x-show="$store.cart.items.length">
                <form id="checkoutForm" class="checkout-form" x-data="checkoutForm()" @submit.prevent="handleCheckout"
                    data-user-profile='<?php echo htmlspecialchars(json_encode($userProfileData), ENT_QUOTES, 'UTF-8'); ?>'>
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
    <section id="menu<?php echo $categoryId; ?>" class="menu"
        x-data="{ items: menuData[<?php echo $categoryId; ?>] || [] }">
        <h2><span><?php echo substr($category['name'], 0, 4); ?></span><?php echo substr($category['name'], 4); ?></h2>
        <p><?php echo htmlspecialchars($category['description']); ?></p>

        <div class="row">
            <template x-for="item in items" :key="item.id">
                <div class="menu-card" :data-item-id="item.id">
                    <div class="product-icons">
                        <a href="#" @click.prevent="$store.cart.add(item)" x-show="item.stock > 0"
                            :class="{ 'disabled': !$store.cart.isStockAvailable(item) }">
                            <i data-feather="shopping-cart"></i>
                        </a>
                        <a href="#" @click.prevent="showItemDetails(item)" class="items-detail-button">
                            <i data-feather="eye"></i>
                        </a>
                    </div>

                    <img :src="item.img" :alt="item.name" class="menu-card-img" onerror="this.src='img/default.jpg'" />
                    <h3 class="menu-card-title" x-text="item.name"></h3>

                    <div class="product-stars">
                        <template x-for="i in 5">
                            <i data-feather="star" class="star-full"></i>
                        </template>
                    </div>

                    <div class="menu-card-price">
                        <div class="price-amount" x-text="rupiah(item.price)"></div>
                        <div class="stock-status">
                            <template x-if="item.stock <= 0">
                                <span class="out-of-stock">Stok Habis</span>
                            </template>
                            <template x-if="item.stock > 0 && item.stock <= 5">
                                <span class="low-stock">Sisa Stok: <span x-text="item.stock"></span></span>
                            </template>
                            <template x-if="item.stock > 5">
                                <span class="in-stock">Stok: <span x-text="item.stock"></span></span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
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
            <p>Created By <a href="">GenZMart</a> | &copy; 2024.</p>
        </div>
    </footer>

    <!-- Item Detail Modal -->
    <div class="modal" id="items-detail-modal">
        <div class="modal-container">
            <button class="close-icon"><i data-feather="x"></i></button>
            <div class="modal-content"></div>
        </div>
    </div>

    <!-- Initialize Cart Store -->
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('cart', {
            items: [],
            quantity: 0,
            total: 0,

            findMenuDataItem(itemId) {
                for (const categoryItems of Object.values(menuData)) {
                    const item = categoryItems.find(item => item.id === itemId);
                    if (item) return item;
                }
                return null;
            },

            isStockAvailable(item) {
                if (!item?.id) return false;
                const cartItem = this.items.find(i => i.id === item.id);
                const currentQuantity = cartItem?.quantity || 0;
                const menuItem = this.findMenuDataItem(item.id);
                return menuItem && menuItem.stock > currentQuantity;
            },

            add(item) {
                if (!this.isStockAvailable(item)) {
                    showNotification('Stok tidak mencukupi', 'error');
                    return false;
                }

                const existingItem = this.items.find(i => i.id === item.id);
                if (existingItem) {
                    existingItem.quantity++;
                    existingItem.total = existingItem.quantity * existingItem.price;
                } else {
                    this.items.push({
                        ...item,
                        quantity: 1,
                        total: item.price
                    });
                }

                this.updateTotals();
                showNotification('Item berhasil ditambahkan ke keranjang', 'success');
                return true;
            },

            remove(itemId) {
                const itemIndex = this.items.findIndex(i => i.id === itemId);
                if (itemIndex === -1) return;

                if (this.items[itemIndex].quantity > 1) {
                    this.items[itemIndex].quantity--;
                    this.items[itemIndex].total = this.items[itemIndex].price * this.items[itemIndex]
                        .quantity;
                } else {
                    this.items.splice(itemIndex, 1);
                }

                this.updateTotals();
            },

            removeAll(itemId) {
                this.items = this.items.filter(i => i.id !== itemId);
                this.updateTotals();
            },

            clear() {
                this.items = [];
                this.updateTotals();
                showNotification('Keranjang telah dikosongkan', 'success');
            },

            updateTotals() {
                this.quantity = this.items.reduce((sum, item) => sum + item.quantity, 0);
                this.total = this.items.reduce((sum, item) => sum + item.total, 0);
            }
        });
    });

    // Show notification
    window.showNotification = function(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;

        Object.assign(notification.style, {
            position: 'fixed',
            top: '4.5rem',
            right: '1rem',
            zIndex: '9999',
            padding: '1rem',
            borderRadius: '0.5rem',
            backgroundColor: type === 'success' ? '#4CAF50' : '#f44336',
            color: 'white',
            boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            transform: 'translateX(110%)',
            transition: 'transform 0.3s ease'
        });

        document.body.appendChild(notification);
        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(0)';
        });

        setTimeout(() => {
            notification.style.transform = 'translateX(110%)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    };

    // Initialize Feather icons
    document.addEventListener('DOMContentLoaded', function() {
        feather.replace();
        if (typeof particlesJS !== 'undefined') {
            initParticles();
        }
    });
    </script>
</body>

</html>