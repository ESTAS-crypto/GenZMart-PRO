// app.js
document.addEventListener("alpine:init", () => {
            // Cart Store - Implementasi Alpine.js store untuk cart
            Alpine.store("cart", {
                items: [],
                total: 0,
                quantity: 0,

                // Initialize cart dari localStorage jika ada
                init() {
                    const savedCart = localStorage.getItem('cart');
                    if (savedCart) {
                        const cartData = JSON.parse(savedCart);
                        this.items = cartData.items || [];
                        this.total = cartData.total || 0;
                        this.quantity = cartData.quantity || 0;
                    }
                },

                // Save cart ke localStorage setiap ada perubahan
                saveCart() {
                    localStorage.setItem('cart', JSON.stringify({
                        items: this.items,
                        total: this.total,
                        quantity: this.quantity
                    }));
                },

                // Add item ke cart
                add(item) {
                    console.log('Adding item:', item); // Debug
                    if (!item || !item.id) {
                        console.error('Invalid item data:', item);
                        showNotification('Data item tidak valid', 'error');
                        return false;
                    }

                    try {
                        const cartItem = this.items.find(i => i.id === item.id);
                        const menuItem = this.findMenuDataItem(item.id);

                        if (!menuItem) {
                            console.error('Item not found in menuData:', item.id);
                            showNotification('Item tidak ditemukan', 'error');
                            return false;
                        }

                        if (menuItem.stock <= 0) {
                            showNotification('Stok habis', 'error');
                            return false;
                        }

                        const currentQuantity = cartItem ? cartItem.quantity : 0;
                        if (currentQuantity >= menuItem.stock) {
                            showNotification('Stok tidak mencukupi', 'error');
                            return false;
                        }

                        if (cartItem) {
                            cartItem.quantity++;
                            cartItem.total = cartItem.price * cartItem.quantity;
                        } else {
                            this.items.push({
                                id: item.id,
                                name: item.name,
                                price: parseFloat(item.price),
                                img: item.img,
                                quantity: 1,
                                total: parseFloat(item.price),
                                stock: parseInt(menuItem.stock)
                            });
                        }

                        this.quantity++;
                        this.total += parseFloat(item.price);
                        this.updateStockDisplay(item.id);
                        this.saveCart();
                        showNotification('Item berhasil ditambahkan ke keranjang', 'success');
                        return true;

                    } catch (error) {
                        console.error('Error adding item to cart:', error);
                        showNotification('Gagal menambahkan item ke keranjang', 'error');
                        return false;
                    }
                },

                // Remove item dari cart
                remove(id) {
                    const cartItem = this.items.find(item => item.id === id);
                    if (!cartItem) return;

                    if (cartItem.quantity > 1) {
                        cartItem.quantity--;
                        cartItem.total = cartItem.price * cartItem.quantity;
                    } else {
                        this.items = this.items.filter(item => item.id !== id);
                    }

                    this.quantity--;
                    this.total -= cartItem.price;
                    this.updateStockDisplay(id);
                    this.saveCart();
                },

                // Remove all quantities dari item
                removeAll(id) {
                    const cartItem = this.items.find(item => item.id === id);
                    if (!cartItem) return;

                    this.quantity -= cartItem.quantity;
                    this.total -= cartItem.total;
                    this.items = this.items.filter(item => item.id !== id);
                    this.updateStockDisplay(id);
                    this.saveCart();
                },

                // Clear cart
                clear() {
                    const previousItems = [...this.items];
                    this.items = [];
                    this.total = 0;
                    this.quantity = 0;
                    this.saveCart();
                    previousItems.forEach(item => {
                        this.updateStockDisplay(item.id);
                    });
                    showNotification('Keranjang berhasil dikosongkan', 'success');
                },

                // Find item di menuData
                findMenuDataItem(itemId) {
                    if (!window.menuData) return null;

                    for (const categoryItems of Object.values(window.menuData)) {
                        const item = categoryItems.find(item => parseInt(item.id) === parseInt(itemId));
                        if (item) return item;
                    }
                    return null;
                },

                // Update display stock
                updateStockDisplay(itemId) {
                    const menuItem = this.findMenuDataItem(itemId);
                    if (!menuItem) return;

                    const cartItem = this.items.find(item => item.id === itemId);
                    const currentQuantity = cartItem ? cartItem.quantity : 0;
                    const remainingStock = menuItem.stock - currentQuantity;

                    document.querySelectorAll(`[data-item-id="${itemId}"]`).forEach(el => {
                        const stockDisplay = el.querySelector(".stock-status");
                        const addButton = el.querySelector('.product-icons a[href="#"]:first-child');

                        if (stockDisplay) {
                            if (remainingStock <= 0) {
                                stockDisplay.innerHTML = '<span class="out-of-stock">Stok Habis</span>';
                                if (addButton) {
                                    addButton.style.display = 'none';
                                    addButton.classList.add('disabled');
                                }
                            } else if (remainingStock <= 5) {
                                stockDisplay.innerHTML = `<span class="low-stock">Sisa Stok: ${remainingStock}</span>`;
                                if (addButton) {
                                    addButton.style.display = 'inline-flex';
                                    addButton.classList.remove('disabled');
                                }
                            } else {
                                stockDisplay.innerHTML = `<span class="in-stock">Stok: ${remainingStock}</span>`;
                                if (addButton) {
                                    addButton.style.display = 'inline-flex';
                                    addButton.classList.remove('disabled');
                                }
                            }
                        }
                    });
                },

                // Check ketersediaan stock
                isStockAvailable(item) {
                    const menuItem = this.findMenuDataItem(item.id);
                    if (!menuItem) return false;

                    const cartItem = this.items.find(i => i.id === item.id);
                    const currentQuantity = cartItem ? cartItem.quantity : 0;
                    return currentQuantity < menuItem.stock;
                }
            });

            // Checkout Form
            Alpine.data('checkoutForm', () => ({
                            formData: {
                                name: '',
                                email: '',
                                phone: '',
                                alamat: ''
                            },
                            isCheckoutValid: false,
                            isProcessing: false,

                            init() {
                                this.loadProfileData();
                            },

                            loadProfileData() {
                                const form = document.getElementById('checkoutForm');
                                if (!form) return;

                                try {
                                    const profileData = form.dataset.userProfile;
                                    if (profileData) {
                                        const data = JSON.parse(profileData);
                                        this.formData = {
                                            name: data.full_name || '',
                                            email: data.email || '',
                                            phone: data.phone || '',
                                            alamat: data.address || ''
                                        };
                                        this.validateCheckoutForm();
                                    }
                                } catch (error) {
                                    console.error('Error loading profile:', error);
                                }
                            },

                            validateCheckoutForm() {
                                const phoneRegex = /^(\+62|62|0)8[1-9][0-9]{7,11}$/;
                                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                                this.isCheckoutValid = Boolean(
                                    this.formData.name &&
                                    this.formData.name.length >= 3 &&
                                    this.formData.email &&
                                    emailRegex.test(this.formData.email) &&
                                    this.formData.phone &&
                                    phoneRegex.test(this.formData.phone.replace(/[-\s]/g, '')) &&
                                    this.formData.alamat &&
                                    this.formData.alamat.length >= 10
                                );
                            },

                            handleCheckout() {
                                if (!this.isCheckoutValid || this.isProcessing) return;

                                const cart = Alpine.store('cart');
                                if (!cart.items.length) {
                                    showNotification('Keranjang belanja masih kosong', 'error');
                                    return;
                                }

                                this.isProcessing = true;

                                try {
                                    const message = this.formatWhatsAppMessage(cart);
                                    window.open(
                                        `https://api.whatsapp.com/send?phone=YOUR_PHONE_NUMBER&text=${encodeURIComponent(message)}`,
                                        '_blank'
                                    );

                                    cart.clear();
                                    document.querySelector('.shopping-cart').classList.remove('active');
                                    showNotification('Pesanan berhasil dibuat!', 'success');
                                } catch (error) {
                                    console.error('Checkout error:', error);
                                    showNotification('Gagal membuat pesanan', 'error');
                                } finally {
                                    this.isProcessing = false;
                                }
                            },

                            formatWhatsAppMessage(cart) {
                                return `*PESANAN BARU*\n` +
                                    `------------------\n` +
                                    `Nama: ${this.formData.name}\n` +
                                    `Email: ${this.formData.email}\n` +
                                    `No. HP: ${this.formData.phone}\n` +
                                    `Alamat: ${this.formData.alamat}\n\n` +
                                    `*Detail Pesanan:*\n${cart.items.map(item => 
                       `• ${item.name}\n   ${item.quantity}x @ ${rupiah(item.price)} = ${rupiah(item.total)}`
                   ).join('\n')}\n\n` +
                   `Total: ${rupiah(cart.total)}\n` +
                   `------------------`;
        }
    }));
});

// Global helper functions
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;

    Object.assign(notification.style, {
        position: 'fixed',
        top: '1rem',
        right: '1rem',
        padding: '1rem',
        backgroundColor: type === 'success' ? '#4CAF50' : '#f44336',
        color: 'white',
        borderRadius: '4px',
        boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
        zIndex: '10000',
        display: 'flex',
        alignItems: 'center',
        gap: '0.5rem',
        transform: 'translateX(120%)',
        transition: 'transform 0.3s ease'
    });

    document.body.appendChild(notification);
    requestAnimationFrame(() => {
        notification.style.transform = 'translateX(0)';
    });

    setTimeout(() => {
        notification.style.transform = 'translateX(120%)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function rupiah(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(number);
}

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    initializeCartUI();
    initializeEventListeners();
});

function initializeCartUI() {
    const cart = Alpine.store('cart');
    if (!cart) return;

    // Update quantity badge
    const badge = document.querySelector('.quantity-badge');
    if (badge) {
        badge.textContent = cart.quantity;
        badge.style.display = cart.quantity > 0 ? 'inline-block' : 'none';
    }

    // Update cart items display
    const cartItems = document.querySelectorAll('[data-item-id]');
    cartItems.forEach(item => {
        const itemId = parseInt(item.dataset.itemId);
        cart.updateStockDisplay(itemId);
    });
}

function initializeEventListeners() {
    // Toggle shopping cart
    const cartButton = document.querySelector('#shopping-cart-button');
    const shoppingCart = document.querySelector('.shopping-cart');
    
    if (cartButton && shoppingCart) {
        cartButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            shoppingCart.classList.toggle('active');
        });

        // Close cart when clicking outside
        document.addEventListener('click', (e) => {
            if (!shoppingCart.contains(e.target) && 
                !cartButton.contains(e.target) &&
                shoppingCart.classList.contains('active')) {
                shoppingCart.classList.remove('active');
            }
        });
    }

    // Initialize feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

// Global add to cart function
window.addToCart = function(item) {
    console.log('Adding to cart:', item); // Debug
    if (!item || !item.id) {
        console.error('Invalid item data:', item);
        showNotification('Data item tidak valid', 'error');
        return false;
    }

    const cart = Alpine.store('cart');
    return cart.add(item);
};