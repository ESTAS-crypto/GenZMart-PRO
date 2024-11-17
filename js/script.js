// Toggle class active menu
const navbarNav = document.querySelector(".navbar-nav");
const hamburgerMenu = document.querySelector("#hamburger-menu");
const dropdowns = document.querySelectorAll(".dropdown");

hamburgerMenu.onclick = (e) => {
    navbarNav.classList.toggle("active");
    e.preventDefault();
};

// Toggle class active search form
const searchForm = document.querySelector(".search-form");
const searchBox = document.querySelector("#search-box");
const searchButton = document.querySelector("#search-button");

searchButton.onclick = (e) => {
    searchForm.classList.toggle("active");
    searchBox.focus();
    e.preventDefault();
};

// Toggle class active shopping cart
const shoppingCart = document.querySelector(".shopping-cart");
const shoppingCartButton = document.querySelector("#shopping-cart-button");

shoppingCartButton.onclick = (e) => {
    shoppingCart.classList.toggle("active");
    e.preventDefault();
};

// Modal box
const itemsDetailModal = document.querySelector("#items-detail-modal");
const itemsDetailButtons = document.querySelectorAll(".items-detail-button");
const closeModalButton = document.querySelector(".modal .close-icon");

itemsDetailButtons.forEach((btn) => {
    btn.onclick = (e) => {
        itemsDetailModal.style.display = "flex";
        e.preventDefault();
    };
});

// Close modal on close icon click
closeModalButton.onclick = (e) => {
    itemsDetailModal.style.display = "none";
    e.preventDefault();
};

// Close modal on outside click
window.onclick = (e) => {
    if (e.target === itemsDetailModal) {
        itemsDetailModal.style.display = "none";
    }
};


document.addEventListener("DOMContentLoaded", function(e) {
    const dropdowns = document.querySelectorAll(".dropdown");

    dropdowns.forEach((dropdown) => {
        const dropdownContent = dropdown.querySelector(".dropdown-content");
        const dropdownToggle = dropdown.querySelector(".dropbtn");

        dropdownToggle.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Close all other dropdowns
            dropdowns.forEach((otherDropdown) => {
                if (otherDropdown !== dropdown) {
                    otherDropdown
                        .querySelector(".dropdown-content")
                        .classList.remove("show");
                }
            });

            // Toggle current dropdown
            dropdownContent.classList.toggle("show");

            // Adjust spacing for mobile view
            if (window.innerWidth <= 768) {
                const nextSibling = dropdown.nextElementSibling;
                if (nextSibling) {
                    if (dropdownContent.classList.contains("show")) {
                        // Hitung tinggi konten dropdown
                        const dropdownHeight = dropdownContent.scrollHeight;
                        // Tambahkan margin-top yang sesuai (misalnya, setengah dari tinggi dropdown)
                        // nextSibling.style.marginTop = dropdownHeight / 2 + "px";
                    } else {
                        // nextSibling.style.marginTop = "0";
                    }
                }
            }
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener("click", function(e) {
        dropdowns.forEach((dropdown) => {
            const dropdownContent = dropdown.querySelector(".dropdown-content");
            if (!dropdown.contains(e.target)) {
                dropdownContent.classList.remove("show");
                // Reset spacing for mobile view
                if (window.innerWidth <= 768) {
                    // const nextSibling = dropdown.nextElementSibling;
                    if (nextSibling) {
                        nextSibling.style.marginTop = "0";
                    }
                }
            }
        });
    });

    // Tambahkan animasi untuk desktop
    if (window.innerWidth > 768) {
        dropdowns.forEach((dropdown) => {
            const dropdownContent = dropdown.querySelector(".dropdown-content");
            dropdown.addEventListener("mouseenter", () => {
                dropdownContent.style.display = "block";
                setTimeout(() => {
                    dropdownContent.style.opacity = "1";
                    dropdownContent.style.transform = "translateY(0)";
                }, 10);
            });
            dropdown.addEventListener("mouseleave", () => {
                dropdownContent.style.opacity = "0";
                dropdownContent.style.transform = "translateY(-10px)";
                setTimeout(() => {
                    dropdownContent.style.display = "none";
                }, 300);
            });
        });
    }
});
// Close elements when clicking outside
document.addEventListener("click", (e) => {
    // Close navbar if clicking outside
    if (!hamburgerMenu.contains(e.target) && !navbarNav.contains(e.target)) {
        navbarNav.classList.remove("active");
    }

    // Close search form if clicking outside
    if (!searchButton.contains(e.target) && !searchForm.contains(e.target)) {
        searchForm.classList.remove("active");
    }

    // Close shopping cart if clicking outside
    if (!shoppingCartButton.contains(e.target) &&
        !shoppingCart.contains(e.target)
    ) {
        shoppingCart.classList.remove("active");
    }

    // Close dropdown if clicking outside
    dropdowns.forEach((dropdown) => {
        const dropdownContent = dropdown.querySelector(".dropdown-content");
        if (!dropdown.contains(e.target)) {
            dropdownContent.classList.remove("show");
        }
    });
});

// Close elements when touching outside (for mobile)
document.addEventListener("touchstart", (e) => {
    // Close navbar if touching outside
    if (!hamburgerMenu.contains(e.target) && !navbarNav.contains(e.target)) {
        navbarNav.classList.remove("active");
    }

    // Close search form if touching outside
    if (!searchButton.contains(e.target) && !searchForm.contains(e.target)) {
        searchForm.classList.remove("active");
    }

    // Close shopping cart if touching outside
    if (!shoppingCartButton.contains(e.target) &&
        !shoppingCart.contains(e.target)
    ) {
        shoppingCart.classList.remove("active");
    }

    // Close dropdown if touching outside
    dropdowns.forEach((dropdown) => {
        const dropdownContent = dropdown.querySelector(".dropdown-content");
        if (!dropdown.contains(e.target)) {
            dropdownContent.classList.remove("show");
        }
    });
});

// Fungsi untuk mengecek apakah device mobile
function isMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Konfigurasi particles untuk mobile
const mobileConfig = {
    particles: {
        number: {
            value: 30, // Kurangi jumlah partikel
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
            value: 2, // Kurangi opacity
            random: false
        },
        size: {
            value: 3, // Kurangi ukuran partikel
            random: true
        },
        line_linked: {
            enable: true,
            distance: 150,
            color: "#ffffff",
            opacity: 1, // Kurangi opacity garis
            width: 1 // Kurangi ketebalan garis
        },
        move: {
            enable: true,
            speed: 6, // Kurangi kecepatan
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
            onhover: {
                enable: false // Nonaktifkan hover effect di mobile
            },
            onclick: {
                enable: true,
                mode: "push"
            },
            resize: true
        },
        modes: {
            push: {
                particles_nb: 2 // Kurangi jumlah partikel yang ditambahkan saat klik
            }
        }
    },
    retina_detect: false // Nonaktifkan retina detection untuk performa
};

// Konfigurasi particles untuk desktop
const desktopConfig = {
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
            value: 2,
            random: false
        },
        size: {
            value: 5,
            random: true
        },
        line_linked: {
            enable: true,
            distance: 150,
            color: "#ffffff",
            opacity: 1,
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
            onhover: {
                enable: true,
                mode: "repulse"
            },
            onclick: {
                enable: true,
                mode: "push"
            },
            resize: true
        },
        modes: {
            grab: {
                distance: 400,
                line_linked: {
                    opacity: 1
                }
            },
            bubble: {
                distance: 400,
                size: 40,
                duration: 2,
                opacity: 8,
                speed: 3
            },
            repulse: {
                distance: 200,
                duration: 0.4
            },
            push: {
                particles_nb: 4
            },
            remove: {
                particles_nb: 2
            }
        }
    },
    retina_detect: true
};

// Fungsi untuk menginisialisasi particles.js dengan konfigurasi yang sesuai
function initParticles(elementId) {
    const config = isMobile() ? mobileConfig : desktopConfig;
    particlesJS(elementId, config);
}

// Inisialisasi untuk semua container particles
document.addEventListener('DOMContentLoaded', () => {
    const particleContainers = [
        'particles-js',
        'partikel-js',
        'par-js',

    ];

    particleContainers.forEach(containerId => {
        initParticles(containerId);
    });

    // Tambahkan throttle untuk event resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
        if (resizeTimeout) clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            particleContainers.forEach(containerId => {
                const container = document.getElementById(containerId);
                if (container) {
                    // Destroy dan reinit particles pada resize
                    pJSDom[pJSDom.findIndex(p => p.pJS.canvas.el.id === containerId)].pJS.fn.vendors.destroypJS();
                    initParticles(containerId);
                }
            });
        }, 250);
    });
});