// Main initialization when document is ready
document.addEventListener('DOMContentLoaded', function() {
            // Initialize DOM elements with null checking
            const navbarNav = document.querySelector(".navbar-nav");
            const hamburgerMenu = document.querySelector("#hamburger-menu");
            const searchForm = document.querySelector(".search-form");
            const searchBox = document.querySelector("#search-box");
            const searchButton = document.querySelector("#search-button");
            const shoppingCart = document.querySelector(".shopping-cart");
            const shoppingCartButton = document.querySelector("#shopping-cart-button");
            const itemsDetailModal = document.querySelector("#items-detail-modal");
            const dropdowns = document.querySelectorAll(".dropdown");

            // Enhanced addToCart function with proper error handling and validation
            window.addToCart = function(item) {
                if (!item || !item.id) {
                    console.error('Invalid item data:', item);
                    showNotification('Data item tidak valid', 'error');
                    return false;
                }

                const cart = Alpine.store('cart');
                if (!cart) {
                    console.error('Cart store not initialized');
                    showNotification('Sistem keranjang belum siap', 'error');
                    return false;
                }

                try {
                    // Validate item stock before adding
                    const menuItem = cart.findMenuDataItem(item.id);
                    if (!menuItem) {
                        showNotification('Item tidak ditemukan', 'error');
                        return false;
                    }

                    if (menuItem.stock <= 0) {
                        showNotification('Maaf, stok habis', 'error');
                        return false;
                    }

                    const cartItem = cart.items.find(i => i.id === item.id);
                    if (cartItem && cartItem.quantity >= menuItem.stock) {
                        showNotification('Maaf, stok tidak mencukupi', 'error');
                        return false;
                    }

                    const success = cart.add({
                        id: item.id,
                        name: item.name,
                        price: parseFloat(item.price),
                        img: item.img,
                        stock: parseInt(menuItem.stock)
                    });

                    if (success) {
                        updateCartUI();
                        updateStockDisplay(item.id, menuItem.stock - (cartItem ? cartItem.quantity + 1 : 1));
                        showNotification('Item berhasil ditambahkan ke keranjang', 'success');
                        return true;
                    }
                } catch (error) {
                    console.error('Error adding item to cart:', error);
                    showNotification('Gagal menambahkan item ke keranjang', 'error');
                }
                return false;
            };

            // Function to update cart UI elements
            function updateCartUI() {
                const cart = Alpine.store('cart');
                if (!cart) return;

                // Update quantity badge
                const badge = document.querySelector('.quantity-badge');
                if (badge) {
                    badge.textContent = cart.quantity;
                    badge.style.display = cart.quantity > 0 ? 'inline-block' : 'none';
                }

                // Update cart items display
                updateCartItemsDisplay();

                // Update cart total
                updateCartTotal();
            }

            // Function to create and update cart items display
            function updateCartItemsDisplay() {
                const cartItemsContainer = document.querySelector('.shopping-cart-items');
                if (!cartItemsContainer) return;

                const cart = Alpine.store('cart');
                if (!cart) return;

                // Clear existing content
                cartItemsContainer.innerHTML = '';

                if (cart.items.length === 0) {
                    cartItemsContainer.innerHTML = `
                <div class="empty-cart">
                    <i data-feather="shopping-cart"></i>
                    <p>Keranjang belanja masih kosong</p>
                </div>
            `;
                    feather.replace();
                    return;
                }

                // Add each item to the cart display
                cart.items.forEach(item => {
                    const itemElement = createCartItemElement(item);
                    cartItemsContainer.appendChild(itemElement);
                });

                // Reinitialize Feather icons
                feather.replace();
            }

            // Function to create individual cart item element
            function createCartItemElement(item) {
                const div = document.createElement('div');
                div.className = 'cart-items';
                div.dataset.itemId = item.id;

                const menuItem = Alpine.store('cart').findMenuDataItem(item.id);
                const remainingStock = menuItem ? menuItem.stock - item.quantity : 0;

                div.innerHTML = `
            <img src="${item.img}" alt="${item.name}" onerror="this.src='img/default.jpg'" />
            <div class="items-detail">
                <h3 class="item-name">${item.name}</h3>
                <div class="items-price">
                    <div class="price-info">
                        <span class="unit-price">${rupiah(item.price)}</span>
                        <span class="quantity-controls">
                            <button class="qty-btn minus" onclick="Alpine.store('cart').remove(${item.id})">&minus;</button>
                            <span class="qty-display">${item.quantity}</span>
                            <button class="qty-btn plus" onclick="addToCart({
                                id: ${item.id},
                                name: '${item.name}',
                                price: ${item.price},
                                img: '${item.img}',
                                stock: ${item.stock}
                            })" ${remainingStock <= 0 ? 'disabled' : ''}>&plus;</button>
                        </span>
                    </div>
                    <div class="total-price">= ${rupiah(item.price * item.quantity)}</div>
                </div>
                ${remainingStock <= 5 ? `
                    <div class="stock-info">
                        <small class="stock-warning">Sisa stok: ${remainingStock}</small>
                    </div>
                ` : ''}
            </div>
            <button class="remove-item" onclick="Alpine.store('cart').removeAll(${item.id})">
                <i data-feather="x"></i>
            </button>
        `;

        return div;
    }

    // Function to update cart total
    function updateCartTotal() {
        const cart = Alpine.store('cart');
        const totalElement = document.querySelector('.cart-total');
        
        if (totalElement && cart) {
            totalElement.innerHTML = `
                <h4>Total: ${rupiah(cart.total)}</h4>
            `;
        }
    }

    // Function to update stock display
    function updateStockDisplay(itemId, remainingStock) {
        document.querySelectorAll(`[data-item-id="${itemId}"]`).forEach(el => {
            const stockDisplay = el.querySelector(".stock-status");
            const addButton = el.querySelector('.product-icons a[href="#"]');
            const detailButton = el.querySelector('.items-detail-button');

            if (stockDisplay) {
                if (remainingStock <= 0) {
                    stockDisplay.innerHTML = '<span class="out-of-stock">Stok Habis</span>';
                    if (addButton) {
                        addButton.classList.add('disabled');
                        addButton.style.display = 'none';
                    }
                } else if (remainingStock <= 5) {
                    stockDisplay.innerHTML = `<span class="low-stock">Sisa Stok: ${remainingStock}</span>`;
                    if (addButton) {
                        addButton.classList.remove('disabled');
                        addButton.style.display = 'inline-flex';
                    }
                } else {
                    stockDisplay.innerHTML = `<span class="in-stock">Stok: ${remainingStock}</span>`;
                    if (addButton) {
                        addButton.classList.remove('disabled');
                        addButton.style.display = 'inline-flex';
                    }
                }
            }
        });
    }

    // Initialize event listeners for add to cart buttons
    function initializeAddToCartButtons() {
        document.querySelectorAll('.product-icons a[href="#"]:first-child').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const menuCard = this.closest('.menu-card');
                if (!menuCard) return;

                const itemId = parseInt(menuCard.dataset.itemId);
                const categorySection = menuCard.closest('section[id^="menu"]');
                if (!categorySection) return;

                const categoryId = categorySection.id.replace('menu', '');
                
                if (window.menuData && window.menuData[categoryId]) {
                    const item = window.menuData[categoryId].find(i => i.id === itemId);
                    if (item) {
                        addToCart(item);
                    }
                }
            });
        });
    }

    // Enhanced Modal Functions
    window.showItemDetails = function(item) {
        if (!itemsDetailModal || !item) return;

        const modalContent = itemsDetailModal.querySelector(".modal-content");
        if (!modalContent) return;

        const cart = Alpine.store('cart');
        const cartItem = cart ? cart.items.find(i => i.id === item.id) : null;
        const currentQuantity = cartItem ? cartItem.quantity : 0;
        const remainingStock = item.stock - currentQuantity;

        modalContent.innerHTML = `
            <div class="modal-item-detail">
                <div class="modal-img-container">
                    <img src="${item.img}" alt="${item.name}" onerror="this.src='img/default.jpg'">
                </div>
                <div class="modal-info">
                    <h3>${item.name}</h3>
                    <p class="modal-description">${item.description || 'Tidak ada deskripsi'}</p>
                    <div class="modal-price">${rupiah(item.price)}</div>
                    <div class="stock-status">
                        ${remainingStock <= 0 ? 
                            '<span class="out-of-stock">Stok Habis</span>' :
                            remainingStock <= 5 ? 
                                `<span class="low-stock">Sisa Stok: ${remainingStock}</span>` :
                                `<span class="in-stock">Stok: ${remainingStock}</span>`
                        }
                    </div>
                    ${remainingStock > 0 ? `
                        <div class="quantity-wrapper">
                            <div class="quantity-control">
                                <button type="button" class="qty-btn minus" onclick="updateModalQuantity(-1)">&minus;</button>
                                <input type="number" id="modalItemQuantity" value="1" min="1" max="${remainingStock}"
                                       onchange="validateModalQuantity(this, ${remainingStock})">
                                <button type="button" class="qty-btn plus" onclick="updateModalQuantity(1, ${remainingStock})">&plus;</button>
                            </div>
                            <button type="button" class="modal-add-cart" onclick="addToCartFromModal(${JSON.stringify(item).replace(/"/g, '&quot;')})">
                                <i class="fas fa-shopping-cart"></i> Tambah ke Keranjang
                            </button>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;

        itemsDetailModal.style.display = "flex";
        setTimeout(() => {
            modalContent.style.opacity = "1";
            modalContent.style.transform = "translateY(0)";
        }, 10);
    };

    window.updateModalQuantity = function(change, maxStock) {
        const input = document.getElementById('modalItemQuantity');
        if (!input) return;
        
        let newValue = parseInt(input.value) + change;
        newValue = Math.max(1, Math.min(newValue, maxStock));
        input.value = newValue;
    };

    window.validateModalQuantity = function(input, maxStock) {
        if (!input) return;
        let value = parseInt(input.value);
        if (isNaN(value) || value < 1) value = 1;
        if (value > maxStock) value = maxStock;
        input.value = value;
    };

    window.addToCartFromModal = function(item) {
        const quantityInput = document.getElementById('modalItemQuantity');
        if (!quantityInput || !item) return;

        const quantity = parseInt(quantityInput.value);
        if (isNaN(quantity) || quantity < 1) return;

        const cart = Alpine.store('cart');
        if (!cart) return;

        let successful = 0;
        for (let i = 0; i < quantity; i++) {
            if (addToCart(item)) {
                successful++;
            } else {
                break;
            }
        }

        if (successful > 0) {
            if (itemsDetailModal) {
                itemsDetailModal.style.display = "none";
            }
        }
    };

    // Initialize all main event listeners
    function initializeEventListeners() {
        // Hamburger menu
        if (hamburgerMenu && navbarNav) {
            hamburgerMenu.addEventListener('click', (e) => {
                e.preventDefault();
                navbarNav.classList.toggle('active');
            });
        }

        // Search form
        if (searchButton && searchForm) {
            searchButton.addEventListener('click', (e) => {
                e.preventDefault();
                searchForm.classList.toggle('active');
                if (searchBox) searchBox.focus();
            });
        }

        // Shopping cart
        if (shoppingCartButton && shoppingCart) {
            shoppingCartButton.addEventListener('click', (e) => {
                e.preventDefault();
                shoppingCart.classList.toggle('active');
            });
        }

        // Modal close button
        const modalCloseButton = itemsDetailModal?.querySelector('.close-icon');
        if (modalCloseButton) {
            modalCloseButton.addEventListener('click', () => {
                itemsDetailModal.style.display = "none";
            });
        }

        // Close on outside click
        window.addEventListener('click', (e) => {
            if (e.target === itemsDetailModal) {
                itemsDetailModal.style.display = "none";
            }
        });

        // Close dropdowns and menus on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.navbar-nav')) {
                navbarNav?.classList.remove('active');
            }
            if (!e.target.closest('.search-form') && !e.target.matches('#search-button')) {
                searchForm?.classList.remove('active');
            }
            if (!e.target.closest('.shopping-cart') && !e.target.matches('#shopping-cart-button')) {
                shoppingCart?.classList.remove('active');
            }
        });

        // Initialize search functionality
        if (searchBox) {
            searchBox.addEventListener('input', (e) => {
                filterItems(e.target.value.toLowerCase());
            });
        }

        // Initialize dropdowns
        dropdowns.forEach(dropdown => {
            const dropdownContent = dropdown.querySelector(".dropdown-content");
            const dropdownToggle = dropdown.querySelector(".dropbtn");

            if (!dropdownContent || !dropdownToggle) return;

            // Handle click events for dropdown
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Close other dropdowns first
                dropdowns.forEach(otherDropdown => {
                    if (otherDropdown !== dropdown) {
                        const otherContent = otherDropdown.querySelector(".dropdown-content");
                        if (otherContent) {
                            otherContent.classList.remove("show");
                        }
                    }
                });

                dropdownContent.classList.toggle("show");
            });

            // Desktop hover handling
            if (window.innerWidth > 768) {
                dropdown.addEventListener('mouseenter', () => {
                    dropdownContent.style.display = "block";
                    setTimeout(() => {
                        dropdownContent.style.opacity = "1";
                        dropdownContent.style.transform = "translateY(0)";
                    }, 10);
                });
                
                dropdown.addEventListener('mouseleave', () => {
                    dropdownContent.style.opacity = "0";
                    dropdownContent.style.transform = "translateY(-10px)";
                    setTimeout(() => {
                        dropdownContent.style.display = "none";
                    }, 300);
                });
            }
        });

        // Handle user dropdown menu toggle
        window.toggleUserMenu = function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }

            const userDropdown = document.getElementById("userDropdown");
            if (userDropdown) {
                userDropdown.classList.toggle("active");

                function handleClickOutside(event) {
                    if (!userDropdown.contains(event.target) &&
                        !event.target.matches('.info-button')) {
                        userDropdown.classList.remove("active");
                        document.removeEventListener("click", handleClickOutside);
                    }
                }

                setTimeout(() => {
                    document.addEventListener("click", handleClickOutside);
                }, 0);
            }
        };
    }

    // Initialize everything when document loads
    initializeAddToCartButtons();
    initializeEventListeners();
    
    // Initialize Feather icons if available
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // Initialize particles if available
    if (typeof particlesJS !== 'undefined') {
        initParticles();
    }
});

// Currency formatter
window.rupiah = function(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(number);
};

// Enhanced notification function with animations
window.showNotification = function(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;

    // Style the notification
    Object.assign(notification.style, {
        position: 'fixed',
        top: '1rem',
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
        minWidth: '250px',
        maxWidth: '400px',
        transform: 'translateX(110%)'
    });

    document.body.appendChild(notification);

    // Animate in
    requestAnimationFrame(() => {
        notification.style.transition = 'transform 0.4s cubic-bezier(0.2, 1, 0.3, 1)';
        notification.style.transform = 'translateX(0)';
    });

    // Animate out and remove
    setTimeout(() => {
        notification.style.transform = 'translateX(110%)';
        setTimeout(() => notification.remove(), 400);
    }, 3000);
};

// Search filter function
function filterItems(searchTerm) {
    document.querySelectorAll('.menu-card').forEach(card => {
        const title = card.querySelector('.menu-card-title')?.textContent.toLowerCase() || '';
        const description = card.querySelector('.modal-description')?.textContent.toLowerCase() || '';
        
        if (title.includes(searchTerm) || description.includes(searchTerm)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// Initialize particles
function initParticles() {
    const particleContainers = ['particles-js', 'partikel-js', 'par-js', 'par1-js'];
    const config = isMobile() ? mobileParticleConfig : desktopParticleConfig;
    
    particleContainers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container && typeof particlesJS !== 'undefined') {
            try {
                particlesJS(containerId, config);
            } catch (error) {
                console.error(`Failed to initialize particles for ${containerId}:`, error);
            }
        }
    });
}

// Check if device is mobile
function isMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Particle configurations
const mobileParticleConfig = {
    particles: {
        number: { value: 30, density: { enable: true, value_area: 800 } },
        color: { value: "#ffffff" },
        shape: { type: "circle" },
        opacity: { value: 0.5, random: false },
        size: { value: 3, random: true },
        line_linked: {
            enable: true,
            distance: 150,
            color: "#ffffff",
            opacity: 0.4,
            width: 1
        },
        move: {
            enable: true,
            speed: 4,
            direction: "none",
            random: false,
            straight: false,
            out_mode: "out",
            bounce: false
        }
    },
    interactivity: {
        detect_on: "canvas",
        events: {
            onhover: { enable: false },
            onclick: { enable: true, mode: "push" },
            resize: true
        }
    },
    retina_detect: false
};

const desktopParticleConfig = {
    particles: {
        number: { value: 80, density: { enable: true, value_area: 800 } },
        color: { value: "#ffffff" },
        shape: { type: "circle" },
        opacity: { value: 0.5, random: false },
        size: { value: 5, random: true },
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
            bounce: false
        }
    },
    interactivity: {
        detect_on: "canvas",
        events: {
            onhover: { enable: true, mode: "repulse" },
            onclick: { enable: true, mode: "push" },
            resize: true
        },
        modes: {
            grab: { distance: 400, line_linked: { opacity: 1 } },
            bubble: { distance: 400, size: 40, duration: 2, opacity: 8, speed: 3 },
            repulse: { distance: 200, duration: 0.4 },
            push: { particles_nb: 4 },
            remove: { particles_nb: 2 }
        }
    },
    retina_detect: true
};