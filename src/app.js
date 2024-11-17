document.addEventListener("alpine:init", () => {
            const showItemDetails = (item) => {
                    const modal = document.getElementById("items-detail-modal");
                    if (!modal) {
                        console.error("Modal element not found");
                        return;
                    }

                    let modalContent = modal.querySelector(".modal-content");
                    if (!modalContent) {
                        console.warn("Modal content element not found, creating one");
                        modalContent = document.createElement("div");
                        modalContent.className = "modal-content";
                        modal.querySelector(".modal-container").appendChild(modalContent);
                    }

                    // Determine which menu category the item belongs to
                    const getImagePath = (item) => {
                        // Check item ID ranges to determine the correct folder
                        if (item.id <= 6) {
                            return `img/menu0/${item.img}`;
                        } else if (item.id <= 12) {
                            return `img/menu2/${item.img}`;
                        } else {
                            return `img/menu3/${item.img}`;
                        }
                    };

                    modalContent.innerHTML = `
  <img src="${getImagePath(item)}" alt="${item.name}"/>
  <div class="product-content">
      <h3>${item.name}</h3>
      <p>${item.description}</p>
      <div class="product-stars">
          ${Array(5).fill().map((_, i) => `
              <svg width="24" height="24" fill="${i < 5 ? "currentColor" : "none"}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <use href="img/feather-sprite.svg#star" />
              </svg>
          `).join("")}
      </div>
      <div class="menu-card-price">${rupiah(item.price)}</div>
      <a href="#" class="add-to-cart-button">
          <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <use href="img/feather-sprite.svg#shopping-cart" />
          </svg>
          <span>Add to cart</span>
      </a>
  </div>
`;

modal.style.display = "flex";

// Add event listener for Add to Cart button
const addToCartButton = modalContent.querySelector('.add-to-cart-button');
addToCartButton.addEventListener('click', (e) => {
  e.preventDefault();
  Alpine.store('cart').add(item);
  modal.style.display = "none"; // Close modal after adding to cart
});

// Add close modal functionality
const closeButton = modal.querySelector('.close-icon');
closeButton.addEventListener('click', (e) => {
  e.preventDefault();
  modal.style.display = "none";
});
};

// Rest of the Alpine.js code remains the same
Alpine.data("menu", () => ({
items: [
  { id: 1, name: "Indomie Goreng", img: "1.jpg", price: 3500, description: "Indomie Goreng - 85g." },
  { id: 2, name: "Gula", img: "2.jpg", price: 19000, description: "Gula - 1000g." },
  { id: 3, name: "Beras Sania", img: "3.jpg", price: 80000, description: "Beras Sania - 5kg." },
  { id: 4, name: "Tepung Segitiga Biru", img: "4.jpg", price: 15000, description: "Tepung Segitiga Biru - 1kg." },
  { id: 5, name: "Bimoli", img: "5.jpg", price: 37000, description: "Minyak Bimoli - 2L." },
  { id: 6, name: "Telur", img: "6.jpg", price: 15000, description: "Telur Ayam - 500g." },
],
showItemDetails
}));

Alpine.data("menu2", () => ({
items: [
{ id: 7, name: "Aqua", img: "7.jpg", price: 4000, description: "Aqua -300ml" },
{ id: 8, name: "Coca Cola", img: "8.jpg", price: 6000, description: "Coca-cola -250ml" },
{ id: 9, name: "Fanta", img: "9.jpg", price: 6000, description: "Fanta -250ml" },
{ id: 10, name: "Teh Pucuk", img: "10.jpg", price: 4000, description: "Teh Pucuk -350ml" },
{ id: 11, name: "Pocari Sweat", img: "11.jpg", price: 8000, description: "Pocari Sweat -500ml" },
{ id: 12, name: "Thai Tea", img: "12.jpg", price: 9000, description: "Ichitan Thai Tea -310ml" },
],
showItemDetails
}));

Alpine.data("menu3", () => ({
items: [
{ id: 13, name: "Bayam", img: "13.jpg", price: 3000, description: "Sayuran hijau segar kaya akan zat besi dan nutrisi penting." },
{ id: 14, name: "Wortel", img: "14.jpg", price: 5000, description: "Wortel segar kaya vitamin A untuk kesehatan mata dan kulit." },
{ id: 15, name: "Brokoli", img: "15.jpg", price: 9000, description: "Sayuran hijau kaya serat dan antioksidan untuk kesehatan optimal." },
{ id: 16, name: "Kale", img: "16.jpg", price: 4000, description: "Superfood kaya nutrisi dengan manfaat kesehatan yang luar biasa." },
{ id: 17, name: "Kangkung", img: "17.jpg", price: 3000, description: "Sayuran hijau lokal yang lezat dan kaya akan zat besi." },
{ id: 18, name: "Sawi", img: "18.jpg", price: 4000, description: "Sayuran hijau segar dengan rasa ringan dan kaya vitamin." },
],
showItemDetails
}));
Alpine.store("cart", {
items: [],
total: 0,
quantity: 0,
add(newItem) {
const cartItem = this.items.find((item) => item.id === newItem.id);

if (!cartItem) {
this.items.push({...newItem, quantity: 1, total: newItem.price });
this.quantity++;
this.total += newItem.price;
} else {
this.items = this.items.map((item) => {
if (item.id !== newItem.id) {
return item;
} else {
item.quantity++;
item.total = item.price * item.quantity;
this.quantity++;
this.total += item.price;
return item;
}
});
}
},  
remove(id) {
const cartItem = this.items.find((item) => item.id === id);

if (cartItem.quantity > 1) {
this.items = this.items.map((item) => {
if (item.id !== id) {
return item;
} else {
item.quantity--;
item.total = item.price * item.quantity;
this.quantity--;
this.total -= item.price;
return item;
}
});
} else if (cartItem.quantity === 1) {
this.items = this.items.filter((item) => item.id !== id);
this.quantity--;
this.total -= cartItem.price;
}
},
});
});
// Tempatkan kode ini di file src/app.js atau di bagian script Alpine.js initialization

document.addEventListener("alpine:init", () => {
    // Store definition yang sudah ada
    Alpine.store("cart", {
        items: [],
        total: 0,
        quantity: 0,
        
        // Tambahkan method getImagePath ke dalam store
        getImagePath(item) {
            if (item.id <= 6) {
                return `img/menu0/${item.img}`;
            } else if (item.id <= 12) {
                return `img/menu2/${item.img}`;
            } else {
                return `img/menu3/${item.img}`;
            }
        },

        add(newItem) {
            const cartItem = this.items.find((item) => item.id === newItem.id);

            if (!cartItem) {
                this.items.push({...newItem, quantity: 1, total: newItem.price });
                this.quantity++;
                this.total += newItem.price;
            } else {
                this.items = this.items.map((item) => {
                    if (item.id !== newItem.id) {
                        return item;
                    } else {
                        item.quantity++;
                        item.total = item.price * item.quantity;
                        this.quantity++;
                        this.total += item.price;
                        return item;
                    }
                });
            }
        },

        remove(id) {
            const cartItem = this.items.find((item) => item.id === id);

            if (cartItem.quantity > 1) {
                this.items = this.items.map((item) => {
                    if (item.id !== id) {
                        return item;
                    } else {
                        item.quantity--;
                        item.total = item.price * item.quantity;
                        this.quantity--;
                        this.total -= item.price;
                        return item;
                    }
                });
            } else if (cartItem.quantity === 1) {
                this.items = this.items.filter((item) => item.id !== id);
                this.quantity--;
                this.total -= cartItem.price;
            }
        }
    });
});
document.addEventListener('DOMContentLoaded', () => {
const itemsDetailButtons = document.querySelectorAll('.items-detail-button');
itemsDetailButtons.forEach(button => {
button.addEventListener('click', (e) => {
e.preventDefault();
const item = JSON.parse(button.getAttribute('data-item'));
showItemDetails(item);
});
});
});

// Form validation
const checkoutButton = document.querySelector(".checkout-button");
checkoutButton.disabled = true;

const form = document.querySelector("#checkoutForm");

form.addEventListener("keyup", function() {
let allFieldsFilled = true;
for (let i = 0; i < form.elements.length; i++) {
if (
form.elements[i].type !== "hidden" &&
form.elements[i].value.length === 0
) {
allFieldsFilled = false;
break;
}
}

if (allFieldsFilled) {
checkoutButton.disabled = false;
checkoutButton.classList.remove("disabled");
} else {
checkoutButton.disabled = true;
checkoutButton.classList.add("disabled");
}
});

// Kirim data ketika tombol checkout di klik
checkoutButton.addEventListener("click", function(e) {
e.preventDefault();
const formData = new FormData(form);
const data = new URLSearchParams(formData);
const objData = Object.fromEntries(data);

const message = formatMessage(objData);

// Buka WhatsApp dengan pesan
window.open(
"https://api.whatsapp.com/send?phone=62895385890629&text=" +
encodeURIComponent(message),
"_blank"
);
});

// Format pesan WA
const formatMessage = (obj) => {
return `Data Customer 
Nama: ${obj.name}
Email: ${obj.email}
Alamat: ${obj.alamat}
No Hp: ${obj.phone}
Data Pesanan:
${JSON.parse(obj.items)
.map((item) => `${item.name} (${item.quantity} x ${rupiah(item.total)})`)
.join("")}
TOTAL: ${rupiah(obj.total)}
Terima Kasih`;
};

// Konversi ke rupiah
const rupiah = (number) => {
return new Intl.NumberFormat("id-ID", {
style: "currency",
currency: "IDR",
minimumFractionDigits: 0,
}).format(number);
};

// Tambahkan event listener untuk tombol Kirim Pesan di form kontak
document.getElementById("contactForm").addEventListener("submit", function (event) {
event.preventDefault(); // Mencegah form dari submit default

var nama = document.getElementById("nama").value;  // Ambil nama dari input
var email = document.getElementById("email").value;
var pesan = document.getElementById("pesan").value;

var subject = "Pesan Kontak dari " + nama;
var body = `Nama: ${nama}%0AEmail: ${email}%0APesan: ${pesan}%0A%0ATerima Kasih`;

var mailtoUrl = `mailto:eatharasya@gmail.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;

window.open(mailtoUrl, "_blank");
});