<?php
session_start();
require_once '../config.php';

// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit();
}

$userRole = $_SESSION['role'];
if ($userRole !== 'admin,GOD' && $userRole !== 'admin' && $userRole !== 'GOD') {
    header('Location: ../index.php');
    exit();
}

$adminName = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';

// Mengambil statistik untuk dashboard
try {
    $stats = [
        'total_categories' => 0,
        'active_categories' => 0,
        'total_items' => 0,
        'out_of_stock' => 0,
        'active_items' => 0,
        'to_be_replaced' => 0,
        'low_stock_items' => 0
    ];

    // Kategori statistik
    $category_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM categories";
    $cat_result = $conn->query($category_query);
    if ($cat_result && $cat_row = $cat_result->fetch_assoc()) {
        $stats['total_categories'] = $cat_row['total'];
        $stats['active_categories'] = $cat_row['active'];
    }

    // Item statistik
    $items_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'out_of_stock' OR stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN status = 'active' AND stock > 0 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'to_be_replaced' THEN 1 ELSE 0 END) as to_be_replaced,
        SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END) as low_stock
        FROM items";
    $items_result = $conn->query($items_query);
    if ($items_result && $items_row = $items_result->fetch_assoc()) {
        $stats['total_items'] = $items_row['total'];
        $stats['out_of_stock'] = $items_row['out_of_stock'];
        $stats['active_items'] = $items_row['active'];
        $stats['to_be_replaced'] = $items_row['to_be_replaced'];
        $stats['low_stock_items'] = $items_row['low_stock'];
    }

    // Mengambil items dengan stok rendah
    $low_stock_items = $conn->query("
        SELECT i.*, c.name as category_name 
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.stock > 0 AND i.stock <= 5 AND i.status = 'active'
        ORDER BY i.stock ASC
        LIMIT 5
    ");

    // Mengambil items yang akan diganti
    $to_be_replaced_items = $conn->query("
        SELECT i.*, c.name as category_name 
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.status = 'to_be_replaced'
        ORDER BY c.name, i.name
        LIMIT 5
    ");

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - GenZMart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>

<body>
    <div id="particles-js"></div>

    <div class="admin-container">
        <!-- Admin Info -->
        <div class="admin-info">
            <div class="dropdown">
                <button class="info-button">
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($adminName); ?>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-content">
                    <div>
                        <i class="fas fa-id-badge"></i>
                        Role: <?php 
                    $roleDisplay = str_replace(',admin', ' (Admin)', $userRole);
                    $roleDisplay = str_replace('admin', 'Admin', $roleDisplay);
                    $roleDisplay = str_replace(',GOD', ' (CODER)', $userRole);
                    $roleDisplay = str_replace('GOD', 'CODER👑', $roleDisplay);
                    echo htmlspecialchars($roleDisplay); 
                ?>
                    </div>
                    <div onclick="window.location.href='logout.php?redirect=menu'">
                        <i class="fas fa-store"></i> Logout ke Menu
                    </div>
                    <div onclick="window.location.href='logout.php?redirect=login'">
                        <i class="fas fa-sign-out-alt"></i> back ke Login
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <img src="../img/logo-genzmart.png" alt="GenZMart Logo">
            </div>
            <nav>
                <ul>
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="categories.php">Categories</a></li>
                    <li><a href="items.php">Items</a></li>
                    <li><a href="users.php">Users</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <h1>Dashboard</h1>

            <!-- Statistik Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-list"></i>
                    <div class="stat-content">
                        <h3>Total Kategori</h3>
                        <p class="stat-number"><?php echo $stats['total_categories']; ?></p>
                        <small><?php echo $stats['active_categories']; ?> Aktif</small>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-utensils"></i>
                    <div class="stat-content">
                        <h3>Total Menu</h3>
                        <p class="stat-number"><?php echo $stats['total_items']; ?></p>
                        <small><?php echo $stats['active_items']; ?> Aktif</small>
                    </div>
                </div>
                <div class="stat-card warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="stat-content">
                        <h3>Stok Habis</h3>
                        <p class="stat-number"><?php echo $stats['out_of_stock']; ?></p>
                        <small><?php echo $stats['low_stock_items']; ?> Stok Menipis</small>
                    </div>
                </div>
                <div class="stat-card alert">
                    <i class="fas fa-sync-alt"></i>
                    <div class="stat-content">
                        <h3>Menu Akan Diganti</h3>
                        <p class="stat-number"><?php echo $stats['to_be_replaced']; ?></p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Aksi Cepat</h2>
                <div class="action-buttons">
                    <a href="categories.php?action=add" class="btn">
                        <i class="fas fa-plus"></i> Tambah Kategori Baru
                    </a>
                    <a href="items.php?action=add" class="btn">
                        <i class="fas fa-plus"></i> Tambah Menu Baru
                    </a>
                </div>
            </div>

            <!-- Low Stock Warning -->
            <?php if ($low_stock_items && $low_stock_items->num_rows > 0): ?>
            <div class="dashboard-section warning-section">
                <h2><i class="fas fa-exclamation-circle"></i> Peringatan Stok Menipis</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Kategori</th>
                            <th>Sisa Stok</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $low_stock_items->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td>
                                <span class="warning-text"><?php echo $item['stock']; ?></span>
                            </td>
                            <td>
                                <a href="items.php?edit=<?php echo $item['id']; ?>" class="btn-smalll">
                                    Update Stok
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Menu Yang Akan Diganti -->
            <?php if ($to_be_replaced_items && $to_be_replaced_items->num_rows > 0): ?>
            <div class="dashboard-section alert-section">
                <h2><i class="fas fa-sync-alt"></i> Menu Yang Akan Diganti</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Kategori</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $to_be_replaced_items->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td>
                                <a href="items.php?edit=<?php echo $item['id']; ?>" class="btn-small">
                                    Edit
                                </a>
                                <a href="items.php?delete=<?php echo $item['id']; ?>" class="btn-small btn-danger"
                                    onclick="return confirm('Yakin ingin menghapus menu ini?')">
                                    Hapus
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js">
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        particlesJS('particles-js', {
            particles: {
                number: {
                    value: 80,
                    density: {
                        enable: true,
                        value_area: 800
                    }
                },
                color: {
                    value: "#ffffff"
                },
                shape: {
                    type: "circle"
                },
                opacity: {
                    value: 0.5,
                    random: false,
                    anim: {
                        enable: false
                    }
                },
                size: {
                    value: 3,
                    random: true
                },
                line_linked: {
                    enable: true,
                    distance: 150,
                    color: "#ffffff",
                    opacity: 0.4,
                    width: 1
                },
                move: {
                    enable: true,
                    speed: 6,
                    direction: "none",
                    random: false,
                    straight: false,
                    out_mode: "out",
                    bounce: false,
                }
            },
            interactivity: {
                detect_on: "canvas",
                events: {
                    onhover: {
                        enable: true,
                        mode: "repulse"
                    },
                    onclick: {
                        enable: true,
                        mode: "push"
                    },
                    resize: true
                }
            },
            retina_detect: true
        });
    });
    </script>
</body>

</html>