document.addEventListener('DOMContentLoaded', () => {
  const cartButtons = document.querySelectorAll('.btn-fill, .btn-outline');

  cartButtons.forEach(button => {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      openSizeModal(this);
    });
  });
});

function openSizeModal(button) {
  const modal = document.getElementById('sizeModal');
  modal.style.display = 'block';
  modal.dataset.action = button.classList.contains('btn-fill') ? 'buy' : 'cart';
}

function closeSizeModal() {
  const modal = document.getElementById('sizeModal');
  modal.style.display = 'none';
}

function confirmSize() {
  const size = document.getElementById('sizeSelect').value;
  if (!size) {
    alert('Vui lòng chọn size!');
    return;
  }
  const modal = document.getElementById('sizeModal');
  const action = modal.dataset.action;

  modal.style.display = 'none';
  if (action === 'buy') {
    window.location.href = 'cart.html';
  } else {
    alert(`Đã thêm vào giỏ hàng - Size ${size}`);
  }
}

// Giỏ hàng - Tăng giảm số lượng
function updateQty(button, delta) {
  const input = button.parentNode.querySelector('input[type="number"]');
  let value = parseInt(input.value) + delta;
  if (value < 1) value = 1;
  input.value = value;
  calcTotal(input);
}

// Tính tổng
function calcTotal(input) {
  const row = input.closest('tr');
  const unitPrice = parseFloat(row.querySelector('.unit-price').textContent);
  const qty = parseInt(row.querySelector('input[type="number"]').value);
  const total = unitPrice * qty;
  row.querySelector('.line-total').textContent = total.toLocaleString('vi-VN') + ' đ';
  updateCartSummary();
}

function updateCartSummary() {
  let total = 0;
  document.querySelectorAll('.line-total').forEach(cell => {
    const value = parseInt(cell.textContent.replace(/\D/g, ''));
    total += value;
  });
  document.getElementById('subTotal').textContent = total.toLocaleString('vi-VN') + ' đ';
  document.getElementById('total').textContent = total.toLocaleString('vi-VN') + ' đ';
}

// Xoá sản phẩm
function removeRow(button) {
  const row = button.closest('tr');
  row.remove();
  updateCartSummary();
}
document.addEventListener('DOMContentLoaded', () => {
    // Sample data (replace with API calls or database in production)
    let products = [
        { id: 1, name: "Dép da nam MWC", price: 250000, stock: 100, category: "men" },
        { id: 2, name: "Balo Unisex Thời Trang", price: 350000, stock: 50, category: "bag" },
        // Add more products as needed
    ];

    let cart = [];
    let users = [{ id: "U01", name: "admin", email: "admin@twoad.com", role: "Quản trị" }];
    let orders = [{ id: "DH001", customer: "Nguyễn A", size: 42, status: "Chờ xác nhận" }];

    // Authentication
    const loginForm = document.querySelector('form[action="#"]'); // Adjust selector for login form
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const username = document.querySelector('input[placeholder="Tên đăng nhập *"]').value;
            const password = document.querySelector('input[placeholder="Mật khẩu *"]').value;
            
            // Simple authentication (replace with secure backend validation)
            if (username === "admin" && password === "admin123") {
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('userRole', 'admin');
                window.location.href = 'admin.html';
            } else {
                alert('Tên đăng nhập hoặc mật khẩu không đúng!');
            }
        });
    }

    // Cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const productElement = e.target.closest('.product-item'); // Adjust selector for product container
            const productId = parseInt(productElement.dataset.id);
            const product = products.find(p => p.id === productId);
            
            if (product && product.stock > 0) {
                const cartItem = cart.find(item => item.id === productId);
                if (cartItem) {
                    cartItem.quantity += 1;
                } else {
                    cart.push({ id: productId, name: product.name, price: product.price, quantity: 1, size: 36 });
                }
                updateCart();
                alert(`${product.name} đã được thêm vào giỏ hàng!`);
            } else {
                alert('Sản phẩm hết hàng!');
            }
        });
    });

    // Update cart display
    function updateCart() {
        const cartTable = document.querySelector('.cart-table'); // Adjust selector for cart table
        if (cartTable) {
            const tbody = cartTable.querySelector('tbody');
            tbody.innerHTML = '';
            let total = 0;

            cart.forEach(item => {
                const product = products.find(p => p.id === item.id);
                const itemTotal = item.price * item.quantity;
                total += itemTotal;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${product.name}</td>
                    <td>${item.price.toLocaleString()} đ</td>
                    <td>
                        <button class="decrease-quantity">-</button>
                        ${item.quantity}
                        <button class="increase-quantity">+</button>
                    </td>
                    <td>
                        <select class="size-select">
                            ${[36, 37, 38, 39, 40, 41].map(size => 
                                `<option value="${size}" ${item.size === size ? 'selected' : ''}>${size}</option>`
                            ).join('')}
                        </select>
                    </td>
                    <td>${itemTotal.toLocaleString()} đ</td>
                    <td><button class="remove-item">Xóa</button></td>
                `;
                tbody.appendChild(row);
            });

            // Update total price
            const totalElement = document.querySelector('.cart-total'); // Adjust selector
            if (totalElement) {
                totalElement.textContent = `TỔNG: ${total.toLocaleString()} đ`;
            }

            // Add event listeners for quantity and remove buttons
            tbody.querySelectorAll('.decrease-quantity').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const row = e.target.closest('tr');
                    const productId = parseInt(row.dataset.id);
                    const cartItem = cart.find(item => item.id === productId);
                    if (cartItem.quantity > 1) {
                        cartItem.quantity -= 1;
                    } else {
                        cart = cart.filter(item => item.id !== productId);
                    }
                    updateCart();
                });
            });

            tbody.querySelectorAll('.increase-quantity').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const row = e.target.closest('tr');
                    const productId = parseInt(row.dataset.id);
                    const cartItem = cart.find(item => item.id === productId);
                    const product = products.find(p => p.id === productId);
                    if (product.stock > cartItem.quantity) {
                        cartItem.quantity += 1;
                        updateCart();
                    } else {
                        alert('Không đủ hàng trong kho!');
                    }
                });
            });

            tbody.querySelectorAll('.remove-item').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const row = e.target.closest('tr');
                    const productId = parseInt(row.dataset.id);
                    cart = cart.filter(item => item.id !== productId);
                    updateCart();
                });
            });

            tbody.querySelectorAll('.size-select').forEach(select => {
                select.addEventListener('change', (e) => {
                    const row = e.target.closest('tr');
                    const productId = parseInt(row.dataset.id);
                    const cartItem = cart.find(item => item.id === productId);
                    cartItem.size = parseInt(e.target.value);
                });
            });
        }
    }

    // Admin product management
    const addProductForm = document.querySelector('.add-product-form'); // Adjust selector
    if (addProductForm) {
        addProductForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const name = document.querySelector('input[placeholder="Tên sản phẩm"]').value;
            const price = parseInt(document.querySelector('input[placeholder="Giá"]').value);
            const stock = parseInt(document.querySelector('input[placeholder="Tồn kho"]').value);
            const category = document.querySelector('input[placeholder="Danh mục"]').value;

            const newProduct = {
                id: products.length + 1,
                name,
                price,
                stock,
                category
            };
            products.push(newProduct);
            updateProductList();
            addProductForm.reset();
            alert('Sản phẩm đã được thêm!');
        });
    }

    // Update product list in admin panel
    function updateProductList() {
        const productTable = document.querySelector('.product-table'); // Adjust selector
        if (productTable) {
            const tbody = productTable.querySelector('tbody');
            tbody.innerHTML = '';
            products.forEach(product => {
                const row = document.createElement('tr');
                row.dataset.id = product.id;
                row.innerHTML = `
                    <td>${product.id}</td>
                    <td>${product.name}</td>
                    <td>${product.price.toLocaleString()} đ</td>
                    <td>${product.stock}</td>
                    <td>
                        <button class="edit-product">Sửa</button>
                        <button class="delete-product">Xóa</button>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Add event listeners for edit and delete
            tbody.querySelectorAll('.edit-product').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const row = e.target.closest('tr');
                    const productId = parseInt(row.dataset.id);
                    const product = products.find(p => p.id === productId);
                    // Populate form with product data for editing
                    document.querySelector('input[placeholder="Tên sản phẩm"]').value = product.name;
                    document.querySelector('input[placeholder="Giá"]').value = product.price;
                    document.querySelector('input[placeholder="Tồn kho"]').value = product.stock;
                    document.querySelector('input[placeholder="Danh mục"]').value = product.category;
                    // Remove product and update form to save edited product
                    products = products.filter(p => p.id !== productId);
                });
            });

            tbody.querySelectorAll('.delete-product').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const row = e.target.closest('tr');
                    const productId = parseInt(row.dataset.id);
                    products = products.filter(p => p.id !== productId);
                    updateProductList();
                    alert('Sản phẩm đã được xóa!');
                });
            });
        }
    }

    // Filtering and sorting
    const filterSelect = document.querySelector('.filter-select'); // Adjust selector
    const sortSelect = document.querySelector('.sort-select'); // Adjust selector
    if (filterSelect) {
        filterSelect.addEventListener('change', () => {
            const category = filterSelect.value;
            displayProducts(category, sortSelect ? sortSelect.value : '');
        });
    }
    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            const category = filterSelect ? filterSelect.value : '';
            displayProducts(category, sortSelect.value);
        });
    }

    function displayProducts(category, sort) {
        const productContainer = document.querySelector('.product-list'); // Adjust selector
        if (productContainer) {
            let filteredProducts = category ? products.filter(p => p.category === category) : products;

            if (sort === 'price-low-high') {
                filteredProducts.sort((a, b) => a.price - b.price);
            } else if (sort === 'price-high-low') {
                filteredProducts.sort((a, b) => b.price - a.price);
            } else if (sort === 'newest') {
                filteredProducts.sort((a, b) => b.id - a.id);
            }

            productContainer.innerHTML = '';
            filteredProducts.forEach(product => {
                const productElement = document.createElement('div');
                productElement.className = 'product-item';
                productElement.dataset.id = product.id;
                productElement.innerHTML = `
                    <h3>${product.name}</h3>
                    <p>${product.price.toLocaleString()} đ</p>
                    <button class="add-to-cart">Thêm vào giỏ hàng</button>
                    <button class="buy-now">Mua ngay</button>
                `;
                productContainer.appendChild(productElement);
            });

            // Re-attach add to cart event listeners
            productContainer.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', (e) => {
                    const productElement = e.target.closest('.product-item');
                    const productId = parseInt(productElement.dataset.id);
                    const product = products.find(p => p.id === productId);
                    if (product && product.stock > 0) {
                        const cartItem = cart.find(item => item.id === productId);
                        if (cartItem) {
                            cartItem.quantity += 1;
                        } else {
                            cart.push({ id: productId, name: product.name, price: product.price, quantity: 1, size: 36 });
                        }
                        updateCart();
                        alert(`${product.name} đã được thêm vào giỏ hàng!`);
                    } else {
                        alert('Sản phẩm hết hàng!');
                    }
                });
            });
        }
    }

    // Logout
    const logoutButton = document.querySelector('.logout-button'); // Adjust selector
    if (logoutButton) {
        logoutButton.addEventListener('click', () => {
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('userRole');
            window.location.href = 'login.html';
        });
    }

    // Initial render
    updateCart();
    updateProductList();
    displayProducts('', '');
});