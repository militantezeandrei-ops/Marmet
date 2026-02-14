/**
 * MARMET - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    initNavbar();
    initUserDropdown();
    initCart();
    initQuantityControls();
    initProductFilters();
    initAlerts();
    initButtonAnimations();
});

/**
 * Global Button Animations
 */
function initButtonAnimations() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn');
        if (btn) {
            btn.classList.remove('btn-pop-animation');
            void btn.offsetWidth; // Trigger reflow
            btn.classList.add('btn-pop-animation');
            setTimeout(() => btn.classList.remove('btn-pop-animation'), 300);
        }
    });
}

/**
 * Mobile Navigation Toggle
 */
function initNavbar() {
    const toggle = document.getElementById('navToggle');
    const menu = document.getElementById('navMenu');

    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            menu.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('active');
            }
        });
    }
}

/**
 * User Dropdown
 */
function initUserDropdown() {
    const btn = document.getElementById('userBtn');
    const dropdown = document.getElementById('userDropdown');

    if (btn && dropdown) {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });

        document.addEventListener('click', () => {
            dropdown.classList.remove('active');
        });
    }
}

/**
 * Cart Functions
 */
function initCart() {
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            addToCart(productId);
        });
    });

    document.querySelectorAll('.remove-from-cart').forEach(btn => {
        btn.addEventListener('click', function () {
            const productId = this.dataset.productId;
            removeFromCart(productId);
        });
    });
}

function addToCart(productId, quantity = 1) {
    fetch('/MarMet/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', product_id: productId, quantity })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateCartCount(data.cart_count);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Added to Cart!',
                        text: 'Product has been added to your cart.',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'View Cart',
                        cancelButtonText: 'Continue Shopping',
                        confirmButtonColor: '#3b82f6',
                        cancelButtonColor: '#64748b'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '/MarMet/cart.php';
                        }
                    });
                } else {
                    showNotification('Added to cart!', 'success');
                }
            } else if (data.unauthorized) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Login Required',
                        text: 'Please log in to add items to your cart.',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Log In',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#3b82f6'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '/MarMet/auth/login.php';
                        }
                    });
                } else {
                    window.location.href = '/MarMet/auth/login.php';
                }
            } else {
                showNotification(data.message || 'Failed to add', 'error');
            }
        })
        .catch(() => showNotification('Error adding to cart', 'error'));
}

function buyNow(productId, quantity = 1) {
    // Check if user is likely logged in by looking for userBtn
    const userBtn = document.getElementById('userBtn');
    if (!userBtn) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Login Required',
                text: 'Please log in to proceed with "Buy Now".',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Log In',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#3b82f6'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/MarMet/auth/login.php';
                }
            });
        } else {
            window.location.href = '/MarMet/auth/login.php';
        }
        return;
    }
    // Direct redirect to checkout with buy_now parameters
    window.location.href = `/MarMet/checkout.php?buy_now_product_id=${productId}&buy_now_qty=${quantity}`;
}

function removeFromCart(productId) {
    fetch('/MarMet/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove', product_id: productId })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
}

function updateCartItem(productId, quantity) {
    fetch('/MarMet/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update', product_id: productId, quantity })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
}

function updateCartCount(count) {
    const badge = document.querySelector('.cart-count');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    } else if (count > 0) {
        const cart = document.querySelector('.nav-cart');
        if (cart) {
            const span = document.createElement('span');
            span.className = 'cart-count';
            span.textContent = count;
            cart.appendChild(span);
        }
    }
}

/**
 * Quantity Controls
 */
function initQuantityControls() {
    document.querySelectorAll('.quantity-control').forEach(control => {
        const minus = control.querySelector('.qty-minus');
        const plus = control.querySelector('.qty-plus');
        const input = control.querySelector('.quantity-input');
        const productId = control.dataset.productId;

        if (minus) {
            minus.addEventListener('click', () => {
                let val = parseInt(input.value) - 1;
                if (val >= 1) {
                    input.value = val;
                    if (productId) updateCartItem(productId, val);
                }
            });
        }

        if (plus) {
            plus.addEventListener('click', () => {
                let val = parseInt(input.value) + 1;
                input.value = val;
                if (productId) updateCartItem(productId, val);
            });
        }

        if (input) {
            input.addEventListener('change', () => {
                let val = parseInt(input.value) || 1;
                if (val < 1) val = 1;
                input.value = val;
                if (productId) updateCartItem(productId, val);
            });
        }
    });
}

/**
 * Product Filters
 */
function initProductFilters() {
    const filterForm = document.getElementById('filterForm');
    if (!filterForm) return;

    const categorySelect = document.getElementById('categoryFilter');
    const sortSelect = document.getElementById('sortFilter');
    const searchInput = document.getElementById('searchInput');

    [categorySelect, sortSelect].forEach(el => {
        if (el) {
            el.addEventListener('change', () => filterForm.submit());
        }
    });

    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => filterForm.submit(), 500);
        });
    }
}

/**
 * Auto-dismiss Alerts
 */
function initAlerts() {
    document.querySelectorAll('.alert[data-dismiss]').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}

/**
 * Show Notification Toast
 */
function showNotification(message, type = 'info') {
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
    toast.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        animation: slideIn 0.3s ease;
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Confirm Delete
 */
function confirmDelete(message = 'Are you sure?') {
    return confirm(message);
}

/**
 * Format Currency
 */
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}
