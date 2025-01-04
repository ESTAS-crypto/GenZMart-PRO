"use strict";

// Inisialisasi ketika dokumen siap
document.addEventListener("alpine:init", () => {
    // Store Cart
    Alpine.store("cart", {
        items: [],
        total: 0,
        quantity: 0,

        // Cek ketersediaan stok
        isStockAvailable(item) {
            if (!item || !item.id) return false;
            const cartItem = this.items.find(i => i.id === item.id);
            const currentQuantity = cartItem ? cartItem.quantity : 0;
            const menuItem = this.findMenuDataItem(item.id);
            const availableStock = menuItem ? menuItem.stock : 0;
            return availableStock > currentQuantity;
        },

        // Mencari item di menuData
        findMenuDataItem(itemId) {
            if (typeof menuData === 'undefined' || !menuData) {
                return null;
            }
            for (const categoryId in menuData) {
                const item = menuData[categoryId].find(item => item.id === itemId);
                if (item) return item;
            }
            return null;
        },

        // Update stok ke server
        async updateStock() {
            try {
                if (!this.items.length) return true;

                const response = await fetch("menu.php?action=update_stock", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: JSON.stringify({
                        items: this.items.map(item => ({
                            id: item.id,
                            quantity: item.quantity,
                            price: item.price
                        }))
                    })
                });

                if (!response.ok) {
                    throw new Error("Network response was not ok");
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.message || "Gagal memperbarui stok");
                }

                // Update local menu data
                this.items.forEach(item => {
                    const menuItem = this.findMenuDataItem(item.id);
                    if (menuItem) {
                        menuItem.stock = Math.max(0, menuItem.stock - item.quantity);
                    }
                });

                return true;
            } catch (error) {
                console.error("Stock update failed:", error);
                showNotification(error.message || "Gagal memperbarui stok", "error");
                return false;
            }
        },

        // Tambah item ke keranjang
        add(newItem) {
            if (!newItem || !newItem.id) {
                showNotification("Item tidak valid", "error");
                return false;
            }

            const menuItem = this.findMenuDataItem(newItem.id);
            if (!menuItem) {
                showNotification("Item tidak ditemukan", "error");
                return false;
            }

            const cartItem = this.items.find(item => item.id === newItem.id);
            const currentQuantity = cartItem ? cartItem.quantity : 0;

            if (menuItem.stock <= 0) {
                showNotification("Maaf, stok habis", "error");
                return false;
            }

            if (currentQuantity >= menuItem.stock) {
                showNotification("Maaf, stok tidak mencukupi", "error");
                return false;
            }

            if (!cartItem) {
                this.items.push({
                    id: newItem.id,
                    name: newItem.name,
                    price: newItem.price,
                    img: newItem.img,
                    quantity: 1,
                    total: newItem.price,
                    stock: menuItem.stock
                });
            } else {
                cartItem.quantity++;
                cartItem.total = cartItem.quantity * cartItem.price;
            }

            this.quantity++;
            this.total += newItem.price;
            this.updateStockDisplay(newItem.id, menuItem.stock - (currentQuantity + 1));
            showNotification("Item berhasil ditambahkan ke keranjang", "success");
            return true;
        },

        // Hapus 1 quantity item dari keranjang
        remove(itemId) {
            const cartItem = this.items.find(item => item.id === itemId);
            if (!cartItem) return;

            const menuItem = this.findMenuDataItem(itemId);
            if (!menuItem) return;

            if (cartItem.quantity > 1) {
                cartItem.quantity--;
                cartItem.total = cartItem.quantity * cartItem.price;
            } else {
                this.items = this.items.filter(item => item.id !== itemId);
            }

            this.quantity--;
            this.total -= cartItem.price;

            const currentCartItem = this.items.find(item => item.id === itemId);
            const currentCartQuantity = currentCartItem ? currentCartItem.quantity : 0;
            this.updateStockDisplay(itemId, menuItem.stock - currentCartQuantity);
        },

        // Hapus semua quantity item dari keranjang
        removeAll(itemId) {
            const cartItem = this.items.find(item => item.id === itemId);
            if (!cartItem) return;

            const menuItem = this.findMenuDataItem(itemId);
            if (menuItem) {
                this.updateStockDisplay(itemId, menuItem.stock);
            }

            this.quantity -= cartItem.quantity;
            this.total -= cartItem.total;
            this.items = this.items.filter(item => item.id !== itemId);
        },

        // Update tampilan stok
        updateStockDisplay(itemId, remainingStock) {
            document.querySelectorAll(`[data-item-id="${itemId}"]`).forEach(el => {
                const stockDisplay = el.querySelector(".stock-status");
                const addButton = el.querySelector('.product-icons a[href="#"]:first-child');

                if (stockDisplay) {
                    if (remainingStock <= 0) {
                        stockDisplay.innerHTML = '<span class="out-of-stock">Stok Habis</span>';
                        if (addButton) addButton.style.display = "none";
                    } else if (remainingStock <= 5) {
                        stockDisplay.innerHTML = `<span class="low-stock">Sisa Stok: ${remainingStock}</span>`;
                        if (addButton) addButton.style.display = "inline-flex";
                    } else {
                        stockDisplay.innerHTML = `<span class="in-stock">Stok: ${remainingStock}</span>`;
                        if (addButton) addButton.style.display = "inline-flex";
                    }
                }
            });
        },

        // Kosongkan keranjang
        clear() {
            this.items.forEach(item => {
                const menuItem = this.findMenuDataItem(item.id);
                if (menuItem) {
                    this.updateStockDisplay(item.id, menuItem.stock);
                }
            });
            this.items = [];
            this.total = 0;
            this.quantity = 0;
        },

        // Format detail pesanan untuk WhatsApp
        formatOrderDetails() {
            return this.items.map(item =>
                `• ${item.name}\n   ${item.quantity}x ${rupiah(item.price)} = ${rupiah(item.total)}`
            ).join("\n");
        }
    });

    // Form Checkout
    Alpine.data("checkoutForm", () => ({
        isCheckoutValid: false,
        isProcessing: false,
        formData: {
            name: "",
            email: "",
            phone: "",
            alamat: ""
        },

        // Initialize
        async init() {
            await this.loadProfileData();
        },

        // Load profile data
        async loadProfileData() {
            try {
                const response = await fetch('get_profile.php');
                const data = await response.json();

                if (data.success && data.user) {
                    this.formData = {
                        name: data.user.full_name || '',
                        email: data.user.email || '',
                        phone: data.user.phone || '',
                        alamat: data.user.address || ''
                    };

                    this.validateCheckoutForm();
                }
            } catch (error) {
                console.error('Error loading profile:', error);
                showNotification('Gagal memuat data profil', 'error');
            }
        },

        // Validate checkout form
        validateCheckoutForm() {
            const validations = {
                name: Boolean(this.formData.name ? .trim().length >= 3),
                email: Boolean(this.formData.email ? .trim().match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)),
                phone: Boolean(this.formData.phone ? .trim().replace(/[-\s]/g, '').match(/^(\+62|62|0)8[1-9][0-9]{7,11}$/)),
                alamat: Boolean(this.formData.alamat ? .trim().length >= 10)
            };

            this.isCheckoutValid = Object.values(validations).every(Boolean);

            Object.entries(validations).forEach(([field, isValid]) => {
                const element = document.getElementById(field);
                if (element) {
                    element.classList.toggle('invalid', !isValid);
                    element.classList.toggle('valid', isValid);
                }
            });

            return this.isCheckoutValid;
        },

        // Handle checkout
        async handleCheckout() {
            if (this.isProcessing || !this.isCheckoutValid) return;

            const cart = Alpine.store('cart');
            if (!cart.items.length) {
                showNotification('Keranjang belanja masih kosong', 'error');
                return;
            }

            this.isProcessing = true;

            try {
                const stockUpdated = await cart.updateStock();
                if (!stockUpdated) throw new Error('Gagal memperbarui stok');

                const message = this.formatWhatsAppMessage(cart);
                window.open(
                    `https://api.whatsapp.com/send?phone=62895385890629&text=${encodeURIComponent(message)}`,
                    '_blank'
                );

                await cart.clear();
                this.closeCart();
                showNotification('Pesanan berhasil dibuat!', 'success');
            } catch (error) {
                console.error('Checkout error:', error);
                showNotification(error.message || 'Gagal membuat pesanan', 'error');
            } finally {
                this.isProcessing = false;
            }
        },

        // Format WhatsApp message
        formatWhatsAppMessage(cart) {
            const now = new Date();
            const date = now.toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const time = now.toLocaleTimeString('id-ID');

            return `*PESANAN BARU GENZMART* 🛍️\n` +
                `--------------------------------\n` +
                `Tanggal: ${date}\n` +
                `Waktu: ${time}\n\n` +
                `*Data Pemesan:*\n` +
                `👤 Nama: ${this.formData.name}\n` +
                `📧 Email: ${this.formData.email}\n` +
                `📱 No. HP: ${this.formData.phone}\n` +
                `📍 Alamat: ${this.formData.alamat}\n\n` +
                `*Detail Pesanan:*\n${cart.formatOrderDetails()}\n` +
                `--------------------------------\n` +
                `*Total Pembayaran: ${rupiah(cart.total)}* 💰\n\n` +
                `Terima kasih telah berbelanja di GenZMart! 🙏✨`;
        },

        // Close cart
        closeCart() {
            const cart = document.querySelector('.shopping-cart');
            if (cart) cart.classList.remove('active');
        }
    }));
});
// Setup Event Listeners
function setupEventListeners() {
    const elements = {
        shoppingCart: document.querySelector(".shopping-cart"),
        searchForm: document.querySelector(".search-form"),
        searchBox: document.querySelector("#search-box"),
        navbar: document.querySelector(".navbar-nav"),
        modal: document.querySelector("#items-detail-modal"),
        hamburgerMenu: document.querySelector("#hamburger-menu"),
        searchButton: document.querySelector("#search-button"),
        cartButton: document.querySelector("#shopping-cart-button"),
        profileInput: document.querySelector("#profileInput"),
        previewContainer: document.querySelector("#previewContainer"),
        previewImage: document.querySelector("#previewImage"),
        profilePhotoContainer: document.querySelector("#profilePhotoContainer"),
        profileForm: document.querySelector("#profileForm")
    };

    let selectedFile = null;

    // Setup Hamburger Menu
    elements.hamburgerMenu ? .addEventListener("click", () => {
        elements.navbar ? .classList.toggle("active");
    });

    // Setup Shopping Cart Toggle
    elements.cartButton ? .addEventListener("click", (e) => {
        e.preventDefault();
        elements.shoppingCart ? .classList.toggle("active");
        elements.searchForm ? .classList.remove("active");
    });

    // Setup Search Form Toggle
    elements.searchButton ? .addEventListener("click", (e) => {
        e.preventDefault();
        elements.searchForm ? .classList.toggle("active");
        elements.shoppingCart ? .classList.remove("active");
    });

    // Setup Search Functionality
    elements.searchBox ? .addEventListener("input", (e) => {
        filterItems(e.target.value.toLowerCase());
    });

    // Setup Modal Close
    if (elements.modal) {
        const closeIcon = elements.modal.querySelector(".close-icon");
        closeIcon ? .addEventListener("click", (e) => {
            e.preventDefault();
            elements.modal.style.display = "none";
        });

        // Close modal on outside click
        window.addEventListener("click", (e) => {
            if (e.target === elements.modal) {
                elements.modal.style.display = "none";
            }
        });
    }

    // Setup Add to Cart Functionality
    document.querySelectorAll('.add-to-cart-button').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const itemCard = this.closest('.menu-card');
            if (!itemCard) return;

            const itemId = parseInt(itemCard.dataset.itemId);
            const categoryId = itemCard.closest('section[id^="menu"]').id.replace('menu', '');

            if (window.menuData && window.menuData[categoryId]) {
                const item = window.menuData[categoryId].find(i => i.id === itemId);
                if (item) {
                    const cart = Alpine.store('cart');
                    if (cart) {
                        cart.add(item);
                    }
                }
            }
        });
    });

    // Handle profile image selection
    if (elements.profileInput && elements.previewContainer && elements.previewImage) {
        elements.profileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                    showNotification('Format file harus JPG, PNG, atau GIF', 'error');
                    this.value = '';
                    return;
                }

                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showNotification('Ukuran file tidak boleh lebih dari 5MB', 'error');
                    this.value = '';
                    return;
                }

                selectedFile = file;
                const reader = new FileReader();
                reader.onload = function(e) {
                    elements.previewImage.src = e.target.result;
                    elements.previewContainer.style.display = 'flex';
                };
                reader.onerror = function() {
                    showNotification('Gagal membaca file', 'error');
                    selectedFile = null;
                    elements.profileInput.value = '';
                };
                reader.readAsDataURL(file);
            }
        });

        // Handle image confirmation
        const confirmButton = document.getElementById('confirmImage');
        if (confirmButton) {
            confirmButton.addEventListener('click', async function() {
                if (!selectedFile) return;

                const formData = new FormData();
                formData.append('profile_image', selectedFile);

                try {
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Terjadi kesalahan saat upload');
                    }

                    // Update UI with new image
                    const currentProfile = document.getElementById('currentProfile');
                    const defaultProfileIcon = document.getElementById('defaultProfileIcon');

                    if (defaultProfileIcon) {
                        defaultProfileIcon.style.display = 'none';
                    }

                    const imageUrl = `${result.image_url}?t=${new Date().getTime()}`; // Add cache buster

                    if (currentProfile) {
                        currentProfile.src = imageUrl;
                        currentProfile.style.display = 'block';
                    } else {
                        const newImg = document.createElement('img');
                        newImg.id = 'currentProfile';
                        newImg.alt = 'Profile Photo';
                        newImg.src = imageUrl;
                        newImg.setAttribute('data-profile-image', 'true');
                        elements.profilePhotoContainer.insertBefore(
                            newImg,
                            defaultProfileIcon || elements.profilePhotoContainer.firstChild
                        );
                    }

                    // Reset state
                    elements.previewContainer.style.display = 'none';
                    elements.profileInput.value = '';
                    selectedFile = null;

                    showNotification(result.message || 'Foto profil berhasil diperbarui', 'success');

                } catch (error) {
                    console.error('Error:', error);
                    showNotification(error.message || 'Gagal mengupload foto profil', 'error');
                } finally {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-check"></i> Konfirmasi';
                }
            });
        }

        // Handle preview cancellation
        document.querySelectorAll('#cancelImage, #cancelPreview, .close-preview').forEach(button => {
            button.addEventListener('click', function() {
                elements.previewContainer.style.display = 'none';
                elements.profileInput.value = '';
                selectedFile = null;
            });
        });

        // Handle form submission
        if (elements.profileForm) {
            elements.profileForm.addEventListener('submit', function(e) {
                if (selectedFile) {
                    e.preventDefault();
                    showNotification('Harap konfirmasi upload foto profil terlebih dahulu', 'error');
                    return false;
                }
            });
        }
    }
}

// Initialize event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', setupEventListeners);

// Initialize tooltips
document.querySelectorAll('[data-tooltip]').forEach(element => {
    element.addEventListener('mouseenter', function(e) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = this.dataset.tooltip;
        document.body.appendChild(tooltip);

        const rect = this.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';

        this.addEventListener('mouseleave', () => tooltip.remove());
    });
});

// Filter items based on search
window.filterItems = function(searchTerm) {
    document.querySelectorAll('.menu-card').forEach(card => {
        const title = card.querySelector('.menu-card-title').textContent.toLowerCase();
        const description = card.querySelector('.modal-description') ? .textContent.toLowerCase() || '';

        if (title.includes(searchTerm) || description.includes(searchTerm)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
};

// Global helper functions
window.showNotification = function(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;

    notification.style.position = 'fixed';
    notification.style.top = '1rem';
    notification.style.right = '1rem';
    notification.style.zIndex = '9999';

    document.body.appendChild(notification);

    notification.style.animation = 'slideInRight 0.4s cubic-bezier(0.2, 1, 0.3, 1)';

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.4s cubic-bezier(0.2, 1, 0.3, 1)';
        setTimeout(() => notification.remove(), 400);
    }, 3000);
};

// Initialize Feather icons if available
if (typeof feather !== 'undefined') {
    feather.replace();
}