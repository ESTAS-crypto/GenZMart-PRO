<?php
require_once 'config.php';

// Fungsi untuk mengambil data menu dari database
function getMenuItems($category_id) {
    global $conn;
    $items = [];
    
    $sql = "SELECT * FROM items WHERE category_id = ? AND status = 'active' AND stock > 0 ORDER BY name";
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
            'stock' => (int)$row['stock']
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>GenZMart</title>

    <!--Fonts-->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,300;0,400;0,700;1,700&display=swap"
        rel="stylesheet" />

    <!--Icons-->
    <script src="https://unpkg.com/feather-icons"></script>

    <link rel="stylesheet" href="css/style.css" />

    <!--alpine-js-->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
    // Data menu dari database
    const menuData = <?php echo json_encode($menu_data); ?>;
    </script>

    <!--app alpine-->
    <script src="src/app.js" async></script>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
</head>

<body>
    <!-- navbar start -->
    <nav class="navbar" x-data>
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
            <a href="#contact" style="margin-top: 0px">CS</a>
        </div>

        <div class="navbar-extra">
            <a href="#" id="search-button"><i data-feather="search"></i></a>
            <a href="#" id="shopping-cart-button">
                <i data-feather="shopping-cart"></i>
                <span class="quantity-badge" x-show="$store.cart.quantity" x-text="$store.cart.quantity"></span>
            </a>
            <a href="#" id="hamburger-menu"><i data-feather="menu"></i></a>
        </div>

        <!-- search form start -->
        <div class="search-form">
            <input type="search" id="search-box" placeholder="search here..." />
            <label for="search-box"><i data-feather="search"></i></label>
        </div>
        <!-- search form end -->

        <!--shopping cart start-->
        <div class="shopping-cart">
            <template x-for="(item,index) in $store.cart.items" x-key="index">
                <div class="cart-items">
                    <img :src="item.img" :alt="item.name" />
                    <div class="items-detail">
                        <h3 x-text="item.name"></h3>
                        <div class="items-price">
                            <span x-text="rupiah(item.price)"></span> &times;
                            <button id="remove" @click="$store.cart.remove(item.id)">&minus;</button>
                            <span x-text="item.quantity"></span>
                            <button id="add" @click="$store.cart.add(item)">&plus;</button>
                            &equals;
                            <span x-text="rupiah(item.total)"></span>
                        </div>
                    </div>
                </div>
            </template>

            <h4 x-show="!$store.cart.items.length" style="margin-top: 1rem">
                Cart is Empty
            </h4>

            <h4 x-show="$store.cart.items.length">
                Total: <span x-text="rupiah($store.cart.total)"></span>
            </h4>

            <div class="form-container" x-show="$store.cart.items.length">
                <form action="" id="checkoutForm">
                    <input type="hidden" name="items" x-model="JSON.stringify($store.cart.items)" />
                    <input type="hidden" name="total" x-model="$store.cart.total" />
                    <h5>Customer Detail</h5>
                    <label for="name">
                        <span>Name</span>
                        <input type="text" name="name" id="name" required />
                    </label>
                    <label for="email">
                        <span>Email</span>
                        <input type="email" name="email" id="email" required />
                    </label>
                    <label for="alamat">
                        <span>Alamat</span>
                        <input type="text" name="alamat" id="alamat" required />
                    </label>
                    <label for="phone">
                        <span>Phone</span>
                        <input type="number" name="phone" id="phone" autocomplete="off" required />
                    </label>

                    <button class="checkout-button disabled" type="submit" id="checkout-button" value="checkout">
                        Checkout
                    </button>
                </form>
            </div>
        </div>
        <!--shopping cart end-->
    </nav>
    <!-- navbar end -->

    <!--hero section start -->
    <div id="partikel-js"></div>
    <section class="hero" id="home">
        <div class="mask-container">
            <main class="content">
                <h1>Selamat Datang di GenZ <span>Mart</span></h1>
                <p>
                    Belanja Semua yang Kamu Butuhkan dengan Harga Terbaik! Dari Sembako Hingga Elektronik, Semuanya Ada
                    di Sini!
                </p>
            </main>
        </div>
    </section>
    <!--hero section end -->

    <div id="par-js"></div>

    <!--about section start-->
    <section id="about" class="about">
        <h2><span>Kenali GenZ</span> Mart</h2>

        <div class="row">
            <div class="about-img">
                <img src="img/okee.jpeg" alt="Tentang Kami" />
            </div>
            <div class="content">
                <h3>Kenapa kalian harus memilih GenZ Mart?</h3>
                <p>
                    Di GenZ Mart, kami tidak hanya menyediakan sembako berkualitas, tetapi juga berbagai kebutuhan
                    lainnya, mulai dari elektronik hingga peralatan rumah tangga. Semua yang kamu butuhkan ada di sini!
                </p>
                <p>
                    Dedikasi kami adalah memberikan pengalaman belanja yang mudah dan nyaman, dengan produk-produk
                    berkualitas dan harga terbaik. Kami selalu berupaya memberikan layanan terbaik untuk setiap
                    pelanggan, kapanpun dan di manapun!
                </p>
            </div>
        </div>
    </section>
    <!--about section end-->

    <!--menu sections start-->
    <?php 
    $categories->data_seek(0); // Reset pointer kategori
    while($category = $categories->fetch_assoc()): 
        $categoryId = $category['id'];
    ?>
    <item id="menu<?php echo $categoryId; ?>" class="menu"
        x-data="{ items: menuData[<?php echo $categoryId; ?>] || [] }">
        <div id="par<?php echo $categoryId-1; ?>-js"></div>
        <h2>
            <span><?php echo substr($category['name'], 0, 4); ?></span><?php echo substr($category['name'], 4); ?>
        </h2>
        <p><?php echo htmlspecialchars($category['description']); ?></p>

        <div class="row">
            <template x-for="(item, index) in items" x-key="index">
                <div class="menu-card">
                    <div class="product-icons">
                        <a href="#" @click.prevent="$store.cart.add(item)" x-show="item.stock > 0">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <use href="img/feather-sprite.svg#shopping-cart" />
                            </svg>
                        </a>
                        <a href="#" @click.prevent="showItemDetails(item)" class="items-detail-button">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <use href="img/feather-sprite.svg#eye" />
                            </svg>
                        </a>
                    </div>

                    <img :src="item.img" :alt="item.name" class="menu-card-img" />
                    <h3 x-text="item.name" class="menu-card-title"></h3>

                    <div class="product-stars">
                        <template x-for="i in 5">
                            <svg width="24" height="24" fill="currentColor" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <use href="img/feather-sprite.svg#star" />
                            </svg>
                        </template>
                    </div>

                    <div class="menu-card-price">
                        <div class="price-amount">
                            <span x-text="rupiah(item.price)"></span>
                        </div>
                        <div class="stock-status">
                            <template x-if="item.stock <= 0">
                                <span class="out-of-stock">Stok Habis</span>
                            </template>
                            <template x-if="item.stock > 0 && item.stock <= 5">
                                <span class="low-stock">Stok Terbatas</span>
                            </template>
                        </div>
                    </div>
            </template>
        </div>
        </section>
        <?php endwhile; ?>
        <!--menu sections end-->

        <!--contact section start-->
        <section id="contact" class="contact">
            <div id="par3-js"></div>
            <h2><span>Customer</span> Service</h2>
            <p>
                Kami di GenZMart selalu siap membantu Anda dengan pertanyaan apa pun, mulai dari informasi produk hingga
                bantuan pemesanan. Hubungi tim Customer Service kami yang ramah dan profesional untuk pengalaman belanja
                yang lebih mudah.
            </p>

            <div class="row">
                <form id="contactForm">
                    <div class="input-group">
                        <i data-feather="user"></i>
                        <input type="text" id="nama" placeholder="Nama" required />
                    </div>
                    <div class="input-group">
                        <i data-feather="mail"></i>
                        <input type="email" id="email" placeholder="Email" required />
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
        <!--contact section end-->

        <!--footer start-->
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
        <!--footer end-->

        <!-- modal box items detail -->
        <div class="modal" id="items-detail-modal">
            <div class="modal-container">
                <a href="#" class="close-icon"><i data-feather="x"></i></a>
                <div class="modal-content"></div>
            </div>
        </div>
        <!-- modal box items detail end -->

        <!--Icons-->
        <script>
        feather.replace();
        </script>

        <!--javascript-->
        <script src="js/script.js"></script>
</body>

</html>